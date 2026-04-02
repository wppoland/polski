<?php

declare(strict_types=1);

use Spolszczony\Hook\AdminHooks;
use Spolszczony\Hook\ProductHooks;
use Spolszczony\Hook\CartHooks;
use Spolszczony\Hook\CheckoutHooks;
use Spolszczony\Hook\OrderHooks;
use Spolszczony\Hook\EmailHooks;
use Spolszczony\Hook\LoopHooks;
use Spolszczony\Integration\IntegrationManager;
use Spolszczony\Rest\SettingsController;
use Spolszczony\Service\OmnibusService;
use Spolszczony\Service\DisputeResolutionService;

/**
 * Hook subscriber classes to boot and register.
 *
 * Order matters: services are booted and hooks registered in this order.
 *
 * @return list<class-string<\Spolszczony\Contract\HasHooks>>
 */
return [
    // Core services.
    OmnibusService::class,
    DisputeResolutionService::class,

    // Integrations (detect third-party plugins).
    IntegrationManager::class,

    // Hook subscribers.
    AdminHooks::class,
    ProductHooks::class,
    LoopHooks::class,
    CartHooks::class,
    CheckoutHooks::class,
    OrderHooks::class,
    EmailHooks::class,

    // REST API.
    SettingsController::class,
];
