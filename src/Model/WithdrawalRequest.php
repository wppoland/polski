<?php

declare(strict_types=1);

namespace Spolszczony\Model;

use Spolszczony\Enum\WithdrawalStatus;

/**
 * Value object for a consumer withdrawal (return) request.
 */
final class WithdrawalRequest
{
    public function __construct(
        public readonly int $id,
        public readonly int $orderId,
        public readonly ?int $customerId,
        public WithdrawalStatus $status,
        public readonly ?string $reason,
        /** @var list<array{product_id: int, quantity: int}>|null */
        public readonly ?array $items,
        public readonly \DateTimeImmutable $requestedAt,
        public readonly ?\DateTimeImmutable $confirmedAt,
        public readonly ?\DateTimeImmutable $completedAt,
    ) {
    }

    /**
     * @param object $row Database row.
     */
    public static function fromRow(object $row): self
    {
        $items = null;
        if ($row->items_json !== null) {
            $decoded = json_decode($row->items_json, true);
            $items = is_array($decoded) ? $decoded : null;
        }

        return new self(
            id: (int) $row->id,
            orderId: (int) $row->order_id,
            customerId: $row->customer_id !== null ? (int) $row->customer_id : null,
            status: WithdrawalStatus::from($row->status),
            reason: $row->reason,
            items: $items,
            requestedAt: new \DateTimeImmutable($row->requested_at),
            confirmedAt: $row->confirmed_at !== null ? new \DateTimeImmutable($row->confirmed_at) : null,
            completedAt: $row->completed_at !== null ? new \DateTimeImmutable($row->completed_at) : null,
        );
    }

    /**
     * Check if the request can be confirmed.
     */
    public function canConfirm(): bool
    {
        return $this->status === WithdrawalStatus::Requested;
    }

    /**
     * Check if the request can be completed.
     */
    public function canComplete(): bool
    {
        return $this->status === WithdrawalStatus::Confirmed;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->orderId,
            'customer_id' => $this->customerId,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reason' => $this->reason,
            'items' => $this->items,
            'requested_at' => $this->requestedAt->format('c'),
            'confirmed_at' => $this->confirmedAt?->format('c'),
            'completed_at' => $this->completedAt?->format('c'),
        ];
    }
}
