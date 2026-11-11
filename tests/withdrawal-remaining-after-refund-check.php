<?php

// NOTE: no `declare(strict_types=1)` here on purpose - `wp eval-file` wraps this
// file in eval(), where a declare() can't be the first statement.

/**
 * Items the shop refunded by hand cannot be withdrawn again, while a refund
 * booked for a withdrawal is not counted twice.
 *
 * Run: wp eval-file wp-content/plugins/<dir>/tests/withdrawal-remaining-after-refund-check.php
 */

global $wpdb;
$polski_failures = [];
$polski_check = static function (string $label, bool $ok) use (&$polski_failures): void {
    if (! $ok) {
        $polski_failures[] = $label;
    }
};

$polski_product = new WC_Product_Simple();
$polski_product->set_name('Remaining check');
$polski_product->set_regular_price('10');
$polski_product->save();

$polski_order = wc_create_order();
$polski_item_id = $polski_order->add_product($polski_product, 4);
$polski_order->calculate_totals();
$polski_order->save();

$polski_service = \Polski\Plugin::instance()->container()->get(\Polski\Service\WithdrawalService::class);
$polski_remaining = static function () use ($polski_service, $polski_order): float {
    $rows = $polski_service->getRemainingItems(wc_get_order($polski_order->get_id()));

    return (float) ($rows[0]['quantity_remaining'] ?? 0);
};

$polski_refund = static fn (int $qty) => wc_create_refund([
    'order_id' => $polski_order->get_id(),
    'amount' => (string) (10 * $qty),
    'line_items' => [$polski_item_id => ['qty' => $qty, 'refund_total' => 10 * $qty]],
]);

$polski_check('nothing refunded: all 4 remain', 4.0 === $polski_remaining());

$polski_refund(1);
$polski_check('a manual refund of 1 leaves 3', 3.0 === $polski_remaining());

// A withdrawal of 1 whose refund was booked by the PRO refund flow.
$polski_booked = $polski_refund(1);
$wpdb->insert($wpdb->prefix . 'polski_withdrawals', ['order_id' => $polski_order->get_id(), 'status' => 'completed', 'refund_id' => $polski_booked->get_id()]);
$polski_withdrawal_id = (int) $wpdb->insert_id;
$wpdb->insert($wpdb->prefix . 'polski_withdrawal_items', ['withdrawal_id' => $polski_withdrawal_id, 'order_item_id' => $polski_item_id, 'quantity' => 1]);
$polski_check('a refund booked for a withdrawal is counted once: 2 remain', 2.0 === $polski_remaining());

$wpdb->delete($wpdb->prefix . 'polski_withdrawal_items', ['withdrawal_id' => $polski_withdrawal_id]);
$wpdb->delete($wpdb->prefix . 'polski_withdrawals', ['id' => $polski_withdrawal_id]);
$polski_order->delete(true);
$polski_product->delete(true);

if ($polski_failures !== []) {
    echo "FAIL  withdrawal-remaining-after-refund\n  - " . implode("\n  - ", $polski_failures) . "\n";
    exit(1);
}

echo "ok    withdrawal-remaining-after-refund\n";
