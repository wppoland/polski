<?php

declare(strict_types=1);
namespace Polski\Hook;

defined('ABSPATH') || exit;

use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Service\DeliveryTimeService;
use Polski\Service\PriceDisplayService;
use Polski\Service\ProductInfoService;
use Polski\Shopmark\Location;
use Polski\Shopmark\Shopmark;
use Polski\Shopmark\ShopmarkManager;
use Polski\Util\TemplateLoader;

/**
 * Registers shopmarks for product archive/loop pages.
 */
final class LoopHooks implements Bootable, HasHooks
{
    public function __construct(
        private readonly PriceDisplayService $priceDisplay,
        private readonly ProductInfoService $productInfo,
        private readonly DeliveryTimeService $deliveryTime,
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
        add_action('woocommerce_after_shop_loop_item_title', [$this, 'renderLoopShopmarks'], 15);
    }

    private function registerShopmarks(): void
    {
        $this->shopmarks->register(new Shopmark(
            id: 'loop_unit_price',
            location: Location::Loop,
            hookName: 'woocommerce_after_shop_loop_item_title',
            priority: 15,
            callback: fn () => $this->renderUnitPrice(),
        ));

        $this->shopmarks->register(new Shopmark(
            id: 'loop_omnibus_price',
            location: Location::Loop,
            hookName: 'woocommerce_after_shop_loop_item_title',
            priority: 16,
            callback: fn () => $this->renderOmnibusPrice(),
        ));

        $this->shopmarks->register(new Shopmark(
            id: 'loop_delivery_time',
            location: Location::Loop,
            hookName: 'woocommerce_after_shop_loop_item_title',
            priority: 17,
            callback: fn () => $this->renderDeliveryTime(),
        ));

        $this->shopmarks->register(new Shopmark(
            id: 'loop_brand',
            location: Location::Loop,
            hookName: 'woocommerce_after_shop_loop_item_title',
            priority: 14,
            callback: fn () => $this->renderBrand(),
        ));
    }

    public function renderLoopShopmarks(): void
    {
        $marks = $this->shopmarks->getForLocation(Location::Loop);

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
            $this->templateLoader->include('loop/unit-price', [
                'unit_price_html' => $html,
                'product' => $product,
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
            $this->templateLoader->include('loop/omnibus-price', [
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

        $settings = \Polski\Util\OptionCache::get('polski_delivery', []);

        if (! is_array($settings) || ! ($settings['show_in_loop'] ?? false)) {
            return;
        }

        $html = $this->deliveryTime->getDeliveryTimeHtml($product);

        if ($html !== '') {
            $this->templateLoader->include('loop/delivery-time', [
                'delivery_time_html' => $html,
                'product' => $product,
            ]);
        }
    }

    private function renderBrand(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $settings = \Polski\Util\OptionCache::get('polski_brand', []);

        if (is_array($settings) && ! ($settings['show_on_loop'] ?? true)) {
            return;
        }

        $html = $this->productInfo->getBrandHtml($product);

        if ($html !== '') {
            $this->templateLoader->include('loop/brand', [
                'brand_html' => $html,
                'product' => $product,
            ]);
        }
    }
}
