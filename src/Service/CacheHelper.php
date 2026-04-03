<?php

declare(strict_types=1);

namespace Polski\Service;

/**
 * Cache helper: flushes caches for popular caching plugins when
 * Polski settings or product data changes.
 */
final class CacheHelper
{
    /**
     * Flush all known caches.
     */
    public static function flush(): void
    {
        // W3 Total Cache.
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }

        // WP Super Cache.
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }

        // LiteSpeed Cache.
        if (class_exists(\LiteSpeed\Purge::class)) {
            do_action('litespeed_purge_all');
        }

        // WP Fastest Cache.
        if (function_exists('wpfc_clear_all_cache')) {
            wpfc_clear_all_cache(true);
        }

        // Generic WordPress object cache.
        wp_cache_flush();

        // WooCommerce transients.
        wc_delete_product_transients();

        do_action('polski/cache/flushed');
    }

    /**
     * Flush cache for a specific product.
     */
    public static function flushProduct(int $productId): void
    {
        clean_post_cache($productId);
        wc_delete_product_transients($productId);

        do_action('polski/cache/product_flushed', $productId);
    }
}
