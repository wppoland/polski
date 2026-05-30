<?php

use Polski\Admin\ModulesPage;
use Polski\Plugin;

defined('ABSPATH') || exit;

if (! class_exists(Plugin::class)) {
    require_once dirname(__DIR__, 3) . '/polski.php';
}

Plugin::instance()->boot();

require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/taxonomy.php';

/**
 * Remove deterministic WordPress.org screenshot fixtures so E2E search remains isolated.
 *
 * @param list<string> $slugs
 */
function polski_e2e_delete_posts_by_slug(array $slugs, string $postType): void
{
    foreach ($slugs as $slug) {
        $post = get_page_by_path($slug, OBJECT, $postType);

        if ($post instanceof WP_Post) {
            wp_delete_post($post->ID, true);
        }
    }
}

/**
 * Ensure a page exists and return its ID.
 */
function polski_e2e_ensure_page(string $slug, string $title, string $content = ''): int
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
 * Ensure a simple product exists and return its ID.
 *
 * @param list<int> $categoryIds
 */
function polski_e2e_ensure_product(
    string $slug,
    string $name,
    string $sku,
    float $price,
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
    $product->set_regular_price(wc_format_decimal($price));
    $product->set_price(wc_format_decimal($price));
    $product->set_sku($sku);
    $product->set_manage_stock(false);
    $product->set_stock_status('instock');
    $product->set_short_description($shortDescription);
    $product->set_description($shortDescription . "\n\n" . 'Produkt testowy dla smoke testów storefront.');
    $productId = $product->save();

    wp_set_object_terms($productId, $categoryIds, 'product_cat');
    wp_set_object_terms($productId, [$brandSlug], 'polski_brand');

    return (int) $productId;
}

$savedModules = get_option('polski_modules', []);
$savedModules = is_array($savedModules) ? $savedModules : [];

delete_option('polski_deactivation_feedback');
delete_option('polski_pro_deactivation_feedback');

polski_e2e_delete_posts_by_slug([
    'polski-wporg-alpha-drill',
    'polski-wporg-beta-saw',
], 'product');

polski_e2e_delete_posts_by_slug([
    'polski-search',
    'polski-dsa-report',
], 'page');

$enabledModules = [
    'ajax_search' => true,
    'brands' => true,
    'ajax_filters' => true,
    'wishlist' => true,
    'compare' => true,
    'quick_view' => true,
    'legal_checkboxes' => true,
];

update_option('polski_modules', array_merge(ModulesPage::getDefaultModuleStates(), $savedModules, $enabledModules));
update_option('polski_setup_wizard_completed', 'yes');
update_option('woocommerce_coming_soon', 'no');
update_option('woocommerce_store_pages_only', 'no');

$termsPageId = polski_e2e_ensure_page('terms', 'Terms and Conditions', 'Our terms...');
$privacyPageId = polski_e2e_ensure_page('privacy', 'Privacy Policy', 'Our privacy policy...');
$returnsPageId = polski_e2e_ensure_page('returns', 'Right of Withdrawal', 'Information about returns...');

update_option('polski_terms_page_id', $termsPageId);
update_option('polski_privacy_page_id', $privacyPageId);
update_option('polski_returns_page_id', $returnsPageId);

update_option('polski_checkout', [
    'order_button_text' => 'Place order',
    'terms_checkbox_enabled' => true,
    'terms_checkbox_label' => 'I have read and accept the <a href="%s" target="_blank">Terms and Conditions</a>.',
    'privacy_checkbox_enabled' => true,
    'privacy_checkbox_label' => 'I have read and accept the <a href="%s" target="_blank">Privacy Policy</a>.',
    'withdrawal_checkbox_enabled' => true,
    'withdrawal_checkbox_label' => 'I have been informed about my <a href="%s" target="_blank">right to withdraw from the contract</a> within 14 days.',
]);

update_option('polski_search', array_merge(
    (array) get_option('polski_search', []),
    [
        'search_label' => 'Search products',
        'placeholder' => 'Search E2E product',
        'results_label' => 'Product search results',
        'submit_button_text' => 'Search',
        'show_submit_button' => true,
        'show_image' => false,
        'show_price' => true,
        'show_sku' => true,
        'show_view_all_link' => true,
        'view_all_text' => 'See all results',
        'no_results_text' => 'No results',
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
        'title' => 'Filter products',
        'show_categories' => true,
        'show_brands' => true,
        'show_price' => true,
        'show_stock' => true,
        'show_sale' => false,
        'show_attributes' => false,
        'show_reset_link' => true,
        'submit_text' => 'Filter',
        'reset_text' => 'Reset',
    ],
));

update_option('polski_wishlist', array_merge(
    (array) get_option('polski_wishlist', []),
    [
        'allow_guests' => true,
        'show_on_single' => true,
        'show_on_loop' => true,
        'show_in_account' => true,
        'button_add_text' => 'Add to Wishlist',
        'button_remove_text' => 'Remove from Wishlist',
    ],
));

update_option('polski_compare', array_merge(
    (array) get_option('polski_compare', []),
    [
        'allow_guests' => true,
        'show_on_single' => true,
        'show_on_loop' => true,
        'show_in_account' => false,
        'button_add_text' => 'Add to comparison',
        'button_remove_text' => 'Remove from comparison',
        'compare_link_text' => 'Compare products',
        'title' => 'Product Comparison',
        'empty_text' => 'No products to compare.',
    ],
));

update_option('polski_quick_view', array_merge(
    (array) get_option('polski_quick_view', []),
    [
        'show_on_loop' => true,
        'show_modal_label' => true,
        'show_close_button' => true,
        'show_view_product_link' => true,
        'button_text' => 'Quick View',
        'modal_title' => 'Product Quick View',
        'loading_text' => 'Loading product...',
        'error_text' => 'Could not load product preview.',
        'show_price' => true,
        'show_sku' => true,
        'show_short_description' => true,
    ],
));

update_option('polski_brand', array_merge(
    (array) get_option('polski_brand', []),
    [
        'show_on_single' => true,
        'show_on_loop' => true,
        'label' => 'Marka',
        'show_label' => true,
        'separator' => ', ',
        'link_terms' => true,
    ],
));

// Enable pretty permalinks so /shop/, /checkout/, etc. work.
update_option('permalink_structure', '/%postname%/');

// Ensure WooCommerce core pages exist.
$shopPageId = (int) get_option('woocommerce_shop_page_id', 0);

if ($shopPageId <= 0 || get_post_status($shopPageId) === false) {
    $shopPageId = polski_e2e_ensure_page('shop', 'Shop');
    update_option('woocommerce_shop_page_id', $shopPageId);
}

$cartPageId = (int) get_option('woocommerce_cart_page_id', 0);

if ($cartPageId <= 0 || get_post_status($cartPageId) === false) {
    $cartPageId = polski_e2e_ensure_page('cart', 'Cart', '<!-- wp:woocommerce/cart --><!-- /wp:woocommerce/cart -->');
    update_option('woocommerce_cart_page_id', $cartPageId);
}

$checkoutPageId = (int) get_option('woocommerce_checkout_page_id', 0);

// Classic [woocommerce_checkout] so PHP hooks (e.g. woocommerce_review_order_before_submit) run;
// the WooCommerce Checkout block does not fire those hooks - E2E asserts .polski-legal-checkboxes from templates.
$checkoutPageContent = "<!-- wp:shortcode -->\n[woocommerce_checkout]\n<!-- /wp:shortcode -->";

if ($checkoutPageId <= 0 || get_post_status($checkoutPageId) === false) {
    $checkoutPageId = polski_e2e_ensure_page('checkout', 'Checkout', $checkoutPageContent);
    update_option('woocommerce_checkout_page_id', $checkoutPageId);
} else {
    polski_e2e_ensure_page('checkout', 'Checkout', $checkoutPageContent);
}

$myAccountPageId = (int) get_option('woocommerce_myaccount_page_id', 0);

if ($myAccountPageId <= 0 || get_post_status($myAccountPageId) === false) {
    $myAccountPageId = polski_e2e_ensure_page('my-account', 'My account', '<!-- wp:woocommerce/my-account --><!-- /wp:woocommerce/my-account -->');
    update_option('woocommerce_myaccount_page_id', $myAccountPageId);
}

// Enable a payment gateway so checkout can complete.
update_option('woocommerce_cod_settings', [
    'enabled' => 'yes',
    'title' => 'Cash on delivery',
]);

// Add a "Rest of the World" flat rate shipping method so checkout shows the order review.
$defaultZone = \WC_Shipping_Zones::get_zone(0);

if (count($defaultZone->get_shipping_methods()) === 0) {
    $defaultZone->add_shipping_method('flat_rate');

    // Configure the flat rate method.
    $methods = $defaultZone->get_shipping_methods();
    $method = reset($methods);

    if ($method) {
        $method->init_instance_settings();
        $method->instance_settings['cost'] = '10';
        update_option($method->get_instance_option_key(), $method->instance_settings);
    }
}

// Mark wizard as complete.
update_option('polski_wizard_complete', true);

$searchPageId = polski_e2e_ensure_page(
    'polski-e2e-search',
    'Polski E2E Search',
    '[polski_ajax_search]'
);

$categoryTools = wp_insert_term('E2E Tools', 'product_cat', ['slug' => 'e2e-tools']);
$categoryToolsId = is_wp_error($categoryTools)
    ? (int) get_term_by('slug', 'e2e-tools', 'product_cat')->term_id
    : (int) $categoryTools['term_id'];

$categoryAccessories = wp_insert_term('E2E Accessories', 'product_cat', ['slug' => 'e2e-accessories']);
$categoryAccessoriesId = is_wp_error($categoryAccessories)
    ? (int) get_term_by('slug', 'e2e-accessories', 'product_cat')->term_id
    : (int) $categoryAccessories['term_id'];

wp_insert_term('E2E Brand Alpha', 'polski_brand', ['slug' => 'e2e-brand-alpha']);
wp_insert_term('E2E Brand Beta', 'polski_brand', ['slug' => 'e2e-brand-beta']);

$alphaId = polski_e2e_ensure_product(
    'polski-e2e-alpha-drill',
    'Polski E2E Alpha Drill',
    'E2E-ALPHA-001',
    199.00,
    'Alpha drill for storefront smoke tests.',
    [$categoryToolsId],
    'e2e-brand-alpha',
);

$betaId = polski_e2e_ensure_product(
    'polski-e2e-beta-saw',
    'Polski E2E Beta Saw',
    'E2E-BETA-002',
    299.00,
    'Beta saw for storefront smoke tests.',
    [$categoryAccessoriesId],
    'e2e-brand-beta',
);

flush_rewrite_rules(false);

echo wp_json_encode([
    'shop_page_id' => $shopPageId,
    'search_page_id' => $searchPageId,
    'products' => [
        'alpha' => $alphaId,
        'beta' => $betaId,
    ],
], JSON_PRETTY_PRINT) . PHP_EOL;
