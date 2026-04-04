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
 * Progressive AJAX product filters for shop archives.
 */
final class FilterService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_filters';

    public function __construct(
        private readonly TemplateLoader $templateLoader,
    ) {
    }

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('woocommerce_before_shop_loop', [$this, 'renderArchiveFilters'], 9);
        add_action('woocommerce_product_query', [$this, 'applyFiltersToQuery']);
        add_shortcode('polski_ajax_filters', [$this, 'renderShortcode']);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('ajax_filters');
    }

    public function enqueueAssets(): void
    {
        if (! $this->isEnabled() || ! $this->shouldRenderOnCurrentPage()) {
            return;
        }

        wp_enqueue_style(
            'polski-ajax-filters',
            \Polski\Plugin::instance()->url('assets/css/ajax-filters.css'),
            [],
            \Polski\VERSION,
        );

        wp_enqueue_script(
            'polski-ajax-filters',
            \Polski\Plugin::instance()->url('assets/js/ajax-filters.js'),
            [],
            \Polski\VERSION,
            true,
        );
    }

    public function renderArchiveFilters(): void
    {
        if (! $this->shouldRenderOnCurrentPage() || ! ($this->getSettings()['show_on_shop'] ?? true)) {
            return;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in render method
        echo $this->renderFilterForm();
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function renderShortcode(array|string $atts = []): string
    {
        if (! $this->isEnabled()) {
            return '';
        }

        return $this->renderFilterForm();
    }

    public function applyFiltersToQuery(\WP_Query $query): void
    {
        if (! $this->isEnabled() || is_admin()) {
            return;
        }

        $taxQuery = $query->get('tax_query');
        $metaQuery = $query->get('meta_query');

        $taxQuery = is_array($taxQuery) ? $taxQuery : [];
        $metaQuery = is_array($metaQuery) ? $metaQuery : [];

        $category = sanitize_title((string) wp_unslash($_GET['polski_filter_category'] ?? ''));
        if ($category !== '') {
            $taxQuery[] = [
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => [$category],
            ];
        }

        $brand = sanitize_title((string) wp_unslash($_GET['polski_filter_brand'] ?? ''));
        if ($brand !== '') {
            $taxQuery[] = [
                'taxonomy' => 'polski_brand',
                'field' => 'slug',
                'terms' => [$brand],
            ];
        }

        $minPrice = wc_format_decimal((string) wp_unslash($_GET['polski_filter_min_price'] ?? ''));
        if ($minPrice !== '') {
            $metaQuery[] = [
                'key' => '_price',
                'value' => $minPrice,
                'compare' => '>=',
                'type' => 'DECIMAL(10,2)',
            ];
        }

        $maxPrice = wc_format_decimal((string) wp_unslash($_GET['polski_filter_max_price'] ?? ''));
        if ($maxPrice !== '') {
            $metaQuery[] = [
                'key' => '_price',
                'value' => $maxPrice,
                'compare' => '<=',
                'type' => 'DECIMAL(10,2)',
            ];
        }

        $stock = sanitize_key((string) wp_unslash($_GET['polski_filter_stock'] ?? ''));
        if ($stock === 'instock') {
            $metaQuery[] = [
                'key' => '_stock_status',
                'value' => 'instock',
            ];
        }

        $onSale = sanitize_key((string) wp_unslash($_GET['polski_filter_sale'] ?? ''));
        if ($onSale === '1') {
            $query->set('post__in', wc_get_product_ids_on_sale());
        }

        foreach ($this->getAttributeTaxonomies() as $taxonomy) {
            $param = 'polski_filter_' . $taxonomy;
            $term = sanitize_title((string) wp_unslash($_GET[$param] ?? ''));

            if ($term === '') {
                continue;
            }

            $taxQuery[] = [
                'taxonomy' => $taxonomy,
                'field' => 'slug',
                'terms' => [$term],
            ];
        }

        if ($taxQuery !== []) {
            $query->set('tax_query', $taxQuery);
        }

        if ($metaQuery !== []) {
            $query->set('meta_query', $metaQuery);
        }
    }

    /**
     * @return list<string>
     */
    public function getAttributeTaxonomies(): array
    {
        $settings = $this->getSettings();

        if (! ($settings['show_attributes'] ?? true)) {
            return [];
        }

        $max = max(0, (int) ($settings['max_attribute_taxonomies'] ?? 4));
        $taxonomies = wc_get_attribute_taxonomy_names();

        return array_slice(array_values($taxonomies), 0, $max);
    }

    private function shouldRenderOnCurrentPage(): bool
    {
        return is_shop() || is_post_type_archive('product') || is_product_taxonomy() || is_product_category() || is_product_tag();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public function renderFilterForm(array $overrides = []): string
    {
        if (! $this->isEnabled()) {
            return '';
        }

        $this->enqueueRenderedAssets();

        return $this->templateLoader->render('forms/ajax-filters', [
            'settings' => array_merge($this->getSettings(), $overrides),
            'categories' => $this->getTerms('product_cat'),
            'brands' => $this->getTerms('polski_brand'),
            'attribute_taxonomies' => $this->getAttributeTaxonomies(),
            'action_url' => $this->getActionUrl(),
            'reset_url' => remove_query_arg(array_keys($_GET)),
        ]);
    }

    private function enqueueRenderedAssets(): void
    {
        wp_enqueue_style(
            'polski-ajax-filters',
            \Polski\Plugin::instance()->url('assets/css/ajax-filters.css'),
            [],
            \Polski\VERSION,
        );

        wp_enqueue_script(
            'polski-ajax-filters',
            \Polski\Plugin::instance()->url('assets/js/ajax-filters.js'),
            [],
            \Polski\VERSION,
            true,
        );
    }

    private function getActionUrl(): string
    {
        if (is_post_type_archive('product')) {
            return home_url('/?post_type=product');
        }

        return (string) (get_permalink(wc_get_page_id('shop')) ?: '');
    }

    /**
     * @return list<\WP_Term>
     */
    private function getTerms(string $taxonomy): array
    {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
        ]);

        if (! is_array($terms)) {
            return [];
        }

        return array_values(array_filter($terms, static fn ($term): bool => $term instanceof \WP_Term));
    }
}
