<?php

declare(strict_types=1);

namespace Spolszczony\Util;

use const Spolszczony\PLUGIN_DIR;

/**
 * Loads templates with theme override support.
 *
 * Templates are looked up in this order:
 * 1. {theme}/spolszczony/{template}.php
 * 2. {plugin}/templates/{template}.php
 */
final class TemplateLoader
{
    private const THEME_DIR = 'spolszczony';

    /**
     * Render a template and return the HTML.
     *
     * @param string               $template Template name (e.g., 'single-product/unit-price').
     * @param array<string, mixed> $args     Variables to extract into the template scope.
     */
    public function render(string $template, array $args = []): string
    {
        ob_start();
        $this->include($template, $args);
        return (string) ob_get_clean();
    }

    /**
     * Include a template directly (outputs to buffer).
     *
     * @param string               $template Template name.
     * @param array<string, mixed> $args     Variables to extract into the template scope.
     */
    public function include(string $template, array $args = []): void
    {
        $path = $this->locate($template);

        if ($path === null) {
            return;
        }

        /**
         * Filter template arguments before rendering.
         *
         * @param array<string, mixed> $args     Template arguments.
         * @param string               $template Template name.
         */
        $args = apply_filters('spolszczony/template/args', $args, $template);

        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template variable extraction is intentional.
        extract($args, EXTR_SKIP);

        include $path;
    }

    /**
     * Locate a template file. Returns null if not found.
     */
    public function locate(string $template): ?string
    {
        $template = ltrim($template, '/');

        if (! str_ends_with($template, '.php')) {
            $template .= '.php';
        }

        // Check theme first.
        $themePath = locate_template(self::THEME_DIR . '/' . $template);

        if ($themePath !== '') {
            /** @var string */
            return apply_filters('spolszczony/template/path', $themePath, $template);
        }

        // Fall back to plugin.
        $pluginPath = PLUGIN_DIR . '/templates/' . $template;

        if (file_exists($pluginPath)) {
            /** @var string */
            return apply_filters('spolszczony/template/path', $pluginPath, $template);
        }

        return null;
    }
}
