<?php
/**
 * One-click withdrawal confirmation page.
 *
 * This template can be overridden by copying it to yourtheme/polski/account/withdrawal-confirm.php.
 *
 * @var WC_Order $polski_order
 * @var array    $polski_settings
 *
 * @package Polski
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
$polski_orderNumber = $polski_order->get_order_number();
$polski_orderDate   = $polski_order->get_date_created();
$polski_orderId     = $polski_order->get_id();
?>

<div class="polski-withdrawal-confirm">
    <h2>
        <?php
        printf(
            /* translators: %s: order number */
            esc_html__('Withdrawal request for order #%s', 'polski'),
            esc_html($polski_orderNumber),
        );
        ?>
    </h2>

    <div class="polski-withdrawal-confirm__order-details">
        <table class="shop_table shop_table_responsive">
            <thead>
                <tr>
                    <th><?php esc_html_e('Order', 'polski'); ?></th>
                    <th><?php esc_html_e('Date', 'polski'); ?></th>
                    <th><?php esc_html_e('Amount', 'polski'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td data-title="<?php esc_attr_e('Order', 'polski'); ?>">
                        #<?php echo esc_html($polski_orderNumber); ?>
                    </td>
                    <td data-title="<?php esc_attr_e('Date', 'polski'); ?>">
                        <?php echo $polski_orderDate !== null ? esc_html(wc_format_datetime($polski_orderDate)) : '&mdash;'; ?>
                    </td>
                    <td data-title="<?php esc_attr_e('Amount', 'polski'); ?>">
                        <?php echo wp_kses_post($polski_order->get_formatted_order_total()); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="polski-withdrawal-confirm__items">
        <h3><?php esc_html_e('Products in this order', 'polski'); ?></h3>
        <table class="shop_table shop_table_responsive">
            <thead>
                <tr>
                    <th><?php esc_html_e('Product', 'polski'); ?></th>
                    <th><?php esc_html_e('Quantity', 'polski'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($polski_order->get_items() as $polski_item) : ?>
                    <?php if (! $polski_item instanceof WC_Order_Item_Product) { continue; } ?>
                    <tr>
                        <td data-title="<?php esc_attr_e('Product', 'polski'); ?>">
                            <?php echo esc_html($polski_item->get_name()); ?>
                        </td>
                        <td data-title="<?php esc_attr_e('Quantity', 'polski'); ?>">
                            <?php echo esc_html((string) $polski_item->get_quantity()); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="polski-withdrawal-confirm__message">
        <p>
            <?php
            printf(
                /* translators: %s: order number */
                esc_html__('Are you sure you want to submit a withdrawal request for order #%s?', 'polski'),
                esc_html($polski_orderNumber),
            );
            ?>
        </p>
    </div>

    <form method="post" class="polski-withdrawal-confirm__form">
        <?php wp_nonce_field('polski_confirm_withdrawal_' . $polski_orderId, '_polski_confirm_withdrawal'); ?>

        <p class="polski-withdrawal-confirm__actions">
            <button type="submit" class="button alt">
                <?php echo esc_html($polski_settings['oneclick_confirm_text'] ?? __('Confirm withdrawal request', 'polski')); ?>
            </button>
            <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>" class="button">
                <?php esc_html_e('Cancel', 'polski'); ?>
            </a>
        </p>
    </form>
</div>
