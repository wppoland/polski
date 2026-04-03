<?php

declare(strict_types=1);

namespace Polski\Service;

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

        global $product;

        if (! $product instanceof \WC_Product) {
            return '';
        }

        $atts = shortcode_atts([
            'source' => $this->getSettings()['source'] ?? 'related',
            'title' => $this->getSettings()['title'] ?? 'Polecane produkty',
            'limit' => $this->getSettings()['limit'] ?? 8,
        ], is_array($atts) ? $atts : []);

        return $this->renderForProduct($product, $atts);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function renderForProduct(\WC_Product $product, array $config): string
    {
        $products = $this->getProducts($product, (string) ($config['source'] ?? 'related'), (int) ($config['limit'] ?? 8));

        if ($products === [] && ! (bool) ($this->getSettings()['show_empty_state'] ?? false)) {
            return '';
        }

        return $this->templateLoader->render('shared/product-slider', [
            'products' => $products,
            'settings' => $this->getSettings(),
            'title' => (string) ($config['title'] ?? __('Polecane produkty', 'polski')),
            'show_title' => (bool) ($this->getSettings()['show_title'] ?? true),
            'show_intro_text' => (bool) ($this->getSettings()['show_intro_text'] ?? false),
            'intro_text' => (string) ($this->getSettings()['intro_text'] ?? ''),
            'show_image' => (bool) ($this->getSettings()['show_image'] ?? true),
            'show_name' => (bool) ($this->getSettings()['show_name'] ?? true),
            'show_price' => (bool) ($this->getSettings()['show_price'] ?? true),
            'show_add_to_cart' => (bool) ($this->getSettings()['show_add_to_cart'] ?? true),
            'show_view_all_link' => (bool) ($this->getSettings()['show_view_all_link'] ?? false),
            'show_empty_state' => (bool) ($this->getSettings()['show_empty_state'] ?? false),
            'empty_text' => (string) ($this->getSettings()['empty_text'] ?? ''),
            'view_all_url' => $this->getViewAllUrl((string) ($config['source'] ?? 'related')),
        ]);
    }

    /**
     * @return list<\WC_Product>
     */
    private function getProducts(\WC_Product $product, string $source, int $limit): array
    {
        $limit = max(1, min(12, $limit));
        $ids = [];

        switch ($source) {
            case 'upsell':
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
}
