<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Util\SettingsCacheable;
use Polski\Util\TemplateLoader;

/**
 * Enhanced WooCommerce product search.
 *
 * Extends search to include Polski product meta fields
 * (manufacturer, GTIN/EAN, ingredients) in search results.
 */
final class SearchService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_search';

    public function __construct(
        private readonly TemplateLoader $templateLoader,
        private readonly PriceDisplayService $priceDisplay,
    ) {
    }

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        // Extend product search to include Polski meta.
        add_filter('posts_search', [$this, 'extendProductSearch'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_shortcode('polski_ajax_search', [$this, 'renderShortcode']);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('ajax_search');
    }

    public function enqueueAssets(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        wp_enqueue_style(
            'polski-ajax-search',
            \Polski\Plugin::instance()->url('assets/css/ajax-search.css'),
            [],
            \Polski\VERSION,
        );

        wp_enqueue_script(
            'polski-ajax-search',
            \Polski\Plugin::instance()->url('assets/js/ajax-search.js'),
            [],
            \Polski\VERSION,
            ['in_footer' => true, 'strategy' => 'defer'],
        );

        $settings = $this->getAjaxSettings();

        wp_localize_script('polski-ajax-search', 'polskiAjaxSearch', [
            'endpoint' => home_url('/?rest_route=/polski/v1/search'),
            'minChars' => max(1, (int) ($settings['min_chars'] ?? 2)),
            'debounceMs' => max(0, (int) ($settings['debounce_ms'] ?? 180)),
            'noResultsText' => (string) ($settings['no_results_text'] ?? ''),
            'viewAllText' => (string) ($settings['view_all_text'] ?? ''),
            'showImage' => (bool) ($settings['show_image'] ?? true),
            'showPrice' => (bool) ($settings['show_price'] ?? true),
            'showUnitPrice' => (bool) ($settings['show_unit_price'] ?? true),
            'showOmnibus' => (bool) ($settings['show_omnibus'] ?? true),
            'showSku' => (bool) ($settings['show_sku'] ?? true),
            'showViewAllLink' => (bool) ($settings['show_view_all_link'] ?? true),
            'skuLabel' => (string) ($settings['sku_label'] ?? __('SKU', 'polski')),
        ]);
    }

    /**
     * @param array<string, string>|string $atts
     */
    public function renderShortcode(array|string $atts = []): string
    {
        if (! $this->isEnabled()) {
            return '';
        }

        return $this->renderSearchForm();
    }

    /**
     * @return array<string, mixed>
     */
    public function getAjaxSettings(): array
    {
        return $this->getSettings();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public function renderSearchForm(array $overrides = []): string
    {
        if (! $this->isEnabled()) {
            return '';
        }

        $this->enqueueAssets();

        return $this->templateLoader->render('forms/ajax-search', [
            'settings' => array_merge($this->getAjaxSettings(), $overrides),
        ]);
    }

    /**
     * @return array{results: list<array<string, mixed>>, search_url: string}
     */
    public function searchAjax(string $term, int $perPage): array
    {
        $settings = $this->getAjaxSettings();
        $perPage = max(1, min(50, $perPage));
        $queryArgs = [
            's' => $term,
            'limit' => min($perPage, max(1, (int) ($settings['limit'] ?? 6))),
            'status' => 'publish',
        ];

        if (! (bool) ($settings['include_out_of_stock'] ?? false)) {
            $queryArgs['stock_status'] = 'instock';
        }

        $products = $this->collectAjaxProducts($term, $queryArgs);
        $results = [];

        foreach ($products as $product) {
            if (! $product instanceof \WC_Product) {
                continue;
            }

            $results[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'url' => $product->get_permalink(),
                'image' => (bool) ($settings['show_image'] ?? true) ? wp_get_attachment_image_url((int) $product->get_image_id(), 'thumbnail') : '',
                'sku' => (bool) ($settings['show_sku'] ?? true) ? $product->get_sku() : '',
                'price_html' => (bool) ($settings['show_price'] ?? true) ? $product->get_price_html() : '',
                'unit_price_html' => (bool) ($settings['show_unit_price'] ?? true) ? $this->priceDisplay->getUnitPriceHtml($product) : '',
                'omnibus_html' => (bool) ($settings['show_omnibus'] ?? true) ? $this->priceDisplay->getOmnibusPriceHtml($product) : '',
            ];
        }

        return [
            'results' => $results,
            'search_url' => (bool) ($settings['show_view_all_link'] ?? true)
                ? add_query_arg(['s' => $term, 'post_type' => 'product'], home_url('/'))
                : '',
        ];
    }

    /**
     * Extend WooCommerce product search to include manufacturer, GTIN, ingredients.
     */
    public function extendProductSearch(string $search, \WP_Query $query): string
    {
        if (! $query->is_search() || ! $query->is_main_query()) {
            return $search;
        }

        if (($query->query_vars['post_type'] ?? '') !== 'product') {
            return $search;
        }

        $searchTerm = (string) ($query->query_vars['s'] ?? '');

        if ($searchTerm === '' || $search === '') {
            return $search;
        }

        $ids = $this->extraMatchIds($searchTerm);

        if ($ids === []) {
            return $search;
        }

        global $wpdb;

        $clause = " OR ({$wpdb->posts}.ID IN (" . implode(',', array_map('intval', $ids)) . '))';

        // Insert before the closing parenthesis of the search clause.
        return preg_replace('/\)\s*$/', $clause . ')', $search) ?? $search;
    }

    /**
     * Run the dropdown's two passes: WooCommerce's own title/content search
     * first, so the most obvious matches stay on top, then top up from the
     * fields WooCommerce does not look at.
     *
     * @param array<string, mixed> $queryArgs
     * @return list<\WC_Product>
     */
    private function collectAjaxProducts(string $term, array $queryArgs): array
    {
        $limit = (int) $queryArgs['limit'];
        $products = wc_get_products($queryArgs);
        $found = [];

        foreach (is_array($products) ? $products : [] as $product) {
            if ($product instanceof \WC_Product) {
                $found[$product->get_id()] = $product;
            }
        }

        if (count($found) >= $limit) {
            return array_values($found);
        }

        $extra = array_values(array_diff($this->extraMatchIds($term), array_keys($found)));

        if ($extra === []) {
            return array_values($found);
        }

        $topUpArgs = $queryArgs;
        unset($topUpArgs['s']);
        $topUpArgs['include'] = $extra;
        $topUpArgs['limit'] = $limit - count($found);

        foreach ((array) wc_get_products($topUpArgs) as $product) {
            if ($product instanceof \WC_Product) {
                $found[$product->get_id()] = $product;
            }
        }

        return array_values($found);
    }

    /**
     * Product IDs matched by data WooCommerce's own product search ignores:
     * Polski meta, the SKU, category names and the values of the global
     * attributes the shop chose to index.
     *
     * The AJAX dropdown and the storefront results page both go through here,
     * so the two cannot disagree about what counts as a match. Before this
     * existed the dropdown ran wc_get_products(), which is not the main query,
     * so none of the extra matching applied to it at all.
     *
     * @return list<int>
     */
    private function extraMatchIds(string $term): array
    {
        global $wpdb;

        $settings = $this->getAjaxSettings();
        $like = '%' . $wpdb->esc_like($term) . '%';

        $metaKeys = ['_polski_gtin', '_polski_ingredients', '_polski_gpsr_responsible'];

        if ((bool) ($settings['search_sku'] ?? true)) {
            $metaKeys[] = '_sku';
        }

        $taxonomies = ['polski_manufacturer'];

        if ((bool) ($settings['search_categories'] ?? true)) {
            $taxonomies[] = 'product_cat';
        }

        foreach ((array) ($settings['search_attributes'] ?? []) as $slug) {
            $slug = sanitize_key((string) $slug);
            if ($slug !== '' && taxonomy_exists($slug)) {
                $taxonomies[] = $slug;
            }
        }

        // ponytail: capped id list, plenty for a dropdown and for widening a
        // search clause. Move to a FULLTEXT index if a catalogue ever outgrows it.
        $cap = 500;

        $metaPlaceholders = implode(',', array_fill(0, count($metaKeys), '%s'));
        $metaIds = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key IN ({$metaPlaceholders}) AND meta_value LIKE %s
                 LIMIT %d",
                [...$metaKeys, $like, $cap],
            )
        );

        $taxPlaceholders = implode(',', array_fill(0, count($taxonomies), '%s'));
        $taxIds = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT tr.object_id FROM {$wpdb->term_relationships} tr
                 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                 INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                 WHERE tt.taxonomy IN ({$taxPlaceholders}) AND t.name LIKE %s
                 LIMIT %d",
                [...$taxonomies, $like, $cap],
            )
        );

        $ids = array_map('intval', array_merge((array) $metaIds, (array) $taxIds));

        return array_values(array_unique(array_filter($ids)));
    }
}
