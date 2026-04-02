<?php
/**
 * Withdrawal confirmation email (HTML).
 *
 * @var WC_Order                              $order
 * @var \Spolszczony\Model\WithdrawalRequest  $request
 * @var string                                $email_heading
 * @var string                                $additional_content
 * @var bool                                  $sent_to_admin
 * @var bool                                  $plain_text
 * @var WC_Email                              $email
 *
 * @package Spolszczony/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

do_action('woocommerce_email_header', $email_heading, $email);
?>

<p>
    <?php
    printf(
        /* translators: %s: customer first name */
        esc_html__('Dear %s,', 'spolszczony'),
        esc_html($order->get_billing_first_name()),
    );
    ?>
</p>

<p>
    <?php
    printf(
        /* translators: %s: order number */
        esc_html__('Your withdrawal request for order #%s has been confirmed.', 'spolszczony'),
        esc_html($order->get_order_number()),
    );
    ?>
</p>

<?php if ($request->reason) : ?>
<p>
    <strong><?php esc_html_e('Your reason:', 'spolszczony'); ?></strong><br />
    <?php echo esc_html($request->reason); ?>
</p>
<?php endif; ?>

<p>
    <?php esc_html_e('Please return the items to the following address within 14 days:', 'spolszczony'); ?>
</p>

<p>
    <?php echo wp_kses_post(get_option('woocommerce_store_address', '')); ?><br />
    <?php echo wp_kses_post(get_option('woocommerce_store_address_2', '')); ?><br />
    <?php echo wp_kses_post(get_option('woocommerce_store_postcode', '') . ' ' . get_option('woocommerce_store_city', '')); ?>
</p>

<?php if ($additional_content) : ?>
    <p><?php echo wp_kses_post($additional_content); ?></p>
<?php endif; ?>

<?php
do_action('woocommerce_email_footer', $email);
