<?php
/**
 * Fixtures the withdrawal E2E specs need, seeded in one place.
 *
 * The a11y workflow used to inline two wp-cli calls for this, and the second
 * one wrapped a command substitution in single quotes, so the option was
 * written with the literal `$(npx ...)` inside it and `|| true` swallowed the
 * error. Nothing seeded the customer or the order at all, which is why the two
 * My Account specs could never pass no matter what the locators said.
 *
 * Run with: wp eval-file tests/E2E/fixtures/withdrawal-bootstrap.php
 *
 * @package Polski
 */

// No declare(strict_types=1): wp-cli eval-file evals the file, and a strict
// types declaration is only legal as the very first statement of a script.

if (! defined('ABSPATH')) {
    exit;
}

$report = [];

// 1. The module has to be on, or registerHooks() returns before adding the
//    order action and the shortcode.
$modules = get_option('polski_modules', []);
$modules = is_array($modules) ? $modules : [];
$modules['withdrawal'] = true;
update_option('polski_modules', $modules);
$report['module'] = 'enabled';

// 2. Guest entry point.
$page = get_page_by_path('odstapienie');

if (! $page instanceof WP_Post) {
    $pageId = wp_insert_post([
        'post_type' => 'page',
        'post_title' => 'Odstapienie',
        'post_name' => 'odstapienie',
        'post_status' => 'publish',
        'post_content' => '[polski_withdrawal_lookup]',
    ]);
} else {
    $pageId = $page->ID;
}

$report['lookup_page_id'] = (int) $pageId;

$settings = get_option('polski_withdrawal', []);
$settings = is_array($settings) ? $settings : [];
$settings['lookup_page_id'] = (int) $pageId;
$settings['period_days'] = 14;
update_option('polski_withdrawal', $settings);

// 3. A customer the specs can log in as.
$customer = get_user_by('login', 'customer');

if (! $customer instanceof WP_User) {
    $customerId = wp_insert_user([
        'user_login' => 'customer',
        'user_pass' => 'password',
        'user_email' => 'customer@example.test',
        'role' => 'customer',
    ]);
} else {
    $customerId = $customer->ID;
    wp_set_password('password', $customerId);
}

$report['customer_id'] = (int) $customerId;

// 4. An order that is actually eligible: owned by the customer, completed so
//    the countdown has started, dated today so it is inside the 14 day window,
//    and carrying more than one unit so the partial-quantity spec has something
//    left to withdraw on its second pass.
// A fresh order every run, deliberately. The partial-quantity spec withdraws
// one unit and then asserts the rest is still withdrawable, so reusing the
// previous run's order drains it a unit at a time until the action disappears
// and the spec fails for a reason that has nothing to do with the code.
$product = null;

foreach (wc_get_products(['limit' => 1, 'status' => 'publish', 'type' => 'simple']) as $candidate) {
    $product = $candidate;
}

if ($product === null) {
    $product = new WC_Product_Simple();
    $product->set_name('Withdrawal fixture product');
    $product->set_regular_price('25.00');
    $product->set_status('publish');
    $product->save();
}

$order = wc_create_order(['customer_id' => $customerId]);
$order->add_product($product, 3);
$order->set_billing_email('customer@example.test');
$order->calculate_totals();
$order->set_status('completed');
$order->save();

$report['order_id'] = $order->get_id();

// 5. The guest lookup limiter counts per IP over a 15 minute window and ignores
//    the email entirely, so a second run of the suite inside that window starts
//    already throttled and the masked-notice spec sees an error instead. Clear
//    the counters so the run is deterministic.
global $wpdb;
$cleared = $wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_polski_wrl_%'"
        . " OR option_name LIKE '_transient_timeout_polski_wrl_%'"
);
$report['rate_limit_rows_cleared'] = (int) $cleared;

WP_CLI::log(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
