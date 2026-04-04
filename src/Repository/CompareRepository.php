<?php

declare(strict_types=1);
namespace Polski\Repository;

defined('ABSPATH') || exit;
/**
 * Data access for product compare items.
 *
 * Inherits add, remove, exists, findAll, count, clear, transferSessionToUser
 * from AbstractProductListRepository. Compare sorts oldest-first and supports
 * removeOldest() for list size enforcement.
 */
final class CompareRepository extends AbstractProductListRepository
{
    protected function tableSuffix(): string
    {
        return 'compare_items';
    }

    protected function defaultOrder(): string
    {
        return 'ASC';
    }

    /**
     * Remove the oldest item from the list (used when max items limit is reached).
     */
    public function removeOldest(?int $userId, ?string $sessionId): void
    {
        $items = $this->findAll($userId, $sessionId);

        if ($items === []) {
            return;
        }

        $this->wpdb->delete(
            $this->tableName(),
            ['id' => $items[0]->id],
            ['%d'],
        );
    }
}
