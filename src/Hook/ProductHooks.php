<?php

declare(strict_types=1);

namespace Spolszczony\Hook;

use Spolszczony\Contract\HasHooks;
use Spolszczony\Service\PriceDisplayService;
use Spolszczony\Service\DeliveryTimeService;
use Spolszczony\Shopmark\ShopmarkManager;
use Spolszczony\Util\TemplateLoader;

final class ProductHooks implements HasHooks
{
    public function __construct(
        private readonly PriceDisplayService $priceDisplay,
        private readonly DeliveryTimeService $deliveryTime,
        private readonly ShopmarkManager $shopmarks,
        private readonly TemplateLoader $templateLoader,
    ) {
    }

    public function registerHooks(): void
    {
        // Product display hooks will be implemented in Phase 2.
    }
}
