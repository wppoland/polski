<?php

declare(strict_types=1);

defined('ABSPATH') || exit;
use Polski\Admin\AdminNotes;
use Polski\Admin\CSVImportExport;
use Polski\Admin\ModulesPage;
use Polski\Admin\PostTypes;
use Polski\Admin\ProductMetaBox;
use Polski\Compatibility\ElementorCompat;
use Polski\Hook\AdminHooks;
use Polski\Hook\AIFeedHooks;
use Polski\Hook\AIFeedLlmsTxtHooks;
use Polski\Hook\B2BCheckoutHooks;
use Polski\Hook\DSAProductReportHooks;
use Polski\Hook\ProductHooks;
use Polski\Hook\CartHooks;
use Polski\Hook\CheckoutHooks;
use Polski\Hook\OrderHooks;
use Polski\Hook\LoopHooks;
use Polski\Integration\IntegrationManager;
use Polski\Rest\CheckboxController;
use Polski\Rest\LegalPageController;
use Polski\Rest\PageComplianceController;
use Polski\Rest\SearchController;
use Polski\Rest\SettingsController;
use Polski\Rest\WithdrawalController;
use Polski\Admin\PageCompliancePage;
use Polski\CRA\IncidentService as CRAIncidentService;
use Polski\Admin\CRAIncidentsPage;
use Polski\Admin\SBOMPage;
use Polski\Service\WithdrawalService;
use Polski\Service\CheckboxService;
use Polski\Service\DoubleOptInService;
use Polski\Service\EmailService;
use Polski\Service\FilterService;
use Polski\Service\OmnibusService;
use Polski\Service\SearchService;
use Polski\Service\CompareService;
use Polski\Service\QuickViewService;
use Polski\Service\BadgeService;
use Polski\Service\TabManagerService;
use Polski\Service\FeaturedVideoService;
use Polski\Service\GalleryZoomService;
use Polski\Service\ProductSliderService;
use Polski\Service\WaitlistService;
use Polski\Service\MinimumOrderService;
use Polski\Service\ReviewRequestService;
use Polski\Service\AutoRestoreStockService;
use Polski\Service\AjaxAddToCartService;
use Polski\Service\CustomCheckoutFieldsService;
use Polski\Service\DataLayerService;
use Polski\Service\StockExportService;
use Polski\Service\ExpertReviewService;
use Polski\Service\SocialLoginService;
use Polski\Service\ProductAuthorService;
use Polski\Service\OrderExportService;
use Polski\Service\FaqService;
use Polski\Service\SocialProofService;
use Polski\Service\ProductQAService;
use Polski\Service\PriceHistoryChartService;
use Polski\Service\InfiniteScrollService;
use Polski\Service\PopupService;
use Polski\Service\TrustBadgeService;
use Polski\Service\LiveCartService;
use Polski\Service\WishlistService;
use Polski\Service\DisputeResolutionService;
use Polski\Service\BusinessInfoService;
use Polski\Service\ComplaintTemplateService;
use Polski\Service\CopyrightNoticeService;
use Polski\Service\RodoTrainingDocsService;
use Polski\Shortcode\ShortcodeManager;

/**
 * Hook subscriber classes to boot and register.
 *
 * Order matters: services are booted and hooks registered in this order.
 *
 * @return list<class-string<\Polski\Contract\HasHooks>>
 */
return [
    // Administration and Core Infrastructure.
    AdminHooks::class,
    ModulesPage::class,
    PostTypes::class,
    ProductMetaBox::class,
    AdminNotes::class,
    CSVImportExport::class,
    \Polski\Admin\DeactivationHandler::class,

    // Core services.
    CheckboxService::class,
    OmnibusService::class,
    FilterService::class,
    DoubleOptInService::class,
    \Polski\Service\OssObserverService::class,
    EmailService::class,
    DisputeResolutionService::class,
    SearchService::class,
    WishlistService::class,
    CompareService::class,
    QuickViewService::class,
    BadgeService::class,
    TabManagerService::class,
    FeaturedVideoService::class,
    GalleryZoomService::class,
    ProductSliderService::class,
    WaitlistService::class,
    MinimumOrderService::class,
    ReviewRequestService::class,
    AutoRestoreStockService::class,
    AjaxAddToCartService::class,
    CustomCheckoutFieldsService::class,
    DataLayerService::class,
    StockExportService::class,
    ExpertReviewService::class,
    SocialLoginService::class,
    ProductAuthorService::class,
    OrderExportService::class,
    FaqService::class,
    SocialProofService::class,
    ProductQAService::class,
    PriceHistoryChartService::class,
    InfiniteScrollService::class,
    PopupService::class,
    TrustBadgeService::class,
    LiveCartService::class,

    // New compliance & feature modules.
    \Polski\Service\GPSRService::class,
    \Polski\Service\VerifiedReviewService::class,
    \Polski\Service\DSAService::class,
    \Polski\Service\KSeFReadyService::class,
    \Polski\Service\SecurityIncidentService::class,
    \Polski\Service\SiteAuditService::class,
    \Polski\Service\CRAReadinessService::class,
    \Polski\Service\DPATrackerService::class,
    \Polski\Service\NipLookupService::class,
    ProductHooks::class,
    LoopHooks::class,
    CartHooks::class,
    CheckoutHooks::class,
    OrderHooks::class,

    // Withdrawal service (needs hooks for My Account).
    WithdrawalService::class,
    \Polski\Service\WithdrawalOrderStatusService::class,
    \Polski\Service\GuestWithdrawalService::class,
    \Polski\Service\AnnexGeneratorService::class,
    \Polski\Service\WithdrawalExemptionService::class,
    \Polski\Service\DigitalConsentService::class,
    \Polski\Admin\WithdrawalsAdminPage::class,
    \Polski\Admin\WithdrawalSettingsPage::class,
    \Polski\Service\WithdrawalBlocksService::class,
    \Polski\Service\WithdrawalAssetsService::class,
    \Polski\Service\WithdrawalErrorTelemetry::class,
    \Polski\Rest\GuestWithdrawalController::class,
    \Polski\Service\MyAccountWithdrawalsService::class,
    \Polski\Service\AbilitiesService::class,

    // Shortcodes.
    ShortcodeManager::class,

    // REST API.
    SettingsController::class,
    CheckboxController::class,
    WithdrawalController::class,
    LegalPageController::class,
    SearchController::class,
    PageComplianceController::class,

    // Page compliance checker admin.
    PageCompliancePage::class,

    // CRA incident reporting.
    CRAIncidentService::class,
    CRAIncidentsPage::class,

    // SBOM generator.
    SBOMPage::class,

    // Business identification footer / block / shortcode.
    BusinessInfoService::class,

    // Complaint template generator.
    ComplaintTemplateService::class,

    // Copyright / license notice helpers.
    CopyrightNoticeService::class,

    // RODO training documentation generator.
    RodoTrainingDocsService::class,

    // AI Feed: Markdown content negotiation for AI agents.
    AIFeedHooks::class,
    AIFeedLlmsTxtHooks::class,

    // B2B checkout fields (company toggle, NIP / REGON / IBAN).
    B2BCheckoutHooks::class,

    // Per-product DSA report widget.
    DSAProductReportHooks::class,
];
