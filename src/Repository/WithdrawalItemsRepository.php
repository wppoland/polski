<?php

declare(strict_types=1);
namespace Polski\Repository;

defined('ABSPATH') || exit;

use wpdb;

/**
 * Normalised per-item lines for withdrawal requests. Stored in
 * `{prefix}polski_withdrawal_items`. Enables partial refunds, sequential
 * withdrawals (each one targets the items not yet withdrawn), and per-item
 * reporting without parsing the legacy JSON column.
 */
final class WithdrawalItemsRepository
{
    public function __construct(
        private readonly wpdb $wpdb,
    ) {
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . 'polski_withdrawal_items';
    }

    /**
     * @param list<array{
     *     order_item_id: int,
     *     product_id: int,
     *     variation_id?: int|null,
     *     quantity: float,
     *     line_subtotal?: float,
     *     line_total?: float,
     *     line_tax?: float,
     *     sku?: string|null,
     *     name?: string,
     *     attributes?: array<string, string>|null,
     * }> $items
     */
    public function insertMany(int $withdrawalId, array $items): void
    {
        foreach ($items as $item) {
            $this->wpdb->insert(
                $this->tableName(),
                [
                    'withdrawal_id' => $withdrawalId,
                    'order_item_id' => (int) $item['order_item_id'],
                    'product_id' => (int) $item['product_id'],
                    'variation_id' => isset($item['variation_id']) && $item['variation_id'] !== null
                        ? (int) $item['variation_id']
                        : null,
                    'quantity' => (float) $item['quantity'],
                    'line_subtotal' => isset($item['line_subtotal']) ? (float) $item['line_subtotal'] : 0,
                    'line_total' => isset($item['line_total']) ? (float) $item['line_total'] : 0,
                    'line_tax' => isset($item['line_tax']) ? (float) $item['line_tax'] : 0,
                    'sku' => isset($item['sku']) ? (string) $item['sku'] : null,
                    'name' => isset($item['name']) ? mb_substr((string) $item['name'], 0, 255) : '',
                    'attributes_json' => isset($item['attributes']) && $item['attributes'] !== null
                        ? wp_json_encode($item['attributes'])
                        : null,
                    'created_at' => current_time('mysql', true),
                ],
                ['%d', '%d', '%d', '%d', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s'],
            );
        }
    }

    /**
     * Sum already-withdrawn quantities per order item across every non-rejected
     * withdrawal for the given order. Use this to compute remaining qty for the
     * "Withdraw more items" flow.
     *
     * @return array<int, float> Map of order_item_id => withdrawn quantity.
     */
    public function withdrawnQuantitiesForOrder(int $orderId): array
    {
        global $wpdb;
        $itemsTable = $this->tableName();
        $withdrawalsTable = $wpdb->prefix . 'polski_withdrawals';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables, prepared statement.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT i.order_item_id AS order_item_id, SUM(i.quantity) AS qty
                 FROM %i AS i
                 INNER JOIN %i AS w ON i.withdrawal_id = w.id
                 WHERE w.order_id = %d AND w.status != %s
                 GROUP BY i.order_item_id',
                $itemsTable,
                $withdrawalsTable,
                $orderId,
                'rejected',
            ),
        );

        $map = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $map[(int) $row->order_item_id] = (float) $row->qty;
            }
        }

        return $map;
    }

    /**
     * @return list<\stdClass>
     */
    public function findByWithdrawal(int $withdrawalId): array
    {
        global $wpdb;
        $table = $this->tableName();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, prepared statement.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE withdrawal_id = %d ORDER BY id ASC',
                $table,
                $withdrawalId,
            ),
        );

        return is_array($rows) ? $rows : [];
    }
}
