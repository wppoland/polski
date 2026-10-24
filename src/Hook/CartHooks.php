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
 * Cross-sells under the cart are a product listing, not a cart item: they get
 * the loop notice from {@see LoopHooks::appendToPriceBlock()}, which follows
 * the "show in product lists" setting like every other grid.
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

        // WooCommerce renders item data as "key: display" and adds the colon
        // itself, in the classic templates and in the block cart alike. Handing
        // it the finished sentence put a second, technical label in front of it
        // ("Omnibus: Lowest price in the last 30 days: ..."), so take the two
        // halves the notice is already made of.
        $parts = $this->omnibus->getLowestPriceParts($productId);

        if ($parts === null) {
            return $itemData;
        }

        $itemData[] = [
            'key' => $parts['label'],
            'value' => $parts['value'],
            'display' => $parts['value'],
        ];

        return $itemData;
    }
}
