<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;
use Polski\Util\TemplateLoader;

/**
 * GPSR (General Product Safety Regulation) product data display.
 */
final class GPSRService implements HasHooks
{
    public function __construct(
        private readonly TemplateLoader $templateLoader,
    ) {}

    public function registerHooks(): void
    {
        add_action('woocommerce_product_meta_end', [$this, 'renderGPSRSection'], 20);
        add_filter('manage_edit-product_columns', [$this, 'addProductColumn'], 20);
        add_action('manage_product_posts_custom_column', [$this, 'renderProductColumn'], 10, 2);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('gpsr');
    }

    /**
     * WooCommerce hooks can pass an empty string or no product at all,
     * especially on block-based templates. Resolve the product defensively.
     */
    public function renderGPSRSection(mixed $product = null): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if (! $product instanceof \WC_Product) {
            global $product;
        }

        if (! $product instanceof \WC_Product) {
            $queriedProductId = get_the_ID();

            if (is_numeric($queriedProductId) && (int) $queriedProductId > 0) {
                $product = wc_get_product((int) $queriedProductId);
            }
        }

        if (! $product instanceof \WC_Product) {
            return;
        }

        $data = $this->getGPSRData($product);

        // Only render if at least one field is filled.
        $hasData = array_filter($data, static fn (string $value): bool => $value !== '');

        if (empty($hasData)) {
            return;
        }

        $this->templateLoader->include('single-product/gpsr-info', [
            'data' => $data,
            'settings' => $this->getSettings(),
        ]);
    }

    /**
     * Add GPSR status column to WooCommerce products list.
     *
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function addProductColumn(array $columns): array
    {
        if (!$this->isEnabled()) {
            return $columns;
        }

        $columns['polski_gpsr'] = __('GPSR', 'polski');

        return $columns;
    }

    /**
     * Render GPSR status indicator in the products list column.
     */
    public function renderProductColumn(string $column, int $postId): void
    {
        if ($column !== 'polski_gpsr') {
            return;
        }

        $product = wc_get_product($postId);

        if (!$product) {
            echo '—';
            return;
        }

        $data = $this->getGPSRData($product);
        $filled = array_filter($data, fn($v) => $v !== '');
        $filledCount = count($filled);

        if ($filledCount >= 3) {
            /* translators: %d: number of filled GPSR fields out of 8 */
            echo '<span style="color:#46b450;" title="' . esc_attr(sprintf(__('%d/8 pól wypełnionych', 'polski'), $filledCount)) . '">&#10003;</span>';
        } elseif ($filledCount > 0) {
            /* translators: %d: number of filled GPSR fields out of 8 */
            echo '<span style="color:#f0ad4e;" title="' . esc_attr(sprintf(__('%d/8 pól wypełnionych', 'polski'), $filledCount)) . '">&#9888;</span>';
        } else {
            echo '<span style="color:#ccc;">—</span>';
        }
    }

    /**
     * Get all GPSR meta fields for a product.
     *
     * @return array<string, string>
     */
    public function getGPSRData(\WC_Product $product): array
    {
        return [
            'manufacturer_name'    => (string) $product->get_meta('_polski_gpsr_manufacturer_name', true),
            'manufacturer_address' => (string) $product->get_meta('_polski_gpsr_manufacturer_address', true),
            'importer_name'        => (string) $product->get_meta('_polski_gpsr_importer_name', true),
            'importer_address'     => (string) $product->get_meta('_polski_gpsr_importer_address', true),
            'responsible_person'   => (string) $product->get_meta('_polski_gpsr_responsible_person', true),
            'product_identifier'   => (string) $product->get_meta('_polski_gpsr_product_identifier', true),
            'safety_warnings'      => (string) $product->get_meta('_polski_gpsr_safety_warnings', true),
            'instructions'         => (string) $product->get_meta('_polski_gpsr_instructions', true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        $settings = get_option('polski_gpsr', []);

        return is_array($settings) ? $settings : [];
    }
}
