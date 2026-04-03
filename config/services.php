<?php

declare(strict_types=1);

use Polski\Container;
use Polski\Admin\AdminPage;
use Polski\Admin\ProductMetaBox;
use Polski\Admin\PostTypes;
use Polski\Hook\AdminHooks;
use Polski\Hook\ProductHooks;
use Polski\Hook\CartHooks;
use Polski\Hook\CheckoutHooks;
use Polski\Hook\OrderHooks;
use Polski\Hook\EmailHooks;
use Polski\Hook\LoopHooks;
use Polski\Integration\IntegrationManager;
use Polski\Email\WithdrawalConfirmationEmail;
use Polski\Rest\SearchController;
use Polski\Rest\CheckboxController;
use Polski\Rest\LegalPageController;
use Polski\Rest\SettingsController;
use Polski\Rest\WithdrawalController;
use Polski\Service\ContractService;
use Polski\Service\CatalogModeService;
use Polski\Service\FilterService;
use Polski\Service\SearchService;
use Polski\Service\CompareService;
use Polski\Service\QuickViewService;
use Polski\Service\FrequentlyBoughtTogetherService;
use Polski\Service\BadgeService;
use Polski\Service\TabManagerService;
use Polski\Service\FeaturedVideoService;
use Polski\Service\GalleryZoomService;
use Polski\Service\ProductSliderService;
use Polski\Service\PreOrderService;
use Polski\Service\WaitlistService;
use Polski\Service\AddOnsService;
use Polski\Service\ProductBundlesService;
use Polski\Service\GiftCardService;
use Polski\Service\SubscriptionService;
use Polski\Service\InfiniteScrollService;
use Polski\Service\PopupService;
use Polski\Service\AffiliateService;
use Polski\Service\WishlistService;
use Polski\Shortcode\ShortcodeManager;
use Polski\Service\PriceDisplayService;
use Polski\Service\OmnibusService;
use Polski\Service\QuoteService;
use Polski\Service\TaxDisplayService;
use Polski\Service\DeliveryTimeService;
use Polski\Service\CheckboxService;
use Polski\Service\WithdrawalService;
use Polski\Service\LegalPageService;
use Polski\Service\DoubleOptInService;
use Polski\Service\ProductInfoService;
use Polski\Service\FoodService;
use Polski\Service\DisputeResolutionService;
use Polski\Service\EmailService;
use Polski\Service\ComplianceCheckService;
use Polski\Repository\OmnibusPriceRepository;
use Polski\Repository\QuoteRequestRepository;
use Polski\Repository\CompareRepository;
use Polski\Repository\WishlistRepository;
use Polski\Repository\WaitlistRepository;
use Polski\Repository\GiftCardRepository;
use Polski\Repository\SubscriptionRepository;
use Polski\Repository\AffiliateRepository;
use Polski\Repository\ConsentLogRepository;
use Polski\Repository\WithdrawalRepository;
use Polski\Shopmark\ShopmarkManager;
use Polski\Util\TemplateLoader;

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

    $c->singleton(WishlistRepository::class, static function () {
        global $wpdb;
        return new WishlistRepository($wpdb);
    });

    $c->singleton(WaitlistRepository::class, static function () {
        global $wpdb;
        return new WaitlistRepository($wpdb);
    });

    $c->singleton(CompareRepository::class, static function () {
        global $wpdb;
        return new CompareRepository($wpdb);
    });

    $c->singleton(GiftCardRepository::class, static function () {
        global $wpdb;
        return new GiftCardRepository($wpdb);
    });

    $c->singleton(SubscriptionRepository::class, static function () {
        global $wpdb;
        return new SubscriptionRepository($wpdb);
    });

    $c->singleton(AffiliateRepository::class, static function () {
        global $wpdb;
        return new AffiliateRepository($wpdb);
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
    $c->singleton(FilterService::class, static fn () => new FilterService(
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(WishlistService::class, static fn () => new WishlistService(
        $c->get(WishlistRepository::class),
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(CompareService::class, static fn () => new CompareService(
        $c->get(CompareRepository::class),
        $c->get(TemplateLoader::class),
        $c->get(PriceDisplayService::class),
        $c->get(DeliveryTimeService::class),
        $c->get(ProductInfoService::class),
    ));
    $c->singleton(QuickViewService::class, static fn () => new QuickViewService(
        $c->get(TemplateLoader::class),
        $c->get(PriceDisplayService::class),
        $c->get(DeliveryTimeService::class),
        $c->get(ProductInfoService::class),
    ));
    $c->singleton(FrequentlyBoughtTogetherService::class, static fn () => new FrequentlyBoughtTogetherService(
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(BadgeService::class, static fn () => new BadgeService(
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(TabManagerService::class, static fn () => new TabManagerService());
    $c->singleton(FeaturedVideoService::class, static fn () => new FeaturedVideoService(
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(GalleryZoomService::class, static fn () => new GalleryZoomService(
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(ProductSliderService::class, static fn () => new ProductSliderService(
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(PreOrderService::class, static fn () => new PreOrderService(
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(WaitlistService::class, static fn () => new WaitlistService(
        $c->get(WaitlistRepository::class),
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(AddOnsService::class, static fn () => new AddOnsService(
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(ProductBundlesService::class, static fn () => new ProductBundlesService(
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(GiftCardService::class, static fn () => new GiftCardService(
        $c->get(GiftCardRepository::class),
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(SubscriptionService::class, static fn () => new SubscriptionService(
        $c->get(SubscriptionRepository::class),
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(InfiniteScrollService::class, static fn () => new InfiniteScrollService(
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(PopupService::class, static fn () => new PopupService(
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(AffiliateService::class, static fn () => new AffiliateService(
        $c->get(AffiliateRepository::class),
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(SearchService::class, static fn () => new SearchService(
        $c->get(TemplateLoader::class),
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
    $c->singleton(\Polski\Block\StoreApi\ProductDataExtension::class, static fn () => new \Polski\Block\StoreApi\ProductDataExtension(
        $c->get(PriceDisplayService::class),
        $c->get(OmnibusService::class),
        $c->get(DeliveryTimeService::class),
        $c->get(ProductInfoService::class),
    ));
    $c->singleton(\Polski\Block\StoreApi\CheckoutValidation::class, static fn () => new \Polski\Block\StoreApi\CheckoutValidation(
        $c->get(CheckboxService::class),
        $c->get(ConsentLogRepository::class),
    ));

    // Compatibility.
    $c->singleton(\Polski\Compatibility\ElementorCompat::class, static fn () => new \Polski\Compatibility\ElementorCompat());
    $c->singleton(\Polski\Compatibility\DynamicPricingCompat::class, static fn () => new \Polski\Compatibility\DynamicPricingCompat());
    $c->singleton(\Polski\Compatibility\ProductBundlesCompat::class, static fn () => new \Polski\Compatibility\ProductBundlesCompat());
    $c->singleton(\Polski\Compatibility\SubscriptionsCompat::class, static fn () => new \Polski\Compatibility\SubscriptionsCompat());
    $c->singleton(\Polski\Compatibility\CartFlowsCompat::class, static fn () => new \Polski\Compatibility\CartFlowsCompat());
    $c->singleton(\Polski\Compatibility\GoogleCompat::class, static fn () => new \Polski\Compatibility\GoogleCompat());

    // Admin.
    $c->singleton(AdminPage::class, static fn () => new AdminPage());
    $c->singleton(ProductMetaBox::class, static fn () => new ProductMetaBox());
    $c->singleton(PostTypes::class, static fn () => new PostTypes());
    $c->singleton(\Polski\Admin\AdminNotes::class, static fn () => new \Polski\Admin\AdminNotes());
    $c->singleton(\Polski\Admin\CSVImportExport::class, static fn () => new \Polski\Admin\CSVImportExport());
    $c->singleton(\Polski\Admin\ModulesPage::class, static fn () => new \Polski\Admin\ModulesPage());
    $c->singleton(\Polski\Admin\QuoteRequestsPage::class, static fn () => new \Polski\Admin\QuoteRequestsPage(
        $c->get(QuoteService::class),
    ));

    // REST API.
    $c->singleton(SettingsController::class, static fn () => new SettingsController());
    $c->singleton(CheckboxController::class, static fn () => new CheckboxController());
    $c->singleton(WithdrawalController::class, static fn () => new WithdrawalController());
    $c->singleton(LegalPageController::class, static fn () => new LegalPageController());
    $c->singleton(SearchController::class, static fn () => new SearchController(
        $c->get(SearchService::class),
    ));

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
        $c->get(TemplateLoader::class),
    ));

    $c->singleton(LoopHooks::class, static fn () => new LoopHooks(
        $c->get(PriceDisplayService::class),
        $c->get(ProductInfoService::class),
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
