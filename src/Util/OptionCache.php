<?php

declare(strict_types=1);
namespace Polski\Util;

defined('ABSPATH') || exit;

/**
 * Per-request static memoiser for `get_option()` reads that are otherwise
 * hit on every iteration of a hot loop (typically per-product callbacks on
 * `woocommerce_after_shop_loop_item_title` and friends).
 *
 * WordPress already caches autoloaded options in memory through
 * `wp_load_alloptions()`, but every individual `get_option()` call still
 * walks the wp_filter chain, the object-cache layer, and returns a fresh
 * array copy from `maybe_unserialize`. For options that are read tens of
 * times per page render (a 12-product archive triggers many such reads),
 * a tiny in-process cache eliminates the per-call overhead without
 * changing observable behaviour.
 *
 * Settings save handlers must call `forget()` for any option they mutate
 * so subsequent reads in the same request see the fresh value.
 */
final class OptionCache
{
    /** @var array<string, mixed> */
    private static array $cache = [];

    public static function get(string $option, mixed $default = false): mixed
    {
        if (! array_key_exists($option, self::$cache)) {
            self::$cache[$option] = get_option($option, $default);
        }

        return self::$cache[$option];
    }

    public static function forget(string $option): void
    {
        unset(self::$cache[$option]);
    }

    public static function clear(): void
    {
        self::$cache = [];
    }
}
