<?php

declare(strict_types=1);

namespace Polski\Invoice;

defined('ABSPATH') || exit;

/**
 * An issued invoice, as read back from storage.
 *
 * The snapshot is the document. Everything a reader sees comes from it, not
 * from the order, so editing an order after the fact cannot rewrite history.
 */
final class Invoice
{
    /**
     * @param array<string, mixed> $snapshot
     */
    public function __construct(
        public readonly int $id,
        public readonly int $orderId,
        public readonly string $number,
        public readonly string $issuedAt,
        public readonly ?string $soldAt,
        public readonly ?string $dueAt,
        public readonly array $snapshot,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $snapshot = json_decode((string) ($row['snapshot'] ?? '{}'), true);

        return new self(
            (int) ($row['id'] ?? 0),
            (int) ($row['order_id'] ?? 0),
            (string) ($row['number'] ?? ''),
            (string) ($row['issued_at'] ?? ''),
            isset($row['sold_at']) && '' !== (string) $row['sold_at'] ? (string) $row['sold_at'] : null,
            isset($row['due_at']) && '' !== (string) $row['due_at'] ? (string) $row['due_at'] : null,
            is_array($snapshot) ? $snapshot : [],
        );
    }

    /**
     * A stable, unguessable token so a customer can open their own invoice
     * without being logged in (an emailed link), and nobody can walk the ids.
     */
    public function token(): string
    {
        return substr(hash_hmac('sha256', $this->id . '|' . $this->number, wp_salt('auth')), 0, 32);
    }
}
