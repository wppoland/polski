<?php

declare(strict_types=1);

defined('ABSPATH') || exit;
use Polski\AIFeed\LlmsTxtService;
use Polski\AIFeed\MarkdownConverter;
use Polski\AIFeed\PostMarkdownBuilder;
use Polski\AIFeed\ProductMarkdownBuilder;
use Polski\AIFeed\RequestNegotiator;
use Polski\Container;
use Polski\Admin\AdminPage;
use Polski\Admin\ProductMetaBox;
use Polski\Admin\PostTypes;
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
use Polski\Email\WithdrawalConfirmationEmail;
use Polski\Rest\SearchController;
use Polski\Rest\CheckboxController;
use Polski\Rest\LegalPageController;
use Polski\Rest\PageComplianceController;
use Polski\PageCompliance\PageComplianceService;
use Polski\Admin\PageCompliancePage;
use Polski\CRA\IncidentService as CRAIncidentService;
use Polski\CRA\Repository\IncidentRepository as CRAIncidentRepository;
use Polski\Admin\CRAIncidentsPage;
use Polski\SBOM\SBOMGenerator;
use Polski\Admin\SBOMPage;
use Polski\Rest\SettingsController;
use Polski\Rest\WithdrawalController;
use Polski\Service\FilterService;
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
use Polski\Service\BusinessInfoService;
use Polski\Service\ComplaintTemplateService;
use Polski\Service\CopyrightNoticeService;
use Polski\Service\RodoTrainingDocsService;
use Polski\Shortcode\ShortcodeManager;
use Polski\Service\B2BCheckoutService;
use Polski\Service\DSAProductReportService;
use Polski\Service\PriceDisplayService;
use Polski\Service\OmnibusService;
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
use Polski\Service\SecurityIncidentService;
use Polski\Repository\OmnibusPriceRepository;
use Polski\Repository\CompareRepository;
use Polski\Repository\WishlistRepository;
use Polski\Repository\WaitlistRepository;
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

    $c->singleton(\Polski\Repository\WithdrawalItemsRepository::class, static function () {
        global $wpdb;
        return new \Polski\Repository\WithdrawalItemsRepository($wpdb);
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
    $c->singleton(\Polski\Email\WithdrawalEmailCta::class, static fn () => new \Polski\Email\WithdrawalEmailCta(
        $c->get(\Polski\Service\WithdrawalService::class),
    ));
    $c->singleton(DisputeResolutionService::class, static fn () => new DisputeResolutionService());
    $c->singleton(ProductInfoService::class, static fn () => new ProductInfoService());
    $c->singleton(FoodService::class, static fn () => new FoodService());
    $c->singleton(DoubleOptInService::class, static fn () => new DoubleOptInService());
    $c->singleton(\Polski\Service\OssObserverService::class, static fn () => new \Polski\Service\OssObserverService());
    $c->singleton(ComplianceCheckService::class, static fn () => new ComplianceCheckService());
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
    $c->singleton(MinimumOrderService::class, static fn () => new MinimumOrderService());
    $c->singleton(ReviewRequestService::class, static fn () => new ReviewRequestService());
    $c->singleton(AutoRestoreStockService::class, static fn () => new AutoRestoreStockService());
    $c->singleton(AjaxAddToCartService::class, static fn () => new AjaxAddToCartService());
    $c->singleton(CustomCheckoutFieldsService::class, static fn () => new CustomCheckoutFieldsService());
    $c->singleton(DataLayerService::class, static fn () => new DataLayerService());
    $c->singleton(StockExportService::class, static fn () => new StockExportService());
    $c->singleton(ExpertReviewService::class, static fn () => new ExpertReviewService());
    $c->singleton(SocialLoginService::class, static fn () => new SocialLoginService());
    $c->singleton(ProductAuthorService::class, static fn () => new ProductAuthorService());
    $c->singleton(OrderExportService::class, static fn () => new OrderExportService());
    $c->singleton(FaqService::class, static fn () => new FaqService());
    $c->singleton(SocialProofService::class, static fn () => new SocialProofService());
    $c->singleton(ProductQAService::class, static fn () => new ProductQAService());
    $c->singleton(PriceHistoryChartService::class, static fn () => new PriceHistoryChartService(
        $c->get(OmnibusPriceRepository::class),
    ));
    $c->singleton(WaitlistService::class, static fn () => new WaitlistService(
        $c->get(WaitlistRepository::class),
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(InfiniteScrollService::class, static fn () => new InfiniteScrollService(
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(PopupService::class, static fn () => new PopupService(
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(TrustBadgeService::class, static fn () => new TrustBadgeService());
    $c->singleton(LiveCartService::class, static fn () => new LiveCartService());
    $c->singleton(SearchService::class, static fn () => new SearchService(
        $c->get(TemplateLoader::class),
    ));

    $c->singleton(WithdrawalService::class, static fn () => new WithdrawalService(
        $c->get(WithdrawalRepository::class),
        $c->get(TemplateLoader::class),
        $c->get(\Polski\Repository\WithdrawalItemsRepository::class),
    ));
    $c->singleton(\Polski\Service\WithdrawalOrderStatusService::class, static fn () => new \Polski\Service\WithdrawalOrderStatusService());
    $c->singleton(\Polski\Service\GuestWithdrawalService::class, static fn () => new \Polski\Service\GuestWithdrawalService(
        $c->get(WithdrawalService::class),
        $c->get(WithdrawalRepository::class),
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(\Polski\Service\AnnexGeneratorService::class, static fn () => new \Polski\Service\AnnexGeneratorService());
    $c->singleton(\Polski\Service\WithdrawalExemptionService::class, static fn () => new \Polski\Service\WithdrawalExemptionService());
    $c->singleton(\Polski\Service\DigitalConsentService::class, static fn () => new \Polski\Service\DigitalConsentService());
    $c->singleton(\Polski\Admin\WithdrawalsAdminPage::class, static fn () => new \Polski\Admin\WithdrawalsAdminPage(
        $c->get(WithdrawalRepository::class),
        $c->get(WithdrawalService::class),
    ));
    $c->singleton(\Polski\Admin\WithdrawalSettingsPage::class, static fn () => new \Polski\Admin\WithdrawalSettingsPage());
    $c->singleton(\Polski\Service\WithdrawalBlocksService::class, static fn () => new \Polski\Service\WithdrawalBlocksService());
    $c->singleton(\Polski\Service\WithdrawalAssetsService::class, static fn () => new \Polski\Service\WithdrawalAssetsService());
    $c->singleton(\Polski\Service\WithdrawalErrorTelemetry::class, static fn () => new \Polski\Service\WithdrawalErrorTelemetry());
    $c->singleton(\Polski\Rest\GuestWithdrawalController::class, static fn () => new \Polski\Rest\GuestWithdrawalController(
        $c->get(\Polski\Service\GuestWithdrawalService::class),
        $c->get(WithdrawalRepository::class),
    ));
    $c->singleton(\Polski\Service\WithdrawalSiteHealthService::class, static fn () => new \Polski\Service\WithdrawalSiteHealthService());
    $c->singleton(\Polski\Admin\WithdrawalOrderMetaBox::class, static fn () => new \Polski\Admin\WithdrawalOrderMetaBox(
        $c->get(WithdrawalRepository::class),
        $c->get(WithdrawalService::class),
    ));
    $c->singleton(\Polski\Service\WithdrawalQueryHelper::class, static fn () => new \Polski\Service\WithdrawalQueryHelper(
        $c->get(WithdrawalRepository::class),
    ));
    $c->singleton(\Polski\Service\WithdrawalAnalyticsService::class, static fn () => new \Polski\Service\WithdrawalAnalyticsService());
    $c->singleton(\Polski\Privacy\WithdrawalPrivacyService::class, static fn () => new \Polski\Privacy\WithdrawalPrivacyService(
        $c->get(WithdrawalRepository::class),
        $c->get(ConsentLogRepository::class),
    ));
    $c->singleton(\Polski\AI\WithdrawalReasonClassifier::class, static fn () => new \Polski\AI\WithdrawalReasonClassifier(
        $c->get(WithdrawalRepository::class),
    ));
    $c->singleton(\Polski\AI\AnnexTranslator::class, static fn () => new \Polski\AI\AnnexTranslator());
    $c->singleton(\Polski\Service\MyAccountWithdrawalsService::class, static fn () => new \Polski\Service\MyAccountWithdrawalsService(
        $c->get(WithdrawalRepository::class),
    ));
    $c->singleton(\Polski\Service\AbilitiesService::class, static fn () => new \Polski\Service\AbilitiesService(
        $c->get(WithdrawalService::class),
        $c->get(\Polski\Service\WithdrawalExemptionService::class),
        $c->get(\Polski\Service\AnnexGeneratorService::class),
        $c->get(WithdrawalRepository::class),
        $c->get(\Polski\Service\LegalPageService::class),
        $c->get(\Polski\PageCompliance\PageComplianceService::class),
    ));

    // New modules: GPSR, Verified Review, DSA, KSeF-ready.
    $c->singleton(\Polski\Service\GPSRService::class, static fn () => new \Polski\Service\GPSRService(
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(\Polski\Service\VerifiedReviewService::class, static fn () => new \Polski\Service\VerifiedReviewService());
    $c->singleton(\Polski\Service\DSAService::class, static fn () => new \Polski\Service\DSAService(
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(\Polski\Service\KSeFReadyService::class, static fn () => new \Polski\Service\KSeFReadyService());
    $c->singleton(SecurityIncidentService::class, static fn () => new SecurityIncidentService());
    $c->singleton(\Polski\Service\SiteAuditService::class, static fn () => new \Polski\Service\SiteAuditService());
    $c->singleton(\Polski\Service\StoreHealthMonitorService::class, static fn () => new \Polski\Service\StoreHealthMonitorService());
    $c->singleton(\Polski\Service\CRAReadinessService::class, static fn () => new \Polski\Service\CRAReadinessService());
    $c->singleton(\Polski\Service\DPATrackerService::class, static fn () => new \Polski\Service\DPATrackerService());
    $c->singleton(\Polski\Service\NipLookupService::class, static fn () => new \Polski\Service\NipLookupService());

    // Shopmarks.
    $c->singleton(ShopmarkManager::class, static fn () => new ShopmarkManager());

    // Integration manager.
    $c->singleton(IntegrationManager::class, static fn () => new IntegrationManager());

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
    $c->singleton(\Polski\Block\ModuleBlocks::class, static fn () => new \Polski\Block\ModuleBlocks(
        $c->get(SearchService::class),
        $c->get(FilterService::class),
        $c->get(ProductSliderService::class),
    ));

    // Compatibility.
    $c->singleton(\Polski\Compatibility\ElementorCompat::class, static fn () => new \Polski\Compatibility\ElementorCompat());
    $c->singleton(\Polski\Compatibility\DynamicPricingCompat::class, static fn () => new \Polski\Compatibility\DynamicPricingCompat());
    $c->singleton(\Polski\Compatibility\CartFlowsCompat::class, static fn () => new \Polski\Compatibility\CartFlowsCompat());
    $c->singleton(\Polski\Compatibility\GoogleCompat::class, static fn () => new \Polski\Compatibility\GoogleCompat());

    // Admin.
    $c->singleton(AdminPage::class, static fn () => new AdminPage());
    $c->singleton(ProductMetaBox::class, static fn () => new ProductMetaBox());
    $c->singleton(PostTypes::class, static fn () => new PostTypes());
    $c->singleton(\Polski\Admin\AdminNotes::class, static fn () => new \Polski\Admin\AdminNotes());
    $c->singleton(\Polski\Admin\CSVImportExport::class, static fn () => new \Polski\Admin\CSVImportExport());
    $c->singleton(\Polski\Admin\ModulesPage::class, static fn () => new \Polski\Admin\ModulesPage());
    $c->singleton(\Polski\Admin\DeactivationHandler::class, static fn () => new \Polski\Admin\DeactivationHandler());

    // REST API.
    $c->singleton(SettingsController::class, static fn () => new SettingsController());
    $c->singleton(CheckboxController::class, static fn () => new CheckboxController());
    $c->singleton(WithdrawalController::class, static fn () => new WithdrawalController());
    $c->singleton(LegalPageController::class, static fn () => new LegalPageController());

    // Page compliance (Privacy Policy / Regulamin checker).
    $c->singleton(PageComplianceService::class, static fn () => new PageComplianceService());
    $c->singleton(PageComplianceController::class, static fn () => new PageComplianceController(
        $c->get(PageComplianceService::class),
    ));
    $c->singleton(PageCompliancePage::class, static fn () => new PageCompliancePage(
        $c->get(PageComplianceService::class),
    ));

    // CRA incident reporting.
    $c->singleton(CRAIncidentRepository::class, static function () {
        global $wpdb;
        return new CRAIncidentRepository($wpdb);
    });
    $c->singleton(CRAIncidentService::class, static fn () => new CRAIncidentService(
        $c->get(CRAIncidentRepository::class),
    ));
    $c->singleton(CRAIncidentsPage::class, static fn () => new CRAIncidentsPage(
        $c->get(CRAIncidentService::class),
        $c->get(CRAIncidentRepository::class),
    ));

    // SBOM generator.
    $c->singleton(SBOMGenerator::class, static fn () => new SBOMGenerator());
    $c->singleton(SBOMPage::class, static fn () => new SBOMPage(
        $c->get(SBOMGenerator::class),
    ));

    // Business identification footer/block/shortcode.
    $c->singleton(BusinessInfoService::class, static fn () => new BusinessInfoService());

    // Complaint template generator.
    $c->singleton(ComplaintTemplateService::class, static fn () => new ComplaintTemplateService());

    // Copyright / license notice helpers.
    $c->singleton(CopyrightNoticeService::class, static fn () => new CopyrightNoticeService());

    // RODO training documentation generator.
    $c->singleton(RodoTrainingDocsService::class, static fn () => new RodoTrainingDocsService());
    $c->singleton(SearchController::class, static fn () => new SearchController(
        $c->get(SearchService::class),
    ));

    // Shortcodes.
    $c->singleton(ShortcodeManager::class, static fn () => new ShortcodeManager());

    // Hook subscribers.
    $c->singleton(AdminHooks::class, static fn () => new AdminHooks(
        $c->get(AdminPage::class),
    ));

    $c->singleton(\Polski\Admin\BrandAssets::class, static fn () => new \Polski\Admin\BrandAssets());

    $c->singleton(\Polski\Hook\StructuredDataHooks::class, static fn () => new \Polski\Hook\StructuredDataHooks(
        $c->get(OmnibusService::class),
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

    $c->singleton(CartHooks::class, static fn () => new CartHooks());

    $c->singleton(OrderHooks::class, static fn () => new OrderHooks());

    // AI Feed: Markdown content negotiation for AI agents.
    $c->singleton(MarkdownConverter::class, static fn () => new MarkdownConverter());
    $c->singleton(RequestNegotiator::class, static fn () => new RequestNegotiator());
    $c->singleton(PostMarkdownBuilder::class, static fn () => new PostMarkdownBuilder(
        $c->get(MarkdownConverter::class),
    ));
    $c->singleton(ProductMarkdownBuilder::class, static fn () => new ProductMarkdownBuilder(
        $c->get(MarkdownConverter::class),
        $c->get(PostMarkdownBuilder::class),
        $c->get(OmnibusService::class),
        $c->get(DeliveryTimeService::class),
        $c->get(ProductInfoService::class),
    ));
    $c->singleton(AIFeedHooks::class, static fn () => new AIFeedHooks(
        $c->get(RequestNegotiator::class),
        $c->get(PostMarkdownBuilder::class),
        $c->get(ProductMarkdownBuilder::class),
    ));

    $c->singleton(LlmsTxtService::class, static fn () => new LlmsTxtService());
    $c->singleton(AIFeedLlmsTxtHooks::class, static fn () => new AIFeedLlmsTxtHooks(
        $c->get(LlmsTxtService::class),
    ));

    // B2B checkout: company toggle, NIP / REGON / IBAN fields.
    $c->singleton(B2BCheckoutService::class, static fn () => new B2BCheckoutService());
    $c->singleton(B2BCheckoutHooks::class, static fn () => new B2BCheckoutHooks(
        $c->get(B2BCheckoutService::class),
    ));

    // Per-product DSA report widget.
    $c->singleton(DSAProductReportService::class, static fn () => new DSAProductReportService(
        $c->get(\Polski\Service\DSAService::class),
        $c->get(TemplateLoader::class),
    ));
    $c->singleton(DSAProductReportHooks::class, static fn () => new DSAProductReportHooks(
        $c->get(DSAProductReportService::class),
    ));

};
