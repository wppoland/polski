<?php

declare(strict_types=1);

namespace Polski\Model;

use Polski\Enum\ReturnRequestStatus;

defined('ABSPATH') || exit;

/**
 * A customer return or complaint (RMA) request.
 */
final class ReturnRequest
{
    public function __construct(
        public readonly int $id,
        public readonly int $orderId,
        public readonly ?int $customerId,
        public readonly string $type,
        public ReturnRequestStatus $status,
        public readonly ?string $reason,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromRow(\stdClass $row): self
    {
        return new self(
            (int) $row->id,
            (int) $row->order_id,
            $row->customer_id !== null ? (int) $row->customer_id : null,
            (string) $row->type,
            ReturnRequestStatus::from((string) $row->status),
            $row->reason !== null ? (string) $row->reason : null,
            new \DateTimeImmutable((string) $row->created_at),
            $row->updated_at !== null ? new \DateTimeImmutable((string) $row->updated_at) : null,
        );
    }
}
