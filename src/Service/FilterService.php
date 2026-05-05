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
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- GET-based product list filters; bookmarkable URLs must work without a nonce.
        if (! $this->isEnabled() || is_admin()) {
            return;
        }

        $taxQuery = $query->get('tax_query');
        $metaQuery = $query->get('meta_query');

        $taxQuery = is_array($taxQuery) ? $taxQuery : [];
        $metaQuery = is_array($metaQuery) ? $metaQuery : [];

        $taxonomyOperator = $this->getTaxonomyOperator();

        $categories = $this->getRequestedTermValues('polski_filter_category');
        if ($categories !== []) {
            $taxQuery[] = [
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => $categories,
                'operator' => $taxonomyOperator,
            ];
        }

        $brands = $this->getRequestedTermValues('polski_filter_brand');
        if ($brands !== []) {
            $taxQuery[] = [
                'taxonomy' => 'polski_brand',
                'field' => 'slug',
                'terms' => $brands,
                'operator' => $taxonomyOperator,
            ];
        }

        $minPrice = isset($_GET['polski_filter_min_price'])
            ? wc_format_decimal(sanitize_text_field((string) wp_unslash($_GET['polski_filter_min_price'])))
            : '';
        if ($minPrice !== '') {
            $metaQuery[] = [
                'key' => '_price',
                'value' => $minPrice,
                'compare' => '>=',
                'type' => 'DECIMAL(10,2)',
            ];
        }

        $maxPrice = isset($_GET['polski_filter_max_price'])
            ? wc_format_decimal(sanitize_text_field((string) wp_unslash($_GET['polski_filter_max_price'])))
            : '';
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
            $terms = $this->getRequestedTermValues($param);

            if ($terms === []) {
                continue;
            }

            $taxQuery[] = [
                'taxonomy' => $taxonomy,
                'field' => 'slug',
                'terms' => $terms,
                'operator' => $taxonomyOperator,
            ];
        }

        if ($taxQuery !== []) {
            $query->set('tax_query', $taxQuery);
        }

        if ($metaQuery !== []) {
            $query->set('meta_query', $metaQuery);
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
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
            'categories' => $this->getTermOptions('product_cat', (bool) ($this->getSettings()['show_hierarchical_categories'] ?? true)),
            'brands' => $this->getTermOptions('polski_brand'),
            'attribute_options' => $this->getAttributeOptions(),
            'attribute_taxonomies' => $this->getAttributeTaxonomies(),
            'action_url' => $this->getActionUrl(),
            'reset_url' => $this->getResetUrl(),
            'active_filters' => $this->getActiveFilters(),
        ]);
    }

    /**
     * @param array<string, string|list<string>> $query
     * @param list<string> $attributeTaxonomies
     * @param callable(string, string): string $labelResolver
     * @return list<array{param: string, label: string, value: string, raw_value: string}>
     */
    public function buildActiveFilterItems(array $query, array $attributeTaxonomies, callable $labelResolver): array
    {
        $settings = $this->getSettings();
        $items = [];

        $items = array_merge(
            $items,
            $this->buildTaxonomyActiveItems(
                'polski_filter_category',
                'product_cat',
                (string) ($settings['category_label'] ?? __('Kategoria', 'polski')),
                $query,
                $labelResolver,
            ),
            $this->buildTaxonomyActiveItems(
                'polski_filter_brand',
                'polski_brand',
                (string) ($settings['brand_label'] ?? __('Marka', 'polski')),
                $query,
                $labelResolver,
            ),
        );

        $minPrice = is_string($query['polski_filter_min_price'] ?? null)
            ? (string) $query['polski_filter_min_price']
            : '';
        if ($minPrice !== '') {
            $items[] = [
                'param' => 'polski_filter_min_price',
                'label' => (string) ($settings['min_price_label'] ?? __('Cena od', 'polski')),
                'value' => $minPrice,
                'raw_value' => $minPrice,
            ];
        }

        $maxPrice = is_string($query['polski_filter_max_price'] ?? null)
            ? (string) $query['polski_filter_max_price']
            : '';
        if ($maxPrice !== '') {
            $items[] = [
                'param' => 'polski_filter_max_price',
                'label' => (string) ($settings['max_price_label'] ?? __('Cena do', 'polski')),
                'value' => $maxPrice,
                'raw_value' => $maxPrice,
            ];
        }

        if (($query['polski_filter_stock'] ?? '') === 'instock') {
            $items[] = [
                'param' => 'polski_filter_stock',
                'label' => (string) ($settings['stock_label'] ?? __('Dostępność', 'polski')),
                'value' => (string) ($settings['stock_instock_text'] ?? __('Dostępne od ręki', 'polski')),
                'raw_value' => 'instock',
            ];
        }

        if (($query['polski_filter_sale'] ?? '') === '1') {
            $items[] = [
                'param' => 'polski_filter_sale',
                'label' => (string) ($settings['sale_label'] ?? __('Promocje', 'polski')),
                'value' => (string) ($settings['sale_active_text'] ?? __('Tylko promocje', 'polski')),
                'raw_value' => '1',
            ];
        }

        foreach ($attributeTaxonomies as $taxonomy) {
            $param = 'polski_filter_' . $taxonomy;
            $items = array_merge(
                $items,
                $this->buildTaxonomyActiveItems(
                    $param,
                    $taxonomy,
                    wc_attribute_label($taxonomy),
                    $query,
                    $labelResolver,
                ),
            );
        }

        return $items;
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
        if (is_product_category() || is_product_tag() || is_product_taxonomy()) {
            $term = get_queried_object();

            if ($term instanceof \WP_Term) {
                $url = get_term_link($term);

                if (! is_wp_error($url)) {
                    return $url;
                }
            }
        }

        if (is_shop()) {
            $shopUrl = get_permalink(wc_get_page_id('shop'));

            if (is_string($shopUrl) && $shopUrl !== '') {
                return $shopUrl;
            }
        }

        if (is_singular()) {
            $url = get_permalink();

            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        if (is_post_type_archive('product')) {
            return home_url('/?post_type=product');
        }

        return (string) (get_permalink(wc_get_page_id('shop')) ?: '');
    }

    /**
     * @return list<array{term: \WP_Term, label: string, depth: int}>
     */
    private function getTermOptions(string $taxonomy, bool $hierarchical = false): array
    {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        if (! is_array($terms)) {
            return [];
        }

        $terms = array_values($terms);

        if (! $hierarchical || ! is_taxonomy_hierarchical($taxonomy)) {
            return array_map(
                static fn (\WP_Term $term): array => [
                    'term' => $term,
                    'label' => $term->name,
                    'depth' => 0,
                ],
                $terms,
            );
        }

        return $this->flattenHierarchicalTerms($terms);
    }

    /**
     * @return array<string, list<array{term: \WP_Term, label: string, depth: int}>>
     */
    private function getAttributeOptions(): array
    {
        $options = [];

        foreach ($this->getAttributeTaxonomies() as $taxonomy) {
            $options[$taxonomy] = $this->getTermOptions($taxonomy);
        }

        return $options;
    }

    private function getResetUrl(): string
    {
        return add_query_arg($this->getPersistedQueryArgs([]), $this->getActionUrl());
    }

    /**
     * @return list<array{param: string, label: string, value: string, raw_value: string, remove_url: string}>
     */
    private function getActiveFilters(): array
    {
        $query = $this->getActiveQueryValues();

        $items = $this->buildActiveFilterItems(
            $query,
            $this->getAttributeTaxonomies(),
            fn (string $taxonomy, string $slug): string => $this->resolveTermLabel($taxonomy, $slug),
        );

        return array_map(
            fn (array $item): array => [
                'param' => $item['param'],
                'label' => $item['label'],
                'value' => $item['value'],
                'raw_value' => $item['raw_value'],
                'remove_url' => add_query_arg(
                    $this->getPersistedQueryArgs([$item]),
                    $this->getActionUrl(),
                ),
            ],
            $items,
        );
    }

    /**
     * @return array<string, string|list<string>>
     */
    private function getActiveQueryValues(): array
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- GET-based product list filters; bookmarkable URLs must work without a nonce.
        $values = [
            'polski_filter_category' => $this->getRequestedTermValues('polski_filter_category'),
            'polski_filter_brand' => $this->getRequestedTermValues('polski_filter_brand'),
            'polski_filter_min_price' => sanitize_text_field((string) wp_unslash($_GET['polski_filter_min_price'] ?? '')),
            'polski_filter_max_price' => sanitize_text_field((string) wp_unslash($_GET['polski_filter_max_price'] ?? '')),
            'polski_filter_stock' => sanitize_key((string) wp_unslash($_GET['polski_filter_stock'] ?? '')),
            'polski_filter_sale' => sanitize_key((string) wp_unslash($_GET['polski_filter_sale'] ?? '')),
        ];
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        foreach ($this->getAttributeTaxonomies() as $taxonomy) {
            $param = 'polski_filter_' . $taxonomy;
            $values[$param] = $this->getRequestedTermValues($param);
        }

        return array_filter(
            $values,
            static fn (string|array $value): bool => is_array($value) ? $value !== [] : $value !== '',
        );
    }

    private function resolveTermLabel(string $taxonomy, string $slug): string
    {
        $term = get_term_by('slug', $slug, $taxonomy);

        if ($term instanceof \WP_Term && $term->name !== '') {
            return $term->name;
        }

        return $slug;
    }

    /**
     * @param list<array{param: string, label: string, value: string, raw_value: string}> $excludedItems
     * @return array<string, string|list<string>>
     */
    private function getPersistedQueryArgs(array $excludedItems): array
    {
        $query = $this->getActiveQueryValues();
        unset($query['paged']);

        foreach ($excludedItems as $item) {
            $param = $item['param'];

            if (! array_key_exists($param, $query)) {
                continue;
            }

            $value = $query[$param];

            if (! is_array($value)) {
                unset($query[$param]);
                continue;
            }

            $remaining = array_values(array_filter(
                $value,
                static fn (string $candidate): bool => $candidate !== $item['raw_value'],
            ));

            if ($remaining === []) {
                unset($query[$param]);
                continue;
            }

            $query[$param] = $remaining;
        }

        return $query;
    }

    /**
     * @param list<\WP_Term> $terms
     * @return list<array{term: \WP_Term, label: string, depth: int}>
     */
    private function flattenHierarchicalTerms(array $terms): array
    {
        $grouped = [];

        foreach ($terms as $term) {
            $grouped[(int) $term->parent][] = $term;
        }

        foreach ($grouped as &$children) {
            usort(
                $children,
                static fn (\WP_Term $left, \WP_Term $right): int => strcasecmp($left->name, $right->name),
            );
        }
        unset($children);

        return $this->flattenChildren($grouped, 0, 0);
    }

    /**
     * @param array<int, list<\WP_Term>> $grouped
     * @return list<array{term: \WP_Term, label: string, depth: int}>
     */
    private function flattenChildren(array $grouped, int $parentId, int $depth): array
    {
        $items = [];

        foreach ($grouped[$parentId] ?? [] as $term) {
            $items[] = [
                'term' => $term,
                'label' => str_repeat('— ', $depth) . $term->name,
                'depth' => $depth,
            ];

            $items = array_merge(
                $items,
                $this->flattenChildren($grouped, (int) $term->term_id, $depth + 1),
            );
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function getRequestedTermValues(string $param): array
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only storefront filters; each value sanitised via sanitize_title() below.
        $raw = wp_unslash($_GET[$param] ?? []);

        if (is_string($raw)) {
            $raw = [$raw];
        }

        if (! is_array($raw)) {
            return [];
        }

        $values = array_map(
            static fn (mixed $value): string => sanitize_title((string) $value),
            $raw,
        );

        return array_values(array_filter(array_unique($values)));
    }

    private function getTaxonomyOperator(): string
    {
        return strtolower((string) ($this->getSettings()['taxonomy_multi_select_relation'] ?? 'or')) === 'and'
            ? 'AND'
            : 'IN';
    }

    /**
     * @param array<string, string|list<string>> $query
     * @param callable(string, string): string $labelResolver
     * @return list<array{param: string, label: string, value: string, raw_value: string}>
     */
    private function buildTaxonomyActiveItems(
        string $param,
        string $taxonomy,
        string $label,
        array $query,
        callable $labelResolver,
    ): array {
        $rawValues = $query[$param] ?? [];
        $values = is_array($rawValues) ? $rawValues : (($rawValues !== '') ? [(string) $rawValues] : []);

        return array_map(
            static fn (string $value) => [
                'param' => $param,
                'label' => $label,
                'value' => $labelResolver($taxonomy, $value),
                'raw_value' => $value,
            ],
            $values,
        );
    }
}
