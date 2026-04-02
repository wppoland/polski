<?php

declare(strict_types=1);

namespace Spolszczony\Hook;

use Spolszczony\Contract\Bootable;
use Spolszczony\Contract\HasHooks;
use Spolszczony\Service\DeliveryTimeService;
use Spolszczony\Service\FoodService;
use Spolszczony\Service\PriceDisplayService;
use Spolszczony\Service\ProductInfoService;
use Spolszczony\Shopmark\Location;
use Spolszczony\Shopmark\Shopmark;
use Spolszczony\Shopmark\ShopmarkManager;
use Spolszczony\Util\TemplateLoader;

/**
 * Registers shopmarks and hooks for single product page display.
 */
final class ProductHooks implements Bootable, HasHooks
{
    public function __construct(
        private readonly PriceDisplayService $priceDisplay,
        private readonly DeliveryTimeService $deliveryTime,
        private readonly ProductInfoService $productInfo,
        private readonly FoodService $foodService,
        private readonly ShopmarkManager $shopmarks,
        private readonly TemplateLoader $templateLoader,
    ) {
    }

    public function boot(): void
    {
        $this->registerShopmarks();
    }

    public function registerHooks(): void
    {
        // Attach registered shopmarks to WooCommerce hooks.
        add_action('woocommerce_single_product_summary', [$this, 'renderSingleProductShopmarks'], 25);

        // Extend structured data for SEO.
        add_filter('woocommerce_structured_data_product', [$this, 'enrichStructuredData'], 10, 2);
    }

    /**
     * Register all single product shopmarks.
     */
    private function registerShopmarks(): void
    {
        // Unit price (cena jednostkowa).
        $this->shopmarks->register(new Shopmark(
            id: 'unit_price',
            location: Location::SingleProduct,
            hookName: 'woocommerce_single_product_summary',
            priority: 25,
            callback: fn () => $this->renderUnitPrice(),
        ));

        // VAT / tax info notice.
        $this->shopmarks->register(new Shopmark(
            id: 'tax_info',
            location: Location::SingleProduct,
            hookName: 'woocommerce_single_product_summary',
            priority: 26,
            callback: fn () => $this->renderTaxInfo(),
        ));

        // Shipping costs notice.
        $this->shopmarks->register(new Shopmark(
            id: 'shipping_notice',
            location: Location::SingleProduct,
            hookName: 'woocommerce_single_product_summary',
            priority: 27,
            callback: fn () => $this->renderShippingNotice(),
        ));

        // Omnibus lowest price.
        $this->shopmarks->register(new Shopmark(
            id: 'omnibus_price',
            location: Location::SingleProduct,
            hookName: 'woocommerce_single_product_summary',
            priority: 28,
            callback: fn () => $this->renderOmnibusPrice(),
        ));

        // Delivery time.
        $this->shopmarks->register(new Shopmark(
            id: 'delivery_time',
            location: Location::SingleProduct,
            hookName: 'woocommerce_single_product_summary',
            priority: 29,
            callback: fn () => $this->renderDeliveryTime(),
        ));

        // Manufacturer (GPSR).
        $this->shopmarks->register(new Shopmark(
            id: 'manufacturer',
            location: Location::SingleProduct,
            hookName: 'woocommerce_single_product_summary',
            priority: 35,
            callback: fn () => $this->renderManufacturer(),
        ));

        // Safety info (GPSR).
        $this->shopmarks->register(new Shopmark(
            id: 'safety_info',
            location: Location::SingleProduct,
            hookName: 'woocommerce_single_product_summary',
            priority: 36,
            callback: fn () => $this->renderSafetyInfo(),
        ));

        // Food info (nutrients, allergens, ingredients).
        $this->shopmarks->register(new Shopmark(
            id: 'food_info',
            location: Location::SingleProduct,
            hookName: 'woocommerce_single_product_summary',
            priority: 40,
            callback: fn () => $this->renderFoodInfo(),
        ));

        /**
         * Fires after default shopmarks are registered for single product pages.
         *
         * @param ShopmarkManager $shopmarks The shopmark manager.
         */
        do_action('spolszczony/shopmarks/registered', $this->shopmarks);
    }

    /**
     * Render all shopmarks for the single product page.
     */
    public function renderSingleProductShopmarks(): void
    {
        $marks = $this->shopmarks->getForLocation(Location::SingleProduct);

        foreach ($marks as $mark) {
            ($mark->callback)();
        }
    }

    private function renderUnitPrice(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $html = $this->priceDisplay->getUnitPriceHtml($product);

        if ($html !== '') {
            $this->templateLoader->include('single-product/unit-price', [
                'unit_price_html' => $html,
                'product' => $product,
            ]);
        }
    }

    private function renderTaxInfo(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $html = $this->priceDisplay->getVatNoticeHtml($product);

        if ($html !== '') {
            $this->templateLoader->include('single-product/tax-info', [
                'tax_info_html' => $html,
                'product' => $product,
            ]);
        }
    }

    private function renderShippingNotice(): void
    {
        $html = $this->priceDisplay->getShippingNoticeHtml();

        if ($html !== '') {
            $this->templateLoader->include('single-product/shipping-notice', [
                'shipping_notice_html' => $html,
            ]);
        }
    }

    private function renderOmnibusPrice(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $html = $this->priceDisplay->getOmnibusPriceHtml($product);

        if ($html !== '') {
            $this->templateLoader->include('single-product/omnibus-price', [
                'omnibus_price_html' => $html,
                'product' => $product,
            ]);
        }
    }

    private function renderDeliveryTime(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $html = $this->deliveryTime->getDeliveryTimeHtml($product);

        if ($html !== '') {
            $this->templateLoader->include('single-product/delivery-time', [
                'delivery_time_html' => $html,
                'product' => $product,
            ]);
        }
    }

    private function renderManufacturer(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $html = $this->productInfo->getManufacturerHtml($product);

        if ($html !== '') {
            $this->templateLoader->include('single-product/manufacturer', [
                'manufacturer_html' => $html,
                'product' => $product,
            ]);
        }
    }

    private function renderSafetyInfo(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $parts = array_filter([
            $this->productInfo->getSafetyDocumentsHtml($product),
            $this->productInfo->getPowerSupplyHtml($product),
            $this->productInfo->getDefectDescriptionHtml($product),
        ]);

        $gpsr = $this->productInfo->getGPSRResponsible($product);
        if ($gpsr !== '') {
            array_unshift($parts, sprintf(
                '<div class="spolszczony-gpsr"><span class="spolszczony-gpsr__label">%s:</span> %s</div>',
                esc_html__('Responsible person (GPSR)', 'spolszczony'),
                esc_html($gpsr),
            ));
        }

        if (! empty($parts)) {
            $html = '<div class="spolszczony-safety">' . implode('', $parts) . '</div>';
            $this->templateLoader->include('single-product/safety-info', [
                'safety_html' => $html,
                'product' => $product,
            ]);
        }
    }

    private function renderFoodInfo(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $html = $this->foodService->getFoodInfoHtml($product);

        if ($html !== '') {
            $this->templateLoader->include('single-product/food-info', [
                'food_info_html' => $html,
                'product' => $product,
            ]);
        }
    }

    /**
     * Add Spolszczony data to structured data (Schema.org).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function enrichStructuredData(array $data, \WC_Product $product): array
    {
        $unitPrice = $this->priceDisplay->getUnitPrice($product);

        if ($unitPrice !== null) {
            $data['spolszczony_unit_price'] = [
                'price' => $unitPrice->pricePerUnit,
                'unit' => $unitPrice->unit,
                'base_amount' => $unitPrice->baseAmount,
            ];
        }

        return $data;
    }
}
