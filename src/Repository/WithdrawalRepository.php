<?php

declare(strict_types=1);
namespace Polski\Repository;

defined('ABSPATH') || exit;

use Polski\Enum\WithdrawalStatus;
use Polski\Model\WithdrawalRequest;
use wpdb;

/**
 * Data access for the withdrawals table.
 */
final class WithdrawalRepository
{
    public function __construct(
        private readonly wpdb $wpdb,
    ) {
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . 'polski_withdrawals';
    }

    /**
     * Create a new withdrawal request.
     *
     * @param list<array{product_id: int, quantity: int}>|null $items
     */
    public function create(
        int $orderId,
        ?int $customerId,
        ?string $reason,
        ?array $items = null,
    ): int {
        $this->wpdb->insert(
            $this->tableName(),
            [
                'order_id' => $orderId,
                'customer_id' => $customerId,
                'status' => WithdrawalStatus::Requested->value,
                'reason' => $reason,
                'items_json' => $items !== null ? wp_json_encode($items) : null,
                'requested_at' => current_time('mysql', true),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s'],
        );

        return (int) $this->wpdb->insert_id;
    }

    public function findById(int $id): ?WithdrawalRequest
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE id = %d',
                $this->tableName(),
                $id,
            ),
        );

        return $row !== null ? WithdrawalRequest::fromRow($row) : null;
    }

    public function findByOrder(int $orderId): ?WithdrawalRequest
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE order_id = %d ORDER BY requested_at DESC LIMIT 1',
                $this->tableName(),
                $orderId,
            ),
        );

        return $row !== null ? WithdrawalRequest::fromRow($row) : null;
    }

    /**
     * @return list<WithdrawalRequest>
     */
    public function findAll(int $limit = 50, int $offset = 0, ?WithdrawalStatus $status = null): array
    {
        global $wpdb;

        if ($status !== null) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM %i WHERE status = %s ORDER BY requested_at DESC LIMIT %d OFFSET %d',
                    $this->tableName(),
                    $status->value,
                    $limit,
                    $offset,
                ),
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM %i ORDER BY requested_at DESC LIMIT %d OFFSET %d',
                    $this->tableName(),
                    $limit,
                    $offset,
                ),
            );
        }

        $list = is_array($rows) ? $rows : [];

        return array_map(
            static fn (\stdClass $row) => WithdrawalRequest::fromRow($row),
            $list,
        );
    }

    public function updateStatus(int $id, WithdrawalStatus $status): bool
    {
        $data = ['status' => $status->value];

        if ($status === WithdrawalStatus::Confirmed) {
            $data['confirmed_at'] = current_time('mysql', true);
        } elseif ($status === WithdrawalStatus::Completed) {
            $data['completed_at'] = current_time('mysql', true);
        }

        $updated = $this->wpdb->update(
            $this->tableName(),
            $data,
            ['id' => $id],
        );

        return $updated !== false;
    }

    public function countByStatus(WithdrawalStatus $status): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE status = %s',
                $this->tableName(),
                $status->value,
            ),
        );
    }
}
