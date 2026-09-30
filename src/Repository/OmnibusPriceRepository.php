<?php

declare(strict_types=1);
namespace Polski\Repository;

defined('ABSPATH') || exit;

use Polski\Enum\PriceType;
use Polski\Model\OmnibusPrice;
use wpdb;

/**
 * Data access for the Omnibus price history table.
 */
class OmnibusPriceRepository
{
    public function __construct(
        private readonly wpdb $wpdb,
    ) {
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . 'polski_price_history';
    }

    /**
     * Record a price snapshot for a product.
     */
    public function recordPrice(
        int $productId,
        float $price,
        ?float $salePrice,
        PriceType $priceType,
        string $currency = 'PLN',
    ): int {
        $this->wpdb->insert(
            $this->tableName(),
            [
                'product_id' => $productId,
                'price' => $price,
                'sale_price' => $salePrice,
                'price_type' => $priceType->value,
                'currency' => $currency,
                'recorded_at' => current_time('mysql', true),
            ],
            ['%d', '%f', '%f', '%s', '%s', '%s'],
        );

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Find the lowest price for a product within the last N days.
     */
    public function findLowest(int $productId, int $days = 30): ?OmnibusPrice
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i
                 WHERE product_id = %d AND recorded_at >= %s
                   AND currency = %s
                 ORDER BY price ASC
                 LIMIT 1',
                $this->tableName(),
                $productId,
                $this->gmDateDaysAgo($days),
                $this->currentCurrency(),
            ),
        );

        if ($row === null) {
            return null;
        }

        return OmnibusPrice::fromRow($row);
    }

    /**
     * Find the lowest effective price (considering sale prices) for a product.
     */
    /**
     * @param ?string $beforeGmt End of the window, UTC 'Y-m-d H:i:s'. The
     *                           Omnibus Directive asks for the lowest price in
     *                           the days *before the reduction*, so passing the
     *                           sale's start date excludes the running sale
     *                           price from its own comparison. Null keeps the
     *                           window ending now.
     */
    public function findLowestEffective(int $productId, int $days = 30, ?string $beforeGmt = null): ?OmnibusPrice
    {
        global $wpdb;

        if ($beforeGmt !== null) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT * FROM %i
                     WHERE product_id = %d AND recorded_at >= %s AND recorded_at < %s
                       AND currency = %s
                     ORDER BY COALESCE(sale_price, price) ASC
                     LIMIT 1',
                    $this->tableName(),
                    $productId,
                    $this->windowStart($productId, $this->gmDateDaysBefore($beforeGmt, $days)),
                    $beforeGmt,
                    $this->currentCurrency(),
                ),
            );

            return $row === null ? null : OmnibusPrice::fromRow($row);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i
                 WHERE product_id = %d AND recorded_at >= %s
                   AND currency = %s
                 ORDER BY COALESCE(sale_price, price) ASC
                 LIMIT 1',
                $this->tableName(),
                $productId,
                $this->windowStart($productId, $this->gmDateDaysAgo($days)),
                $this->currentCurrency(),
            ),
        );

        if ($row === null) {
            return null;
        }

        return OmnibusPrice::fromRow($row);
    }

    /**
     * Batch-fetch the lowest effective price for every product ID in `$productIds`,
     * in a single query. Returns a map keyed by product_id; products that have no
     * recorded history in the window are simply absent from the result.
     *
     * Used by OmnibusService to warm the per-product object cache once at the top
     * of a WooCommerce archive loop, avoiding the N+1 query pattern that would
     * otherwise happen as each product callback in the loop runs.
     *
     * @param list<int> $productIds
     * @return array<int, OmnibusPrice>
     */
    public function findLowestEffectiveBatch(array $productIds, int $days = 30): array
    {
        if ($productIds === []) {
            return [];
        }

        global $wpdb;

        $cleanIds = array_values(array_unique(array_filter(array_map('intval', $productIds), static fn ($v) => $v > 0)));

        if ($cleanIds === []) {
            return [];
        }

        $table = $this->tableName();
        $cutoff = $this->gmDateDaysAgo($days);
        $idPlaceholders = implode(',', array_fill(0, count($cleanIds), '%d'));

        // The subquery picks the lowest effective price per product within the window;
        // the outer join recovers full row data for currency / sale_price / recorded_at.
        // Table names use %i identifier placeholders; the product-id IN list uses %d
        // placeholders bound from the intval-filtered $cleanIds.
        // $idPlaceholders is a list of %d tokens only (built from intval-filtered ids); the
        // placeholder count is dynamic and args are spread via array_merge, so the static
        // sniffs miscount and misread the interpolation, but the query is fully prepared.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        $sql = $wpdb->prepare(
            "SELECT t1.*
             FROM %i t1
             INNER JOIN (
                 SELECT product_id, MIN(COALESCE(sale_price, price)) AS lowest
                 FROM %i
                 WHERE product_id IN ({$idPlaceholders}) AND recorded_at >= %s
                   AND currency = %s
                 GROUP BY product_id
             ) t2
                 ON t1.product_id = t2.product_id
                 AND COALESCE(t1.sale_price, t1.price) = t2.lowest
             WHERE t1.recorded_at >= %s AND t1.currency = %s
             ORDER BY t1.recorded_at DESC",
            ...array_merge(
                [$table, $table],
                $cleanIds,
                [$cutoff, $this->currentCurrency(), $cutoff, $this->currentCurrency()],
            ),
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom plugin table, prepared above.
        $rows = $wpdb->get_results($sql);

        if (! is_array($rows)) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $productId = (int) $row->product_id;
            if ($productId <= 0 || isset($map[$productId])) {
                continue;
            }
            $map[$productId] = OmnibusPrice::fromRow($row);
        }

        return $map;
    }

    /**
     * Get complete price history for a product.
     *
     * @return list<OmnibusPrice>
     */
    public function findHistory(int $productId, int $days = 30): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i
                 WHERE product_id = %d AND recorded_at >= %s
                   AND currency = %s
                 ORDER BY recorded_at DESC, id DESC',
                $this->tableName(),
                $productId,
                $this->windowStart($productId, $this->gmDateDaysAgo($days)),
                $this->currentCurrency(),
            ),
        );

        $list = is_array($rows) ? $rows : [];

        return array_map(
            static fn (\stdClass $row) => OmnibusPrice::fromRow($row),
            $list,
        );
    }

    /**
     * When the running sale price was first recorded, UTC 'Y-m-d H:i:s'.
     *
     * That is the first row carrying this sale price after the last row that
     * did not. Null when the history has no row for it.
     */
    public function findSaleRunStart(int $productId, float $salePrice): ?string
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $start = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT MIN(recorded_at) FROM %i
                 WHERE product_id = %d AND currency = %s
                   AND sale_price IS NOT NULL AND ABS(sale_price - %f) < 0.00005
                   AND recorded_at > COALESCE((
                       SELECT MAX(recorded_at) FROM %i
                       WHERE product_id = %d AND currency = %s
                         AND (sale_price IS NULL OR ABS(sale_price - %f) >= 0.00005)
                   ), %s)',
                $this->tableName(),
                $productId,
                $this->currentCurrency(),
                $salePrice,
                $this->tableName(),
                $productId,
                $this->currentCurrency(),
                $salePrice,
                '1970-01-01 00:00:00',
            ),
        );

        return is_string($start) && $start !== '' ? $start : null;
    }

    /**
     * Delete records older than N days, except the newest of them per product
     * and currency: that price is still in force (or was when the window
     * opened), and deleting it would leave a product whose price never
     * changed with no history at all.
     */
    public function deleteOlderThan(int $days): int
    {
        global $wpdb;

        $cutoff = $this->gmDateDaysAgo($days);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        return (int) $wpdb->query(
            $wpdb->prepare(
                'DELETE h FROM %i h
                 INNER JOIN (
                     SELECT product_id, currency, MAX(recorded_at) AS keep_at
                     FROM %i WHERE recorded_at < %s
                     GROUP BY product_id, currency
                 ) k ON h.product_id = k.product_id AND h.currency = k.currency
                 WHERE h.recorded_at < k.keep_at',
                $this->tableName(),
                $this->tableName(),
                $cutoff,
            ),
        );
    }

    /**
     * Get the most recent recorded price for a product.
     */
    public function findLatest(int $productId): ?OmnibusPrice
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i
                 WHERE product_id = %d
                 ORDER BY recorded_at DESC, id DESC
                 LIMIT 1',
                $this->tableName(),
                $productId,
            ),
        );

        if ($row === null) {
            return null;
        }

        return OmnibusPrice::fromRow($row);
    }

    /**
     * Where a product's window really starts: at the last row recorded before
     * the cutoff when there is one, because that price was still in force when
     * the window opened. Rows are written only when the price changes, so a
     * price that held for months has its only row before the window.
     */
    private function windowStart(int $productId, string $cutoffGmt): string
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $inForce = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT MAX(recorded_at) FROM %i
                 WHERE product_id = %d AND currency = %s AND recorded_at < %s',
                $this->tableName(),
                $productId,
                $this->currentCurrency(),
                $cutoffGmt,
            ),
        );

        return is_string($inForce) && $inForce !== '' ? $inForce : $cutoffGmt;
    }

    private function gmDateDaysAgo(int $days): string
    {
        $ts = strtotime("-{$days} days");

        return gmdate('Y-m-d H:i:s', $ts !== false ? $ts : time());
    }

    /**
     * The same window, but counted back from a given moment instead of now.
     */
    private function gmDateDaysBefore(string $baseGmt, int $days): string
    {
        $base = strtotime($baseGmt . ' UTC');
        $ts = strtotime("-{$days} days", $base !== false ? $base : time());

        return gmdate('Y-m-d H:i:s', $ts !== false ? $ts : time());
    }

    /**
     * The shop's currency right now.
     *
     * The notice and the chart are comparisons, and amounts in different
     * currencies do not compare. A shop that switches currency, or a
     * multi-currency plugin that switches it per request, starts a fresh
     * 30-day window instead of quoting a number in the wrong money.
     */
    private function currentCurrency(): string
    {
        return function_exists('get_woocommerce_currency') ? (string) get_woocommerce_currency() : 'PLN';
    }
}
