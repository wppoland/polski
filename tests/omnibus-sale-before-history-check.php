<?php

// NOTE: no `declare(strict_types=1)` here on purpose - `wp eval-file` wraps this
// file in eval(), where a declare() can't be the first statement.

/**
 * A product already on sale when history began still has a price it was
 * reduced from.
 *
 * Every row is a sale row, so nothing exists before the reduction. The notice
 * must show the regular price stored on the row that opens the sale, not
 * vanish and not repeat the sale price. No history at all stays null.
 *
 * Run: wp eval-file wp-content/plugins/<dir>/tests/omnibus-sale-before-history-check.php
 */

$polski_failures = [];
$polski_check = static function (string $label, bool $ok) use (&$polski_failures): void {
    if (! $ok) {
        $polski_failures[] = $label;
    }
};

global $wpdb;
$polski_table = $wpdb->prefix . 'polski_price_history';
$polski_currency = get_woocommerce_currency();

$polski_product = new WC_Product_Simple();
$polski_product->set_name('Omnibus sale-before-history probe');
$polski_product->set_status('publish');
$polski_product->set_regular_price('20.00');
$polski_product->save();
$polski_id = $polski_product->get_id();

$wpdb->query($wpdb->prepare('DELETE FROM %i WHERE product_id = %d', $polski_table, $polski_id));

$polski_repo = Polski\Plugin::instance()->container()->get(Polski\Repository\OmnibusPriceRepository::class);
wp_cache_flush();

$polski_check(
    'no history at all gives no lowest price',
    $polski_repo->findLowestEffective($polski_id, 30, gmdate('Y-m-d H:i:s')) === null,
);

foreach ([[20.00, 15.00, 120], [20.00, 15.00, 60]] as [$polski_price, $polski_sale, $polski_days]) {
    $wpdb->insert($polski_table, [
        'product_id' => $polski_id,
        'price' => $polski_price,
        'sale_price' => $polski_sale,
        'price_type' => 'sale',
        'currency' => $polski_currency,
        'recorded_at' => gmdate('Y-m-d H:i:s', time() - $polski_days * DAY_IN_SECONDS),
    ]);
}

$polski_start = $polski_repo->findSaleRunStart($polski_id, 15.00);
$polski_lowest = $polski_repo->findLowestEffective($polski_id, 30, $polski_start);
$polski_check(
    'a sale older than its history reports the regular price it was reduced from (20)',
    $polski_lowest !== null && abs($polski_lowest->effectivePrice() - 20.00) < 0.001,
);

$wpdb->query($wpdb->prepare('DELETE FROM %i WHERE product_id = %d', $polski_table, $polski_id));
wp_delete_post($polski_id, true);

if ($polski_failures !== []) {
    echo "FAIL  omnibus-sale-before-history\n  - " . implode("\n  - ", $polski_failures) . "\n";
    exit(1);
}

echo "OK: a sale older than its history shows the regular price it was reduced from.\n";
