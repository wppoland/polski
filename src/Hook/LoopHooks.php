<?php

declare(strict_types=1);

namespace Spolszczony\Hook;

use Spolszczony\Contract\Bootable;
use Spolszczony\Contract\HasHooks;
use Spolszczony\Service\PriceDisplayService;
use Spolszczony\Shopmark\Location;
use Spolszczony\Shopmark\Shopmark;
use Spolszczony\Shopmark\ShopmarkManager;
use Spolszczony\Util\TemplateLoader;

/**
 * Registers shopmarks for product archive/loop pages.
 */
final class LoopHooks implements Bootable, HasHooks
{
    public function __construct(
        private readonly PriceDisplayService $priceDisplay,
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
}
