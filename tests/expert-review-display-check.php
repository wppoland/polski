<?php

/**
 * Run: php tests/expert-review-display-check.php
 *
 * Expert reviews: the product picker offers variations, so a review linked to
 * a variation must show on its variable parent's page (it never did, the query
 * matched the parent ID only). The review's featured image was fetched but
 * never printed; it must render with alt text, falling back to the title.
 */

declare(strict_types=1);

namespace Polski\Admin {
    final class ModulesPage
    {
        public static function isModuleEnabled(string $id): bool { return true; }
    }
}

namespace Polski\Contract {
    interface HasHooks
    {
        public function registerHooks(): void;
    }
}

namespace {
    define('ABSPATH', __DIR__ . '/');

    final class WP_Post
    {
        public int $ID = 7;
        public string $post_title = 'Long-term test';
        public string $post_content = 'Solid.';
        public int $post_author = 1;
    }

    class WC_Product
    {
        public function __construct(private int $id, private string $type, private array $children = []) {}
        public function get_id(): int { return $this->id; }
        public function is_type(string $t): bool { return $t === $this->type; }
        public function get_children(): array { return $this->children; }
    }

    final class WP_Query
    {
        public array $posts = [];

        public function __construct(array $args)
        {
            $GLOBALS['queried_ids'] = $args['meta_query'][0]['value'] ?? [$args['meta_value'] ?? null];
            // A review linked to variation 11.
            $this->posts = in_array(11, $GLOBALS['queried_ids'], true) ? [new WP_Post()] : [];
        }
    }

    $GLOBALS['alt'] = '';

    function __($s, $d = null) { return $s; }
    function esc_html__($s, $d = null) { return $s; }
    function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
    function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
    function wp_kses_post($s) { return $s; }
    function wpautop($s) { return "<p>$s</p>"; }
    function get_post_meta($id, $key, $single = false) { return $key === '_wp_attachment_image_alt' ? $GLOBALS['alt'] : ''; }
    function get_the_author_meta($f, $id) { return 'Ann'; }
    function get_the_date($f = '', $p = null) { return '2026-11-09'; }
    function get_post_thumbnail_id($p) { return 99; }
    function get_the_post_thumbnail($id, $size, $attr = []) { return '<img src="r.jpg" alt="' . esc_attr($attr['alt'] ?? '') . '">'; }

    require __DIR__ . '/../src/Service/ExpertReviewService.php';

    $failures = 0;
    $assert = static function (string $label, bool $ok) use (&$failures): void {
        echo ($ok ? 'ok   ' : 'FAIL ') . $label . "\n";
        $failures += $ok ? 0 : 1;
    };

    $render = static function (WC_Product $p): string {
        global $product;
        $product = $p;
        ob_start();
        (new \Polski\Service\ExpertReviewService())->displayOnProduct();
        return (string) ob_get_clean();
    };

    $html = $render(new WC_Product(10, 'variable', [11, 12]));
    $assert('variation-linked review shows on the variable parent', str_contains($html, 'Long-term test'));
    $assert('featured image is printed', str_contains($html, '<img src="r.jpg"'));
    $assert('image alt falls back to the review title', str_contains($html, 'alt="Long-term test"'));

    $GLOBALS['alt'] = 'Kettle on a counter';
    $assert('image keeps its own alt text', str_contains($render(new WC_Product(10, 'variable', [11, 12])), 'alt="Kettle on a counter"'));

    $render(new WC_Product(20, 'grouped', [11]));
    $assert('non-variable product queries its own ID only', $GLOBALS['queried_ids'] === [20]);

    echo $failures === 0 ? "expert-review-display-check: OK\n" : "expert-review-display-check: {$failures} failure(s)\n";
    exit($failures === 0 ? 0 : 1);
}
