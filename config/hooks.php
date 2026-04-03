<?php

declare(strict_types=1);

use Polski\Admin\AdminNotes;
use Polski\Admin\CSVImportExport;
use Polski\Admin\ModulesPage;
use Polski\Admin\PostTypes;
use Polski\Admin\ProductMetaBox;
use Polski\Compatibility\ElementorCompat;
use Polski\Hook\AdminHooks;
use Polski\Hook\ProductHooks;
use Polski\Hook\CartHooks;
use Polski\Hook\CheckoutHooks;
use Polski\Hook\OrderHooks;
use Polski\Hook\EmailHooks;
use Polski\Hook\LoopHooks;
use Polski\Integration\IntegrationManager;
use Polski\Rest\CheckboxController;
use Polski\Rest\LegalPageController;
use Polski\Rest\SearchController;
use Polski\Rest\SettingsController;
use Polski\Rest\WithdrawalController;
use Polski\Service\QuoteService;
use Polski\Service\WithdrawalService;
use Polski\Service\CheckboxService;
use Polski\Service\CatalogModeService;
use Polski\Service\ContractService;
use Polski\Service\DoubleOptInService;
use Polski\Service\EmailService;
use Polski\Service\FilterService;
use Polski\Service\OmnibusService;
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
use Polski\Service\DisputeResolutionService;
use Polski\Shortcode\ShortcodeManager;

/**
 * Hook subscriber classes to boot and register.
 *
 * Order matters: services are booted and hooks registered in this order.
 *
 * @return list<class-string<\Polski\Contract\HasHooks>>
 */
return [
    // Core services.
    CheckboxService::class,
    OmnibusService::class,
    ContractService::class,
    CatalogModeService::class,
    FilterService::class,
    DoubleOptInService::class,
    EmailService::class,
    DisputeResolutionService::class,
    SearchService::class,
    WishlistService::class,
    CompareService::class,
    QuickViewService::class,
    FrequentlyBoughtTogetherService::class,
    BadgeService::class,
    TabManagerService::class,
    FeaturedVideoService::class,
    GalleryZoomService::class,
    ProductSliderService::class,
    PreOrderService::class,
    WaitlistService::class,
    AddOnsService::class,
    ProductBundlesService::class,
    GiftCardService::class,
    SubscriptionService::class,
    InfiniteScrollService::class,
    PopupService::class,
    AffiliateService::class,
    QuoteService::class,

    // Store API / Block checkout.
    \Polski\Block\StoreApi\ProductDataExtension::class,
    \Polski\Block\StoreApi\CheckoutValidation::class,

    // Integrations and compatibility.
    IntegrationManager::class,
    ElementorCompat::class,
    \Polski\Compatibility\DynamicPricingCompat::class,
    \Polski\Compatibility\ProductBundlesCompat::class,
    \Polski\Compatibility\SubscriptionsCompat::class,
    \Polski\Compatibility\CartFlowsCompat::class,
    \Polski\Compatibility\GoogleCompat::class,

    // Admin (taxonomies + meta boxes + utilities).
    PostTypes::class,
    ProductMetaBox::class,
    AdminNotes::class,
    CSVImportExport::class,
    ModulesPage::class,
    \Polski\Admin\QuoteRequestsPage::class,

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

    // Shortcodes.
    ShortcodeManager::class,

    // REST API.
    SettingsController::class,
    CheckboxController::class,
    WithdrawalController::class,
    LegalPageController::class,
    SearchController::class,
];
