<?php

declare(strict_types=1);
namespace Polski\Model;

defined('ABSPATH') || exit;
/**
 * Value object for a calculated unit price.
 *
 * Example: "12,50 zł / 1 kg" for a 500g product priced at 6,25 zł.
 */
final class UnitPrice
{
    public function __construct(
        public readonly float $pricePerUnit,
        public readonly float $baseAmount,
        public readonly string $unit,
        public readonly float $productAmount,
        public readonly string $currency,
    ) {
    }

    /**
     * Calculate unit price from product data.
     *
     * @param float  $productPrice   The product price (regular or sale).
     * @param float  $productAmount  The amount of product (e.g., 500 for 500g).
     * @param float  $baseAmount     The base unit amount (e.g., 1 for "per 1 kg").
     * @param string $unit           The unit slug (e.g., "kg", "l", "m").
     * @param string $currency       Currency code.
     */
    public static function calculate(
        float $productPrice,
        float $productAmount,
        float $baseAmount,
        string $unit,
        string $currency = 'PLN',
    ): ?self {
        if ($productAmount <= 0 || $baseAmount <= 0 || $productPrice <= 0) {
            return null;
        }

        $pricePerUnit = ($productPrice / $productAmount) * $baseAmount;

        return new self(
            pricePerUnit: round($pricePerUnit, 4),
            baseAmount: $baseAmount,
            unit: $unit,
            productAmount: $productAmount,
            currency: $currency,
        );
    }
}
