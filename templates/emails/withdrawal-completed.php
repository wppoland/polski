<?php
/**
 * Withdrawal completed e-mail (HTML).
 *
 * @var WC_Order                          $order
 * @var \Polski\Model\WithdrawalRequest   $request
 * @var string                            $email_heading
 * @var string                            $additional_content
 * @var bool                              $sent_to_admin
 * @var bool                              $plain_text
 * @var WC_Email                          $email
 *
 * @package Polski/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$declaration_id = sprintf('POL-WD-%06d', $request->id);
$completed_at = $request->completedAt?->format(get_option('date_format') . ' H:i') ?? '';

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking WooCommerce core email header hook for template integration.
do_action('woocommerce_email_header', $email_heading, $email);
?>

<p>
    <?php
    printf(
        /* translators: %s = customer first name */
        esc_html__('Hello %s,', 'polski'),
        esc_html((string) $order->get_billing_first_name()),
    );
    ?>
</p>

<p>
    <?php
    printf(
        /* translators: 1: order number, 2: declaration id */
        esc_html__('We are glad to tell you that your withdrawal declaration (%2$s) for order #%1$s has been settled.', 'polski'),
        esc_html((string) $order->get_order_number()),
        esc_html($declaration_id),
    );
    ?>
</p>

<table cellspacing="0" cellpadding="6" border="1" style="border-collapse: collapse; width: 100%; margin: 16px 0;">
    <tbody>
        <tr>
            <th align="left" width="40%"><?php esc_html_e('Declaration number', 'polski'); ?></th>
            <td><strong><?php echo esc_html($declaration_id); ?></strong></td>
        </tr>
        <tr>
            <th align="left"><?php esc_html_e('Settlement date', 'polski'); ?></th>
            <td><?php echo esc_html($completed_at); ?></td>
        </tr>
        <?php if ($request->refundAmount !== null) : ?>
            <tr>
                <th align="left"><?php esc_html_e('Refund amount', 'polski'); ?></th>
                <td>
                    <strong>
                        <?php echo wp_kses_post(wc_price((float) $request->refundAmount, ['currency' => $order->get_currency()])); ?>
                    </strong>
                </td>
            </tr>
        <?php endif; ?>
        <tr>
            <th align="left"><?php esc_html_e('Order', 'polski'); ?></th>
            <td>#<?php echo esc_html((string) $order->get_order_number()); ?></td>
        </tr>
    </tbody>
</table>

<p>
    <?php esc_html_e('The refund will go back to the payment method used for the purchase. Depending on your bank it may take a few working days to appear.', 'polski'); ?>
</p>

<?php if ($additional_content) : ?>
    <p><?php echo wp_kses_post($additional_content); ?></p>
<?php endif; ?>

<?php
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking WooCommerce core email footer hook for template integration.
do_action('woocommerce_email_footer', $email);
