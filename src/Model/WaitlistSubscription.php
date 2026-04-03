<?php

declare(strict_types=1);

namespace Polski\Model;

/**
 * Waitlist subscription value object.
 */
final class WaitlistSubscription
{
    public function __construct(
        public readonly int $id,
        public readonly int $productId,
        public readonly string $email,
        public readonly ?int $userId,
        public readonly bool $notified,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $notifiedAt,
    ) {
    }

    public static function fromRow(object $row): self
    {
        return new self(
            id: (int) $row->id,
            productId: (int) $row->product_id,
            email: (string) $row->email,
            userId: $row->user_id !== null ? (int) $row->user_id : null,
            notified: (bool) $row->notified,
            createdAt: new \DateTimeImmutable((string) $row->created_at),
            notifiedAt: ! empty($row->notified_at) ? new \DateTimeImmutable((string) $row->notified_at) : null,
        );
    }
}
