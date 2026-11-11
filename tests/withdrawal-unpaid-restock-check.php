<?php

// NOTE: no `declare(strict_types=1)` here on purpose - `wp eval-file` wraps this
// file in eval(), where a declare() can't be the first statement.

/**
 * Completing a withdrawal on an unpaid order (bank transfer on hold) puts the
 * withdrawn items back in stock, once; a paid order is left to the refund.
 *
 * Run: wp eval-file wp-content/plugins/<dir>/tests/withdrawal-unpaid-restock-check.php
 */

global $wpdb;
$polski_failures = [];
$polski_check = static function (string $label, bool $ok) use (&$polski_failures): void {
    if (! $ok) {
        $polski_failures[] = $label;
    }
};

$polski_service = \Polski\Plugin::instance()->container()->get(\Polski\Service\WithdrawalService::class);

$polski_run = static function (string $status) use ($wpdb, $polski_service): array {
    $product = new WC_Product_Simple();
    $product->set_name('Restock check');
    $product->set_regular_price('10');
    $product->set_manage_stock(true);
    $product->set_stock_quantity(5);
    $product->save();

    $order = wc_create_order();
    $itemId = $order->add_product($product, 2);
    $order->calculate_totals();
    $order->set_status($status);
    $order->save();
    wc_reduce_stock_levels($order->get_id());

    $wpdb->insert($wpdb->prefix . 'polski_withdrawals', ['order_id' => $order->get_id(), 'status' => 'confirmed']);
    $withdrawalId = (int) $wpdb->insert_id;
    $wpdb->insert($wpdb->prefix . 'polski_withdrawal_items', ['withdrawal_id' => $withdrawalId, 'order_item_id' => $itemId, 'quantity' => 1]);

    $polski_service->complete($withdrawalId);
    $stock = (int) wc_get_product($product->get_id())->get_stock_quantity();

    // A later cancel of the whole order restores only what is still reduced.
    $cancelled = wc_get_order($order->get_id());
    $cancelled->update_status('cancelled');
    $afterCancel = (int) wc_get_product($product->get_id())->get_stock_quantity();

    $wpdb->delete($wpdb->prefix . 'polski_withdrawal_items', ['withdrawal_id' => $withdrawalId]);
    $wpdb->delete($wpdb->prefix . 'polski_withdrawals', ['id' => $withdrawalId]);
    $cancelled->delete(true);
    $product->delete(true);

    return [$stock, $afterCancel];
};

[$polski_stock, $polski_after_cancel] = $polski_run('on-hold');
$polski_check('unpaid order: the withdrawn item is back in stock (5 - 2 + 1 = 4), got ' . $polski_stock, 4 === $polski_stock);
$polski_check('unpaid order: a later cancel restores only the rest (5), got ' . $polski_after_cancel, 5 === $polski_after_cancel);

[$polski_stock] = $polski_run('processing');
$polski_check('paid order: stock is left to the refund (3), got ' . $polski_stock, 3 === $polski_stock);

if ($polski_failures !== []) {
    echo "FAIL  withdrawal-unpaid-restock\n  - " . implode("\n  - ", $polski_failures) . "\n";
    exit(1);
}

echo "ok    withdrawal-unpaid-restock\n";
