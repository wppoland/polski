<?php

declare(strict_types=1);
namespace Polski\Compatibility;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * Google for WooCommerce (Google Merchant Center / GA4) compatibility.
 *
 * Adds Polski product data to Google product feeds:
 * - Unit pricing for Google Shopping
 * - GTIN/EAN for product identification
 * - Manufacturer/brand for feed requirements
 */
final class GoogleCompat implements HasHooks
{
    /**
     * Google's unit codes for the default Polski unit terms. Units missing here
     * (custom terms) get no unit pricing rather than a value Google rejects.
     */
    private const GOOGLE_UNITS = [
        'g' => 'g', 'kg' => 'kg', 'ml' => 'ml', 'l' => 'l', 'cm' => 'cm', 'm' => 'm',
        'szt' => 'ct', 'm2' => 'sqm', 'm3' => 'cbm',
    ];

    public function registerHooks(): void
    {
        // Google for WooCommerce uses Automattic\WooCommerce\GoogleListingsAndAds.
        if (! defined('WC_GLA_VERSION')) {
            return;
        }

        // Add Polski product data to the Google product GLA builds. GLA passes
        // its adapter (the Google product object) as the third argument.
        add_filter('woocommerce_gla_product_attribute_values', [$this, 'addProductAttributes'], 10, 3);
    }

    /**
     * Keys are Google Content API product properties; GLA maps them onto the
     * product with mapTypes(), so snake_case keys would be silently dropped.
     *
     * @param array<string, mixed> $attributes
     * @param object|null          $adapter    GLA's WCProductAdapter.
     * @return array<string, mixed>
     */
    public function addProductAttributes(array $attributes, \WC_Product $product, $adapter = null): array
    {
        $container = \Polski\Plugin::instance()->container();
        $productInfo = $container->get(\Polski\Service\ProductInfoService::class);

        // Fill GTIN and brand only where GLA found none of its own.
        $gtin = $productInfo->getGTIN($product);
        if ($gtin !== '' && ! $this->adapterHas($adapter, 'getGtin')) {
            $attributes['gtin'] = $gtin;
        }

        $manufacturer = $productInfo->getManufacturer($product);
        if ($manufacturer !== '' && ! $this->adapterHas($adapter, 'getBrand')) {
            $attributes['brand'] = $manufacturer;
        }

        // Unit pricing (required for some product types in some countries).
        $unitPrice = $container->get(\Polski\Service\PriceDisplayService::class)->getUnitPrice($product);
        $unit = $unitPrice !== null ? (self::GOOGLE_UNITS[$unitPrice->unit] ?? null) : null;

        if ($unitPrice !== null && $unit !== null) {
            $attributes['unitPricingMeasure'] = ['value' => $unitPrice->productAmount, 'unit' => $unit];

            if (floor($unitPrice->baseAmount) === $unitPrice->baseAmount) {
                $attributes['unitPricingBaseMeasure'] = ['value' => (int) $unitPrice->baseAmount, 'unit' => $unit];
            }
        }

        return $attributes;
    }

    private function adapterHas(mixed $adapter, string $getter): bool
    {
        return is_object($adapter) && method_exists($adapter, $getter) && ! empty($adapter->{$getter}());
    }
}
