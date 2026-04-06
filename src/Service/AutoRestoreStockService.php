<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * Automatically restore product stock when orders are cancelled or refunded.
 *
 * WooCommerce reduces stock on order placement but does not always restore it
 * when the order is subsequently cancelled or refunded. This service ensures
 * stock levels stay accurate by hooking into status transitions.
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

            $qty = apply_filters('woocommerce_order_item_quantity', $item->get_quantity(), $order, $item);

            if ($qty <= 0) {
                continue;
            }

            $oldStock = $product->get_stock_quantity();
            $newStock = wc_update_product_stock($product, $qty, 'increase');

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
        }
    }
}
