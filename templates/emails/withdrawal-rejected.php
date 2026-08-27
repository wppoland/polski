<?php
/**
 * Withdrawal rejected e-mail (HTML).
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
$rejected_at = $request->rejectedAt?->format(get_option('date_format') . ' H:i') ?? '';
$reason = (string) ($request->rejectedReason ?? '');

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
        /* translators: 1: declaration id, 2: order number */
        esc_html__('We cannot accept your withdrawal declaration (%1$s) for order #%2$s.', 'polski'),
        esc_html($declaration_id),
        esc_html((string) $order->get_order_number()),
    );
    ?>
</p>

<?php if ($reason !== '') : ?>
    <p>
        <strong><?php esc_html_e('Reason for the refusal:', 'polski'); ?></strong><br>
        <?php echo esc_html($reason); ?>
    </p>
<?php endif; ?>

<table cellspacing="0" cellpadding="6" border="1" style="border-collapse: collapse; width: 100%; margin: 16px 0;">
    <tbody>
        <tr>
            <th align="left" width="40%"><?php esc_html_e('Declaration number', 'polski'); ?></th>
            <td><?php echo esc_html($declaration_id); ?></td>
        </tr>
        <tr>
            <th align="left"><?php esc_html_e('Decision date', 'polski'); ?></th>
            <td><?php echo esc_html($rejected_at); ?></td>
        </tr>
        <tr>
            <th align="left"><?php esc_html_e('Order', 'polski'); ?></th>
            <td>#<?php echo esc_html((string) $order->get_order_number()); ?></td>
        </tr>
    </tbody>
</table>

<p>
    <?php esc_html_e('If you think this decision is wrong, reply to this email or contact the shop. We are happy to explain.', 'polski'); ?>
</p>

<?php if ($additional_content) : ?>
    <p><?php echo wp_kses_post($additional_content); ?></p>
<?php endif; ?>

<?php
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking WooCommerce core email footer hook for template integration.
do_action('woocommerce_email_footer', $email);
