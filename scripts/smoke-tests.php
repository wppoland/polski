<?php

use Polski\Plugin;
use Polski\Repository\WaitlistRepository;
use Polski\Rest\SettingsController;
use Polski\Service\GPSRService;
use Polski\Service\SecurityIncidentService;
use Polski\Service\SiteAuditService;
use Polski\Service\VerifiedReviewService;

if (! defined('ABSPATH')) {
    exit(1);
}

if (! class_exists(Plugin::class)) {
    require_once dirname(__DIR__) . '/polski.php';
}

Plugin::instance()->boot();

$failures = [];
$results = [];

$assert = static function (bool $condition, string $label, array &$results, array &$failures): void {
    $results[] = ($condition ? 'PASS' : 'FAIL') . ' - ' . $label;

    if (! $condition) {
        $failures[] = $label;
    }
};

$ensureProduct = static function (string $slug, string $title, float $price): int {
    $existing = get_page_by_path($slug, OBJECT, 'product');

    if ($existing instanceof WP_Post) {
        $id = (int) $existing->ID;
        wp_update_post([
            'ID' => $id,
            'post_title' => $title,
            'post_status' => 'publish',
        ]);
    } else {
        $id = wp_insert_post([
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => $title,
            'post_name' => $slug,
        ]);
    }

    wp_set_object_terms($id, 'simple', 'product_type');
    $product = wc_get_product($id);

    if ($product instanceof WC_Product) {
        $product->set_regular_price((string) $price);
        $product->set_price((string) $price);
        $product->save();
    }

    return $id;
};

/** @var SettingsController $settingsController */
$settingsController = Plugin::instance()->container()->get(SettingsController::class);
$wizardRequest = new WP_REST_Request('POST', '/polski/v1/wizard/complete');
$wizardRequest->set_header('content-type', 'application/json');
$wizardRequest->set_body(wp_json_encode([
    'company_name' => 'Smoke Test Sp. z o.o.',
    'company_address' => 'ul. Testowa 1, 00-001 Warszawa',
    'company_nip' => '5252445763',
    'company_email' => 'smoke@example.com',
    'company_phone' => '500600700',
    'terms_enabled' => true,
    'privacy_enabled' => true,
    'withdrawal_enabled' => true,
    'digital_waiver_enabled' => false,
    'marketing_enabled' => false,
    'order_button_text' => 'Zamawiam z obowiązkiem zapłaty',
    'generate_legal_pages' => false,
    'omnibus_enabled' => true,
]) ?: '{}');
$wizardResponse = $settingsController->completeWizard($wizardRequest);
$wizardData = $wizardResponse->get_data();
$generalSettings = get_option('polski_general', []);
$moduleSettings = get_option('polski_modules', []);

$assert($wizardResponse->get_status() === 200 && is_array($wizardData) && ($wizardData['success'] ?? false) === true, 'Wizard completion request succeeds', $results, $failures);
$assert((bool) get_option('polski_wizard_complete', false), 'Wizard completion flag is saved', $results, $failures);
$assert(is_array($generalSettings) && ($generalSettings['company_name'] ?? '') === 'Smoke Test Sp. z o.o.', 'Wizard saves general company data', $results, $failures);
$assert(is_array($moduleSettings) && ($moduleSettings['checkout_button'] ?? false) === true && ($moduleSettings['omnibus'] ?? false) === true, 'Wizard saves baseline module states', $results, $failures);

update_option('polski_modules', [
    'checkout_button' => true,
    'omnibus' => true,
    'withdrawal' => true,
    'waitlist' => true,
    'gpsr' => true,
    'dsa_toolkit' => true,
    'verified_review' => true,
    'security_incidents' => true,
]);

update_option('polski_waitlist', [
    'show_on_single' => true,
    'allow_guests' => true,
]);

$gpsrProductId = $ensureProduct('produkt-gpsr', 'Produkt GPSR', 123.0);
update_post_meta($gpsrProductId, '_polski_gpsr_manufacturer_name', 'Smoke Manufacturer');
update_post_meta($gpsrProductId, '_polski_gpsr_manufacturer_address', 'ul. Testowa 2, 00-002 Warszawa');
update_post_meta($gpsrProductId, '_polski_gpsr_product_identifier', 'LOT-2026-001');
update_post_meta($gpsrProductId, '_polski_gpsr_safety_warnings', 'Trzymaj z dala od dzieci.');
update_post_meta($gpsrProductId, '_polski_gpsr_instructions', 'Używaj zgodnie z instrukcją.');

$waitlistProductId = $ensureProduct('brakujacy-produkt', 'Brakujący Produkt', 80.0);
$waitlistProduct = wc_get_product($waitlistProductId);

if ($waitlistProduct instanceof WC_Product) {
    $waitlistProduct->set_manage_stock(true);
    $waitlistProduct->set_stock_quantity(0);
    $waitlistProduct->set_stock_status('outofstock');
    $waitlistProduct->save();
}

$container = Plugin::instance()->container();
/** @var GPSRService $gpsrService */
$gpsrService = $container->get(GPSRService::class);
/** @var WaitlistRepository $waitlistRepository */
$waitlistRepository = $container->get(WaitlistRepository::class);
/** @var SecurityIncidentService $securityIncidentService */
$securityIncidentService = $container->get(SecurityIncidentService::class);
/** @var SiteAuditService $siteAuditService */
$siteAuditService = $container->get(SiteAuditService::class);
/** @var VerifiedReviewService $verifiedReviewService */
$verifiedReviewService = $container->get(VerifiedReviewService::class);
$shortcodes = $container->get(\Polski\Shortcode\ShortcodeManager::class);

$gpsrData = $gpsrService->getGPSRData(wc_get_product($gpsrProductId));
$assert(($gpsrData['manufacturer_name'] ?? '') === 'Smoke Manufacturer', 'GPSR data is readable from product meta', $results, $failures);
$assert($shortcodes->gpsrInfo(['product' => (string) $gpsrProductId]) !== '', 'GPSR shortcode renders for explicit product attribute', $results, $failures);

$waitlistRepository->subscribe($waitlistProductId, 'waitlist@example.com', null);
$pending = $waitlistRepository->findPendingByProduct($waitlistProductId);
$assert(count($pending) === 1, 'Waitlist stores subscription for out-of-stock product', $results, $failures);

$dsaForm = $shortcodes->dsaReport();
$assert($dsaForm !== '', 'DSA shortcode renders a report form when the module is enabled', $results, $failures);

$incidentId = $securityIncidentService->createIncident([
    'reported_at' => '2026-04-04T09:30',
    'type' => 'vulnerability',
    'severity' => 'high',
    'title' => 'Smoke test security incident',
    'affected_area' => 'Checkout',
    'notes' => 'Synthetic record created by smoke tests.',
]);
$assert($incidentId !== '', 'Security incident service stores a new incident', $results, $failures);
$assert($securityIncidentService->countOpenIncidents() >= 1, 'Security incident service counts open incidents', $results, $failures);

$guestOrder = wc_create_order([
    'customer_id' => 0,
]);

if ($guestOrder instanceof WC_Order) {
    $guestOrder->set_billing_email('guest-review@example.com');
    $guestOrder->add_product(wc_get_product($gpsrProductId), 1);
    $guestOrder->calculate_totals(false);
    $guestOrder->payment_complete();
    $guestOrder->update_status('completed');
    $guestOrder->save();
}

$guestCommentId = wp_insert_comment([
    'comment_post_ID' => $gpsrProductId,
    'comment_author' => 'Guest Reviewer',
    'comment_author_email' => 'guest-review@example.com',
    'comment_content' => 'Guest review content',
    'comment_type' => 'review',
    'comment_approved' => 1,
    'user_id' => 0,
]);

$guestComment = $guestCommentId ? get_comment($guestCommentId) : null;
$guestBadgeHtml = $guestComment instanceof WP_Comment ? $verifiedReviewService->appendBadge('Guest review content', $guestComment) : '';
$assert(str_contains($guestBadgeHtml, 'polski-verified-badge'), 'Verified purchase badge works for guest purchases matched by email', $results, $failures);

$auditResults = $siteAuditService->runAudit();
$auditLabels = array_map(static fn (array $result): string => (string) ($result['label'] ?? ''), $auditResults);
$assert(in_array('Rejestr DPA (umowy powierzenia)', $auditLabels, true), 'Site audit includes DPA registry coverage', $results, $failures);
$assert(in_array('Rejestr incydentow bezpieczenstwa', $auditLabels, true), 'Site audit includes security incident coverage', $results, $failures);

foreach ($results as $result) {
    echo $result . PHP_EOL;
}

if ($failures !== []) {
    echo PHP_EOL . 'Failures:' . PHP_EOL;

    foreach ($failures as $failure) {
        echo '- ' . $failure . PHP_EOL;
    }

    exit(1);
}

echo PHP_EOL . 'All smoke tests passed.' . PHP_EOL;
