<?php

declare(strict_types=1);
namespace Polski\Util;

defined('ABSPATH') || exit;

/**
 * Shared rules for output added to WooCommerce product blocks.
 *
 * A block theme lists products with the Product Collection block. WooCommerce
 * fires the classic loop hooks (`woocommerce_after_shop_loop_item_title`,
 * `woocommerce_after_shop_loop_item`, ...) inside it only on shop and product
 * taxonomy archives, for a collection that inherits the template query, while
 * its compatibility layer is on. Everywhere else (a homepage grid, related
 * products, cart cross-sells) the classic hooks never run, so a module that
 * only hangs on them is missing there. Modules add a `render_block_*` filter
 * for those grids and ask {@see self::classicHooksCover()} first, so a card
 * never gets the same output from both paths.
 */
final class BlockLoop
{
    /** @var array<string, true> */
    private static array $claimed = [];

    /**
     * Whether WooCommerce's compatibility layer already fires the classic loop
     * hooks for this block, so the block filter must stay out.
     *
     * @param array<string, mixed>|mixed $block Parsed block.
     */
    public static function classicHooksCover(mixed $block): bool
    {
        $attrs = is_array($block) && is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

        return self::covers(
            is_shop() || is_product_taxonomy(),
            ! empty($attrs['isInherited']),
            (bool) apply_filters('woocommerce_disable_compatibility_layer', false),
        );
    }

    /**
     * The decision itself, without WordPress, for the runnable check.
     */
    public static function covers(bool $isArchive, bool $isInherited, bool $layerDisabled): bool
    {
        return $isArchive && $isInherited && ! $layerDisabled;
    }

    /**
     * The product a block renders, from the `postId` block context.
     */
    public static function product(mixed $instance): ?\WC_Product
    {
        $productId = $instance instanceof \WP_Block ? (int) ($instance->context['postId'] ?? 0) : 0;
        $product = $productId > 0 ? wc_get_product($productId) : null;

        return $product instanceof \WC_Product ? $product : null;
    }

    /**
     * True the first time a feature asks for a product in this request.
     *
     * Loop buttons go through this from both the classic hook and the block
     * filter, so one card can never carry two. ponytail: per request, not per
     * grid; the same product in two grids on one page gets the button once.
     * Key by grid if that ever matters. Legal notices do not use this.
     */
    public static function claim(string $feature, int $productId): bool
    {
        $key = $feature . ':' . $productId;

        if (isset(self::$claimed[$key])) {
            return false;
        }

        self::$claimed[$key] = true;

        return true;
    }

    /**
     * Buffer a classic, global-$product callback for one block's product.
     */
    public static function render(\WC_Product $product, callable $callback): string
    {
        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce's loop callbacks read the global product.
        $previous = $GLOBALS['product'] ?? null;
        $GLOBALS['product'] = $product;

        ob_start();
        $callback();
        $html = (string) ob_get_clean();

        $GLOBALS['product'] = $previous;
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

        return $html;
    }

    /**
     * Put markup right after the opening tag of a block's wrapper div, so a
     * positioned wrapper (product image, gallery) holds it. Falls back to
     * prepending when the block has no leading div.
     */
    public static function prependInside(string $content, string $html): string
    {
        if ($html === '') {
            return $content;
        }

        $placed = preg_replace_callback('/^\s*<div\b[^>]*>/', static fn (array $m): string => $m[0] . $html, $content, 1, $count);

        return $count === 1 && is_string($placed) ? $placed : $html . $content;
    }
}
