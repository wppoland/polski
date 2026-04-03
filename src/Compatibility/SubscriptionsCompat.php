<?php

declare(strict_types=1);

namespace Polski\Compatibility;

use Polski\Contract\HasHooks;

/**
 * WooCommerce Subscriptions compatibility.
 *
 * Handles:
 * - Recurring payment checkbox acknowledgment
 * - Withdrawal exemption for subscription products
 * - Unit price display for subscription periods
 */
final class SubscriptionsCompat implements HasHooks
{
    public function registerHooks(): void
    {
        if (! class_exists('WC_Subscriptions')) {
            return;
        }

        // Mark subscription products as withdrawal-exempt (digital content delivered over time).
        add_filter('polski/withdrawal/eligible', [$this, 'checkSubscriptionEligibility'], 10, 2);

        // Adjust unit price display for subscriptions (per month/year).
        add_filter('polski/price/unit_price_html', [$this, 'adjustSubscriptionUnitPrice'], 10, 3);
    }

    public function checkSubscriptionEligibility(bool $eligible, \WC_Order $order): bool
    {
        // Check if the order contains subscription items.
        if (function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($order)) {
            return false; // Subscriptions are exempt from withdrawal.
        }

        return $eligible;
    }

    public function adjustSubscriptionUnitPrice(string $html, $unitPrice, \WC_Product $product): string
    {
        if (! $product->is_type(['subscription', 'variable-subscription'])) {
            return $html;
        }

        // Subscription products have per-period pricing, unit price may not apply.
        return '';
    }
}
