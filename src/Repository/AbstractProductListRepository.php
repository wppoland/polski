<?php

declare(strict_types=1);
namespace Polski\Repository;

defined('ABSPATH') || exit;

use Polski\Model\ProductListItem;
use wpdb;

/**
 * Shared repository for product list tables (wishlist, compare).
 *
 * Subclasses only need to define the table suffix via tableSuffix().
 * Schema assumption: id, product_id, user_id, session_id, created_at.
 */
abstract class AbstractProductListRepository
{
    public function __construct(
        protected readonly wpdb $wpdb,
    ) {
    }

    /**
     * Table suffix without prefix (e.g., 'wishlist_items', 'compare_items').
     */
    abstract protected function tableSuffix(): string;

    /**
     * Default sort direction for findAll() results.
     */
    protected function defaultOrder(): string
    {
        return 'DESC';
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . 'polski_' . $this->tableSuffix();
    }

    public function add(int $productId, ?int $userId, ?string $sessionId): int
    {
        if ($this->exists($productId, $userId, $sessionId)) {
            return 0;
        }

        $this->wpdb->insert(
            $this->tableName(),
            [
                'product_id' => $productId,
                'user_id' => $userId,
                'session_id' => $sessionId,
                'created_at' => current_time('mysql', true),
            ],
            ['%d', '%d', '%s', '%s'],
        );

        return (int) $this->wpdb->insert_id;
    }

    public function remove(int $productId, ?int $userId, ?string $sessionId): bool
    {
        $where = ['product_id' => $productId];
        $format = ['%d'];

        if ($userId !== null) {
            $where['user_id'] = $userId;
            $format[] = '%d';
        } elseif ($sessionId !== null) {
            $where['session_id'] = $sessionId;
            $format[] = '%s';
        } else {
            return false;
        }

        return $this->wpdb->delete($this->tableName(), $where, $format) !== false;
    }

    public function exists(int $productId, ?int $userId, ?string $sessionId): bool
    {
        $table = $this->tableName();

        if ($userId !== null) {
            return (int) $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE product_id = %d AND user_id = %d",
                    $productId,
                    $userId,
                ),
            ) > 0;
        }

        if ($sessionId !== null) {
            return (int) $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE product_id = %d AND session_id = %s",
                    $productId,
                    $sessionId,
                ),
            ) > 0;
        }

        return false;
    }

    /**
     * @return list<ProductListItem>
     */
    public function findAll(?int $userId, ?string $sessionId): array
    {
        $table = $this->tableName();
        $order = $this->defaultOrder();

        if ($userId !== null) {
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at {$order}",
                    $userId,
                ),
            );
        } elseif ($sessionId !== null) {
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$table} WHERE session_id = %s ORDER BY created_at {$order}",
                    $sessionId,
                ),
            );
        } else {
            return [];
        }

        return array_map(
            static fn (object $row): ProductListItem => ProductListItem::fromRow($row),
            is_array($rows) ? $rows : [],
        );
    }

    public function count(?int $userId, ?string $sessionId): int
    {
        $table = $this->tableName();

        if ($userId !== null) {
            return (int) $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
                    $userId,
                ),
            );
        }

        if ($sessionId !== null) {
            return (int) $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE session_id = %s",
                    $sessionId,
                ),
            );
        }

        return 0;
    }

    public function clear(?int $userId, ?string $sessionId): bool
    {
        if ($userId !== null) {
            return $this->wpdb->delete($this->tableName(), ['user_id' => $userId], ['%d']) !== false;
        }

        if ($sessionId !== null) {
            return $this->wpdb->delete($this->tableName(), ['session_id' => $sessionId], ['%s']) !== false;
        }

        return false;
    }

    public function transferSessionToUser(string $sessionId, int $userId): void
    {
        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->tableName()} SET user_id = %d WHERE session_id = %s AND (user_id IS NULL OR user_id = 0)",
                $userId,
                $sessionId,
            ),
        );
    }
}
