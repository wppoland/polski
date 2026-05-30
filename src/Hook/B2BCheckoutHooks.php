<?php

declare(strict_types=1);

namespace Polski\Hook;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Service\B2BCheckoutService;

/**
 * Wires B2B checkout fields into WooCommerce classic checkout and admin
 * order screens. Block-checkout integration is intentionally out of scope
 * for this iteration; B2B fields fall back to standard order meta which
 * pro modules (KSeF, invoices) read directly.
 */
final class B2BCheckoutHooks implements HasHooks
{
    public function __construct(private readonly B2BCheckoutService $service)
    {
    }

    public function registerHooks(): void
    {
        // Modern WC 8.6+ unified field API (Block + classic in one go).
        add_action('woocommerce_init', [$this->service, 'registerAdditionalCheckoutFields']);
        add_action(
            'woocommerce_set_additional_field_value',
            [$this->service, 'mirrorAdditionalFieldToLegacyMeta'],
            10,
            4,
        );

        // Classic-only fallback for stores on WC < 8.6. The service skips
        // this path internally when the modern API is available.
        add_filter('woocommerce_billing_fields', [$this->service, 'addBillingFields'], 20);
        add_action('woocommerce_checkout_process', [$this->service, 'validateAtCheckout']);
        add_action('woocommerce_checkout_create_order', [$this->service, 'saveToOrder'], 10, 2);

        add_filter('woocommerce_admin_billing_fields', [$this->service, 'addAdminBillingFields']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueToggleScript']);
    }

    /**
     * Inline script that hides B2B fields until the "Buying as a company"
     * checkbox is ticked. Keeps the dependency surface to zero - no asset
     * pipeline required.
     */
    public function enqueueToggleScript(): void
    {
        if (! $this->service->isEnabled()) {
            return;
        }

        if (! function_exists('is_checkout') || ! is_checkout()) {
            return;
        }

        $script = <<<'JS'
        (function() {
            function sync() {
                var toggle = document.getElementById('polski_buying_as_company');
                if (!toggle) { return; }
                var rows = document.querySelectorAll('.polski-b2b-field');
                var visible = !!toggle.checked;
                for (var i = 0; i < rows.length; i++) {
                    rows[i].style.display = visible ? '' : 'none';
                }
            }
            document.addEventListener('change', function(event) {
                if (event.target && event.target.id === 'polski_buying_as_company') {
                    sync();
                }
            });
            if (document.readyState !== 'loading') {
                sync();
            } else {
                document.addEventListener('DOMContentLoaded', sync);
            }
            if (window.jQuery) {
                window.jQuery(document.body).on('updated_checkout', sync);
            }
        })();
        JS;

        wp_register_script('polski-b2b-checkout', '', [], '1.0', ['in_footer' => true]);
        wp_enqueue_script('polski-b2b-checkout');
        wp_add_inline_script('polski-b2b-checkout', $script);
    }
}
