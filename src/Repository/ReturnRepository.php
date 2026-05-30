<?php

declare(strict_types=1);

namespace Polski\Repository;

use Polski\Enum\ReturnRequestStatus;
use Polski\Model\ReturnRequest;

defined('ABSPATH') || exit;

/**
 * Storage for return / complaint (RMA) requests in the custom polski_returns table.
 */
final class ReturnRepository
{
    public function __construct(
        private readonly \wpdb $wpdb,
    ) {
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . 'polski_returns';
    }

    public function create(int $orderId, ?int $customerId, string $type, ?string $reason): int
    {
        $this->wpdb->insert(
            $this->tableName(),
            [
                'order_id' => $orderId,
                'customer_id' => $customerId,
                'type' => $type,
                'status' => ReturnRequestStatus::Submitted->value,
                'reason' => $reason,
                'created_at' => current_time('mysql', true),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s'],
        );

        return (int) $this->wpdb->insert_id;
    }

    public function findById(int $id): ?ReturnRequest
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM %i WHERE id = %d', $this->tableName(), $id),
        );

        return $row !== null ? ReturnRequest::fromRow($row) : null;
    }

    /**
     * @return list<ReturnRequest>
     */
    public function findByOrder(int $orderId): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM %i WHERE order_id = %d ORDER BY created_at DESC', $this->tableName(), $orderId),
        );

        return array_values(array_map([ReturnRequest::class, 'fromRow'], $rows ?: []));
    }

    /**
     * @return list<ReturnRequest>
     */
    public function findAll(int $limit = 50, int $offset = 0): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM %i ORDER BY created_at DESC LIMIT %d OFFSET %d', $this->tableName(), $limit, $offset),
        );

        return array_values(array_map([ReturnRequest::class, 'fromRow'], $rows ?: []));
    }

    public function updateStatus(int $id, ReturnRequestStatus $status): bool
    {
        $updated = $this->wpdb->update(
            $this->tableName(),
            ['status' => $status->value, 'updated_at' => current_time('mysql', true)],
            ['id' => $id],
            ['%s', '%s'],
            ['%d'],
        );

        return $updated !== false;
    }
}
