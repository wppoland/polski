<?php

declare(strict_types=1);

namespace Polski\KSeF\Model;

/**
 * Single line item on an invoice.
 */
final class InvoiceItem
{
    public function __construct(
        public readonly int $id,
        public readonly int $invoiceId,
        public readonly ?int $productId,
        public readonly string $name,
        public readonly float $quantity,
        public readonly string $unit,
        public readonly float $netPrice,
        public readonly float $vatRate,
        public readonly float $vatAmount,
        public readonly float $grossPrice,
        public readonly int $sortOrder,
    ) {
    }

    public static function fromRow(\stdClass $row): self
    {
        return new self(
            id: (int) $row->id,
            invoiceId: (int) $row->invoice_id,
            productId: $row->product_id !== null ? (int) $row->product_id : null,
            name: $row->name,
            quantity: (float) $row->quantity,
            unit: $row->unit,
            netPrice: (float) $row->net_price,
            vatRate: (float) $row->vat_rate,
            vatAmount: (float) $row->vat_amount,
            grossPrice: (float) $row->gross_price,
            sortOrder: (int) $row->sort_order,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->productId,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'net_price' => $this->netPrice,
            'vat_rate' => $this->vatRate,
            'vat_amount' => $this->vatAmount,
            'gross_price' => $this->grossPrice,
        ];
    }
}
