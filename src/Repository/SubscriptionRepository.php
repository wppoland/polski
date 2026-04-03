<?php

declare(strict_types=1);

namespace Polski\Repository;

use Polski\Model\Subscription;
use wpdb;

/**
 * Persistence for subscriptions.
 */
final class SubscriptionRepository
{
    public function __construct(
        private readonly wpdb $wpdb,
    ) {
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . 'polski_subscriptions';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $this->wpdb->insert(
            $this->tableName(),
            [
                'user_id' => $data['user_id'] ?: null,
                'email' => (string) $data['email'],
                'product_id' => (int) $data['product_id'],
                'product_name' => (string) $data['product_name'],
                'source_order_id' => (int) $data['source_order_id'],
                'quantity' => (int) $data['quantity'],
                'status' => (string) $data['status'],
                'interval_count' => (int) $data['interval_count'],
                'interval_period' => (string) $data['interval_period'],
                'cycles_total' => (int) $data['cycles_total'],
                'cycles_completed' => (int) $data['cycles_completed'],
                'recurring_amount' => (float) $data['recurring_amount'],
                'signup_fee' => (float) $data['signup_fee'],
                'trial_days' => (int) $data['trial_days'],
                'next_payment_at' => $data['next_payment_at'],
                'last_payment_at' => $data['last_payment_at'] ?? null,
                'last_reminder_at' => null,
                'cancelled_at' => null,
                'created_at' => current_time('mysql', true),
            ],
            ['%d', '%s', '%d', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%d', '%f', '%f', '%d', '%s', '%s', '%s', '%s'],
        );

        return (int) $this->wpdb->insert_id;
    }

    public function findByOrderAndProduct(int $orderId, int $productId): ?Subscription
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                'SELECT * FROM ' . $this->tableName() . ' WHERE source_order_id = %d AND product_id = %d LIMIT 1',
                $orderId,
                $productId,
            ),
        );

        return $row !== null ? Subscription::fromRow($row) : null;
    }

    /**
     * @return list<Subscription>
     */
    public function findForAccount(int $userId, string $email): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                'SELECT * FROM ' . $this->tableName() . ' WHERE user_id = %d OR email = %s ORDER BY created_at DESC',
                $userId,
                $email,
            ),
        );

        return array_map(
            static fn (object $row): Subscription => Subscription::fromRow($row),
            is_array($rows) ? $rows : [],
        );
    }

    /**
     * @return list<Subscription>
     */
    public function findDueRenewals(string $now): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                'SELECT * FROM ' . $this->tableName() . ' WHERE status IN (%s, %s) AND next_payment_at IS NOT NULL AND next_payment_at <= %s ORDER BY next_payment_at ASC',
                'active',
                'trial',
                $now,
            ),
        );

        return array_map(
            static fn (object $row): Subscription => Subscription::fromRow($row),
            is_array($rows) ? $rows : [],
        );
    }

    /**
     * @return list<Subscription>
     */
    public function findUpcomingReminders(string $threshold, int $reminderDays): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                'SELECT * FROM ' . $this->tableName() . ' WHERE status IN (%s, %s) AND next_payment_at IS NOT NULL AND next_payment_at <= %s AND (last_reminder_at IS NULL OR last_reminder_at < DATE_SUB(next_payment_at, INTERVAL %d DAY))',
                'active',
                'trial',
                $threshold,
                $reminderDays,
            ),
        );

        return array_map(
            static fn (object $row): Subscription => Subscription::fromRow($row),
            is_array($rows) ? $rows : [],
        );
    }

    public function markReminderSent(int $id): void
    {
        $this->wpdb->update(
            $this->tableName(),
            ['last_reminder_at' => current_time('mysql', true)],
            ['id' => $id],
            ['%s'],
            ['%d'],
        );
    }

    public function markRenewed(int $id, string $nextPaymentAt, string $status, int $cyclesCompleted): void
    {
        $this->wpdb->update(
            $this->tableName(),
            [
                'next_payment_at' => $nextPaymentAt,
                'last_payment_at' => current_time('mysql', true),
                'status' => $status,
                'cycles_completed' => $cyclesCompleted,
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%d'],
            ['%d'],
        );
    }

    public function updateStatus(int $id, string $status): void
    {
        $data = ['status' => $status];
        $format = ['%s'];

        if ($status === 'cancelled') {
            $data['cancelled_at'] = current_time('mysql', true);
            $format[] = '%s';
        }

        $this->wpdb->update(
            $this->tableName(),
            $data,
            ['id' => $id],
            $format,
            ['%d'],
        );
    }

    public function findById(int $id): ?Subscription
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare('SELECT * FROM ' . $this->tableName() . ' WHERE id = %d LIMIT 1', $id),
        );

        return $row !== null ? Subscription::fromRow($row) : null;
    }
}
