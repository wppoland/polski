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
 * Scroll-snap product slider for merchandising sections.
 */
final class ProductSliderService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_slider';

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
        add_action('woocommerce_after_single_product_summary', [$this, 'renderSingleSlider'], 19);
        add_shortcode('polski_product_slider', [$this, 'renderShortcode']);
    }

    public function enqueueAssets(): void
    {
        if (! ModulesPage::isModuleEnabled('product_slider_carousel')) {
            return;
        }

        wp_enqueue_style(
            'polski-product-slider',
            \Polski\Plugin::instance()->url('assets/css/product-slider.css'),
            [],
            \Polski\VERSION,
        );
    }

    public function renderSingleSlider(): void
    {
        global $product;

        if (! $product instanceof \WC_Product || ! ModulesPage::isModuleEnabled('product_slider_carousel') || ! ($this->getSettings()['show_on_single'] ?? true)) {
            return;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in render method
        echo $this->renderForProduct($product, $this->getSettings());
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function renderShortcode(array|string $atts = []): string
    {
        if (! ModulesPage::isModuleEnabled('product_slider_carousel')) {
            return '';
        }

        $atts = shortcode_atts([
            'source' => $this->getSettings()['source'] ?? 'related',
            'title' => $this->getSettings()['title'] ?? __('Polecane produkty', 'polski'),
            'limit' => $this->getSettings()['limit'] ?? 8,
        ], is_array($atts) ? $atts : []);

        return $this->renderSlider($atts);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function renderSlider(array $config = []): string
    {
        if (! ModulesPage::isModuleEnabled('product_slider_carousel')) {
            return '';
        }

        $this->enqueueAssets();

        $productId = isset($config['product_id']) ? (int) $config['product_id'] : 0;
        $source = (string) ($config['source'] ?? $this->getSettings()['source'] ?? 'related');
        $product = $this->resolveContextProduct($productId);

        if ($product === null && in_array($source, ['related', 'upsell'], true)) {
            return '';
        }

        return $this->renderForContext($product, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function renderForContext(?\WC_Product $product, array $config): string
    {
        $settings = array_merge($this->getSettings(), $config);
        $products = $this->getProducts($product, (string) ($settings['source'] ?? 'related'), (int) ($settings['limit'] ?? 8));

        if ($products === [] && ! (bool) ($settings['show_empty_state'] ?? false)) {
            return '';
        }

        return $this->templateLoader->render('shared/product-slider', [
            'products' => $products,
            'settings' => $settings,
            'title' => (string) ($settings['title'] ?? __('Polecane produkty', 'polski')),
            'show_title' => (bool) ($settings['show_title'] ?? true),
            'show_intro_text' => (bool) ($settings['show_intro_text'] ?? false),
            'intro_text' => (string) ($settings['intro_text'] ?? ''),
            'show_image' => (bool) ($settings['show_image'] ?? true),
            'show_name' => (bool) ($settings['show_name'] ?? true),
            'show_price' => (bool) ($settings['show_price'] ?? true),
            'show_add_to_cart' => (bool) ($settings['show_add_to_cart'] ?? true),
            'show_view_all_link' => (bool) ($settings['show_view_all_link'] ?? false),
            'show_empty_state' => (bool) ($settings['show_empty_state'] ?? false),
            'empty_text' => (string) ($settings['empty_text'] ?? ''),
            'view_all_url' => $this->getViewAllUrl((string) ($settings['source'] ?? 'related')),
        ]);
    }

    /**
     * @return list<\WC_Product>
     */
    private function getProducts(?\WC_Product $product, string $source, int $limit): array
    {
        $limit = max(1, min(12, $limit));
        $ids = [];

        switch ($source) {
            case 'upsell':
                if (! $product instanceof \WC_Product) {
                    return [];
                }
                $ids = $product->get_upsell_ids();
                break;
            case 'sale':
                $ids = wc_get_product_ids_on_sale();
                break;
            case 'featured':
                $ids = wc_get_featured_product_ids();
                break;
            case 'related':
            default:
                if (! $product instanceof \WC_Product) {
                    return [];
                }
                $ids = wc_get_related_products($product->get_id(), $limit);
                break;
        }

        $products = [];

        foreach (array_slice(array_values(array_unique(array_map('intval', $ids))), 0, $limit) as $id) {
            $item = wc_get_product($id);

            if ($item instanceof \WC_Product && $item->is_visible()) {
                $products[] = $item;
            }
        }

        return $products;
    }

    private function getViewAllUrl(string $source): string
    {
        return match ($source) {
            'sale' => wc_get_page_permalink('shop') . '?on_sale=1',
            'featured' => wc_get_page_permalink('shop') . '?featured=1',
            default => wc_get_page_permalink('shop'),
        };
    }

    private function resolveContextProduct(int $productId = 0): ?\WC_Product
    {
        if ($productId > 0) {
            $product = wc_get_product($productId);

            if ($product instanceof \WC_Product) {
                return $product;
            }
        }

        global $product;

        return $product instanceof \WC_Product ? $product : null;
    }
}
