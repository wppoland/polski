<?php

// NOTE: no `declare(strict_types=1)` here on purpose - `wp eval-file` wraps this
// file in eval(), where a declare() can't be the first statement.

/**
 * A scheduled sale that has not started yet is not a price the product sells at.
 *
 * Saving a sale price with a future start used to record a sale row at save
 * time. Once the sale started, that row sat inside the 30 days before the start
 * and the notice reported the sale price as its own lowest price.
 *
 * Run: wp eval-file wp-content/plugins/<dir>/tests/omnibus-scheduled-sale-check.php
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

$polski_parent = new WC_Product_Variable();
$polski_parent->set_name('Omnibus scheduled-sale probe (variable)');
$polski_parent->set_status('publish');
$polski_parent->save();

$polski_simple = new WC_Product_Simple();
$polski_variation = new WC_Product_Variation();
$polski_variation->set_parent_id($polski_parent->get_id());

foreach (['simple' => $polski_simple, 'variation' => $polski_variation] as $polski_kind => $polski_product) {
    $polski_product->set_name('Omnibus scheduled-sale probe');
    $polski_product->set_status('publish');
    $polski_product->set_regular_price('84.00');
    $polski_product->save();
    $polski_id = $polski_product->get_id();

    // The regular price has held for 55 days.
    $wpdb->query($wpdb->prepare('UPDATE %i SET recorded_at = %s WHERE product_id = %d', $polski_table, gmdate('Y-m-d H:i:s', time() - 55 * DAY_IN_SECONDS), $polski_id));

    // Schedule a sale that starts in 5 days.
    $polski_product->set_sale_price('70.00');
    $polski_product->set_date_on_sale_from(time() + 5 * DAY_IN_SECONDS);
    $polski_product->save();

    $polski_sale_rows = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE product_id = %d AND sale_price IS NOT NULL', $polski_table, $polski_id));
    $polski_check("$polski_kind: a sale that has not started records no sale row", $polski_sale_rows === 0);

    // Ten days pass, so the start lies 5 days behind us and the sale runs.
    $wpdb->query($wpdb->prepare('UPDATE %i SET recorded_at = DATE_SUB(recorded_at, INTERVAL 10 DAY) WHERE product_id = %d', $polski_table, $polski_id));
    $polski_product->set_date_on_sale_from(time() - 5 * DAY_IN_SECONDS);
    $polski_product->save();
    wp_cache_flush();

    $polski_html = $polski_service->getLowestPriceHtml($polski_id);
    $polski_check("$polski_kind: the notice shows 84, not the sale price 70 (got: " . wp_strip_all_tags($polski_html) . ')', str_contains($polski_html, '84') && ! str_contains($polski_html, '70'));

    $wpdb->query($wpdb->prepare('DELETE FROM %i WHERE product_id = %d', $polski_table, $polski_id));
    wp_delete_post($polski_id, true);
}

wp_delete_post($polski_parent->get_id(), true);

if ($polski_failures !== []) {
    echo "FAIL  omnibus-scheduled-sale\n  - " . implode("\n  - ", $polski_failures) . "\n";
    exit(1);
}

echo "OK: a scheduled sale is not recorded before it starts, so it never becomes its own lowest price.\n";
