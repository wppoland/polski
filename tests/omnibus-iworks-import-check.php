<?php

// NOTE: no `declare(strict_types=1)` here on purpose - `wp eval-file` wraps this
// file in eval(), where a declare() can't be the first statement.

/**
 * History kept by the "Omnibus" plugin (iworks) is imported into Polski, once.
 *
 * A product on sale for 10 days whose only pre-sale history lives in iworks
 * must show the iworks pre-sale price as its lowest price, not the sale price
 * and not nothing. Page-view snapshots (selling price only) are not imported.
 *
 * Run: wp eval-file wp-content/plugins/<dir>/tests/omnibus-iworks-import-check.php
 */

$polski_failures = [];
$polski_check = static function (string $label, bool $ok) use (&$polski_failures): void {
    if (! $ok) {
        $polski_failures[] = $label;
    }
};

global $wpdb;
$polski_table = $wpdb->prefix . 'polski_price_history';
$polski_service = Polski\Plugin::instance()->container()->get(Polski\Service\OmnibusService::class);

$polski_product = new WC_Product_Simple();
$polski_product->set_name('Omnibus iworks import probe');
$polski_product->set_status('publish');
$polski_product->set_regular_price('120.00');
$polski_product->set_sale_price('90.00');
$polski_product->save();
$polski_id = $polski_product->get_id();

// Polski only saw the product once the sale was running: its history is the sale row alone.
$wpdb->query($wpdb->prepare('UPDATE %i SET recorded_at = %s WHERE product_id = %d', $polski_table, gmdate('Y-m-d H:i:s', time() - 10 * DAY_IN_SECONDS), $polski_id));

// iworks v3: regular 120 from 40 days ago, a 110 promo 20 days ago, the 90 sale 11 days ago
// (iworks saw it a day before Polski did).
foreach ([[40, '120.00', ''], [20, '120.00', '110.00'], [11, '120.00', '90.00']] as [$polski_days, $polski_regular, $polski_sale]) {
    $polski_ts = time() - $polski_days * DAY_IN_SECONDS;
    $polski_log = wp_insert_post([
        'post_type' => 'iw_omnibus_price_log',
        'post_status' => 'publish',
        'post_parent' => $polski_id,
        'post_title' => 'probe',
        'post_date_gmt' => gmdate('Y-m-d H:i:s', $polski_ts),
    ]);
    add_post_meta($polski_log, 'price_regular', $polski_regular);
    add_post_meta($polski_log, 'price_sale', $polski_sale);
    add_post_meta($polski_log, 'timestamp', $polski_ts);
}

// iworks v2: a page-view snapshot holding only the selling price, 15 days ago.
add_post_meta($polski_id, '_iwo_price_lowest', ['price' => '50.00', 'timestamp' => time() - 15 * DAY_IN_SECONDS, 'type' => 'autosaved']);

// Mapping rules.
$polski_check('a record without a regular price is skipped', Polski\Service\IworksOmnibusImport::mapEntry(['price_sale' => '5'], time(), 'PLN') === null);
$polski_check('a sale not below the regular price is no sale', Polski\Service\IworksOmnibusImport::mapEntry(['price_regular' => '5', 'price_sale' => '5', 'timestamp' => time()], 0, 'PLN')['sale_price'] === null);

$polski_import = new Polski\Service\IworksOmnibusImport();
$polski_first = $polski_import->run();
$polski_second = $polski_import->run();
wp_cache_flush();

$polski_check("first run imports the three log rows (got $polski_first)", $polski_first === 3);
$polski_check("second run imports nothing (got $polski_second)", $polski_second === 0);

$polski_snapshot = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE product_id = %d AND ABS(price - 50) < 0.001', $polski_table, $polski_id));
$polski_check('the page-view snapshot is not imported', $polski_snapshot === 0);

// The sale started 10 days ago; the lowest in the 30 days before it is the 110 promo.
$polski_text = wp_strip_all_tags($polski_service->getLowestPriceHtml($polski_id));
$polski_check("notice shows the pre-sale lowest 110 (got '$polski_text')", str_contains($polski_text, '110'));

// Clean up.
foreach (get_children(['post_parent' => $polski_id, 'post_type' => 'iw_omnibus_price_log', 'fields' => 'ids']) as $polski_log) {
    wp_delete_post($polski_log, true);
}
$wpdb->delete($polski_table, ['product_id' => $polski_id]);
$polski_product->delete(true);

if ($polski_failures !== []) {
    WP_CLI::error("Omnibus iworks import check failed:\n - " . implode("\n - ", $polski_failures));
}

WP_CLI::success('Omnibus iworks import check passed.');
