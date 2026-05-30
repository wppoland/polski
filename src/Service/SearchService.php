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

        $products = wc_get_products($queryArgs);
        $products = is_array($products) ? $products : [];
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

        global $wpdb;

        $searchTerm = $query->query_vars['s'] ?? '';

        if ($searchTerm === '') {
            return $search;
        }

        $like = '%' . $wpdb->esc_like($searchTerm) . '%';

        $metaSearch = $wpdb->prepare(
            " OR ({$wpdb->posts}.ID IN (
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key IN ('_polski_gtin', '_polski_ingredients', '_polski_gpsr_responsible')
                AND meta_value LIKE %s
            ))",
            $like,
        );

        // Also search manufacturer taxonomy.
        $taxSearch = $wpdb->prepare(
            " OR ({$wpdb->posts}.ID IN (
                SELECT tr.object_id FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE tt.taxonomy = 'polski_manufacturer'
                AND t.name LIKE %s
            ))",
            $like,
        );

        // Insert before the closing parenthesis of the search clause.
        if ($search !== '') {
            $search = preg_replace('/\)\s*$/', $metaSearch . $taxSearch . ')', $search) ?? $search;
        }

        return $search;
    }
}
