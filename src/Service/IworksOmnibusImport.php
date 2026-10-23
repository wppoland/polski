<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

/**
 * Copies the price history kept by the "Omnibus" plugin (iworks) into
 * polski_price_history, so a shop switching over keeps the 30-day window it
 * already has instead of starting from an empty one.
 *
 * Three stores are read, all of them may coexist on one site:
 *  - v3: posts of type iw_omnibus_price_log, child of the product or variation,
 *        with price_regular / price_sale / timestamp in post meta;
 *  - v2: repeated `_iwo_price_lowest` post meta, one array per change;
 *  - `_iwo_price_last_change`: the latest regular and sale price, the regular
 *    one stored as `price`.
 *
 * Entries without a regular price are skipped: iworks also writes snapshots
 * from page views that hold only the selling price, and storing that as a
 * regular price would make a sale price its own lowest price.
 *
 * Safe to run repeatedly: a row identical in product, moment and prices is
 * never written twice.
 */
final class IworksOmnibusImport
{
    /**
     * @return int Number of rows written.
     */
    public function run(): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'polski_price_history';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema probe, prepared.
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return 0;
        }

        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'PLN';
        $written = 0;

        foreach ($this->entries() as $entry) {
            $row = self::mapEntry($entry['data'], $entry['fallback_ts'], $currency);

            if ($row === null) {
                continue;
            }

            $row['product_id'] = $entry['product_id'];
            $written += $this->insertOnce($table, $row);
        }

        if ($written > 0) {
            CacheHelper::flush();
        }

        return $written;
    }

    /**
     * Turn one iworks record into a polski_price_history row, or null when it
     * carries no usable regular price.
     *
     * @param array<string, mixed> $data
     * @return array{price: float, sale_price: ?float, price_type: string, currency: string, recorded_at: string}|null
     */
    public static function mapEntry(array $data, int $fallbackTs, string $currency): ?array
    {
        $regular = (float) ($data['price_regular'] ?? 0);

        if ($regular <= 0) {
            return null;
        }

        $sale = (float) ($data['price_sale'] ?? 0);
        $sale = $sale > 0 && $sale < $regular ? $sale : null;

        $ts = is_numeric($data['timestamp'] ?? null) ? (int) $data['timestamp'] : $fallbackTs;

        if ($ts <= 0) {
            return null;
        }

        return [
            'price' => $regular,
            'sale_price' => $sale,
            'price_type' => $sale !== null ? 'sale' : 'regular',
            'currency' => is_string($data['currency'] ?? null) && $data['currency'] !== '' ? $data['currency'] : $currency,
            'recorded_at' => gmdate('Y-m-d H:i:s', $ts),
        ];
    }

    /**
     * Every iworks record as product id, raw data and the moment to fall back on.
     *
     * @return \Generator<array{product_id: int, data: array<string, mixed>, fallback_ts: int}>
     */
    private function entries(): \Generator
    {
        global $wpdb;

        // v3 log posts. Meta is read per post; the log is a few rows per product.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off import from another plugin's storage.
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT ID, post_parent, post_date_gmt FROM %i WHERE post_type = %s AND post_parent > 0',
                $wpdb->posts,
                'iw_omnibus_price_log',
            ),
        );

        foreach ((array) $logs as $log) {
            $data = [];
            foreach ((array) get_post_meta((int) $log->ID) as $key => $values) {
                $data[(string) $key] = is_array($values) ? reset($values) : $values;
            }

            yield [
                'product_id' => (int) $log->post_parent,
                'data' => $data,
                'fallback_ts' => (int) strtotime((string) $log->post_date_gmt . ' UTC'),
            ];
        }

        // v2 meta.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off import from another plugin's storage.
        $metas = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT post_id, meta_key, meta_value FROM %i WHERE meta_key IN (%s, %s)',
                $wpdb->postmeta,
                '_iwo_price_lowest',
                '_iwo_price_last_change',
            ),
        );

        foreach ((array) $metas as $meta) {
            $data = maybe_unserialize((string) $meta->meta_value);

            if (! is_array($data)) {
                continue;
            }

            // Page-view snapshots hold the selling price only, see the class doc.
            if (($data['type'] ?? '') === 'autosaved') {
                continue;
            }

            // Only `_iwo_price_last_change` stores the regular price as `price`;
            // in `_iwo_price_lowest` it is the selling price.
            if ($meta->meta_key === '_iwo_price_last_change' && ! isset($data['price_regular']) && isset($data['price'])) {
                $data['price_regular'] = $data['price'];
            }

            yield [
                'product_id' => (int) $meta->post_id,
                'data' => $data,
                'fallback_ts' => 0,
            ];
        }
    }

    /**
     * @param array{product_id: int, price: float, sale_price: ?float, price_type: string, currency: string, recorded_at: string} $row
     */
    private function insertOnce(string $table, array $row): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared.
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM %i WHERE product_id = %d AND recorded_at = %s AND currency = %s
                   AND ABS(price - %f) < 0.00005
                   AND COALESCE(sale_price, -1) BETWEEN %f - 0.00005 AND %f + 0.00005
                 LIMIT 1',
                $table,
                $row['product_id'],
                $row['recorded_at'],
                $row['currency'],
                $row['price'],
                $row['sale_price'] ?? -1,
                $row['sale_price'] ?? -1,
            ),
        );

        if ($exists !== null) {
            return 0;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table.
        return (int) $wpdb->insert(
            $table,
            $row,
            ['%f', '%f', '%s', '%s', '%s', '%d'],
        );
    }
}
