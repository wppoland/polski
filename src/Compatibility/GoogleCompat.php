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
    public function registerHooks(): void
    {
        // Google for WooCommerce uses Automattic\WooCommerce\GoogleListingsAndAds.
        if (! defined('WC_GLA_VERSION')) {
            return;
        }

        // Add unit pricing data to Google product feed.
        add_filter('woocommerce_gla_product_attribute_values', [$this, 'addProductAttributes'], 10, 3);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param \WC_Product $product
     * @param string $targetCountry
     * @return array<string, mixed>
     */
    public function addProductAttributes(array $attributes, \WC_Product $product, string $targetCountry): array
    {
        $container = \Polski\Plugin::instance()->container();

        // Add GTIN.
        $productInfo = $container->get(\Polski\Service\ProductInfoService::class);
        $gtin = $productInfo->getGTIN($product);

        if ($gtin !== '' && empty($attributes['gtin'])) {
            $attributes['gtin'] = $gtin;
        }

        // Add brand/manufacturer.
        $manufacturer = $productInfo->getManufacturer($product);

        if ($manufacturer !== '' && empty($attributes['brand'])) {
            $attributes['brand'] = $manufacturer;
        }

        // Add unit pricing for Google Shopping (required in some countries).
        $priceDisplay = $container->get(\Polski\Service\PriceDisplayService::class);
        $unitPrice = $priceDisplay->getUnitPrice($product);

        if ($unitPrice !== null) {
            $attributes['unit_pricing_measure'] = $unitPrice->productAmount . ' ' . $unitPrice->unit;
            $attributes['unit_pricing_base_measure'] = $unitPrice->baseAmount . ' ' . $unitPrice->unit;
        }

        return $attributes;
    }
}
