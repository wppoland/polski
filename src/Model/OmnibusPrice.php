<?php

declare(strict_types=1);
namespace Polski\Model;

defined('ABSPATH') || exit;

use Polski\Enum\PriceType;

/**
 * Value object representing a recorded price snapshot for Omnibus compliance.
 */
final class OmnibusPrice
{
    public function __construct(
        public readonly int $id,
        public readonly int $productId,
        public readonly float $price,
        public readonly ?float $salePrice,
        public readonly PriceType $priceType,
        public readonly string $currency,
        public readonly \DateTimeImmutable $recordedAt,
    ) {
    }

    /**
     * Create from a database row.
     *
     * @param object $row Database row with named properties.
     */
    public static function fromRow(object $row): self
    {
        return new self(
            id: (int) $row->id,
            productId: (int) $row->product_id,
            price: (float) $row->price,
            salePrice: $row->sale_price !== null ? (float) $row->sale_price : null,
            priceType: PriceType::from($row->price_type),
            currency: $row->currency,
            recordedAt: new \DateTimeImmutable($row->recorded_at),
        );
    }

    /**
     * The effective price (sale price if available, otherwise regular).
     */
    public function effectivePrice(): float
    {
        return $this->salePrice ?? $this->price;
    }
}
