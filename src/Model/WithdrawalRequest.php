<?php

declare(strict_types=1);
namespace Polski\Model;

defined('ABSPATH') || exit;

use Polski\Enum\WithdrawalStatus;

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
        public readonly string $channel = 'online',
        public readonly ?string $guestEmail = null,
        public readonly ?int $registeredByUserId = null,
        public readonly ?\DateTimeImmutable $rejectedAt = null,
        public readonly ?string $rejectedReason = null,
        public readonly ?int $refundId = null,
        public readonly ?float $refundAmount = null,
        public readonly ?\DateTimeImmutable $clockStartedAt = null,
        public readonly string $languageCode = 'pl',
    ) {
    }

    /**
     * @phpstan-type WithdrawalItem array{product_id: int, quantity: int}
     *
     * @param \stdClass $row Database row (wpdb).
     */
    public static function fromRow(\stdClass $row): self
    {
        $items = null;
        if (isset($row->items_json) && $row->items_json !== null) {
            $decoded = json_decode($row->items_json, true);
            $items = self::parseItemsFromJsonDecoded($decoded);
        }

        $requestedAt = self::parseDate(isset($row->requested_at) ? (string) $row->requested_at : '')
            ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            id: (int) $row->id,
            orderId: (int) $row->order_id,
            customerId: $row->customer_id !== null ? (int) $row->customer_id : null,
            status: WithdrawalStatus::from($row->status),
            reason: $row->reason,
            items: $items,
            requestedAt: $requestedAt,
            confirmedAt: self::parseDate(isset($row->confirmed_at) ? (string) $row->confirmed_at : null),
            completedAt: self::parseDate(isset($row->completed_at) ? (string) $row->completed_at : null),
            channel: isset($row->channel) ? (string) $row->channel : 'online',
            guestEmail: isset($row->guest_email) && $row->guest_email !== null ? (string) $row->guest_email : null,
            registeredByUserId: isset($row->registered_by_user_id) && $row->registered_by_user_id !== null
                ? (int) $row->registered_by_user_id
                : null,
            rejectedAt: self::parseDate(isset($row->rejected_at) ? (string) $row->rejected_at : null),
            rejectedReason: isset($row->rejected_reason) && $row->rejected_reason !== null
                ? (string) $row->rejected_reason
                : null,
            refundId: isset($row->refund_id) && $row->refund_id !== null ? (int) $row->refund_id : null,
            refundAmount: isset($row->refund_amount) && $row->refund_amount !== null
                ? (float) $row->refund_amount
                : null,
            clockStartedAt: self::parseDate(isset($row->clock_started_at) ? (string) $row->clock_started_at : null),
            languageCode: isset($row->language_code) ? (string) $row->language_code : 'pl',
        );
    }

    /**
     * Parse a MySQL DATETIME string into DateTimeImmutable, rejecting zero-dates
     * and any value that does not match the canonical `Y-m-d H:i:s` format.
     */
    private static function parseDate(?string $raw): ?\DateTimeImmutable
    {
        if ($raw === null) {
            return null;
        }

        $raw = trim($raw);

        if ($raw === '' || str_starts_with($raw, '0000')) {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, new \DateTimeZone('UTC'));

        return $parsed instanceof \DateTimeImmutable ? $parsed : null;
    }

    /**
     * @phpstan-param mixed $decoded
     *
     * @return list<array{product_id: int, quantity: int}>|null
     */
    private static function parseItemsFromJsonDecoded($decoded): ?array
    {
        if (! is_array($decoded)) {
            return null;
        }

        $items = [];

        foreach ($decoded as $item) {
            if (! is_array($item)) {
                return null;
            }

            if (! isset($item['product_id'], $item['quantity'])) {
                return null;
            }

            $productId = filter_var($item['product_id'], FILTER_VALIDATE_INT);
            $quantity = filter_var($item['quantity'], FILTER_VALIDATE_INT);

            if ($productId === false || $quantity === false) {
                return null;
            }

            $items[] = [
                'product_id' => (int) $productId,
                'quantity' => (int) $quantity,
            ];
        }

        return $items;
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
            'channel' => $this->channel,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reason' => $this->reason,
            'items' => $this->items,
            'guest_email' => $this->guestEmail,
            'registered_by_user_id' => $this->registeredByUserId,
            'refund_id' => $this->refundId,
            'refund_amount' => $this->refundAmount,
            'language_code' => $this->languageCode,
            'requested_at' => $this->requestedAt->format('c'),
            'confirmed_at' => $this->confirmedAt?->format('c'),
            'completed_at' => $this->completedAt?->format('c'),
            'rejected_at' => $this->rejectedAt?->format('c'),
            'rejected_reason' => $this->rejectedReason,
            'clock_started_at' => $this->clockStartedAt?->format('c'),
        ];
    }
}
