<?php

declare(strict_types=1);

use Spolszczony\Admin\PostTypes;
use Spolszczony\Admin\ProductMetaBox;
use Spolszczony\Hook\AdminHooks;
use Spolszczony\Hook\ProductHooks;
use Spolszczony\Hook\CartHooks;
use Spolszczony\Hook\CheckoutHooks;
use Spolszczony\Hook\OrderHooks;
use Spolszczony\Hook\EmailHooks;
use Spolszczony\Hook\LoopHooks;
use Spolszczony\Integration\IntegrationManager;
use Spolszczony\Rest\CheckboxController;
use Spolszczony\Rest\LegalPageController;
use Spolszczony\Rest\SettingsController;
use Spolszczony\Rest\WithdrawalController;
use Spolszczony\Service\WithdrawalService;
use Spolszczony\Service\CheckboxService;
use Spolszczony\Service\ContractService;
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
    CheckboxService::class,
    OmnibusService::class,
    ContractService::class,
    DisputeResolutionService::class,

    // Integrations (detect third-party plugins).
    IntegrationManager::class,

    // Admin (taxonomies + meta boxes).
    PostTypes::class,
    ProductMetaBox::class,

    // Hook subscribers.
    AdminHooks::class,
    ProductHooks::class,
    LoopHooks::class,
    CartHooks::class,
    CheckoutHooks::class,
    OrderHooks::class,
    EmailHooks::class,

    // Withdrawal service (needs hooks for My Account).
    WithdrawalService::class,

    // REST API.
    SettingsController::class,
    CheckboxController::class,
    WithdrawalController::class,
    LegalPageController::class,
];
