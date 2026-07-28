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
                 ORDER BY price ASC
                 LIMIT 1',
                $this->tableName(),
                $productId,
                $this->gmDateDaysAgo($days),
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
    public function findLowestEffective(int $productId, int $days = 30): ?OmnibusPrice
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i
                 WHERE product_id = %d AND recorded_at >= %s
                 ORDER BY COALESCE(sale_price, price) ASC
                 LIMIT 1',
                $this->tableName(),
                $productId,
                $this->gmDateDaysAgo($days),
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
                 GROUP BY product_id
             ) t2
                 ON t1.product_id = t2.product_id
                 AND COALESCE(t1.sale_price, t1.price) = t2.lowest
             WHERE t1.recorded_at >= %s
             ORDER BY t1.recorded_at DESC",
            ...array_merge([$table, $table], $cleanIds, [$cutoff, $cutoff]),
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
                 ORDER BY recorded_at DESC',
                $this->tableName(),
                $productId,
                $this->gmDateDaysAgo($days),
            ),
        );

        $list = is_array($rows) ? $rows : [];

        return array_map(
            static fn (\stdClass $row) => OmnibusPrice::fromRow($row),
            $list,
        );
    }

    /**
     * Check if a price has already been recorded today for this product.
     */
    public function hasRecordedToday(int $productId): bool
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i
                 WHERE product_id = %d AND recorded_at >= %s',
                $this->tableName(),
                $productId,
                gmdate('Y-m-d 00:00:00'),
            ),
        );

        return $count > 0;
    }

    /**
     * Delete records older than N days.
     */
    public function deleteOlderThan(int $days): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        return (int) $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM %i WHERE recorded_at < %s',
                $this->tableName(),
                $this->gmDateDaysAgo($days),
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
                 ORDER BY recorded_at DESC
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

    private function gmDateDaysAgo(int $days): string
    {
        $ts = strtotime("-{$days} days");

        return gmdate('Y-m-d H:i:s', $ts !== false ? $ts : time());
    }
}
