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
            ['in_footer' => true, 'strategy' => 'defer'],
        );

        $this->localizeAssets();
    }

    private function localizeAssets(): void
    {
        wp_localize_script('polski-ajax-filters', 'polskiAjaxFilters', [
            /* translators: %d: number of products shown after filtering. */
            'resultsUpdatedText' => __('Zaktualizowano wyniki: %d produktów.', 'polski'),
            'resultsUpdatedGenericText' => __('Zaktualizowano wyniki.', 'polski'),
        ]);
    }

    /**
     * Resolve which preset should drive the current archive view. Themes and
     * site-specific code can hook `polski/filters/archive_preset` to map
     * categories, tags, or arbitrary archive contexts to a preset slug.
     *
     * @return array<string, mixed>
     */
    private function getArchivePresetOverrides(): array
    {
        $name = $this->getArchivePresetSlug();

        if ($name === '') {
            return [];
        }

        return $this->getPreset($name);
    }

    public function getArchivePresetSlug(): string
    {
        $mapped = $this->resolveArchivePresetSlugFromSettings();

        /**
         * Filter the preset slug for the current archive. Default: resolved
         * from settings or empty string if no mapping matched.
         */
        return (string) apply_filters('polski/filters/archive_preset', $mapped);
    }

    public function renderArchiveFilters(): void
    {
        if (! $this->shouldRenderOnCurrentPage() || ! ($this->getSettings()['show_on_shop'] ?? true)) {
            return;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in render method
        echo $this->renderFilterForm($this->getArchivePresetOverrides());
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function renderShortcode(array|string $atts = []): string
    {
        if (! $this->isEnabled()) {
            return '';
        }

        $atts = is_array($atts) ? $atts : [];
        $overrides = [];

        if (isset($atts['preset']) && is_string($atts['preset']) && $atts['preset'] !== '') {
            $overrides = $this->getPreset((string) $atts['preset']);
        }

        return $this->renderFilterForm(array_merge($overrides, $this->parseShortcodeOverrides($atts)));
    }

    /**
     * Look up a named filter preset. Presets can be stored either in the
     * legacy `polski_filter_presets` option or in the admin-managed
     * `polski_filters[presets_json]` setting.
     *
     * @return array<string, mixed>
     */
    public function getPreset(string $name): array
    {
        $presets = $this->getStoredPresets();
        $preset = isset($presets[$name]) && is_array($presets[$name]) ? $presets[$name] : [];

        /**
         * Filter a named preset before it is applied. Useful for per-page or
         * per-archive variants that the admin UI does not (yet) expose.
         *
         * @param array<string, mixed> $preset Preset overrides keyed by filter setting.
         * @param string               $name   Preset slug.
         */
        return (array) apply_filters('polski/filters/preset', $preset, $name);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getStoredPresets(): array
    {
        $legacyPresets = $this->sanitizePresetCollection(get_option('polski_filter_presets', []));
        $jsonPresets = $this->parsePresetsJson((string) ($this->getSettings()['presets_json'] ?? ''));

        return array_replace($legacyPresets, $jsonPresets);
    }

    private function resolveArchivePresetSlugFromSettings(): string
    {
        $mappings = $this->parseArchivePresetMappings((string) ($this->getSettings()['archive_presets_json'] ?? ''));

        if ($mappings === []) {
            return '';
        }

        foreach ($this->getCurrentArchivePresetCandidates() as $candidate) {
            if (isset($mappings[$candidate])) {
                return $mappings[$candidate];
            }
        }

        return '';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function parsePresetsJson(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        return $this->sanitizePresetCollection($decoded);
    }

    /**
     * @return array<string, string>
     */
    private function parseArchivePresetMappings(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $mappings = [];

        foreach ($decoded as $context => $preset) {
            if (! is_string($context) || ! is_string($preset)) {
                continue;
            }

            $normalizedContext = $this->normalizeArchivePresetContext($context);
            $normalizedPreset = sanitize_key($preset);

            if ($normalizedContext === '' || $normalizedPreset === '') {
                continue;
            }

            $mappings[$normalizedContext] = $normalizedPreset;
        }

        return $mappings;
    }

    /**
     * @param mixed $presets
     * @return array<string, array<string, mixed>>
     */
    private function sanitizePresetCollection(mixed $presets): array
    {
        if (! is_array($presets)) {
            return [];
        }

        $sanitized = [];

        foreach ($presets as $name => $preset) {
            if (! is_string($name) || ! is_array($preset)) {
                continue;
            }

            $slug = sanitize_key($name);

            if ($slug === '') {
                continue;
            }

            $sanitized[$slug] = $this->sanitizePresetOverrides($preset);
        }

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $preset
     * @return array<string, mixed>
     */
    private function sanitizePresetOverrides(array $preset): array
    {
        $allowedKeys = [
            'show_on_shop',
            'show_title',
            'show_categories',
            'show_brands',
            'show_price',
            'show_stock',
            'show_sale',
            'show_attributes',
            'show_active_filters',
            'show_counts',
            'show_hierarchical_categories',
            'enable_taxonomy_multiselect',
            'taxonomy_multi_select_relation',
            'enable_mobile_panel',
            'enable_instant_filtering',
            'instant_filtering_debounce_ms',
            'attribute_taxonomies',
            'max_attribute_taxonomies',
            'title',
            'active_filters_label',
            'mobile_toggle_text',
            'mobile_close_text',
            'mobile_panel_title',
            'category_label',
            'category_all_text',
            'brand_label',
            'brand_all_text',
            'min_price_label',
            'max_price_label',
            'stock_label',
            'stock_any_text',
            'stock_instock_text',
            'sale_label',
            'sale_active_text',
            'attribute_any_text',
            'show_reset_link',
            'submit_text',
            'reset_text',
        ];

        $sanitized = [];

        foreach ($allowedKeys as $key) {
            if (! array_key_exists($key, $preset)) {
                continue;
            }

            $value = $preset[$key];

            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $sanitized[$key] = $value;
                continue;
            }

            if (is_string($value)) {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }

    /**
     * @return list<string>
     */
    private function getCurrentArchivePresetCandidates(): array
    {
        $candidates = [];

        if (is_shop()) {
            $candidates[] = 'shop';
        }

        if (is_post_type_archive('product')) {
            $candidates[] = 'post_type_archive:product';
        }

        $object = get_queried_object();

        if ($object instanceof \WP_Term) {
            $short = $this->normalizeArchivePresetContext($object->taxonomy . ':' . $object->slug);
            $explicit = $this->normalizeArchivePresetContext('taxonomy:' . $object->taxonomy . ':' . $object->slug);
            $taxonomyShort = $this->normalizeArchivePresetContext($object->taxonomy);
            $taxonomyExplicit = $this->normalizeArchivePresetContext('taxonomy:' . $object->taxonomy);

            if ($short !== '') {
                $candidates[] = $short;
            }

            if ($explicit !== '') {
                $candidates[] = $explicit;
            }

            if ($taxonomyShort !== '') {
                $candidates[] = $taxonomyShort;
            }

            if ($taxonomyExplicit !== '') {
                $candidates[] = $taxonomyExplicit;
            }
        }

        return array_values(array_unique($candidates));
    }

    private function normalizeArchivePresetContext(string $context): string
    {
        $context = strtolower(trim($context));

        if ($context === '') {
            return '';
        }

        $parts = array_map(
            static fn (string $part): string => sanitize_key($part),
            explode(':', $context),
        );

        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        return implode(':', $parts);
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

        foreach ($this->getRequestedAttributeTaxonomies() as $taxonomy) {
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
     * @param array<string, mixed>|null $settings
     * @return list<string>
     */
    public function getAttributeTaxonomies(?array $settings = null): array
    {
        $settings = $settings ?? $this->getSettings();

        if (! ($settings['show_attributes'] ?? true)) {
            return [];
        }

        $configuredTaxonomies = $this->parseAttributeTaxonomiesList((string) ($settings['attribute_taxonomies'] ?? ''));

        if ($configuredTaxonomies !== []) {
            return $configuredTaxonomies;
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
        $settings = array_merge($this->getSettings(), $overrides);
        $attributeTaxonomies = $this->getAttributeTaxonomies($settings);

        return $this->templateLoader->render('forms/ajax-filters', [
            'settings' => $settings,
            'categories' => $this->getTermOptions('product_cat', (bool) ($settings['show_hierarchical_categories'] ?? true)),
            'brands' => $this->getTermOptions('polski_brand'),
            'attribute_options' => $this->getAttributeOptions($attributeTaxonomies),
            'attribute_taxonomies' => $attributeTaxonomies,
            'action_url' => $this->getActionUrl(),
            'reset_url' => $this->getResetUrl($attributeTaxonomies),
            'active_filters' => $this->getActiveFilters($settings, $attributeTaxonomies),
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
            ['in_footer' => true, 'strategy' => 'defer'],
        );

        $this->localizeAssets();
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
    /**
     * @param list<string> $attributeTaxonomies
     * @return array<string, list<array{term: \WP_Term, label: string, depth: int}>>
     */
    private function getAttributeOptions(array $attributeTaxonomies): array
    {
        $options = [];

        foreach ($attributeTaxonomies as $taxonomy) {
            $options[$taxonomy] = $this->getTermOptions($taxonomy);
        }

        return $options;
    }

    /**
     * @param list<string> $attributeTaxonomies
     */
    private function getResetUrl(array $attributeTaxonomies): string
    {
        return add_query_arg($this->getPersistedQueryArgs([], $attributeTaxonomies), $this->getActionUrl());
    }

    /**
     * @param array<string, mixed> $settings
     * @param list<string> $attributeTaxonomies
     * @return list<array{param: string, label: string, value: string, raw_value: string, remove_url: string}>
     */
    private function getActiveFilters(array $settings, array $attributeTaxonomies): array
    {
        $query = $this->getActiveQueryValues($attributeTaxonomies);

        $items = $this->buildActiveFilterItems(
            $query,
            $attributeTaxonomies,
            fn (string $taxonomy, string $slug): string => $this->resolveTermLabel($taxonomy, $slug),
        );

        return array_map(
            fn (array $item): array => [
                'param' => $item['param'],
                'label' => $item['label'],
                'value' => $item['value'],
                'raw_value' => $item['raw_value'],
                'remove_url' => add_query_arg(
                    $this->getPersistedQueryArgs([$item], $attributeTaxonomies),
                    $this->getActionUrl(),
                ),
            ],
            $items,
        );
    }

    /**
     * @param list<string> $attributeTaxonomies
     * @return array<string, string|list<string>>
     */
    private function getActiveQueryValues(array $attributeTaxonomies): array
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

        foreach ($attributeTaxonomies as $taxonomy) {
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
     * @param list<string> $attributeTaxonomies
     * @return array<string, string|list<string>>
     */
    private function getPersistedQueryArgs(array $excludedItems, array $attributeTaxonomies): array
    {
        $query = $this->getActiveQueryValues($attributeTaxonomies);
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
                'label' => str_repeat('- ', $depth) . $term->name,
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

    /**
     * @return list<string>
     */
    private function getRequestedAttributeTaxonomies(): array
    {
        $allowed = wc_get_attribute_taxonomy_names();
        $requested = [];

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only storefront filters; only query-string keys are inspected, then validated against registered attribute taxonomies.
        foreach (array_keys($_GET) as $key) {
            if (! is_string($key) || ! str_starts_with($key, 'polski_filter_pa_')) {
                continue;
            }

            $taxonomy = substr($key, strlen('polski_filter_'));

            if (in_array($taxonomy, $allowed, true)) {
                $requested[] = $taxonomy;
            }
        }

        return array_values(array_unique($requested));
    }

    private function getTaxonomyOperator(): string
    {
        return strtolower((string) ($this->getSettings()['taxonomy_multi_select_relation'] ?? 'or')) === 'and'
            ? 'AND'
            : 'IN';
    }

    /**
     * @param array<string, mixed> $atts
     * @return array<string, mixed>
     */
    private function parseShortcodeOverrides(array $atts): array
    {
        $atts = shortcode_atts([
            'title' => null,
            'show_title' => null,
            'show_categories' => null,
            'show_brands' => null,
            'show_price' => null,
            'show_stock' => null,
            'show_sale' => null,
            'show_attributes' => null,
            'show_active_filters' => null,
            'show_counts' => null,
            'enable_mobile_panel' => null,
            'enable_instant_filtering' => null,
            'enable_taxonomy_multiselect' => null,
            'taxonomy_multi_select_relation' => null,
            'max_attribute_taxonomies' => null,
            'attribute_taxonomies' => null,
        ], $atts, 'polski_ajax_filters');

        $overrides = [];

        foreach (['title', 'attribute_taxonomies'] as $key) {
            if (is_string($atts[$key]) && $atts[$key] !== '') {
                $overrides[$key] = sanitize_text_field($atts[$key]);
            }
        }

        foreach ([
            'show_title',
            'show_categories',
            'show_brands',
            'show_price',
            'show_stock',
            'show_sale',
            'show_attributes',
            'show_active_filters',
            'show_counts',
            'enable_mobile_panel',
            'enable_instant_filtering',
            'enable_taxonomy_multiselect',
        ] as $key) {
            $parsed = $this->parseOptionalBoolean($atts[$key]);

            if ($parsed !== null) {
                $overrides[$key] = $parsed;
            }
        }

        if (is_string($atts['taxonomy_multi_select_relation'])) {
            $relation = strtolower(sanitize_key($atts['taxonomy_multi_select_relation']));

            if (in_array($relation, ['and', 'or'], true)) {
                $overrides['taxonomy_multi_select_relation'] = $relation;
            }
        }

        if ($atts['max_attribute_taxonomies'] !== null && $atts['max_attribute_taxonomies'] !== '') {
            $overrides['max_attribute_taxonomies'] = max(0, (int) $atts['max_attribute_taxonomies']);
        }

        return $overrides;
    }

    private function parseOptionalBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtolower((string) $value);

        return match ($normalized) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function parseAttributeTaxonomiesList(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $requested = preg_split('/[\s,]+/', $raw) ?: [];
        $requested = array_values(array_filter(array_map('sanitize_key', $requested)));

        if ($requested === []) {
            return [];
        }

        $allowed = wc_get_attribute_taxonomy_names();

        return array_values(array_filter(
            $requested,
            static fn (string $taxonomy): bool => in_array($taxonomy, $allowed, true),
        ));
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
