<?php
/**
 * Consumer withdrawal (return) request form.
 *
 * This template can be overridden by copying it to yourtheme/spolszczony/forms/withdrawal-form.php.
 *
 * @var \WC_Order               $order  The order to withdraw.
 * @var array<string, array>    $fields Form fields.
 * @var string                  $action_url Form action URL.
 *
 * @package Spolszczony/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<div class="spolszczony-withdrawal-form">
    <h3><?php esc_html_e('Withdrawal Request', 'spolszczony'); ?></h3>

    <p class="spolszczony-withdrawal-form__info">
        <?php
        printf(
            /* translators: 1: order number, 2: order date */
            esc_html__('You are requesting withdrawal from order #%1$s placed on %2$s.', 'spolszczony'),
            esc_html($order->get_order_number()),
            esc_html($order->get_date_created()->date_i18n(get_option('date_format'))),
        );
        ?>
    </p>

    <p class="spolszczony-withdrawal-form__notice">
        <?php esc_html_e('According to Polish law, you have the right to withdraw from the contract within 14 days without giving any reason.', 'spolszczony'); ?>
    </p>

    <form method="post" action="<?php echo esc_url($action_url); ?>">
        <?php wp_nonce_field('spolszczony_withdrawal_' . $order->get_id()); ?>
        <input type="hidden" name="spolszczony_withdrawal" value="<?php echo esc_attr((string) $order->get_id()); ?>" />

        <h4><?php esc_html_e('Order Items', 'spolszczony'); ?></h4>
        <table class="spolszczony-withdrawal-form__items shop_table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Product', 'spolszczony'); ?></th>
                    <th><?php esc_html_e('Quantity', 'spolszczony'); ?></th>
                    <th><?php esc_html_e('Price', 'spolszczony'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order->get_items() as $item) : ?>
                    <?php if (! $item instanceof WC_Order_Item_Product) { continue; } ?>
                    <?php
                    $product = $item->get_product();
                    $exempt = $product instanceof WC_Product && $product->get_meta('_spolszczony_withdrawal_exempt', true) === 'yes';
                    ?>
                    <tr class="<?php echo $exempt ? 'spolszczony-withdrawal-form__item--exempt' : ''; ?>">
                        <td>
                            <?php echo esc_html($item->get_name()); ?>
                            <?php if ($exempt) : ?>
                                <br><small><?php esc_html_e('(Not eligible for withdrawal)', 'spolszczony'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html((string) $item->get_quantity()); ?></td>
                        <td><?php echo wp_kses_post($order->get_formatted_line_subtotal($item)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php foreach ($fields as $key => $field) : ?>
            <p class="form-row form-row-wide">
                <label for="spolszczony_withdrawal_<?php echo esc_attr($key); ?>">
                    <?php echo esc_html($field['label']); ?>
                </label>
                <?php if (($field['type'] ?? 'text') === 'textarea') : ?>
                    <textarea
                        name="spolszczony_withdrawal_<?php echo esc_attr($key); ?>"
                        id="spolszczony_withdrawal_<?php echo esc_attr($key); ?>"
                        class="input-text"
                        rows="4"
                        <?php echo ($field['required'] ?? false) ? 'required' : ''; ?>
                    ></textarea>
                <?php else : ?>
                    <input
                        type="<?php echo esc_attr($field['type'] ?? 'text'); ?>"
                        name="spolszczony_withdrawal_<?php echo esc_attr($key); ?>"
                        id="spolszczony_withdrawal_<?php echo esc_attr($key); ?>"
                        class="input-text"
                        <?php echo ($field['required'] ?? false) ? 'required' : ''; ?>
                    />
                <?php endif; ?>
            </p>
        <?php endforeach; ?>

        <p class="form-row">
            <button type="submit" class="button alt">
                <?php esc_html_e('Submit Withdrawal Request', 'spolszczony'); ?>
            </button>
        </p>
    </form>
</div>
