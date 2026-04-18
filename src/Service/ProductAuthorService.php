<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * Product Authors custom taxonomy.
 *
 * Registers a flat taxonomy for product authors/creators.
 * Useful for bookstores, publishers, music stores, etc.
 * Provides archive pages, admin column, and Schema.org Person markup.
 */
final class ProductAuthorService implements HasHooks
{
    private const TAXONOMY = 'product_author';

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('product_authors')) {
            return;
        }

        add_action('init', [$this, 'registerTaxonomy'], 5);

        // Display on product page.
        add_action('woocommerce_single_product_summary', [$this, 'displayOnProduct'], 6);

        // Display on loop.
        add_action('woocommerce_after_shop_loop_item_title', [$this, 'displayOnLoop'], 3);

        // Schema.org markup.
        add_action('wp_footer', [$this, 'outputSchema']);
    }

    public function registerTaxonomy(): void
    {
        register_taxonomy(self::TAXONOMY, 'product', [
            'labels' => [
                'name' => __('Authors', 'polski'),
                'singular_name' => __('Author', 'polski'),
                'search_items' => __('Search authors', 'polski'),
                'all_items' => __('All authors', 'polski'),
                'edit_item' => __('Edit author', 'polski'),
                'update_item' => __('Update author', 'polski'),
                'add_new_item' => __('Add new author', 'polski'),
                'new_item_name' => __('New author name', 'polski'),
                'menu_name' => __('Authors', 'polski'),
            ],
            'hierarchical' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'author', 'with_front' => false],
            'show_in_rest' => true,
        ]);
    }

    /**
     * Display author names on single product page (below title).
     */
    public function displayOnProduct(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $authors = $this->getProductAuthors($product->get_id());

        if (empty($authors)) {
            return;
        }

        echo '<div class="polski-product-authors" style="margin-bottom:8px;font-size:14px;color:#64748b">';

        $links = [];

        foreach ($authors as $author) {
            $termLink = get_term_link($author);
            $href = is_wp_error($termLink) ? '#' : $termLink;
            $links[] = sprintf(
                '<a href="%s" style="color:#0369a1;text-decoration:none">%s</a>',
                esc_url($href),
                esc_html($author->name),
            );
        }

        printf('%s: %s', esc_html__('Author', 'polski'), wp_kses_post(implode(', ', $links)));

        echo '</div>';
    }

    /**
     * Display author on product loop (archives).
     */
    public function displayOnLoop(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $authors = $this->getProductAuthors($product->get_id());

        if (empty($authors)) {
            return;
        }

        printf(
            '<div class="polski-product-author-loop" style="font-size:12px;color:#94a3b8;margin-bottom:4px">%s</div>',
            esc_html(implode(', ', array_map(static fn ($a) => $a->name, $authors))),
        );
    }

    /**
     * Output Schema.org Person markup for product authors.
     */
    public function outputSchema(): void
    {
        if (! is_product()) {
            return;
        }

        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $authors = $this->getProductAuthors($product->get_id());

        if (empty($authors)) {
            return;
        }

        $persons = [];

        foreach ($authors as $author) {
            $termLink = get_term_link($author);
            $url = is_wp_error($termLink) ? '' : $termLink;
            $persons[] = [
                '@type' => 'Person',
                'name' => $author->name,
                'url' => $url,
            ];
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->get_name(),
            'author' => count($persons) === 1 ? $persons[0] : $persons,
        ];

        wp_print_inline_script_tag(
            (string) wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ['type' => 'application/ld+json'],
        );
    }

    /**
     * @return list<\WP_Term>
     */
    private function getProductAuthors(int $productId): array
    {
        $terms = wp_get_post_terms($productId, self::TAXONOMY);

        if (is_wp_error($terms)) {
            return [];
        }

        return array_values($terms);
    }
}
