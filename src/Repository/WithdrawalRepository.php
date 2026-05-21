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
                'channel' => 'online',
                'reason' => $reason,
                'items_json' => $items !== null ? wp_json_encode($items) : null,
                'requested_at' => current_time('mysql', true),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s'],
        );

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Create a withdrawal request initiated by an unauthenticated guest. The visitor's
     * billing email has already been verified by GuestWithdrawalService.
     *
     * @param list<array{product_id: int, quantity: int}>|null $items
     */
    public function createForGuest(
        int $orderId,
        string $guestEmail,
        ?string $reason,
        ?array $items = null,
    ): int {
        $this->wpdb->insert(
            $this->tableName(),
            [
                'order_id' => $orderId,
                'customer_id' => null,
                'status' => WithdrawalStatus::Requested->value,
                'channel' => 'guest',
                'guest_email' => $guestEmail,
                'reason' => $reason,
                'items_json' => $items !== null ? wp_json_encode($items) : null,
                'requested_at' => current_time('mysql', true),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s'],
        );

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Manual / offline registration (phone, mail, letter received by the store).
     *
     * @param list<array{product_id: int, quantity: int}>|null $items
     */
    public function createManual(
        int $orderId,
        ?int $customerId,
        string $channel,
        int $registeredByUserId,
        ?string $reason,
        ?array $items = null,
    ): int {
        $this->wpdb->insert(
            $this->tableName(),
            [
                'order_id' => $orderId,
                'customer_id' => $customerId,
                'status' => WithdrawalStatus::Requested->value,
                'channel' => sanitize_key($channel),
                'registered_by_user_id' => $registeredByUserId,
                'reason' => $reason,
                'items_json' => $items !== null ? wp_json_encode($items) : null,
                'requested_at' => current_time('mysql', true),
            ],
            ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s'],
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

    /**
     * Persist refund metadata onto a withdrawal request (called by Pro after a
     * refund has been processed against the order).
     */
    public function recordRefund(int $id, int $refundId, float $amount): bool
    {
        $updated = $this->wpdb->update(
            $this->tableName(),
            [
                'refund_id' => $refundId,
                'refund_amount' => $amount,
            ],
            ['id' => $id],
            ['%d', '%f'],
            ['%d'],
        );

        return $updated !== false;
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

    /**
     * @return list<WithdrawalRequest>
     */
    public function findByCustomer(int $customerId, int $limit = 50): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, prepared.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE customer_id = %d ORDER BY requested_at DESC LIMIT %d',
                $this->tableName(),
                $customerId,
                $limit,
            ),
        );

        $list = is_array($rows) ? $rows : [];

        return array_map(
            static fn (\stdClass $row) => WithdrawalRequest::fromRow($row),
            $list,
        );
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

    /**
     * Find every withdrawal filed by a guest using the given email address.
     *
     * @return list<WithdrawalRequest>
     */
    public function findByGuestEmail(string $email, int $limit = 200): array
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return [];
        }

        $sql = $this->wpdb->prepare('SELECT * FROM %i WHERE LOWER(guest_email) = %s ORDER BY requested_at DESC LIMIT %d', $this->tableName(), $email, $limit);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom table, prepared above.
        $rows = $this->wpdb->get_results($sql);

        $list = is_array($rows) ? $rows : [];

        return array_map(
            static fn (\stdClass $row) => WithdrawalRequest::fromRow($row),
            $list,
        );
    }

    /**
     * Scrub personally identifiable information from withdrawals belonging to a customer.
     *
     * Withdrawals are legal artifacts and cannot be deleted under accounting and tax
     * retention rules, so the row is kept and only PII columns are reset.
     */
    public function anonymizeForCustomer(int $customerId): int
    {
        if ($customerId <= 0) {
            return 0;
        }

        $affected = $this->wpdb->update(
            $this->tableName(),
            [
                'guest_email' => null,
                'reason' => null,
                'rejected_reason' => null,
            ],
            ['customer_id' => $customerId],
            ['%s', '%s', '%s'],
            ['%d'],
        );

        return (int) $affected;
    }

    /**
     * Scrub personally identifiable information from guest withdrawals filed for a given email.
     */
    public function anonymizeForGuestEmail(string $email): int
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return 0;
        }

        $sql = $this->wpdb->prepare('UPDATE %i SET guest_email = NULL, guest_token_hash = NULL, reason = NULL, rejected_reason = NULL WHERE LOWER(guest_email) = %s', $this->tableName(), $email);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom table, prepared above.
        $affected = $this->wpdb->query($sql);

        return (int) $affected;
    }
}
