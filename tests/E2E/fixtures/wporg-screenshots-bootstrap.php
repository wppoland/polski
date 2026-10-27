<?php

use Polski\Admin\ModulesPage;
use Polski\Enum\LegalPageType;
use Polski\Enum\PriceType;
use Polski\Plugin;

defined('ABSPATH') || exit;

if (! class_exists(Plugin::class)) {
    require_once dirname(__DIR__, 3) . '/polski.php';
}

Plugin::instance()->boot();

require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

update_option('WPLANG', 'pl_PL');
switch_to_locale('pl_PL');

// The price history below is recorded in PLN; a USD shop shows "$99.00" next
// to a zloty Omnibus line, which the plugin itself never renders.
update_option('woocommerce_currency', 'PLN');
update_option('woocommerce_currency_pos', 'right_space');
update_option('woocommerce_price_decimal_sep', ',');
update_option('woocommerce_price_thousand_sep', ' ');

/**
 * Ensure a page exists and return its ID.
 */
function polski_wporg_ensure_page(string $slug, string $title, string $content = ''): int
{
    $page = get_page_by_path($slug, OBJECT, 'page');

    if ($page instanceof WP_Post) {
        wp_update_post([
            'ID' => $page->ID,
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
        ]);

        return (int) $page->ID;
    }

    return (int) wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => $title,
        'post_name' => $slug,
        'post_content' => $content,
    ]);
}

/**
 * Ensure WooCommerce page exists and is assigned.
 */
function polski_wporg_ensure_wc_page(string $key, string $slug, string $title, string $content): int
{
    $optionKey = 'woocommerce_' . $key . '_page_id';
    $pageId = (int) get_option($optionKey, 0);

    if ($pageId > 0 && get_post_status($pageId) !== false) {
        return $pageId;
    }

    $pageId = polski_wporg_ensure_page($slug, $title, $content);
    update_option($optionKey, $pageId);

    return $pageId;
}

/**
 * Ensure a user exists and return its ID.
 */
function polski_wporg_ensure_user(string $login, string $email, string $password, string $role, string $displayName): int
{
    $user = get_user_by('login', $login);

    if (! $user instanceof WP_User) {
        $userId = wp_create_user($login, $password, $email);
        if (is_wp_error($userId)) {
            wp_die(esc_html($userId->get_error_message()));
        }
        $user = get_user_by('id', $userId);
    }

    if (! $user instanceof WP_User) {
        wp_die('Could not create screenshot fixture user.');
    }

    wp_update_user([
        'ID' => $user->ID,
        'user_pass' => $password,
        'role' => $role,
        'display_name' => $displayName,
        'first_name' => explode(' ', $displayName)[0] ?? $displayName,
        'last_name' => explode(' ', $displayName, 2)[1] ?? '',
    ]);

    update_user_meta($user->ID, 'show_admin_bar_front', 'false');

    return (int) $user->ID;
}

/**
 * Ensure a simple product exists and return its ID.
 */
function polski_wporg_ensure_product(
    string $slug,
    string $name,
    string $sku,
    float $regularPrice,
    ?float $salePrice,
    string $shortDescription,
    array $categoryIds,
    string $brandSlug
): int {
    $existing = get_page_by_path($slug, OBJECT, 'product');

    if ($existing instanceof WP_Post) {
        $product = wc_get_product($existing->ID);
        $product = $product instanceof WC_Product ? $product : new WC_Product_Simple($existing->ID);
    } else {
        $product = new WC_Product_Simple();
    }

    $product->set_name($name);
    $product->set_slug($slug);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_regular_price(wc_format_decimal($regularPrice));
    $product->set_price(wc_format_decimal($salePrice ?? $regularPrice));
    $product->set_sku($sku);
    $product->set_manage_stock(false);
    $product->set_stock_status('instock');
    $product->set_short_description($shortDescription);
    $product->set_description($shortDescription . "\n\n" . 'Produkt demonstracyjny do screenshotów WordPress.org.');

    if ($salePrice !== null) {
        $product->set_sale_price(wc_format_decimal($salePrice));
    } else {
        $product->set_sale_price('');
    }

    $productId = $product->save();

    wp_set_object_terms($productId, $categoryIds, 'product_cat');
    wp_set_object_terms($productId, [$brandSlug], 'polski_brand');

    return (int) $productId;
}

/**
 * Seed deterministic Omnibus history.
 */
function polski_wporg_seed_omnibus_history(int $productId): void
{
    global $wpdb;

    $table = $wpdb->prefix . 'polski_price_history';
    $wpdb->delete($table, ['product_id' => $productId], ['%d']);

    $records = [
        [
            'product_id' => $productId,
            'price' => 159.00,
            'sale_price' => null,
            'price_type' => PriceType::Regular->value,
            'currency' => 'PLN',
            'recorded_at' => gmdate('Y-m-d H:i:s', strtotime('-12 days')),
        ],
        [
            'product_id' => $productId,
            'price' => 149.00,
            'sale_price' => 129.00,
            'price_type' => PriceType::Sale->value,
            'currency' => 'PLN',
            'recorded_at' => gmdate('Y-m-d H:i:s', strtotime('-5 days')),
        ],
        [
            'product_id' => $productId,
            'price' => 139.00,
            'sale_price' => 119.00,
            'price_type' => PriceType::Sale->value,
            'currency' => 'PLN',
            'recorded_at' => gmdate('Y-m-d H:i:s', strtotime('-2 days')),
        ],
    ];

    foreach ($records as $record) {
        $wpdb->insert(
            $table,
            $record,
            ['%d', '%f', '%f', '%s', '%s', '%s'],
        );
    }
}

/**
 * Ensure a completed WooCommerce order exists for withdrawal screenshots.
 */
function polski_wporg_ensure_completed_order(int $customerId, int $productId): int
{
    $orders = wc_get_orders([
        'customer_id' => $customerId,
        'limit' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_key' => '_polski_wporg_fixture',
        'meta_value' => 'withdrawal-order',
        'status' => ['completed'],
    ]);

    if (! empty($orders) && $orders[0] instanceof WC_Order) {
        return $orders[0]->get_id();
    }

    $order = wc_create_order([
        'customer_id' => $customerId,
        'status' => 'completed',
    ]);

    $product = wc_get_product($productId);
    if ($product instanceof WC_Product) {
        $order->add_product($product, 1);
    }

    $order->set_billing_first_name('Jan');
    $order->set_billing_last_name('Kowalski');
    $order->set_billing_email('customer@example.com');
    $order->set_billing_phone('500600700');
    $order->set_billing_address_1('ul. Prosta 1');
    $order->set_billing_postcode('00-001');
    $order->set_billing_city('Warszawa');
    $order->set_billing_country('PL');
    $order->set_payment_method('bacs');
    $order->set_payment_method_title('Przelew bankowy');
    $order->update_meta_data('_polski_wporg_fixture', 'withdrawal-order');
    $order->calculate_totals();
    $order->save();

    return $order->get_id();
}

/**
 * Ensure an order for the KSeF column screenshot exists.
 *
 * The column reads _polski_ksef_required and _polski_ksef_status. Checkout
 * hooks set the flag in a real shop; a seeded order never passes checkout,
 * so the meta is written here. 'accepted' is what PRO mirrors once KSeF
 * numbers the invoice. NIPs are checksum-valid test values, not real firms.
 *
 * @param array{key:string,company:string,first:string,last:string,nip:string,ksef:string,status:string,days:int} $row
 */
function polski_wporg_ensure_ksef_order(array $row, int $productId, int $qty): int
{
    $marker = 'ksef-' . $row['key'];
    $orders = wc_get_orders([
        'limit' => 1,
        'meta_key' => '_polski_wporg_fixture',
        'meta_value' => $marker,
        'status' => 'any',
    ]);

    $order = ! empty($orders) && $orders[0] instanceof WC_Order ? $orders[0] : wc_create_order();

    if (count($order->get_items()) === 0) {
        $product = wc_get_product($productId);
        if ($product instanceof WC_Product) {
            $order->add_product($product, $qty);
        }
    }

    $order->set_currency('PLN');
    $order->set_billing_company($row['company']);
    $order->set_billing_first_name($row['first']);
    $order->set_billing_last_name($row['last']);
    $order->set_billing_email($row['key'] . '@example.com');
    $order->set_billing_address_1('ul. Przykładowa 10');
    $order->set_billing_postcode('00-950');
    $order->set_billing_city('Warszawa');
    $order->set_billing_country('PL');
    $order->set_payment_method('bacs');
    $order->set_payment_method_title('Przelew bankowy');
    $order->set_date_created(time() - $row['days'] * DAY_IN_SECONDS);

    if ($row['nip'] !== '') {
        $order->update_meta_data('_billing_nip', $row['nip']);
        $order->update_meta_data('_polski_ksef_required', 'yes');
        $order->update_meta_data('_polski_ksef_status', $row['ksef']);
    } else {
        $order->update_meta_data('_polski_ksef_required', 'no');
    }

    $order->update_meta_data('_polski_wporg_fixture', $marker);
    $order->set_status($row['status']);
    $order->calculate_totals();
    $order->save();

    return $order->get_id();
}

$adminId = polski_wporg_ensure_user('admin', 'admin@example.com', 'password', 'administrator', 'Admin Polski');
$customerId = polski_wporg_ensure_user('wporg-customer', 'customer@example.com', 'password', 'customer', 'Jan Kowalski');

wp_set_current_user($adminId);
// Orders list for screenshot 9: WooCommerce defaults plus Origin hidden.
update_user_meta($adminId, 'managewoocommerce_page_wc-orderscolumnshidden', ['billing_address', 'shipping_address', 'wc_actions', 'origin']);

polski_wporg_ensure_wc_page('shop', 'sklep', 'Sklep', '');
polski_wporg_ensure_wc_page('cart', 'koszyk', 'Koszyk', '[woocommerce_cart]');
polski_wporg_ensure_wc_page('checkout', 'zamowienie', 'Order', '[woocommerce_checkout]');
polski_wporg_ensure_wc_page('myaccount', 'moje-konto', 'Moje konto', '[woocommerce_my_account]');

$termsPageId = polski_wporg_ensure_page('regulamin', 'Regulamin sklepu', '<p>Przykładowy regulamin sklepu.</p>');
$privacyPageId = polski_wporg_ensure_page('polityka-prywatnosci', 'Polityka prywatności', '<p>Przykładowa polityka prywatności.</p>');
$returnsPageId = polski_wporg_ensure_page('odstapienie', 'Right of withdrawal', '<p>Przykładowe informacje o odstąpieniu od umowy.</p>');
$complaintsPageId = polski_wporg_ensure_page('reklamacje', 'Reklamacje', '<p>Przykładowa procedura reklamacyjna.</p>');
$searchPageId = polski_wporg_ensure_page('polski-search', 'Wyszukiwanie produktów', '[polski_ajax_search]');
$dsaPageId = polski_wporg_ensure_page('polski-dsa-report', 'Zgłoś nielegalną treść', '[polski_dsa_report]');
$shopPageId = (int) get_option('woocommerce_shop_page_id', 0);
$checkoutPageId = (int) get_option('woocommerce_checkout_page_id', 0);
$myAccountPageId = (int) get_option('woocommerce_myaccount_page_id', 0);

update_option(LegalPageType::Terms->optionKey(), $termsPageId);
update_option(LegalPageType::Privacy->optionKey(), $privacyPageId);
update_option(LegalPageType::Returns->optionKey(), $returnsPageId);
update_option(LegalPageType::Complaints->optionKey(), $complaintsPageId);

$savedModules = get_option('polski_modules', []);
$savedModules = is_array($savedModules) ? $savedModules : [];

$enabledModules = [
    'unit_price' => true,
    'omnibus' => true,
    'legal_checkboxes' => true,
    'withdrawal' => true,
    'gpsr' => true,
    'dsa_toolkit' => true,
    'ajax_search' => true,
    'ajax_filters' => true,
    'wishlist' => true,
    'compare' => true,
    'quick_view' => true,
    'badges' => true,
    'brands' => true,
    'ksef_ready' => true,
];

update_option('polski_modules', array_merge(ModulesPage::getDefaultModuleStates(), $savedModules, $enabledModules));
update_option('polski_wizard_complete', true);
update_option('polski_setup_wizard_completed', 'yes');
update_option('woocommerce_coming_soon', 'no');
update_option('woocommerce_store_pages_only', 'no');

update_option('polski_general', array_merge(
    (array) get_option('polski_general', []),
    [
        'company_name' => 'Polski Demo Shop',
        'company_address' => 'ul. Testowa 1, 00-001 Warszawa',
        'company_email' => 'demo@example.com',
        'company_phone' => '500600700',
        'company_nip' => '5252445763',
    ],
));

update_option('polski_checkout', array_merge(
    (array) get_option('polski_checkout', []),
    [
        'order_button_text' => 'Order with an obligation to pay',
        'terms_checkbox_enabled' => true,
        'privacy_checkbox_enabled' => true,
        'withdrawal_checkbox_enabled' => true,
        'digital_waiver_checkbox_enabled' => false,
    ],
));

update_option('polski_withdrawal', array_merge(
    (array) get_option('polski_withdrawal', []),
    [
        'button_text' => 'Withdraw from the contract',
        'oneclick_enabled' => true,
        'oneclick_button_text' => 'Odstąpienie jednym kliknięciem',
        'oneclick_confirm_text' => 'Potwierdź odstąpienie',
    ],
));

update_option('polski_dsa', array_merge(
    (array) get_option('polski_dsa', []),
    [
        'contact_email' => 'compliance@example.com',
        'contact_phone' => '+48 500 600 700',
    ],
));

update_option('polski_search', array_merge(
    (array) get_option('polski_search', []),
    [
        'search_label' => 'Search products',
        'placeholder' => 'Wpisz nazwę produktu',
        'results_label' => 'Wyniki wyszukiwania',
        'submit_button_text' => 'Search',
        'show_submit_button' => true,
        'show_image' => false,
        'show_price' => true,
        'show_sku' => true,
        'show_view_all_link' => true,
        'view_all_text' => 'See all results',
        'no_results_text' => 'Brak wyników',
        'min_chars' => 2,
        'debounce_ms' => 50,
        'limit' => 6,
        'include_out_of_stock' => false,
    ],
));

update_option('polski_filters', array_merge(
    (array) get_option('polski_filters', []),
    [
        'show_on_shop' => true,
        'show_title' => true,
        'title' => 'Filtruj produkty',
        'show_categories' => true,
        'show_brands' => true,
        'show_price' => true,
        'show_stock' => true,
        'show_sale' => true,
        'show_attributes' => false,
        'show_reset_link' => true,
        'submit_text' => 'Filter',
        'reset_text' => 'Resetuj',
    ],
));

update_option('polski_wishlist', array_merge(
    (array) get_option('polski_wishlist', []),
    [
        'allow_guests' => true,
        'show_on_single' => true,
        'show_on_loop' => true,
    ],
));

update_option('polski_compare', array_merge(
    (array) get_option('polski_compare', []),
    [
        'allow_guests' => true,
        'show_on_single' => true,
        'show_on_loop' => true,
    ],
));

$categoryToolsId = (int) wp_create_category('Narzędzia');
$categorySafetyId = (int) wp_create_category('Bezpieczeństwo');
$brandAlpha = wp_insert_term('Polski Brand Alpha', 'polski_brand', ['slug' => 'wporg-brand-alpha']);
$brandBeta = wp_insert_term('Polski Brand Beta', 'polski_brand', ['slug' => 'wporg-brand-beta']);

$alphaBrandSlug = is_array($brandAlpha) ? 'wporg-brand-alpha' : 'wporg-brand-alpha';
$betaBrandSlug = is_array($brandBeta) ? 'wporg-brand-beta' : 'wporg-brand-beta';

$saleProductId = polski_wporg_ensure_product(
    'polski-wporg-alpha-drill',
    'Polski WP.org Alpha Drill',
    'WPORG-ALPHA-001',
    139.00,
    99.00,
    'Produkt demonstracyjny z ceną promocyjną, GPSR i Omnibus.',
    [$categoryToolsId, $categorySafetyId],
    $alphaBrandSlug,
);

$secondaryProductId = polski_wporg_ensure_product(
    'polski-wporg-beta-saw',
    'Polski WP.org Beta Saw',
    'WPORG-BETA-002',
    89.00,
    null,
    'Drugi produkt demonstracyjny do search, filters i quick view.',
    [$categoryToolsId],
    $betaBrandSlug,
);

update_post_meta($saleProductId, '_polski_gpsr_manufacturer_name', 'Polski Tools Sp. z o.o.');
update_post_meta($saleProductId, '_polski_gpsr_manufacturer_address', 'ul. Bezpieczna 7, 00-120 Warszawa');
update_post_meta($saleProductId, '_polski_gpsr_importer_name', 'Polski Import Sp. z o.o.');
update_post_meta($saleProductId, '_polski_gpsr_importer_address', 'ul. Portowa 3, 80-000 Gdańsk');
update_post_meta($saleProductId, '_polski_gpsr_responsible_person', 'Anna Kowalska');
update_post_meta($saleProductId, '_polski_gpsr_product_identifier', 'LOT-2026-WPORG-01');
update_post_meta($saleProductId, '_polski_gpsr_safety_warnings', 'Używać wyłącznie zgodnie z instrukcją. Trzymać z dala od dzieci.');
update_post_meta($saleProductId, '_polski_gpsr_instructions', 'Przed pierwszym użyciem przeczytaj instrukcję bezpieczeństwa.');
update_post_meta($saleProductId, '_polski_unit_price_product_amount', '1');
update_post_meta($saleProductId, '_polski_unit_price_base', '1');
update_post_meta($saleProductId, '_polski_unit_price_unit', 'szt');

polski_wporg_seed_omnibus_history($saleProductId);
$orderId = polski_wporg_ensure_completed_order($customerId, $saleProductId);

$ksefRows = [
    ['key' => 'kwadrat', 'company' => 'Kwadrat Meble Sp. z o.o.', 'first' => '', 'last' => '', 'nip' => '1234561001', 'ksef' => 'accepted', 'status' => 'completed', 'days' => 3],
    ['key' => 'lampa', 'company' => 'Zielona Lampa s.c.', 'first' => '', 'last' => '', 'nip' => '9876541004', 'ksef' => 'pending', 'status' => 'processing', 'days' => 2],
    ['key' => 'nowak', 'company' => '', 'first' => 'Anna', 'last' => 'Nowak', 'nip' => '', 'ksef' => '', 'status' => 'processing', 'days' => 1],
    ['key' => 'liczba', 'company' => 'Biuro Rachunkowe Liczba Sp. j.', 'first' => '', 'last' => '', 'nip' => '5551231005', 'ksef' => 'pending', 'status' => 'on-hold', 'days' => 0],
];

foreach ($ksefRows as $i => $ksefRow) {
    polski_wporg_ensure_ksef_order($ksefRow, $i % 2 === 0 ? $saleProductId : $secondaryProductId, $i + 1);
}

wp_cache_flush();

wp_send_json([
    'admin' => [
        'login' => 'admin',
        'password' => 'password',
    ],
    'customer' => [
        'login' => 'wporg-customer',
        'password' => 'password',
    ],
    'products' => [
        'sale' => $saleProductId,
        'secondary' => $secondaryProductId,
    ],
    'pages' => [
        'shop' => $shopPageId,
        'checkout' => $checkoutPageId,
        'myaccount' => $myAccountPageId,
        'search' => $searchPageId,
        'dsa' => $dsaPageId,
        'terms' => $termsPageId,
        'privacy' => $privacyPageId,
        'returns' => $returnsPageId,
        'complaints' => $complaintsPageId,
    ],
    'urls' => [
        'shop' => get_permalink($shopPageId),
        'checkout' => get_permalink($checkoutPageId),
        'myaccount_orders' => wc_get_account_endpoint_url('orders'),
        'search' => get_permalink($searchPageId),
        'dsa' => get_permalink($dsaPageId),
        'sale_product' => get_permalink($saleProductId),
    ],
    'order_id' => $orderId,
]);
