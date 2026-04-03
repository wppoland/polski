<?php

declare(strict_types=1);

namespace Polski\Repository;

use Polski\Model\WaitlistSubscription;
use wpdb;

/**
 * Data access for product waitlist subscriptions.
 */
final class WaitlistRepository
{
    public function __construct(
        private readonly wpdb $wpdb,
    ) {
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . 'polski_waitlist';
    }

    public function subscribe(int $productId, string $email, ?int $userId): int
    {
        $existing = $this->findByProductAndEmail($productId, $email);

        if ($existing !== null) {
            return $existing->id;
        }

        $this->wpdb->insert(
            $this->tableName(),
            [
                'product_id' => $productId,
                'email' => $email,
                'user_id' => $userId,
                'notified' => 0,
                'created_at' => current_time('mysql', true),
            ],
            ['%d', '%s', '%d', '%d', '%s'],
        );

        return (int) $this->wpdb->insert_id;
    }

    public function findByProductAndEmail(int $productId, string $email): ?WaitlistSubscription
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                'SELECT * FROM ' . $this->tableName() . ' WHERE product_id = %d AND email = %s LIMIT 1',
                $productId,
                $email,
            ),
        );

        return $row !== null ? WaitlistSubscription::fromRow($row) : null;
    }

    /**
     * @return list<WaitlistSubscription>
     */
    public function findPendingByProduct(int $productId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                'SELECT * FROM ' . $this->tableName() . ' WHERE product_id = %d AND notified = 0 ORDER BY created_at ASC',
                $productId,
            ),
        );

        return array_map(
            static fn (object $row): WaitlistSubscription => WaitlistSubscription::fromRow($row),
            is_array($rows) ? $rows : [],
        );
    }

    public function markNotified(int $id): void
    {
        $this->wpdb->update(
            $this->tableName(),
            [
                'notified' => 1,
                'notified_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%d', '%s'],
            ['%d'],
        );
    }
}
