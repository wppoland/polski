<?php

declare(strict_types=1);

namespace Spolszczony\Model;

use Spolszczony\Enum\QuoteRequestStatus;

/**
 * Value object for a product quote request.
 */
final class QuoteRequest
{
    /**
     * @param array<string, mixed>|null $meta
     */
    public function __construct(
        public readonly int $id,
        public readonly int $productId,
        public readonly ?int $variationId,
        public readonly ?int $customerId,
        public QuoteRequestStatus $status,
        public readonly string $customerName,
        public readonly string $customerEmail,
        public readonly ?string $customerPhone,
        public readonly ?string $companyName,
        public readonly ?string $nip,
        public readonly string $quantity,
        public readonly ?string $postcode,
        public readonly ?string $message,
        public readonly string $source,
        public readonly ?string $sourceUrl,
        public readonly bool $consented,
        public readonly ?array $meta,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromRow(object $row): self
    {
        $meta = null;

        if (! empty($row->meta_json)) {
            $decoded = json_decode((string) $row->meta_json, true);
            $meta = is_array($decoded) ? $decoded : null;
        }

        return new self(
            id: (int) $row->id,
            productId: (int) $row->product_id,
            variationId: $row->variation_id !== null ? (int) $row->variation_id : null,
            customerId: $row->customer_id !== null ? (int) $row->customer_id : null,
            status: QuoteRequestStatus::from((string) $row->status),
            customerName: (string) $row->customer_name,
            customerEmail: (string) $row->customer_email,
            customerPhone: $row->customer_phone !== null ? (string) $row->customer_phone : null,
            companyName: $row->company_name !== null ? (string) $row->company_name : null,
            nip: $row->nip !== null ? (string) $row->nip : null,
            quantity: (string) $row->quantity,
            postcode: $row->postcode !== null ? (string) $row->postcode : null,
            message: $row->message !== null ? (string) $row->message : null,
            source: (string) $row->source,
            sourceUrl: $row->source_url !== null ? (string) $row->source_url : null,
            consented: (bool) $row->consented,
            meta: $meta,
            createdAt: new \DateTimeImmutable((string) $row->created_at),
            updatedAt: new \DateTimeImmutable((string) $row->updated_at),
        );
    }
}
