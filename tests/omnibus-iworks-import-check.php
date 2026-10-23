<?php

// NOTE: no `declare(strict_types=1)` here on purpose - `wp eval-file` wraps this
// file in eval(), where a declare() can't be the first statement.

/**
 * History kept by the "Omnibus" plugin (iworks) reaches back before Polski's
 * own, and never overrides it.
 *
 * A: Polski saw the product only once its sale was running. The iworks rows
 *    before that fill the window, so the notice shows the pre-sale lowest price.
 * B: Polski has its own history. A 1.41.7-style import left an iworks sale row
 *    inside it (iworks never closes a sale) and a scheduled sale was read from
 *    the raw sale field. Both go; the notice comes from Polski's rows alone.
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
$polski_ago = static fn (int $days): int => time() - $days * DAY_IN_SECONDS;
$polski_rows = static fn (int $id): int => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE product_id = %d', $polski_table, $id));

$polski_log = static function (int $productId, int $ts, string $regular, string $price, string $sale): void {
    $id = wp_insert_post([
        'post_type' => 'iw_omnibus_price_log',
        'post_status' => 'publish',
        'post_parent' => $productId,
        'post_title' => 'probe',
        'post_date_gmt' => gmdate('Y-m-d H:i:s', $ts),
    ]);
    add_post_meta($id, 'price_regular', $regular);
    add_post_meta($id, 'price', $price);
    add_post_meta($id, 'price_sale', $sale);
    add_post_meta($id, 'timestamp', $ts);
};

$polski_new = static function (string $name): WC_Product_Simple {
    $p = new WC_Product_Simple();
    $p->set_name($name);
    $p->set_status('publish');
    $p->set_regular_price('120.00');
    $p->set_sale_price('90.00');
    $p->save();

    return $p;
};

// A: Polski's only row is the running sale, 10 days old.
$polski_a = $polski_new('Omnibus iworks import probe A');
$polski_a_id = $polski_a->get_id();
$wpdb->query($wpdb->prepare('UPDATE %i SET recorded_at = %s WHERE product_id = %d', $polski_table, gmdate('Y-m-d H:i:s', $polski_ago(10)), $polski_a_id));
$polski_log($polski_a_id, $polski_ago(40), '120.00', '120.00', '');
$polski_log($polski_a_id, $polski_ago(20), '120.00', '110.00', '110.00');
$polski_log($polski_a_id, $polski_ago(11), '120.00', '90.00', '90.00');
// iworks v2: a page-view snapshot holding only the selling price.
add_post_meta($polski_a_id, '_iwo_price_lowest', ['price' => '50.00', 'timestamp' => $polski_ago(15), 'type' => 'autosaved']);

// B: Polski's own history, regular for 60 days, sale since 10 days.
$polski_b = $polski_new('Omnibus iworks import probe B');
$polski_b_id = $polski_b->get_id();
// Rewrite its rows in the order Polski writes them, ids rising with time.
$wpdb->delete($polski_table, ['product_id' => $polski_b_id]);
$polski_insert = static function (int $productId, int $days, ?float $sale) use ($wpdb, $polski_table, $polski_ago): void {
    $wpdb->insert($polski_table, ['product_id' => $productId, 'price' => 120, 'sale_price' => $sale, 'price_type' => $sale === null ? 'regular' : 'sale', 'currency' => get_woocommerce_currency(), 'recorded_at' => gmdate('Y-m-d H:i:s', $polski_ago($days))]);
};
$polski_insert($polski_b_id, 60, null);
$polski_insert($polski_b_id, 10, 90.0);
// iworks: an earlier 85 sale it never closed, and a scheduled 90 sale saved 25 days ago.
$polski_log($polski_b_id, $polski_ago(45), '120.00', '85.00', '85.00');
$polski_log($polski_b_id, $polski_ago(25), '120.00', '120.00', '90.00');
// What 1.41.7 wrote from them, after Polski's own rows.
$polski_insert($polski_b_id, 45, 85.0);
$polski_insert($polski_b_id, 25, 90.0);

// Mapping rules.
$polski_check('a record without a selling price is skipped', Polski\Service\IworksOmnibusImport::mapEntry(['price_regular' => '5', 'price_sale' => '4', 'timestamp' => time()], 0, 'PLN', 'v3') === null);
$polski_check('a scheduled sale is a regular price', Polski\Service\IworksOmnibusImport::mapEntry(['price_regular' => '5', 'price' => '5', 'price_sale' => '4', 'timestamp' => time()], 0, 'PLN', 'v3')['sale_price'] === null);
$polski_check('last change with a sale field is skipped', Polski\Service\IworksOmnibusImport::mapEntry(['price' => '5', 'price_sale' => '4', 'timestamp' => time()], 0, 'PLN', 'last_change') === null);

$polski_import = new Polski\Service\IworksOmnibusImport();
$polski_import->run();
$polski_a_after = $polski_rows($polski_a_id);
$polski_b_after = $polski_rows($polski_b_id);
$polski_import->run();
wp_cache_flush();

$polski_check("A: the three iworks rows before Polski's first are added (rows $polski_a_after)", $polski_a_after === 4);
$polski_check("B: only Polski's own two rows remain (rows $polski_b_after)", $polski_b_after === 2);
$polski_check('a second run changes nothing', $polski_rows($polski_a_id) === 4 && $polski_rows($polski_b_id) === 2);

$polski_a_text = wp_strip_all_tags($polski_service->getLowestPriceHtml($polski_a_id));
$polski_check("A: notice shows the pre-sale lowest 110 (got '$polski_a_text')", str_contains($polski_a_text, '110'));
$polski_b_text = wp_strip_all_tags($polski_service->getLowestPriceHtml($polski_b_id));
$polski_check("B: notice shows the regular 120 (got '$polski_b_text')", str_contains($polski_b_text, '120'));

// Clean up.
foreach ([$polski_a, $polski_b] as $polski_product) {
    foreach (get_children(['post_parent' => $polski_product->get_id(), 'post_type' => 'iw_omnibus_price_log', 'fields' => 'ids']) as $polski_child) {
        wp_delete_post($polski_child, true);
    }
    $wpdb->delete($polski_table, ['product_id' => $polski_product->get_id()]);
    $polski_product->delete(true);
}

if ($polski_failures !== []) {
    WP_CLI::error("Omnibus iworks import check failed:\n - " . implode("\n - ", $polski_failures));
}

WP_CLI::success('Omnibus iworks import check passed.');
