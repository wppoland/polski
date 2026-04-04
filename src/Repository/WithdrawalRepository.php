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
    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Table names are from $this->tableName() (safe, not user input).

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
        $table = $this->tableName();

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
        );

        return $row !== null ? WithdrawalRequest::fromRow($row) : null;
    }

    public function findByOrder(int $orderId): ?WithdrawalRequest
    {
        $table = $this->tableName();

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE order_id = %d ORDER BY requested_at DESC LIMIT 1",
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
        $table = $this->tableName();

        if ($status !== null) {
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$table} WHERE status = %s ORDER BY requested_at DESC LIMIT %d OFFSET %d",
                    $status->value,
                    $limit,
                    $offset,
                ),
            );
        } else {
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$table} ORDER BY requested_at DESC LIMIT %d OFFSET %d",
                    $limit,
                    $offset,
                ),
            );
        }

        return array_map(
            static fn (object $row) => WithdrawalRequest::fromRow($row),
            $rows,
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
        $table = $this->tableName();

        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = %s",
                $status->value,
            ),
        );
    }

    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
}
