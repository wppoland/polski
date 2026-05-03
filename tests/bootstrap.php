<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap file.
 */

// Define ABSPATH for plugin files that guard against direct access.
if (! defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (! defined('Polski\\PLUGIN_DIR')) {
    define('Polski\\PLUGIN_DIR', dirname(__DIR__));
}

// Composer autoloader.
require_once dirname(__DIR__) . '/vendor/autoload.php';

// WordPress time constants (used as class-constant values in some services).
if (! defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (! defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 60 * 60);
}
if (! defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 60 * 60 * 24);
}
if (! defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 60 * 60 * 24 * 7);
}

// ── Minimal WordPress function stubs for unit tests ─────────────────────────
// These stubs allow services to be instantiated without a full WordPress load.
// They return sensible defaults and are intentionally simple.

if (! function_exists('get_option')) {
    function get_option(string $option, mixed $default = false): mixed
    {
        if (isset($GLOBALS['polski_test_options']) && is_array($GLOBALS['polski_test_options'])
            && array_key_exists($option, $GLOBALS['polski_test_options'])) {
            return $GLOBALS['polski_test_options'][$option];
        }

        return $default;
    }
}

if (! function_exists('update_option')) {
    function update_option(string $option, mixed $value, string|bool $autoload = 'yes'): bool
    {
        return true;
    }
}

if (! function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('_e')) {
    function _e(string $text, string $domain = 'default'): void
    {
        echo $text;
    }
}

if (! function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (! function_exists('absint')) {
    function absint(mixed $value): int
    {
        return abs((int) $value);
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string
    {
        return trim(strip_tags($str));
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data, int $options = 0, int $depth = 512): string|false
    {
        return json_encode($data, $options, $depth);
    }
}

if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $text, bool $removeBreaks = false): string
    {
        $text = strip_tags($text);
        if ($removeBreaks) {
            $text = preg_replace('/[\r\n\t ]+/', ' ', $text) ?? $text;
        }
        return trim($text);
    }
}

if (! function_exists('wp_parse_args')) {
    function wp_parse_args(array|string $args, array $defaults = []): array
    {
        if (is_string($args)) {
            parse_str($args, $parsed);
            $args = $parsed;
        }
        return array_merge($defaults, $args);
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $hookName, mixed $value, mixed ...$args): mixed
    {
        return $value;
    }
}

if (! function_exists('add_action')) {
    function add_action(string $hookName, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        return true;
    }
}

if (! function_exists('add_filter')) {
    function add_filter(string $hookName, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        return true;
    }
}

if (! function_exists('do_action')) {
    function do_action(string $hookName, mixed ...$args): void
    {
        // No-op in unit tests.
    }
}

if (! function_exists('get_permalink')) {
    function get_permalink(int|object $post = 0): string|false
    {
        return 'https://example.com/page/' . (is_int($post) ? $post : 0);
    }
}

if (! function_exists('get_privacy_policy_url')) {
    function get_privacy_policy_url(): string
    {
        return 'https://example.com/privacy-policy';
    }
}

if (! function_exists('wc_price')) {
    function wc_price(float|string $price, array $args = []): string
    {
        $currency = $args['currency'] ?? 'PLN';
        return '<span class="woocommerce-Price-amount">' . number_format((float) $price, 2, ',', ' ') . '&nbsp;' . $currency . '</span>';
    }
}

if (! function_exists('get_woocommerce_currency')) {
    function get_woocommerce_currency(): string
    {
        return 'PLN';
    }
}

if (! function_exists('wc_get_product')) {
    function wc_get_product(int $productId): ?object
    {
        return null;
    }
}

if (! function_exists('wc_attribute_label')) {
    function wc_attribute_label(string $name, string $product = ''): string
    {
        return match ($name) {
            'pa_color' => 'Kolor',
            'pa_size' => 'Rozmiar',
            default => $name,
        };
    }
}

if (! function_exists('get_term_by')) {
    function get_term_by(string $field, string $value, string $taxonomy = ''): object|false
    {
        return false;
    }
}
