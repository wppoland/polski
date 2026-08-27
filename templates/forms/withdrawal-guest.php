<?php
/**
 * Authenticated guest withdrawal form (revealed after redeeming the magic-link token).
 *
 * Shows the order summary so the visitor can verify what they are about to
 * withdraw - critical for cognitive accessibility and error prevention
 * (WCAG 3.3.4: review before final submission).
 *
 * @var \WC_Order $polski_order
 * @var string    $polski_token
 * @var string    $polski_email
 * @var string    $polski_nonce
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$polski_currency = $polski_order->get_currency();
$polski_order_date = $polski_order->get_date_created();
?>
<section
    class="polski-withdrawal-guest-form"
    aria-labelledby="polski-withdrawal-guest-title"
    lang="pl"
    style="max-width: 65ch;"
>
    <h2 id="polski-withdrawal-guest-title">
        <?php
        printf(
            /* translators: %s = order number */
            esc_html__('Withdrawal from the contract, order #%s', 'polski'),
            esc_html($polski_order->get_order_number()),
        );
        ?>
    </h2>

    <p>
        <?php
        printf(
            /* translators: %s = email address */
            esc_html__('You are filing this declaration for the address: %s', 'polski'),
            '<strong>' . esc_html($polski_email) . '</strong>',
        );
        ?>
    </p>

    <h3><?php esc_html_e('Items covered by the withdrawal', 'polski'); ?></h3>
    <p style="color:#475569;">
        <?php esc_html_e('Filing this declaration covers the whole order below. If you want to withdraw from only some of the items, contact the shop or log in to your account and file a separate declaration.', 'polski'); ?>
    </p>

    <table class="shop_table" style="width: 100%;">
        <caption class="screen-reader-text" style="position:absolute;left:-9999px;">
            <?php esc_html_e('Order items covered by the declaration.', 'polski'); ?>
        </caption>
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e('Product', 'polski'); ?></th>
                <th scope="col"><?php esc_html_e('Quantity', 'polski'); ?></th>
                <th scope="col"><?php esc_html_e('Value', 'polski'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($polski_order->get_items() as $polski_item) :
                if (! $polski_item instanceof \WC_Order_Item_Product) {
                    continue;
                }
                $polski_product = $polski_item->get_product();
                $polski_attrs = '';
                if ($polski_product instanceof \WC_Product && $polski_product->is_type('variation')) {
                    $polski_attrs = wc_get_formatted_variation($polski_product, true, true, false);
                }
                ?>
                <tr>
                    <td data-label="<?php esc_attr_e('Product', 'polski'); ?>">
                        <?php echo esc_html((string) $polski_item->get_name()); ?>
                        <?php if ($polski_attrs !== '') : ?>
                            <br><span style="color:#475569;"><?php echo esc_html($polski_attrs); ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Quantity', 'polski'); ?>">
                        <?php echo esc_html((string) $polski_item->get_quantity()); ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Value', 'polski'); ?>">
                        <?php echo wp_kses_post(wc_price((float) $polski_item->get_total(), ['currency' => $polski_currency])); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2" scope="row" style="text-align: right;">
                    <?php esc_html_e('Order value:', 'polski'); ?>
                </th>
                <td>
                    <strong><?php echo wp_kses_post(wc_price((float) $polski_order->get_total(), ['currency' => $polski_currency])); ?></strong>
                </td>
            </tr>
            <?php if ($polski_order_date !== null) : ?>
                <tr>
                    <th colspan="2" scope="row" style="text-align: right;">
                        <?php esc_html_e('Order date:', 'polski'); ?>
                    </th>
                    <td><?php echo esc_html(wp_date((string) get_option('date_format'), $polski_order_date->getTimestamp())); ?></td>
                </tr>
            <?php endif; ?>
        </tfoot>
    </table>

    <p style="color:#475569;">
        <?php esc_html_e('Once you send the form you will get a confirmation email with the declaration number and a summary of the order.', 'polski'); ?>
    </p>

    <form method="post" action="" novalidate>
        <p>
            <label for="polski_withdrawal_reason">
                <?php esc_html_e('Reason for withdrawal (optional)', 'polski'); ?>
            </label>
            <textarea
                id="polski_withdrawal_reason"
                name="polski_withdrawal_reason"
                rows="4"
                style="width: 100%; max-width: 60ch;"
                aria-describedby="polski_withdrawal_reason_help"
            ></textarea>
            <small id="polski_withdrawal_reason_help" style="display:block; color:#475569;">
                <?php esc_html_e('A reason is not required; withdrawal needs no justification.', 'polski'); ?>
            </small>
        </p>

        <p style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="polski_guest_nonce" value="<?php echo esc_attr($polski_nonce); ?>">
            <button
                type="submit"
                name="polski_guest_submit"
                value="1"
                class="button button-primary"
            >
                <?php esc_html_e('File the declaration and email me the confirmation', 'polski'); ?>
            </button>
        </p>
    </form>
</section>
