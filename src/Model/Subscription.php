<?php

declare(strict_types=1);

namespace Polski\Model;

/**
 * Subscription aggregate for manual renewals.
 */
final class Subscription
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $userId,
        public readonly string $email,
        public readonly int $productId,
        public readonly string $productName,
        public readonly int $sourceOrderId,
        public readonly int $quantity,
        public readonly string $status,
        public readonly int $intervalCount,
        public readonly string $intervalPeriod,
        public readonly int $cyclesTotal,
        public readonly int $cyclesCompleted,
        public readonly float $recurringAmount,
        public readonly float $signupFee,
        public readonly int $trialDays,
        public readonly ?\DateTimeImmutable $nextPaymentAt,
        public readonly ?\DateTimeImmutable $lastPaymentAt,
        public readonly ?\DateTimeImmutable $lastReminderAt,
        public readonly ?\DateTimeImmutable $cancelledAt,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromRow(object $row): self
    {
        return new self(
            id: (int) $row->id,
            userId: ! empty($row->user_id) ? (int) $row->user_id : null,
            email: (string) $row->email,
            productId: (int) $row->product_id,
            productName: (string) $row->product_name,
            sourceOrderId: (int) $row->source_order_id,
            quantity: (int) $row->quantity,
            status: (string) $row->status,
            intervalCount: (int) $row->interval_count,
            intervalPeriod: (string) $row->interval_period,
            cyclesTotal: (int) $row->cycles_total,
            cyclesCompleted: (int) $row->cycles_completed,
            recurringAmount: (float) $row->recurring_amount,
            signupFee: (float) $row->signup_fee,
            trialDays: (int) $row->trial_days,
            nextPaymentAt: ! empty($row->next_payment_at) ? new \DateTimeImmutable((string) $row->next_payment_at) : null,
            lastPaymentAt: ! empty($row->last_payment_at) ? new \DateTimeImmutable((string) $row->last_payment_at) : null,
            lastReminderAt: ! empty($row->last_reminder_at) ? new \DateTimeImmutable((string) $row->last_reminder_at) : null,
            cancelledAt: ! empty($row->cancelled_at) ? new \DateTimeImmutable((string) $row->cancelled_at) : null,
            createdAt: new \DateTimeImmutable((string) $row->created_at),
        );
    }
}
