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
final class OmnibusPriceRepository
{
    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Table names are from $this->tableName() (safe, not user input).

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
        $table = $this->tableName();
        $date = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE product_id = %d AND recorded_at >= %s
                 ORDER BY price ASC
                 LIMIT 1",
                $productId,
                $date,
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
        $table = $this->tableName();
        $date = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE product_id = %d AND recorded_at >= %s
                 ORDER BY COALESCE(sale_price, price) ASC
                 LIMIT 1",
                $productId,
                $date,
            ),
        );

        if ($row === null) {
            return null;
        }

        return OmnibusPrice::fromRow($row);
    }

    /**
     * Get complete price history for a product.
     *
     * @return list<OmnibusPrice>
     */
    public function findHistory(int $productId, int $days = 30): array
    {
        $table = $this->tableName();
        $date = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE product_id = %d AND recorded_at >= %s
                 ORDER BY recorded_at DESC",
                $productId,
                $date,
            ),
        );

        return array_map(
            static fn (object $row) => OmnibusPrice::fromRow($row),
            $rows,
        );
    }

    /**
     * Check if a price has already been recorded today for this product.
     */
    public function hasRecordedToday(int $productId): bool
    {
        $table = $this->tableName();
        $today = gmdate('Y-m-d 00:00:00');

        $count = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE product_id = %d AND recorded_at >= %s",
                $productId,
                $today,
            ),
        );

        return $count > 0;
    }

    /**
     * Delete records older than N days.
     */
    public function deleteOlderThan(int $days): int
    {
        $table = $this->tableName();
        $date = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        return (int) $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$table} WHERE recorded_at < %s",
                $date,
            ),
        );
    }

    /**
     * Get the most recent recorded price for a product.
     */
    public function findLatest(int $productId): ?OmnibusPrice
    {
        $table = $this->tableName();

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE product_id = %d
                 ORDER BY recorded_at DESC
                 LIMIT 1",
                $productId,
            ),
        );

        if ($row === null) {
            return null;
        }

        return OmnibusPrice::fromRow($row);
    }

    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
}
