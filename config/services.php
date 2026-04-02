<?php

declare(strict_types=1);

use Spolszczony\Container;
use Spolszczony\Admin\AdminPage;
use Spolszczony\Hook\AdminHooks;
use Spolszczony\Hook\ProductHooks;
use Spolszczony\Hook\CartHooks;
use Spolszczony\Hook\CheckoutHooks;
use Spolszczony\Hook\OrderHooks;
use Spolszczony\Hook\EmailHooks;
use Spolszczony\Hook\LoopHooks;
use Spolszczony\Integration\IntegrationManager;
use Spolszczony\Rest\SettingsController;
use Spolszczony\Service\PriceDisplayService;
use Spolszczony\Service\OmnibusService;
use Spolszczony\Service\TaxDisplayService;
use Spolszczony\Service\DeliveryTimeService;
use Spolszczony\Service\CheckboxService;
use Spolszczony\Service\WithdrawalService;
use Spolszczony\Service\LegalPageService;
use Spolszczony\Service\DoubleOptInService;
use Spolszczony\Service\ProductInfoService;
use Spolszczony\Service\FoodService;
use Spolszczony\Service\DisputeResolutionService;
use Spolszczony\Service\EmailService;
use Spolszczony\Service\ComplianceCheckService;
use Spolszczony\Repository\OmnibusPriceRepository;
use Spolszczony\Repository\ConsentLogRepository;
use Spolszczony\Repository\WithdrawalRepository;
use Spolszczony\Shopmark\ShopmarkManager;
use Spolszczony\Util\TemplateLoader;

/**
 * Register all services in the DI container.
 *
 * @param Container $c The container instance.
 */
return static function (Container $c): void {
    // Utilities.
    $c->singleton(TemplateLoader::class, static fn () => new TemplateLoader());

    // Repositories.
    $c->singleton(OmnibusPriceRepository::class, static function () {
        global $wpdb;
        return new OmnibusPriceRepository($wpdb);
    });

    $c->singleton(ConsentLogRepository::class, static function () {
        global $wpdb;
        return new ConsentLogRepository($wpdb);
    });

    $c->singleton(WithdrawalRepository::class, static function () {
        global $wpdb;
        return new WithdrawalRepository($wpdb);
    });

    // Services.
    $c->singleton(TaxDisplayService::class, static fn () => new TaxDisplayService());

    $c->singleton(OmnibusService::class, static fn () => new OmnibusService(
        $c->get(OmnibusPriceRepository::class),
    ));

    $c->singleton(PriceDisplayService::class, static fn () => new PriceDisplayService(
        $c->get(TaxDisplayService::class),
        $c->get(OmnibusService::class),
    ));

    $c->singleton(DeliveryTimeService::class, static fn () => new DeliveryTimeService());
    $c->singleton(CheckboxService::class, static fn () => new CheckboxService());
    $c->singleton(LegalPageService::class, static fn () => new LegalPageService());
    $c->singleton(EmailService::class, static fn () => new EmailService());
    $c->singleton(DisputeResolutionService::class, static fn () => new DisputeResolutionService());
    $c->singleton(ProductInfoService::class, static fn () => new ProductInfoService());
    $c->singleton(FoodService::class, static fn () => new FoodService());
    $c->singleton(DoubleOptInService::class, static fn () => new DoubleOptInService());
    $c->singleton(ComplianceCheckService::class, static fn () => new ComplianceCheckService());

    $c->singleton(WithdrawalService::class, static fn () => new WithdrawalService(
        $c->get(WithdrawalRepository::class),
        $c->get(EmailService::class),
    ));

    // Shopmarks.
    $c->singleton(ShopmarkManager::class, static fn () => new ShopmarkManager());

    // Integration manager.
    $c->singleton(IntegrationManager::class, static fn () => new IntegrationManager($c));

    // Admin.
    $c->singleton(AdminPage::class, static fn () => new AdminPage());

    // REST API.
    $c->singleton(SettingsController::class, static fn () => new SettingsController());

    // Hook subscribers.
    $c->singleton(AdminHooks::class, static fn () => new AdminHooks(
        $c->get(AdminPage::class),
    ));

    $c->singleton(ProductHooks::class, static fn () => new ProductHooks(
        $c->get(PriceDisplayService::class),
        $c->get(DeliveryTimeService::class),
        $c->get(ShopmarkManager::class),
        $c->get(TemplateLoader::class),
    ));

    $c->singleton(CheckoutHooks::class, static fn () => new CheckoutHooks(
        $c->get(CheckboxService::class),
        $c->get(ConsentLogRepository::class),
    ));

    $c->singleton(LoopHooks::class, static fn () => new LoopHooks(
        $c->get(PriceDisplayService::class),
        $c->get(ShopmarkManager::class),
        $c->get(TemplateLoader::class),
    ));

    $c->singleton(CartHooks::class, static fn () => new CartHooks(
        $c->get(TemplateLoader::class),
    ));

    $c->singleton(OrderHooks::class, static fn () => new OrderHooks());

    $c->singleton(EmailHooks::class, static fn () => new EmailHooks(
        $c->get(EmailService::class),
    ));
};
