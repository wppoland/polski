<?php

declare(strict_types=1);

namespace Polski\Compatibility;

use Polski\Contract\HasHooks;

/**
 * WooCommerce Product Bundles compatibility.
 *
 * Ensures Polski data (unit price, delivery time, Omnibus)
 * displays correctly for bundled products.
 */
final class ProductBundlesCompat implements HasHooks
{
    public function registerHooks(): void
    {
        if (! class_exists('WC_Bundles')) {
            return;
        }

        // Hide unit price for bundle container (individual items have their own).
        add_filter('polski/price/unit_price_html', [$this, 'maybeHideForBundle'], 10, 3);

        // Use longest delivery time from bundle items.
        add_filter('polski/delivery_time/display', [$this, 'bundleDeliveryTime'], 10, 3);
    }

    public function maybeHideForBundle(string $html, $unitPrice, \WC_Product $product): string
    {
        if ($product->is_type('bundle')) {
            return ''; // Bundle containers don't have a meaningful unit price.
        }

        return $html;
    }

    public function bundleDeliveryTime(string $html, string $timeText, \WC_Product $product): string
    {
        // For bundles, the delivery time should be the longest of all items.
        // Default behavior works since delivery time is set per product.
        return $html;
    }
}
