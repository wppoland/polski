<?php

declare(strict_types=1);

use Spolszczony\Container;
use Spolszczony\Admin\AdminPage;
use Spolszczony\Admin\ProductMetaBox;
use Spolszczony\Admin\PostTypes;
use Spolszczony\Hook\AdminHooks;
use Spolszczony\Hook\ProductHooks;
use Spolszczony\Hook\CartHooks;
use Spolszczony\Hook\CheckoutHooks;
use Spolszczony\Hook\OrderHooks;
use Spolszczony\Hook\EmailHooks;
use Spolszczony\Hook\LoopHooks;
use Spolszczony\Integration\IntegrationManager;
use Spolszczony\Email\WithdrawalConfirmationEmail;
use Spolszczony\Rest\CheckboxController;
use Spolszczony\Rest\LegalPageController;
use Spolszczony\Rest\SettingsController;
use Spolszczony\Rest\WithdrawalController;
use Spolszczony\Service\ContractService;
use Spolszczony\Service\CatalogModeService;
use Spolszczony\Shortcode\ShortcodeManager;
use Spolszczony\Service\PriceDisplayService;
use Spolszczony\Service\OmnibusService;
use Spolszczony\Service\QuoteService;
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
use Spolszczony\Repository\QuoteRequestRepository;
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

    $c->singleton(QuoteRequestRepository::class, static function () {
        global $wpdb;
        return new QuoteRequestRepository($wpdb);
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
    $c->singleton(ContractService::class, static fn () => new ContractService());
    $c->singleton(CatalogModeService::class, static fn () => new CatalogModeService(
        $c->get(TemplateLoader::class),
        $c->get(QuoteService::class),
    ));
    $c->singleton(QuoteService::class, static fn () => new QuoteService(
        $c->get(QuoteRequestRepository::class),
        $c->get(ConsentLogRepository::class),
        $c->get(TemplateLoader::class),
    ));

    $c->singleton(WithdrawalService::class, static fn () => new WithdrawalService(
        $c->get(WithdrawalRepository::class),
        $c->get(EmailService::class),
    ));

    // Shopmarks.
    $c->singleton(ShopmarkManager::class, static fn () => new ShopmarkManager());

    // Integration manager.
    $c->singleton(IntegrationManager::class, static fn () => new IntegrationManager($c));

    // Store API / Block checkout.
    $c->singleton(\Spolszczony\Block\StoreApi\ProductDataExtension::class, static fn () => new \Spolszczony\Block\StoreApi\ProductDataExtension(
        $c->get(PriceDisplayService::class),
        $c->get(OmnibusService::class),
        $c->get(DeliveryTimeService::class),
        $c->get(ProductInfoService::class),
    ));
    $c->singleton(\Spolszczony\Block\StoreApi\CheckoutValidation::class, static fn () => new \Spolszczony\Block\StoreApi\CheckoutValidation(
        $c->get(CheckboxService::class),
        $c->get(ConsentLogRepository::class),
    ));

    // Compatibility.
    $c->singleton(\Spolszczony\Compatibility\ElementorCompat::class, static fn () => new \Spolszczony\Compatibility\ElementorCompat());
    $c->singleton(\Spolszczony\Compatibility\DynamicPricingCompat::class, static fn () => new \Spolszczony\Compatibility\DynamicPricingCompat());
    $c->singleton(\Spolszczony\Compatibility\ProductBundlesCompat::class, static fn () => new \Spolszczony\Compatibility\ProductBundlesCompat());
    $c->singleton(\Spolszczony\Compatibility\SubscriptionsCompat::class, static fn () => new \Spolszczony\Compatibility\SubscriptionsCompat());
    $c->singleton(\Spolszczony\Compatibility\CartFlowsCompat::class, static fn () => new \Spolszczony\Compatibility\CartFlowsCompat());
    $c->singleton(\Spolszczony\Compatibility\GoogleCompat::class, static fn () => new \Spolszczony\Compatibility\GoogleCompat());

    // Admin.
    $c->singleton(AdminPage::class, static fn () => new AdminPage());
    $c->singleton(ProductMetaBox::class, static fn () => new ProductMetaBox());
    $c->singleton(PostTypes::class, static fn () => new PostTypes());
    $c->singleton(\Spolszczony\Admin\AdminNotes::class, static fn () => new \Spolszczony\Admin\AdminNotes());
    $c->singleton(\Spolszczony\Admin\CSVImportExport::class, static fn () => new \Spolszczony\Admin\CSVImportExport());
    $c->singleton(\Spolszczony\Admin\ModulesPage::class, static fn () => new \Spolszczony\Admin\ModulesPage());
    $c->singleton(\Spolszczony\Admin\QuoteRequestsPage::class, static fn () => new \Spolszczony\Admin\QuoteRequestsPage(
        $c->get(QuoteService::class),
    ));

    // REST API.
    $c->singleton(SettingsController::class, static fn () => new SettingsController());
    $c->singleton(CheckboxController::class, static fn () => new CheckboxController());
    $c->singleton(WithdrawalController::class, static fn () => new WithdrawalController());
    $c->singleton(LegalPageController::class, static fn () => new LegalPageController());

    // Shortcodes.
    $c->singleton(ShortcodeManager::class, static fn () => new ShortcodeManager());

    // Hook subscribers.
    $c->singleton(AdminHooks::class, static fn () => new AdminHooks(
        $c->get(AdminPage::class),
    ));

    $c->singleton(ProductHooks::class, static fn () => new ProductHooks(
        $c->get(PriceDisplayService::class),
        $c->get(DeliveryTimeService::class),
        $c->get(ProductInfoService::class),
        $c->get(FoodService::class),
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
