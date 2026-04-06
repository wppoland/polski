<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * AJAX Add to Cart for all product types.
 *
 * WooCommerce natively supports AJAX add-to-cart only for simple products
 * on archive pages. This module extends it to variable, grouped, and external
 * products, plus single product pages.
 */
final class AjaxAddToCartService implements HasHooks
{
    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('ajax_add_to_cart')) {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        // AJAX handler for variable products.
        add_action('wp_ajax_polski_ajax_add_to_cart', [$this, 'handleAjaxAddToCart']);
        add_action('wp_ajax_nopriv_polski_ajax_add_to_cart', [$this, 'handleAjaxAddToCart']);

        // Enable AJAX add-to-cart on single product pages.
        add_filter('woocommerce_product_single_add_to_cart_text', [$this, 'filterButtonText'], 10, 2);
    }

    public function enqueueAssets(): void
    {
        if (! is_shop() && ! is_product_category() && ! is_product_tag() && ! is_product()) {
            return;
        }

        wp_enqueue_script(
            'polski-ajax-cart',
            plugins_url('assets/js/ajax-add-to-cart.js', POLSKI_PLUGIN_FILE),
            ['jquery', 'wc-add-to-cart'],
            defined('POLSKI_VERSION') ? POLSKI_VERSION : '1.0.0',
            true,
        );

        wp_localize_script('polski-ajax-cart', 'polskiAjaxCart', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('polski_ajax_cart'),
            'cartUrl' => wc_get_cart_url(),
            'i18n' => [
                'added' => __('Added to cart!', 'polski'),
                'viewCart' => __('View cart', 'polski'),
                'error' => __('Could not add to cart. Please try again.', 'polski'),
            ],
        ]);

        wp_add_inline_style('polski-frontend', '
            .polski-ajax-cart-notice {
                position: fixed;
                top: 32px;
                right: 16px;
                z-index: 99999;
                padding: 12px 20px;
                border-radius: 8px;
                background: #16a34a;
                color: #fff;
                font-size: 14px;
                box-shadow: 0 4px 12px rgba(0,0,0,.15);
                animation: polskiSlideIn .3s ease-out;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .polski-ajax-cart-notice.error {
                background: #dc2626;
            }
            .polski-ajax-cart-notice a {
                color: #fff;
                text-decoration: underline;
                font-weight: 600;
            }
            @keyframes polskiSlideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        ');
    }

    /**
     * Handle AJAX add-to-cart for variable and other product types.
     */
    public function handleAjaxAddToCart(): void
    {
        check_ajax_referer('polski_ajax_cart', 'security');

        $productId = absint($_POST['product_id'] ?? 0);
        $variationId = absint($_POST['variation_id'] ?? 0);
        $quantity = max(1, absint($_POST['quantity'] ?? 1));

        if ($productId <= 0) {
            wp_send_json_error(['message' => __('Invalid product.', 'polski')]);
        }

        $product = wc_get_product($productId);

        if (! $product) {
            wp_send_json_error(['message' => __('Product not found.', 'polski')]);
        }

        // Build variation attributes.
        $variation = [];

        if ($variationId > 0 && $product->is_type('variable')) {
            foreach ($_POST as $key => $value) {
                if (str_starts_with($key, 'attribute_')) {
                    $variation[$key] = sanitize_text_field($value);
                }
            }
        }

        $passedValidation = apply_filters('woocommerce_add_to_cart_validation', true, $productId, $quantity, $variationId, $variation);

        if (! $passedValidation) {
            $notices = wc_get_notices('error');
            $message = ! empty($notices) ? wp_strip_all_tags($notices[0]['notice'] ?? '') : __('Validation failed.', 'polski');
            wc_clear_notices();
            wp_send_json_error(['message' => $message]);
        }

        $addedKey = WC()->cart->add_to_cart($productId, $quantity, $variationId, $variation);

        if (! $addedKey) {
            wp_send_json_error(['message' => __('Could not add to cart.', 'polski')]);
        }

        do_action('woocommerce_ajax_added_to_cart', $productId);

        // Return updated cart fragments.
        \WC_AJAX::get_refreshed_fragments();
    }

    /**
     * Keep original button text (no filtering needed, just a hook placeholder).
     *
     * @param string      $text
     * @param \WC_Product $product
     */
    public function filterButtonText(string $text, $product): string
    {
        return $text;
    }
}
