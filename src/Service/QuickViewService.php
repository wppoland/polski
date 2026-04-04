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
 * Lightweight AJAX quick view modal for shop archives.
 */
final class QuickViewService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_quick_view';

    public function __construct(
        private readonly TemplateLoader $templateLoader,
        private readonly PriceDisplayService $priceDisplay,
        private readonly DeliveryTimeService $deliveryTime,
        private readonly ProductInfoService $productInfo,
    ) {
    }

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('woocommerce_after_shop_loop_item', [$this, 'renderLoopButton'], 21);
        add_action('wp_footer', [$this, 'renderModalShell']);
        add_action('wp_ajax_polski_quick_view', [$this, 'handleModal']);
        add_action('wp_ajax_nopriv_polski_quick_view', [$this, 'handleModal']);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('quick_view');
    }

    public function enqueueAssets(): void
    {
        if (! $this->isEnabled() || ! $this->shouldRenderOnCurrentPage()) {
            return;
        }

        wp_enqueue_style(
            'polski-quick-view',
            \Polski\Plugin::instance()->url('assets/css/quick-view.css'),
            [],
            \Polski\VERSION,
        );

        wp_enqueue_script(
            'polski-quick-view',
            \Polski\Plugin::instance()->url('assets/js/quick-view.js'),
            ['jquery'],
            \Polski\VERSION,
            true,
        );

        wp_enqueue_script('wc-add-to-cart-variation');

        wp_localize_script('polski-quick-view', 'polskiQuickView', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('polski_quick_view'),
            'loadingText' => (string) ($this->getSettings()['loading_text'] ?? __('Ładowanie produktu...', 'polski')),
            'errorText' => (string) ($this->getSettings()['error_text'] ?? __('Nie udało się wczytać podglądu produktu.', 'polski')),
            'showBackdropClose' => (bool) ($this->getSettings()['show_backdrop_close'] ?? true),
        ]);
    }

    public function renderLoopButton(): void
    {
        global $product;

        if (! $product instanceof \WC_Product || ! ($this->getSettings()['show_on_loop'] ?? true)) {
            return;
        }

        $this->templateLoader->include('loop/quick-view-button', [
            'service' => $this,
            'product' => $product,
        ]);
    }

    public function renderModalShell(): void
    {
        if (! $this->isEnabled() || ! $this->shouldRenderOnCurrentPage()) {
            return;
        }

        $this->templateLoader->include('shared/quick-view-modal', [
            'settings' => $this->getSettings(),
            'loading_text' => (string) ($this->getSettings()['loading_text'] ?? __('Ładowanie produktu...', 'polski')),
            'show_modal_label' => (bool) ($this->getSettings()['show_modal_label'] ?? true),
            'show_close_button' => (bool) ($this->getSettings()['show_close_button'] ?? true),
        ]);
    }

    public function handleModal(): void
    {
        check_ajax_referer('polski_quick_view', 'nonce');

        $productId = (int) wp_unslash($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
        $product = wc_get_product($productId);

        if (! $product instanceof \WC_Product) {
            wp_send_json_error(['message' => (string) ($this->getSettings()['product_not_found_text'] ?? __('Nie znaleziono produktu.', 'polski'))], 404);
        }

        wp_send_json_success([
            'html' => $this->renderContent($product),
        ]);
    }

    public function renderContent(\WC_Product $product): string
    {
        return $this->templateLoader->render('quick-view/content', [
            'service' => $this,
            'product' => $product,
            'settings' => $this->getSettings(),
            'add_to_cart_html' => $this->getAddToCartHtml($product),
        ]);
    }

    public function getButtonText(): string
    {
        return (string) ($this->getSettings()['button_text'] ?? __('Szybki podgląd', 'polski'));
    }

    public function getModalTitle(): string
    {
        return (string) ($this->getSettings()['modal_title'] ?? __('Szybki podgląd produktu', 'polski'));
    }

    public function getProductImages(\WC_Product $product): array
    {
        $images = [];

        if ((bool) ($this->getSettings()['show_image'] ?? true)) {
            $mainId = $product->get_image_id();

            if ($mainId > 0) {
                $images[] = $mainId;
            }
        }

        if ((bool) ($this->getSettings()['show_gallery'] ?? true)) {
            foreach (array_slice($product->get_gallery_image_ids(), 0, 4) as $imageId) {
                $imageId = (int) $imageId;

                if ($imageId > 0 && ! in_array($imageId, $images, true)) {
                    $images[] = $imageId;
                }
            }
        }

        return $images;
    }

    public function getDeliveryTimeHtml(\WC_Product $product): string
    {
        if (! ($this->getSettings()['show_delivery_time'] ?? true)) {
            return '';
        }

        return $this->deliveryTime->getDeliveryTimeHtml($product);
    }

    public function getBrandHtml(\WC_Product $product): string
    {
        if (! ($this->getSettings()['show_brand'] ?? true)) {
            return '';
        }

        return $this->productInfo->getBrandHtml($product);
    }

    public function getManufacturerHtml(\WC_Product $product): string
    {
        if (! ($this->getSettings()['show_manufacturer'] ?? true)) {
            return '';
        }

        return $this->productInfo->getManufacturerHtml($product);
    }

    public function getPriceHtml(\WC_Product $product): string
    {
        if (! ($this->getSettings()['show_price'] ?? true)) {
            return '';
        }

        return $product->get_price_html();
    }

    public function getUnitPriceHtml(\WC_Product $product): string
    {
        if (! ($this->getSettings()['show_price'] ?? true) || ! ($this->getSettings()['show_unit_price'] ?? true)) {
            return '';
        }

        return $this->priceDisplay->getUnitPriceHtml($product);
    }

    public function getSku(\WC_Product $product): string
    {
        if (! ($this->getSettings()['show_sku'] ?? true)) {
            return '';
        }

        return $product->get_sku();
    }

    private function shouldRenderOnCurrentPage(): bool
    {
        return is_shop() || is_product_taxonomy() || is_product_category() || is_product_tag();
    }

    private function getAddToCartHtml(\WC_Product $product): string
    {
        if (! ($this->getSettings()['show_add_to_cart'] ?? true) || ! $product->is_purchasable()) {
            return '';
        }

        $previousProduct = $GLOBALS['product'] ?? null;
        $GLOBALS['product'] = $product;

        ob_start();
        woocommerce_template_single_add_to_cart();
        $html = (string) ob_get_clean();

        if ($previousProduct instanceof \WC_Product) {
            $GLOBALS['product'] = $previousProduct;
        } else {
            unset($GLOBALS['product']);
        }

        return $html;
    }

    public function shouldShowTitle(): bool
    {
        return (bool) ($this->getSettings()['show_title'] ?? true);
    }
}
