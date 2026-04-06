<?php

declare(strict_types=1);
namespace Polski\Model;

defined('ABSPATH') || exit;
/**
 * Value object for a product list item (wishlist, compare, etc.).
 *
 * Shared base for features that store product_id + user_id/session_id.
 */
class ProductListItem
{
    public function __construct(
        public readonly int $id,
        public readonly int $productId,
        public readonly ?int $userId,
        public readonly ?string $sessionId,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param \stdClass $row Database row (wpdb).
     */
    public static function fromRow(\stdClass $row): self
    {
        return new self(
            id: (int) $row->id,
            productId: (int) $row->product_id,
            userId: $row->user_id !== null ? (int) $row->user_id : null,
            sessionId: $row->session_id !== null ? (string) $row->session_id : null,
            createdAt: new \DateTimeImmutable((string) $row->created_at),
        );
    }
}
