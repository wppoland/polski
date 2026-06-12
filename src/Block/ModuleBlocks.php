<?php

declare(strict_types=1);
namespace Polski\Block;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Service\FilterService;
use Polski\Service\ProductSliderService;
use Polski\Service\SearchService;

final class ModuleBlocks implements HasHooks
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly FilterService $filterService,
        private readonly ProductSliderService $productSliderService,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('init', [$this, 'registerBlocks']);
        add_filter('block_categories_all', [$this, 'registerCategory']);
    }

    /**
     * Group every Polski block under a single "Polski" category in the inserter.
     *
     * @param array<int, array<string, mixed>> $categories
     * @return array<int, array<string, mixed>>
     */
    public function registerCategory(array $categories): array
    {
        foreach ($categories as $category) {
            if (($category['slug'] ?? '') === 'polski') {
                return $categories;
            }
        }

        array_unshift($categories, [
            'slug' => 'polski',
            'title' => __('Polski', 'polski'),
            'icon' => null,
        ]);

        return $categories;
    }

    public function registerBlocks(): void
    {
        $this->registerBlock(
            'ajax-search',
            [$this, 'renderSearchBlock'],
        );
        $this->registerBlock(
            'ajax-filters',
            [$this, 'renderFiltersBlock'],
        );
        $this->registerBlock(
            'product-slider',
            [$this, 'renderSliderBlock'],
        );
    }

    /**
     * @param callable(array<string, mixed>): string $renderCallback
     */
    private function registerBlock(string $slug, callable $renderCallback): void
    {
        // Register from build/blocks (shipped) rather than resources/ (excluded
        // from the distributed package via .distignore), so the blocks actually
        // register at runtime on installed sites and not only in the dev tree.
        $metadataPath = \Polski\PLUGIN_DIR . '/build/blocks/' . $slug;
        $assetPath = $metadataPath . '/index.asset.php';

        if (! file_exists($metadataPath . '/block.json') || ! file_exists($assetPath)) {
            return;
        }

        $asset = require $assetPath;

        $handle = 'polski-' . $slug . '-block';

        wp_register_script(
            $handle,
            plugins_url('build/blocks/' . $slug . '/index.js', \Polski\PLUGIN_FILE),
            $asset['dependencies'] ?? [],
            $asset['version'] ?? \Polski\VERSION,
            true,
        );

        wp_set_script_translations($handle, 'polski', \Polski\PLUGIN_DIR . '/languages');

        register_block_type($metadataPath, [
            'editor_script' => $handle,
            'render_callback' => $renderCallback,
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function renderSearchBlock(array $attributes = []): string
    {
        return $this->searchService->renderSearchForm($this->filterSearchAttributes($attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function renderFiltersBlock(array $attributes = []): string
    {
        return $this->filterService->renderFilterForm($this->filterFilterAttributes($attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function renderSliderBlock(array $attributes = []): string
    {
        return $this->productSliderService->renderSlider($this->filterSliderAttributes($attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function filterSearchAttributes(array $attributes): array
    {
        return array_filter([
            'placeholder' => isset($attributes['placeholder']) ? sanitize_text_field((string) $attributes['placeholder']) : null,
            'search_label' => isset($attributes['searchLabel']) ? sanitize_text_field((string) $attributes['searchLabel']) : null,
            'results_label' => isset($attributes['resultsLabel']) ? sanitize_text_field((string) $attributes['resultsLabel']) : null,
            'submit_button_text' => isset($attributes['submitButtonText']) ? sanitize_text_field((string) $attributes['submitButtonText']) : null,
            'show_submit_button' => isset($attributes['showSubmitButton']) ? (bool) $attributes['showSubmitButton'] : null,
            'min_chars' => isset($attributes['minChars']) ? max(1, (int) $attributes['minChars']) : null,
            'limit' => isset($attributes['limit']) ? max(1, min(50, (int) $attributes['limit'])) : null,
        ], static fn ($value): bool => $value !== null);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function filterFilterAttributes(array $attributes): array
    {
        return array_filter([
            'title' => isset($attributes['title']) ? sanitize_text_field((string) $attributes['title']) : null,
            'show_title' => isset($attributes['showTitle']) ? (bool) $attributes['showTitle'] : null,
            'show_categories' => isset($attributes['showCategories']) ? (bool) $attributes['showCategories'] : null,
            'show_brands' => isset($attributes['showBrands']) ? (bool) $attributes['showBrands'] : null,
            'show_price' => isset($attributes['showPrice']) ? (bool) $attributes['showPrice'] : null,
            'show_stock' => isset($attributes['showStock']) ? (bool) $attributes['showStock'] : null,
            'show_sale' => isset($attributes['showSale']) ? (bool) $attributes['showSale'] : null,
            'show_attributes' => isset($attributes['showAttributes']) ? (bool) $attributes['showAttributes'] : null,
        ], static fn ($value): bool => $value !== null);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function filterSliderAttributes(array $attributes): array
    {
        $source = isset($attributes['source']) ? sanitize_key((string) $attributes['source']) : null;

        if (! in_array($source, ['related', 'upsell', 'sale', 'featured'], true)) {
            $source = null;
        }

        return array_filter([
            'product_id' => isset($attributes['productId']) ? max(0, (int) $attributes['productId']) : 0,
            'source' => $source,
            'title' => isset($attributes['title']) ? sanitize_text_field((string) $attributes['title']) : null,
            'limit' => isset($attributes['limit']) ? max(1, min(12, (int) $attributes['limit'])) : null,
            'show_title' => isset($attributes['showTitle']) ? (bool) $attributes['showTitle'] : null,
            'show_price' => isset($attributes['showPrice']) ? (bool) $attributes['showPrice'] : null,
            'show_add_to_cart' => isset($attributes['showAddToCart']) ? (bool) $attributes['showAddToCart'] : null,
        ], static fn ($value): bool => $value !== null);
    }
}
