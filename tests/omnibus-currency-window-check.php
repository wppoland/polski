<?php

// NOTE: no `declare(strict_types=1)` here on purpose - `wp eval-file` wraps this
// file in eval(), where a declare() can't be the first statement.

/**
 * The lowest-price notice must compare money of the same kind.
 *
 * The price history stores the currency each snapshot was recorded in, but the
 * lookups ignored that column. A shop that switched currency, or a
 * multi-currency plugin that switches it per request, would be shown a lowest
 * price in one currency beside a selling price in another, which is not a
 * comparison and not what the Omnibus directive asks for.
 *
 * Run: wp eval-file tests/omnibus-currency-window-check.php
 */

$polski_failures = [];

$polski_check = static function (string $label, bool $ok) use (&$polski_failures): void {
    if (! $ok) {
        $polski_failures[] = $label;
    }
};

$polski_product = new WC_Product_Simple();
$polski_product->set_name('Omnibus currency probe');
$polski_product->set_status('publish');
$polski_product->set_regular_price('100.00');
$polski_product->save();
$polski_id = $polski_product->get_id();

global $wpdb;
$polski_table = $wpdb->prefix . 'polski_price_history';
$polski_now = current_time('mysql', true);
$polski_currency = get_woocommerce_currency();

$wpdb->query($wpdb->prepare("DELETE FROM %i WHERE product_id = %d", $polski_table, $polski_id));

// One snapshot in the shop's currency, one much cheaper in another.
foreach ([[80.00, $polski_currency], [1.00, 'XTS']] as [$polski_amount, $polski_code]) {
    $wpdb->insert($polski_table, [
        'product_id' => $polski_id,
        'price' => $polski_amount,
        'sale_price' => null,
        'price_type' => 'regular',
        'currency' => $polski_code,
        'recorded_at' => $polski_now,
    ]);
}

wp_cache_flush();

$polski_repo = Polski\Plugin::instance()->container()->get(Polski\Repository\OmnibusPriceRepository::class);

$polski_lowest = $polski_repo->findLowest($polski_id, 30);
$polski_check(
    'the cheaper row in another currency is not the lowest price',
    $polski_lowest !== null && (float) $polski_lowest->price === 80.00,
);

$polski_effective = $polski_repo->findLowestEffective($polski_id, 30);
$polski_check(
    'the same holds for the windowed lookup',
    $polski_effective !== null && (float) $polski_effective->price === 80.00,
);

$polski_batch = $polski_repo->findLowestEffectiveBatch([$polski_id], 30);
$polski_check(
    'and for the batch lookup an archive uses',
    isset($polski_batch[$polski_id]) && (float) $polski_batch[$polski_id]->price === 80.00,
);

$polski_history = $polski_repo->findHistory($polski_id, 30);
$polski_check(
    'the chart plots one currency only',
    count($polski_history) === 1 && $polski_history[0]->currency === $polski_currency,
);

$wpdb->query($wpdb->prepare("DELETE FROM %i WHERE product_id = %d", $polski_table, $polski_id));
wp_delete_post($polski_id, true);

if ($polski_failures !== []) {
    echo "FAIL  omnibus-currency-window\n  - " . implode("\n  - ", $polski_failures) . "\n";
    exit(1);
}

echo "OK: the lowest price is only ever compared against the same currency.\n";
