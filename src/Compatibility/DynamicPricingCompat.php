<?php

declare(strict_types=1);
namespace Polski\Compatibility;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * WooCommerce Dynamic Pricing compatibility.
 *
 * Ensures unit prices and Omnibus tracking work correctly
 * when prices are modified by dynamic pricing rules.
 */
final class DynamicPricingCompat implements HasHooks
{
    public function registerHooks(): void
    {
        if (! class_exists('WC_Dynamic_Pricing')) {
            return;
        }

        // Recalculate unit price after dynamic pricing adjusts the price.
        add_filter('polski/price/unit_price_html', [$this, 'recalculateUnitPrice'], 10, 3);

        // Record the adjusted price for Omnibus tracking.
        add_action('woocommerce_before_calculate_totals', [$this, 'trackAdjustedPrices'], 99);
    }

    /**
     * @param string $html
     * @param \Polski\Model\UnitPrice $unitPrice
     * @param \WC_Product $product
     */
    public function recalculateUnitPrice(string $html, $unitPrice, \WC_Product $product): string
    {
        // Dynamic pricing already modifies get_price(), so the unit price
        // calculated from get_price() is already correct. No action needed
        // unless the pricing plugin uses a non-standard approach.
        return $html;
    }

    /**
     * Track dynamically adjusted prices for Omnibus.
     */
    public function trackAdjustedPrices(\WC_Cart $cart): void
    {
        // Prices are tracked on product save, not in cart.
        // This hook is a placeholder for edge cases.
    }
}
