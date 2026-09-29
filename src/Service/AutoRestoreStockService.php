<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * Automatically restore product stock when orders are cancelled or refunded.
 *
 * WooCommerce restores stock itself on cancelled and failed orders, but not on
 * a status change to refunded. Only line items still carrying WooCommerce's
 * _reduced_stock meta are restored, so a transition WooCommerce already handled
 * is never restocked twice.
 */
final class AutoRestoreStockService implements HasHooks
{
    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('auto_restore_stock')) {
            return;
        }

        // Transitions from processing/completed/on-hold to cancelled.
        add_action('woocommerce_order_status_processing_to_cancelled', [$this, 'restoreStock']);
        add_action('woocommerce_order_status_completed_to_cancelled', [$this, 'restoreStock']);
        add_action('woocommerce_order_status_on-hold_to_cancelled', [$this, 'restoreStock']);

        // Transitions from processing/completed/on-hold to refunded.
        add_action('woocommerce_order_status_processing_to_refunded', [$this, 'restoreStock']);
        add_action('woocommerce_order_status_completed_to_refunded', [$this, 'restoreStock']);
        add_action('woocommerce_order_status_on-hold_to_refunded', [$this, 'restoreStock']);

        // Transition to failed.
        add_action('woocommerce_order_status_processing_to_failed', [$this, 'restoreStock']);
        add_action('woocommerce_order_status_on-hold_to_failed', [$this, 'restoreStock']);
    }

    /**
     * Restore stock for all items in the order.
     */
    public function restoreStock(int $orderId): void
    {
        if (get_option('woocommerce_manage_stock') !== 'yes') {
            return;
        }

        $order = wc_get_order($orderId);

        if (! $order instanceof \WC_Order) {
            return;
        }

        // Prevent double-restoration.
        if ($order->get_meta('_polski_stock_restored')) {
            return;
        }

        $restored = false;

        /** @var \WC_Order_Item_Product $item */
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            if (! $product instanceof \WC_Product || ! $product->managing_stock()) {
                continue;
            }

            // WooCommerce records the reduced quantity per item and clears it when it restocks.
            $qty = (float) $item->get_meta('_reduced_stock', true);

            if ($qty <= 0) {
                continue;
            }

            $oldStock = $product->get_stock_quantity();
            $newStock = wc_update_product_stock($product, $qty, 'increase');

            if (is_wp_error($newStock)) {
                continue;
            }

            $item->delete_meta_data('_reduced_stock');
            $item->save();

            $order->add_order_note(
                sprintf(
                    /* translators: 1: product name, 2: old stock, 3: new stock */
                    __('Stock restored: %1$s (%2$d -> %3$d)', 'polski'),
                    $product->get_name(),
                    $oldStock,
                    $newStock,
                ),
            );

            /**
             * Fires after stock is restored for a single product.
             *
             * @param \WC_Product            $product
             * @param \WC_Order_Item_Product  $item
             * @param \WC_Order              $order
             */
            do_action('polski/stock/restored', $product, $item, $order);

            $restored = true;
        }

        if ($restored) {
            $order->update_meta_data('_polski_stock_restored', '1');
            $order->save();
            $order->get_data_store()->set_stock_reduced($orderId, false);
        }
    }
}
