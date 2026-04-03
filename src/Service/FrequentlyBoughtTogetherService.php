<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Util\SettingsCacheable;
use Polski\Util\TemplateLoader;

/**
 * Frequently bought together bundles for product detail pages.
 */
final class FrequentlyBoughtTogetherService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_fbt';

    public function __construct(
        private readonly TemplateLoader $templateLoader,
    ) {
    }

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        add_action('woocommerce_after_single_product_summary', [$this, 'renderSection'], 16);
        add_action('wp_loaded', [$this, 'handleAddBundle']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('frequently_bought_together');
    }

    public function enqueueAssets(): void
    {
        if (! $this->isEnabled() || ! is_product()) {
            return;
        }

        wp_enqueue_style(
            'polski-fbt',
            \Polski\Plugin::instance()->url('assets/css/fbt.css'),
            [],
            \Polski\VERSION,
        );

        wp_enqueue_script(
            'polski-fbt',
            \Polski\Plugin::instance()->url('assets/js/fbt.js'),
            [],
            \Polski\VERSION,
            true,
        );
    }

    public function renderSection(): void
    {
        global $product;

        if (! $product instanceof \WC_Product || ! $this->isEnabled() || ! ($this->getSettings()['show_on_single'] ?? true)) {
            return;
        }

        $bundleProducts = $this->getBundleProducts($product);

        if ($bundleProducts === [] && ! (bool) ($this->getSettings()['show_empty_state'] ?? false)) {
            return;
        }

        echo $this->templateLoader->render('single-product/fbt', [
            'service' => $this,
            'product' => $product,
            'settings' => $this->getSettings(),
            'bundle_products' => $bundleProducts,
            'title' => $this->getSectionTitle($product),
            'intro_text' => $this->getIntroText($product),
            'button_text' => $this->getButtonText($product),
            'empty_text' => (string) ($this->getSettings()['empty_text'] ?? ''),
            'show_title' => (bool) ($this->getSettings()['show_title'] ?? true),
            'show_price' => (bool) ($this->getSettings()['show_price'] ?? true),
            'show_total' => (bool) ($this->getSettings()['show_total'] ?? true),
            'show_images' => (bool) ($this->getSettings()['show_images'] ?? true),
            'show_checkboxes' => (bool) ($this->getSettings()['show_checkboxes'] ?? true),
            'preselect_products' => (bool) ($this->getSettings()['preselect_products'] ?? true),
            'show_short_description' => (bool) ($this->getSettings()['show_short_description'] ?? false),
            'total_html' => $this->getTotalHtml($bundleProducts),
        ]);
    }

    public function handleAddBundle(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (($_POST['polski_fbt_action'] ?? '') !== 'add_bundle') {
            return;
        }

        check_admin_referer('polski_fbt_add_bundle', 'polski_fbt_nonce');

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $productIds = $_POST['polski_fbt_products'] ?? [];
        $productIds = is_array($productIds) ? array_map('intval', $productIds) : [];
        $redirect = wp_validate_redirect(
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            (string) wp_unslash($_POST['polski_fbt_redirect'] ?? ''),
            wc_get_cart_url(),
        );

        $added = 0;

        foreach ($productIds as $productId) {
            $product = wc_get_product($productId);

            if (! $product instanceof \WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock()) {
                continue;
            }

            if ($product->is_type('variation')) {
                $variationId = $product->get_id();
                $parentId = $product->get_parent_id();
                $attributes = $product->get_variation_attributes();

                if (WC()->cart->add_to_cart($parentId, 1, $variationId, $attributes)) {
                    $added++;
                }

                continue;
            }

            if (WC()->cart->add_to_cart($product->get_id(), 1)) {
                $added++;
            }
        }

        if ($added > 0) {
            $template = (string) ($this->getSettings()['success_text'] ?? __('Dodano {count} produktów z zestawu do koszyka.', 'polski'));
            wc_add_notice(
                str_replace('{count}', (string) $added, $template),
                'success',
            );
        }

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * @return list<\WC_Product>
     */
    public function getBundleProducts(\WC_Product $product): array
    {
        $bundleIds = $this->getBundleProductIds($product);
        $products = [$product];

        foreach ($bundleIds as $relatedId) {
            $relatedProduct = wc_get_product($relatedId);

            if (! $relatedProduct instanceof \WC_Product) {
                continue;
            }

            if (! $relatedProduct->is_visible()) {
                continue;
            }

            $products[] = $relatedProduct;
        }

        return $products;
    }

    public function getSectionTitle(\WC_Product $product): string
    {
        $custom = trim((string) $product->get_meta('_polski_fbt_title', true));

        if ($custom !== '') {
            return $custom;
        }

        return (string) ($this->getSettings()['title'] ?? __('Często kupowane razem', 'polski'));
    }

    public function getIntroText(\WC_Product $product): string
    {
        $custom = trim((string) $product->get_meta('_polski_fbt_intro', true));

        if ($custom !== '') {
            return $custom;
        }

        return (string) ($this->getSettings()['intro_text'] ?? '');
    }

    public function getButtonText(\WC_Product $product): string
    {
        $custom = trim((string) $product->get_meta('_polski_fbt_button_text', true));

        if ($custom !== '') {
            return $custom;
        }

        return (string) ($this->getSettings()['button_text'] ?? __('Dodaj zestaw do koszyka', 'polski'));
    }

    public function getTotalHtml(array $products): string
    {
        $total = 0.0;

        foreach ($products as $product) {
            if ($product instanceof \WC_Product) {
                $total += (float) wc_get_price_to_display($product);
            }
        }

        return wc_price($total);
    }

    /**
     * @return list<int>
     */
    private function getBundleProductIds(\WC_Product $product): array
    {
        $raw = (string) $product->get_meta('_polski_fbt_product_ids', true);

        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $raw) ?: [];
        $ids = [];
        $max = max(1, (int) ($this->getSettings()['max_related_products'] ?? 4));

        foreach ($parts as $part) {
            $id = (int) $part;

            if ($id <= 0 || $id === $product->get_id()) {
                continue;
            }

            if (! in_array($id, $ids, true)) {
                $ids[] = $id;
            }

            if (count($ids) >= $max) {
                break;
            }
        }

        return $ids;
    }
}
