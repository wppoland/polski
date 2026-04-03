<?php

declare(strict_types=1);

namespace Polski\Model;

/**
 * Referral attributed to an affiliate.
 */
final class AffiliateReferral
{
    public function __construct(
        public readonly int $id,
        public readonly int $affiliateId,
        public readonly int $orderId,
        public readonly string $customerEmail,
        public readonly float $orderTotal,
        public readonly float $commissionAmount,
        public readonly string $status,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromRow(object $row): self
    {
        return new self(
            id: (int) $row->id,
            affiliateId: (int) $row->affiliate_id,
            orderId: (int) $row->order_id,
            customerEmail: (string) $row->customer_email,
            orderTotal: (float) $row->order_total,
            commissionAmount: (float) $row->commission_amount,
            status: (string) $row->status,
            createdAt: new \DateTimeImmutable((string) $row->created_at),
        );
    }
}
