<?php

use Polski\Enum\QuoteRequestStatus;
use Polski\Plugin;
use Polski\Repository\AffiliateRepository;
use Polski\Repository\GiftCardRepository;
use Polski\Repository\QuoteRequestRepository;
use Polski\Repository\SubscriptionRepository;
use Polski\Repository\WaitlistRepository;
use Polski\Rest\SettingsController;
use Polski\Service\AddOnsService;
use Polski\Service\AffiliateService;
use Polski\Service\ProductBundlesService;
use Polski\Service\QuoteService;
use Polski\Service\SubscriptionService;
use Polski\Service\WaitlistService;
use Polski\Service\GiftCardService;

if (! defined('ABSPATH')) {
    exit(1);
}

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
    'request_quote' => true,
    'product_bundles' => true,
    'gift_cards' => true,
    'subscriptions' => true,
    'affiliates' => true,
    'waitlist' => true,
    'product_add_ons' => true,
]);

update_option('polski_quote', [
    'enabled' => true,
    'availability' => 'selected',
    'show_on_single' => true,
    'show_on_loop' => true,
    'replace_add_to_cart' => true,
    'hide_prices' => true,
    'allow_guest' => true,
    'privacy_required' => true,
    'privacy_label' => 'Akceptuję kontakt w sprawie wyceny.',
    'button_text' => 'Zapytaj o wycenę',
    'submit_text' => 'Wyślij zapytanie',
    'modal_title' => 'Zapytaj o wycenę',
    'success_text' => 'Zapytanie zostało wysłane.',
]);

update_option('polski_bundles', [
    'show_on_single' => true,
    'show_total' => true,
    'show_quantities' => true,
]);

update_option('polski_gift_cards', [
    'show_on_single' => true,
    'show_in_account' => true,
]);

update_option('polski_subscriptions', [
    'show_on_single' => true,
    'show_in_account' => true,
]);

update_option('polski_affiliates', [
    'show_in_account' => true,
    'commission_percent' => 7,
    'pending_statuses' => 'processing,completed',
]);

update_option('polski_waitlist', [
    'show_on_single' => true,
    'allow_guests' => true,
]);

update_option('polski_addons', [
    'show_on_single' => true,
]);

$quoteProductId = $ensureProduct('produkt-quote', 'Produkt Quote', 123.0);
update_post_meta($quoteProductId, '_polski_quote_enabled', 'yes');
update_post_meta($quoteProductId, '_polski_quote_only', 'yes');
update_post_meta($quoteProductId, '_polski_quote_min_qty', '2');

$addOnProductId = $ensureProduct('produkt-dodatki', 'Produkt Dodatki', 50.0);
update_post_meta($addOnProductId, '_polski_addons_config', "checkbox|Pakowanie na prezent|15|no||Eleganckie pakowanie||\ntext|Treść graweru|10|no||Maksymalnie 20 znaków|Np. Dla Ani|20");

$bundleProductId = $ensureProduct('produkt-bundle', 'Produkt Bundle', 200.0);
$bundleProductA = $ensureProduct('akcesorium-a', 'Akcesorium A', 20.0);
$bundleProductB = $ensureProduct('akcesorium-b', 'Akcesorium B', 40.0);
update_post_meta($bundleProductId, '_polski_bundle_items', $bundleProductA . ',' . $bundleProductB);
update_post_meta($bundleProductId, '_polski_bundle_title', 'Dobierz akcesoria');
update_post_meta($bundleProductId, '_polski_bundle_discount_type', 'percent');
update_post_meta($bundleProductId, '_polski_bundle_discount_value', '10');
update_post_meta($bundleProductId, '_polski_bundle_button_text', 'Dodaj zestaw');

$giftProductId = $ensureProduct('karta-podarunkowa', 'Karta Podarunkowa', 50.0);
update_post_meta($giftProductId, '_polski_gift_card_enabled', 'yes');
update_post_meta($giftProductId, '_polski_gift_card_amounts', '50,100,200');
update_post_meta($giftProductId, '_polski_gift_card_allow_custom_amount', 'yes');
update_post_meta($giftProductId, '_polski_gift_card_min_amount', '25');
update_post_meta($giftProductId, '_polski_gift_card_max_amount', '500');

$subscriptionProductId = $ensureProduct('subskrypcja-test', 'Subskrypcja Test', 30.0);
update_post_meta($subscriptionProductId, '_polski_subscription_enabled', 'yes');
update_post_meta($subscriptionProductId, '_polski_subscription_interval', '1');
update_post_meta($subscriptionProductId, '_polski_subscription_period', 'month');
update_post_meta($subscriptionProductId, '_polski_subscription_length', '0');
update_post_meta($subscriptionProductId, '_polski_subscription_signup_fee', '5');
update_post_meta($subscriptionProductId, '_polski_subscription_trial_days', '7');

$waitlistProductId = $ensureProduct('brakujacy-produkt', 'Brakujący Produkt', 80.0);
$waitlistProduct = wc_get_product($waitlistProductId);

if ($waitlistProduct instanceof WC_Product) {
    $waitlistProduct->set_manage_stock(true);
    $waitlistProduct->set_stock_quantity(0);
    $waitlistProduct->set_stock_status('outofstock');
    $waitlistProduct->save();
}

$affiliateUser = get_user_by('login', 'affiliate_test');

if (! $affiliateUser instanceof WP_User) {
    $affiliateUserId = wp_create_user('affiliate_test', 'password', 'affiliate@example.com');
    wp_update_user([
        'ID' => $affiliateUserId,
        'role' => 'customer',
    ]);
    $affiliateUser = get_user_by('id', $affiliateUserId);
}

$container = Plugin::instance()->container();

/** @var QuoteService $quoteService */
$quoteService = $container->get(QuoteService::class);
/** @var QuoteRequestRepository $quoteRepository */
$quoteRepository = $container->get(QuoteRequestRepository::class);
/** @var WaitlistService $waitlistService */
$waitlistService = $container->get(WaitlistService::class);
/** @var WaitlistRepository $waitlistRepository */
$waitlistRepository = $container->get(WaitlistRepository::class);
/** @var AddOnsService $addOnsService */
$addOnsService = $container->get(AddOnsService::class);
/** @var ProductBundlesService $bundlesService */
$bundlesService = $container->get(ProductBundlesService::class);
/** @var GiftCardService $giftCardService */
$giftCardService = $container->get(GiftCardService::class);
/** @var GiftCardRepository $giftCardRepository */
$giftCardRepository = $container->get(GiftCardRepository::class);
/** @var SubscriptionService $subscriptionService */
$subscriptionService = $container->get(SubscriptionService::class);
/** @var SubscriptionRepository $subscriptionRepository */
$subscriptionRepository = $container->get(SubscriptionRepository::class);
/** @var AffiliateService $affiliateService */
$affiliateService = $container->get(AffiliateService::class);
/** @var AffiliateRepository $affiliateRepository */
$affiliateRepository = $container->get(AffiliateRepository::class);

$quoteProduct = wc_get_product($quoteProductId);
$assert($quoteProduct instanceof WC_Product, 'Quote product created', $results, $failures);
$assert($quoteProduct instanceof WC_Product && $quoteService->isAvailableForProduct($quoteProduct), 'Quote service enables configured product', $results, $failures);
$assert($quoteProduct instanceof WC_Product && str_contains($quoteService->filterPriceHtml('123', $quoteProduct), 'polski-quote-price-placeholder'), 'Quote-only price placeholder renders', $results, $failures);

$quoteBefore = $quoteRepository->countByStatus(QuoteRequestStatus::New);
$_POST = [
    '_polski_quote_nonce' => wp_create_nonce('polski_quote_request'),
    'polski_quote_product_id' => (string) $quoteProductId,
    'polski_quote_variation_id' => '0',
    'polski_quote_source_url' => home_url('/?product=produkt-quote'),
    'polski_quote_name' => 'Jan Kowalski',
    'polski_quote_email' => 'smoke-quote@example.com',
    'polski_quote_phone' => '500600700',
    'polski_quote_company' => 'ACME',
    'polski_quote_nip' => '5252445763',
    'polski_quote_postcode' => '00-001',
    'polski_quote_quantity' => '2',
    'polski_quote_message' => 'Smoke test quote request',
    'polski_quote_privacy' => '1',
];
$_REQUEST = $_POST;
$storeRequest = new ReflectionMethod($quoteService, 'storeRequestFromCurrentPayload');
$storeRequest->setAccessible(true);
$quoteResult = $storeRequest->invoke($quoteService, false);
$quoteAfter = $quoteRepository->countByStatus(QuoteRequestStatus::New);
$assert(is_array($quoteResult) && ($quoteResult['success'] ?? false) === true, 'Quote request submission succeeds', $results, $failures);
$assert($quoteAfter === $quoteBefore + 1, 'Quote request saved in repository', $results, $failures);

$waitlistBefore = count($waitlistRepository->findPendingByProduct($waitlistProductId));
$waitlistRenderCheck = new ReflectionMethod($waitlistService, 'shouldRenderForProduct');
$waitlistRenderCheck->setAccessible(true);
$waitlistCanRender = $waitlistProduct instanceof WC_Product ? (bool) $waitlistRenderCheck->invoke($waitlistService, $waitlistProduct) : false;
$waitlistEmail = 'smoke-waitlist+' . wp_generate_password(8, false, false) . '@example.com';
if ($waitlistCanRender) {
    $waitlistRepository->subscribe($waitlistProductId, $waitlistEmail, null);
}
$waitlistAfter = count($waitlistRepository->findPendingByProduct($waitlistProductId));
$assert($waitlistCanRender, 'Waitlist logic allows out-of-stock product signups', $results, $failures);
$assert($waitlistAfter === $waitlistBefore + 1, 'Waitlist subscription saved in repository', $results, $failures);

$addOnProduct = wc_get_product($addOnProductId);
$addOns = $addOnProduct instanceof WC_Product ? $addOnsService->getAddOns($addOnProduct) : [];
$assert(count($addOns) === 2, 'Product add-ons parser returns configured fields', $results, $failures);

$bundleProduct = wc_get_product($bundleProductId);
$bundleItems = $bundleProduct instanceof WC_Product ? $bundlesService->getBundleItems($bundleProduct) : [];
$assert(count($bundleItems) === 2, 'Bundle parser returns linked products', $results, $failures);

$giftOrder = wc_create_order();
$giftOrder->set_billing_email('gift-buyer@example.com');
$giftProduct = wc_get_product($giftProductId);

if ($giftProduct instanceof WC_Product) {
    $giftItem = new WC_Order_Item_Product();
    $giftItem->set_product($giftProduct);
    $giftItem->set_quantity(1);
    $giftItem->set_subtotal(100.0);
    $giftItem->set_total(100.0);
    $giftItem->add_meta_data('_polski_gift_card_purchase', wp_json_encode([
        'recipient_name' => 'Anna',
        'recipient_email' => 'anna@example.com',
        'sender_name' => 'Jan',
        'message' => 'Powodzenia',
        'amount' => 100,
        'currency' => get_woocommerce_currency(),
    ]), true);
    $giftOrder->add_item($giftItem);
}

$giftOrder->calculate_totals();
$giftOrder->save();
$giftCardService->createGiftCardsFromOrder($giftOrder->get_id());
$giftCards = $giftCardRepository->findByOrder($giftOrder->get_id());
$assert(count($giftCards) === 1, 'Gift card order creates a gift card record', $results, $failures);

$subscriptionOrder = wc_create_order();
$subscriptionOrder->set_billing_email('subscription-buyer@example.com');
$subscriptionProduct = wc_get_product($subscriptionProductId);

if ($subscriptionProduct instanceof WC_Product) {
    $subscriptionOrder->add_product($subscriptionProduct, 1);
}

$subscriptionOrder->calculate_totals();
$subscriptionOrder->save();
$subscriptionService->activateSubscriptionsFromOrder($subscriptionOrder->get_id());
$subscription = $subscriptionRepository->findByOrderAndProduct($subscriptionOrder->get_id(), $subscriptionProductId);
$assert($subscription !== null, 'Subscription order creates subscription record', $results, $failures);

wp_set_current_user($affiliateUser instanceof WP_User ? (int) $affiliateUser->ID : 0);
$affiliate = $affiliateService->getOrCreateAffiliate();
$assert($affiliate !== null, 'Affiliate account is created for current user', $results, $failures);

if ($affiliate !== null) {
    $affiliateOrder = wc_create_order();
    $affiliateOrder->set_billing_email('buyer@example.com');

    $affiliateProduct = wc_get_product($bundleProductA);

    if ($affiliateProduct instanceof WC_Product) {
        $affiliateOrder->add_product($affiliateProduct, 3);
    }

    $affiliateOrder->calculate_totals();
    $affiliateOrder->update_meta_data('_polski_affiliate_id', $affiliate->id);
    $affiliateOrder->update_meta_data('_polski_affiliate_token', $affiliate->token);
    $affiliateOrder->save();
    $affiliateOrder->set_status('processing');
    $affiliateOrder->save();

    $affiliateService->registerReferralForOrder($affiliateOrder->get_id());
    $assert($affiliateRepository->referralExists($affiliate->id, $affiliateOrder->get_id()), 'Affiliate referral is registered for qualifying order', $results, $failures);
}

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
