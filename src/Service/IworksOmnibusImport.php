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
 *  - v3: posts of type iw_omnibus_price_log, child of the product or variation;
 *  - v2: repeated `_iwo_price_lowest` post meta, one array per change;
 *  - `_iwo_price_last_change`: the latest regular price, stored as `price`.
 *
 * iworks is a weaker record than Polski's own, so it is used only to reach
 * back before Polski started recording a product:
 *  - `price_sale` there is the raw WooCommerce field, set for a sale that is
 *    only scheduled as well. The selling price is taken from `price`
 *    (get_price()), a record without one is skipped;
 *  - iworks writes no row when a sale ends, so its sale rows are never closed.
 *    Once Polski has its own row for a product, everything later is Polski's.
 *
 * Safe to run repeatedly. It also repairs what 1.41.7 imported, which read the
 * raw sale field and imported into the period Polski already covered.
 */
final class IworksOmnibusImport
{
    private const LOCK = 'polski_iworks_import_lock';

    /**
     * @return array{imported: int, removed: int}
     */
    public function run(): array
    {
        global $wpdb;

        $result = ['imported' => 0, 'removed' => 0];
        $table = $wpdb->prefix . 'polski_price_history';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema probe, prepared.
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return $result;
        }

        // Runs on the first request after an upgrade; a parallel request must not
        // import the same rows a second time. A lock older than an hour is stale.
        $locked = (int) get_option(self::LOCK, 0);
        if ($locked > time() - HOUR_IN_SECONDS || ! update_option(self::LOCK, time(), false)) {
            return $result;
        }

        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'PLN';
        $byProduct = [];

        foreach ($this->entries() as $entry) {
            $pid = $entry['product_id'];
            $byProduct[$pid] ??= ['new' => [], 'old' => []];

            $new = self::mapEntry($entry['data'], $entry['fallback_ts'], $currency, $entry['source']);
            if ($new !== null) {
                $byProduct[$pid]['new'][self::key($new)] = $new;
            }

            $old = self::legacyMapEntry($entry['data'], $entry['fallback_ts'], $currency, $entry['source']);
            if ($old !== null) {
                $byProduct[$pid]['old'][self::key($old)] = true;
            }
        }

        foreach ($byProduct as $pid => $sets) {
            $change = $this->syncProduct($table, (int) $pid, $sets['new'], $sets['old']);
            $result['imported'] += $change['imported'];
            $result['removed'] += $change['removed'];
        }

        delete_option(self::LOCK);

        if ($result['imported'] + $result['removed'] > 0) {
            CacheHelper::flush();
        }

        return $result;
    }

    /**
     * Turn one iworks record into a polski_price_history row, or null when it
     * does not say what the product sold at.
     *
     * @param array<string, mixed> $data
     * @return array{price: float, sale_price: ?float, price_type: string, currency: string, recorded_at: string}|null
     */
    public static function mapEntry(array $data, int $fallbackTs, string $currency, string $source): ?array
    {
        if ($source === 'last_change') {
            // Regular price as `price`, raw sale field only: whether that sale was
            // running cannot be told, so only a record without one is usable.
            if ((float) ($data['price_sale'] ?? 0) > 0) {
                return null;
            }

            return self::row((float) ($data['price'] ?? 0), null, $data, $fallbackTs, $currency);
        }

        if (! is_numeric($data['price'] ?? null)) {
            return null;
        }

        $regular = (float) ($data['price_regular'] ?? 0);
        $selling = (float) $data['price'];

        return self::row($regular, $selling > 0 && $selling < $regular ? $selling : null, $data, $fallbackTs, $currency);
    }

    /**
     * The mapping 1.41.7 used, kept only to find the rows it wrote.
     *
     * @param array<string, mixed> $data
     * @return array{price: float, sale_price: ?float, price_type: string, currency: string, recorded_at: string}|null
     */
    private static function legacyMapEntry(array $data, int $fallbackTs, string $currency, string $source): ?array
    {
        if ($source === 'lowest' && ($data['type'] ?? '') === 'autosaved') {
            return null;
        }

        $regular = (float) ($data['price_regular'] ?? ($source === 'last_change' ? ($data['price'] ?? 0) : 0));
        $sale = (float) ($data['price_sale'] ?? 0);

        return self::row($regular, $sale > 0 && $sale < $regular ? $sale : null, $data, $fallbackTs, $currency);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{price: float, sale_price: ?float, price_type: string, currency: string, recorded_at: string}|null
     */
    private static function row(float $regular, ?float $sale, array $data, int $fallbackTs, string $currency): ?array
    {
        $ts = is_numeric($data['timestamp'] ?? null) ? (int) $data['timestamp'] : $fallbackTs;

        if ($regular <= 0 || $ts <= 0) {
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
     * @param array{price: float|string, sale_price: float|string|null, currency: string, recorded_at: string} $row
     */
    private static function key(array $row): string
    {
        return sprintf(
            '%s|%.4F|%.4F|%s',
            $row['recorded_at'],
            (float) $row['price'],
            $row['sale_price'] === null ? -1.0 : (float) $row['sale_price'],
            $row['currency'],
        );
    }

    /**
     * Bring one product's history in line: drop imported rows that are wrong or
     * fall in the period Polski covers itself, add the ones from before it.
     *
     * Which existing rows are imported is read from the table: Polski writes
     * rows as prices change, so its ids rise with recorded_at. An import adds
     * old moments with new ids, so an iworks-shaped row with a newer row at a
     * lower id was imported, not recorded.
     *
     * @param array<string, array{price: float, sale_price: ?float, price_type: string, currency: string, recorded_at: string}> $new
     * @param array<string, true> $old
     * @return array{imported: int, removed: int}
     */
    private function syncProduct(string $table, int $productId, array $new, array $old): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared.
        $rows = (array) $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id, price, sale_price, currency, recorded_at FROM %i WHERE product_id = %d ORDER BY recorded_at DESC, id DESC',
                $table,
                $productId,
            ),
            ARRAY_A,
        );

        $now = gmdate('Y-m-d H:i:s');
        $remove = [];
        $imported = [];
        $minIdAfter = PHP_INT_MAX;

        // Newest first, so the lowest id among later rows is at hand.
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $key = self::key($row);
            $fromIworks = isset($old[$key]) || isset($new[$key]);

            if ($fromIworks && (! isset($new[$key]) || $row['recorded_at'] > $now)) {
                $remove[] = $id;
            } elseif ($fromIworks && $minIdAfter < $id) {
                $imported[$id] = $row['recorded_at'];
            }

            $minIdAfter = min($minIdAfter, $id);
        }

        $firstOwn = null;
        foreach ($rows as $row) {
            if (! isset($imported[(int) $row['id']]) && ! in_array((int) $row['id'], $remove, true)) {
                $firstOwn = $row['recorded_at'];
            }
        }

        foreach ($imported as $id => $recordedAt) {
            if ($firstOwn !== null && $recordedAt >= $firstOwn) {
                $remove[] = $id;
            }
        }

        foreach ($remove as $id) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table.
            $wpdb->delete($table, ['id' => $id], ['%d']);
        }

        $present = [];
        foreach ($rows as $row) {
            if (! in_array((int) $row['id'], $remove, true)) {
                $present[self::key($row)] = true;
            }
        }

        $added = 0;
        foreach ($new as $key => $row) {
            if (isset($present[$key]) || $row['recorded_at'] > $now || ($firstOwn !== null && $row['recorded_at'] >= $firstOwn)) {
                continue;
            }

            $row['product_id'] = $productId;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table.
            $added += (int) $wpdb->insert($table, $row, ['%f', '%f', '%s', '%s', '%s', '%d']);
        }

        return ['imported' => $added, 'removed' => count($remove)];
    }

    /**
     * Every iworks record as product id, raw data, source and the moment to fall back on.
     *
     * @return \Generator<array{product_id: int, data: array<string, mixed>, fallback_ts: int, source: string}>
     */
    private function entries(): \Generator
    {
        global $wpdb;

        // v3 log posts, with their meta in one query.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off import from another plugin's storage.
        $metaRows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT p.ID, p.post_parent, p.post_date_gmt, m.meta_key, m.meta_value
                 FROM %i p INNER JOIN %i m ON m.post_id = p.ID
                 WHERE p.post_type = %s AND p.post_parent > 0
                 ORDER BY p.ID',
                $wpdb->posts,
                $wpdb->postmeta,
                'iw_omnibus_price_log',
            ),
        );

        $logs = [];
        foreach ((array) $metaRows as $meta) {
            $logs[(int) $meta->ID] ??= [
                'product_id' => (int) $meta->post_parent,
                'data' => [],
                'fallback_ts' => (int) strtotime((string) $meta->post_date_gmt . ' UTC'),
                'source' => 'v3',
            ];
            $logs[(int) $meta->ID]['data'][(string) $meta->meta_key] = maybe_unserialize((string) $meta->meta_value);
        }

        yield from $logs;

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

            yield [
                'product_id' => (int) $meta->post_id,
                'data' => $data,
                'fallback_ts' => 0,
                'source' => $meta->meta_key === '_iwo_price_last_change' ? 'last_change' : 'lowest',
            ];
        }
    }
}
