<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * Promotions / dynamic pricing (basic).
 *
 * Two automatic cart discounts, configurable in the module settings:
 *  - Bulk discount: a percentage off a product line when its quantity reaches a
 *    threshold (applied to the line price).
 *  - Cart discount: a percentage off when the cart subtotal reaches a threshold
 *    (applied as a negative cart fee).
 *
 * Optional module, OFF by default. Recomputed idempotently from the regular price
 * each calculation, so it is safe across WooCommerce's repeated total calculations.
 */
final class DynamicPricingService implements Bootable, HasHooks
{
    private const OPTION = 'polski_pricing';

    public function boot(): void
    {
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('dynamic_pricing');
    }

    public function registerHooks(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        add_action('woocommerce_before_calculate_totals', [$this, 'applyBulkDiscount'], 25);
        add_action('woocommerce_cart_calculate_fees', [$this, 'applyCartThreshold']);
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $settings = get_option(self::OPTION, []);

        return is_array($settings) ? $settings : [];
    }

    public function applyBulkDiscount(\WC_Cart $cart): void
    {
        if (is_admin() && ! wp_doing_ajax()) {
            return;
        }

        $settings = $this->settings();
        $minQty = (int) ($settings['bulk_min_qty'] ?? 0);
        $percent = (float) ($settings['bulk_discount_percent'] ?? 0);

        if ($minQty <= 0 || $percent <= 0) {
            return;
        }

        foreach ($cart->get_cart() as $item) {
            if ((int) ($item['quantity'] ?? 0) < $minQty) {
                continue;
            }

            $product = $item['data'] ?? null;

            if (! $product instanceof \WC_Product) {
                continue;
            }

            $regular = (float) $product->get_regular_price();

            if ($regular <= 0) {
                $regular = (float) $product->get_price();
            }

            $product->set_price((string) round($regular * (1 - $percent / 100), wc_get_price_decimals()));
        }
    }

    public function applyCartThreshold(\WC_Cart $cart): void
    {
        if (is_admin() && ! wp_doing_ajax()) {
            return;
        }

        $settings = $this->settings();
        $threshold = (float) ($settings['cart_threshold'] ?? 0);
        $percent = (float) ($settings['cart_discount_percent'] ?? 0);

        if ($threshold <= 0 || $percent <= 0) {
            return;
        }

        $subtotal = 0.0;

        foreach ($cart->get_cart() as $item) {
            $subtotal += (float) ($item['line_total'] ?? 0);
        }

        if ($subtotal < $threshold) {
            return;
        }

        $cart->add_fee(
            __('Discount', 'polski'),
            -1 * round($subtotal * $percent / 100, wc_get_price_decimals()),
            false,
        );
    }
}
