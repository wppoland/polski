<?php

declare(strict_types=1);

namespace Polski\Admin;

use Polski\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * Brand base assets (self-hosted fonts + design tokens) for wp-admin.
 *
 * Core Web Vitals safe: self-hosted woff2 with font-display:swap, no @import in
 * shipped CSS, admin-only (never enqueued on the storefront). Registers
 * `polski-fonts` and `polski-brand` so every Polski admin stylesheet (menu icon,
 * modules, the React dashboard) can depend on `polski-brand` and resolve the
 * --pl-* tokens. Only the above-the-fold wordmark weight (Schibsted 900) is
 * preloaded, and only on Polski screens.
 */
final class BrandAssets implements HasHooks
{
    public function registerHooks(): void
    {
        // Priority 8 so the brand layer is registered before feature sheets enqueue.
        add_action('admin_enqueue_scripts', [$this, 'enqueueBase'], 8);
        add_action('admin_head', [$this, 'preloadWordmark']);
    }

    public function enqueueBase(): void
    {
        $base = plugins_url('assets/css/', \Polski\PLUGIN_FILE);

        wp_enqueue_style('polski-fonts', $base . 'polski-fonts.css', [], \Polski\VERSION);
        wp_enqueue_style('polski-brand', $base . 'polski-brand.css', ['polski-fonts'], \Polski\VERSION);
    }

    public function preloadWordmark(): void
    {
        if (! $this->isPolskiScreen()) {
            return;
        }

        printf(
            '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
            esc_url(plugins_url('assets/fonts/SchibstedGrotesk-900.woff2', \Polski\PLUGIN_FILE)),
        );
    }

    private function isPolskiScreen(): bool
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        return $screen !== null && str_contains((string) $screen->id, 'polski');
    }
}
