<?php

declare(strict_types=1);

namespace Polski\Model;

/**
 * Gift card value object.
 */
final class GiftCard
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly float $initialBalance,
        public readonly float $balance,
        public readonly string $currency,
        public readonly ?int $purchaserUserId,
        public readonly string $purchaserEmail,
        public readonly string $recipientName,
        public readonly string $recipientEmail,
        public readonly string $senderName,
        public readonly string $message,
        public readonly int $orderId,
        public readonly int $productId,
        public readonly string $status,
        public readonly ?\DateTimeImmutable $expiresAt,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromRow(object $row): self
    {
        return new self(
            id: (int) $row->id,
            code: (string) $row->code,
            initialBalance: (float) $row->initial_balance,
            balance: (float) $row->balance,
            currency: (string) $row->currency,
            purchaserUserId: ! empty($row->purchaser_user_id) ? (int) $row->purchaser_user_id : null,
            purchaserEmail: (string) $row->purchaser_email,
            recipientName: (string) $row->recipient_name,
            recipientEmail: (string) $row->recipient_email,
            senderName: (string) $row->sender_name,
            message: (string) $row->message,
            orderId: (int) $row->order_id,
            productId: (int) $row->product_id,
            status: (string) $row->status,
            expiresAt: ! empty($row->expires_at) ? new \DateTimeImmutable((string) $row->expires_at) : null,
            createdAt: new \DateTimeImmutable((string) $row->created_at),
        );
    }
}
