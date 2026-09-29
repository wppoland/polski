<?php

// NOTE: no `declare(strict_types=1)` here on purpose - `wp eval-file` wraps this
// file in eval(), where a declare() can't be the first statement.

/**
 * A price that held since before the window is still the price in force when
 * the window opens.
 *
 * Rows are written only when the price changes, so a product at 20 for 40 days
 * that goes on sale at 15 today has no row inside the 30-day window. The notice
 * must still say 20, and pruning must not delete that only row.
 *
 * Run: wp eval-file wp-content/plugins/<dir>/tests/omnibus-carry-forward-check.php
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
$polski_product->set_name('Omnibus carry-forward probe');
$polski_product->set_status('publish');
$polski_product->set_regular_price('20.00');
$polski_product->save();
$polski_id = $polski_product->get_id();

$wpdb->query($wpdb->prepare('DELETE FROM %i WHERE product_id = %d', $polski_table, $polski_id));

foreach ([[25.00, null, 50], [20.00, null, 40], [20.00, 15.00, 0]] as [$polski_price, $polski_sale, $polski_days]) {
    $wpdb->insert($polski_table, [
        'product_id' => $polski_id,
        'price' => $polski_price,
        'sale_price' => $polski_sale,
        'price_type' => $polski_sale === null ? 'regular' : 'sale',
        'currency' => $polski_currency,
        'recorded_at' => gmdate('Y-m-d H:i:s', time() - $polski_days * DAY_IN_SECONDS),
    ]);
}

wp_cache_flush();

$polski_repo = Polski\Plugin::instance()->container()->get(Polski\Repository\OmnibusPriceRepository::class);
$polski_start = $polski_repo->findSaleRunStart($polski_id, 15.00);

$polski_lowest = $polski_repo->findLowestEffective($polski_id, 30, $polski_start);
$polski_check(
    'the price in force when the window opens (20) is the lowest before the sale',
    $polski_lowest !== null && abs($polski_lowest->effectivePrice() - 20.00) < 0.001,
);

$polski_history = $polski_repo->findHistory($polski_id, 30);
$polski_check(
    'the chart starts from the price in force, not from an empty window',
    count($polski_history) === 2,
);

$polski_repo->deleteOlderThan(30);
$polski_left = $wpdb->get_col($wpdb->prepare('SELECT price FROM %i WHERE product_id = %d ORDER BY recorded_at', $polski_table, $polski_id));
$polski_check(
    'pruning drops the superseded 25 but keeps the 20 still in force',
    array_map('floatval', $polski_left) === [20.0, 20.0],
);

$wpdb->query($wpdb->prepare('DELETE FROM %i WHERE product_id = %d', $polski_table, $polski_id));
wp_delete_post($polski_id, true);

if ($polski_failures !== []) {
    echo "FAIL  omnibus-carry-forward\n  - " . implode("\n  - ", $polski_failures) . "\n";
    exit(1);
}

echo "OK: the price in force at the window start is carried forward and survives pruning.\n";
