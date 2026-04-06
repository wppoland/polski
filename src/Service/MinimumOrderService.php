<?php

declare(strict_types=1);

namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;
use Polski\Util\SettingsCacheable;

/**
 * Enforces minimum order value and/or minimum order quantity.
 *
 * Displays a notice on cart/checkout and disables the checkout
 * button when the minimum is not met.
 */
final class MinimumOrderService implements HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_minimum_order';

    public function registerHooks(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        add_action('woocommerce_check_cart_items', [$this, 'validateCart']);
        add_action('woocommerce_checkout_process', [$this, 'validateCheckout']);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('minimum_order');
    }

    /**
     * Validate cart against minimum order rules. Fires on cart and checkout pages.
     */
    public function validateCart(): void
    {
        if (is_admin() && ! defined('DOING_AJAX')) {
            return;
        }

        $cart = WC()->cart;
        if ($cart === null || $cart->is_empty()) {
            return;
        }

        $settings = $this->getSettings();
        $minValue = (float) ($settings['min_value'] ?? 0);
        $minQuantity = (int) ($settings['min_quantity'] ?? 0);
        $excludeSale = (bool) ($settings['exclude_sale_items'] ?? false);

        // Check minimum value.
        if ($minValue > 0) {
            $cartTotal = $excludeSale
                ? $this->getCartTotalExcludingSaleItems($cart)
                : (float) $cart->get_subtotal();

            if ($cartTotal < $minValue) {
                $message = str_replace(
                    ['{min_value}', '{current_value}'],
                    [wp_strip_all_tags(wc_price($minValue)), wp_strip_all_tags(wc_price($cartTotal))],
                    (string) ($settings['min_value_message'] ?? __('Minimum order value is {min_value}. Current cart value: {current_value}.', 'polski')),
                );

                wc_add_notice($message, 'error');
            }
        }

        // Check minimum quantity.
        if ($minQuantity > 0) {
            $cartQuantity = (int) $cart->get_cart_contents_count();

            if ($cartQuantity < $minQuantity) {
                $message = str_replace(
                    ['{min_quantity}', '{current_quantity}'],
                    [(string) $minQuantity, (string) $cartQuantity],
                    (string) ($settings['min_quantity_message'] ?? __('Minimum number of items per order is {min_quantity}. Current quantity: {current_quantity}.', 'polski')),
                );

                wc_add_notice($message, 'error');
            }
        }
    }

    /**
     * Same validation on checkout process to prevent order placement.
     */
    public function validateCheckout(): void
    {
        $this->validateCart();
    }

    private function getCartTotalExcludingSaleItems(\WC_Cart $cart): float
    {
        $total = 0.0;

        foreach ($cart->get_cart() as $item) {
            $product = $item['data'] ?? null;

            if (! $product instanceof \WC_Product) {
                continue;
            }

            if ($product->is_on_sale()) {
                continue;
            }

            $total += (float) $product->get_price() * (int) ($item['quantity'] ?? 1);
        }

        return $total;
    }
}
