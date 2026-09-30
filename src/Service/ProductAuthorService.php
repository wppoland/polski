<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

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

    /**
     * Per-request memo for `getProductAuthors()`. The display, loop, and
     * schema callbacks all read the same taxonomy terms for the same
     * product, often in the same request; the WordPress term cache
     * de-duplicates the database hit but not the PHP function-call
     * overhead, which is meaningful when 20+ products render in a shop
     * archive loop.
     *
     * @var array<int, list<\WP_Term>>
     */
    private array $authorsCache = [];

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

        // Schema.org markup, on the WooCommerce Product node rather than a
        // second standalone Product.
        add_filter('woocommerce_structured_data_product', [$this, 'addSchemaAuthor'], 10, 2);
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
            // Not 'author': core author archives own /author/<name>/ and win,
            // so every author link was a 404.
            'rewrite' => ['slug' => 'product-author', 'with_front' => false],
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
     * Add the product authors as Schema.org Person to the Product markup.
     *
     * @param array<string, mixed> $markup
     * @return array<string, mixed>
     */
    public function addSchemaAuthor(array $markup, \WC_Product $product): array
    {
        $persons = [];

        foreach ($this->getProductAuthors($product->get_id()) as $author) {
            $termLink = get_term_link($author);
            $persons[] = array_filter([
                '@type' => 'Person',
                'name' => $author->name,
                'url' => is_wp_error($termLink) ? '' : $termLink,
            ]);
        }

        if ($persons !== []) {
            $markup['author'] = count($persons) === 1 ? $persons[0] : $persons;
        }

        return $markup;
    }

    /**
     * @return list<\WP_Term>
     */
    private function getProductAuthors(int $productId): array
    {
        if (isset($this->authorsCache[$productId])) {
            return $this->authorsCache[$productId];
        }

        $terms = wp_get_post_terms($productId, self::TAXONOMY);

        if (is_wp_error($terms)) {
            $this->authorsCache[$productId] = [];

            return $this->authorsCache[$productId];
        }

        $this->authorsCache[$productId] = array_values($terms);

        return $this->authorsCache[$productId];
    }
}
