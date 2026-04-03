<?php

declare(strict_types=1);

namespace Polski\Model;

/**
 * Affiliate account aggregate.
 */
final class Affiliate
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $token,
        public readonly string $status,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromRow(object $row): self
    {
        return new self(
            id: (int) $row->id,
            userId: (int) $row->user_id,
            token: (string) $row->token,
            status: (string) $row->status,
            createdAt: new \DateTimeImmutable((string) $row->created_at),
        );
    }
}
