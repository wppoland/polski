<?php

declare(strict_types=1);
namespace Polski\Compatibility;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * CartFlows compatibility.
 *
 * Ensures Polski legal checkboxes and button text
 * work correctly on CartFlows checkout pages.
 */
final class CartFlowsCompat implements HasHooks
{
    public function registerHooks(): void
    {
        if (! class_exists('Cartflows_Loader')) {
            return;
        }

        // CartFlows uses its own checkout template. Hook into it.
        add_action('cartflows_checkout_before_shortcode', [$this, 'ensureCheckoutHooks']);
    }

    /**
     * Ensure Polski checkout hooks fire on CartFlows checkout pages.
     */
    public function ensureCheckoutHooks(): void
    {
        // CartFlows checkout renders the WC checkout shortcode,
        // so our hooks on woocommerce_review_order_before_submit should fire.
        // This is a safety check.
        if (! has_action('woocommerce_review_order_before_submit', [
            \Polski\Plugin::instance()->container()->get(\Polski\Hook\CheckoutHooks::class),
            'renderCheckoutCheckboxes',
        ])) {
            $hooks = \Polski\Plugin::instance()->container()->get(\Polski\Hook\CheckoutHooks::class);
            add_action('woocommerce_review_order_before_submit', [$hooks, 'renderCheckoutCheckboxes'], 10);
        }
    }
}
