<?php

declare(strict_types=1);
namespace Polski\Hook;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;
use Polski\Service\OmnibusService;

/**
 * Cart-side display for the Omnibus lowest price.
 *
 * The `show_on_cart` setting had existed since the module shipped with nothing
 * reading it, so it never worked in any cart, classic or block. Reported on
 * wordpress.org against 1.31.6.
 *
 * `woocommerce_get_item_data` is the one seam that reaches both carts. The
 * classic cart prints it under the item name, and the Store API runs the same
 * filter through `wc_get_formatted_cart_item_data()` to build each item's
 * `item_data`, which the Cart Block renders. One filter, both carts, and no
 * JavaScript.
 *
 * Cross-sells under the cart are a different surface again: the Product
 * Collection block renders `woocommerce/product-price` and never fires the
 * classic loop hooks, so that one is handled by filtering the rendered block.
 */
final class CartHooks implements HasHooks
{
    public function __construct(
        private readonly OmnibusService $omnibus,
    ) {
    }

    public function registerHooks(): void
    {
        add_filter('woocommerce_get_item_data', [$this, 'addOmnibusToCartItem'], 10, 2);
        add_filter('render_block', [$this, 'appendOmnibusToPriceBlock'], 10, 2);
    }

    private function enabled(): bool
    {
        if (! ModulesPage::isModuleEnabled('omnibus')) {
            return false;
        }

        $settings = get_option('polski_omnibus', []);

        return is_array($settings) && ! empty($settings['show_on_cart']);
    }

    /**
     * @param array<int, array<string, mixed>>|mixed $itemData
     * @param array<string, mixed>|mixed             $cartItem
     * @return array<int, array<string, mixed>>|mixed
     */
    public function addOmnibusToCartItem(mixed $itemData, mixed $cartItem): mixed
    {
        if (! is_array($itemData) || ! is_array($cartItem) || ! $this->enabled()) {
            return $itemData;
        }

        // A variation carries its own price history, so prefer it over the parent.
        $productId = (int) ($cartItem['variation_id'] ?? 0) ?: (int) ($cartItem['product_id'] ?? 0);

        if ($productId === 0) {
            return $itemData;
        }

        $text = $this->omnibus->getLowestPriceText($productId);

        if ($text === '') {
            return $itemData;
        }

        $itemData[] = [
            'key' => __('Omnibus', 'polski'),
            'value' => $text,
            'display' => $text,
        ];

        return $itemData;
    }

    /**
     * Append the notice to a Product Collection price block.
     *
     * Cross-sells under the block cart, and every other block product listing,
     * render `woocommerce/product-price`. That block does not fire
     * `woocommerce_after_shop_loop_item_title`, which is where the classic loop
     * puts this, so the notice has to be appended to the rendered block instead.
     *
     * @param string|mixed              $content
     * @param array<string, mixed>|mixed $block
     */
    public function appendOmnibusToPriceBlock(mixed $content, mixed $block): mixed
    {
        if (! is_string($content) || $content === '' || ! is_array($block)) {
            return $content;
        }

        if (($block['blockName'] ?? '') !== 'woocommerce/product-price') {
            return $content;
        }

        if (! $this->enabled()) {
            return $content;
        }

        $productId = (int) ($block['attrs']['productId'] ?? 0);

        if ($productId === 0) {
            global $product;
            $productId = $product instanceof \WC_Product ? $product->get_id() : 0;
        }

        if ($productId === 0) {
            return $content;
        }

        $html = $this->omnibus->getLowestPriceHtml($productId);

        return $html === '' ? $content : $content . $html;
    }
}
