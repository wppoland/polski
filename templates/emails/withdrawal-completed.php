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
        esc_html__('Dzień dobry %s,', 'polski'),
        esc_html((string) $order->get_billing_first_name()),
    );
    ?>
</p>

<p>
    <?php
    printf(
        /* translators: 1: order number, 2: declaration id */
        esc_html__('Z przyjemnością informujemy, że Twoje oświadczenie o odstąpieniu (%2$s) dla zamówienia #%1$s zostało rozliczone.', 'polski'),
        esc_html((string) $order->get_order_number()),
        esc_html($declaration_id),
    );
    ?>
</p>

<table cellspacing="0" cellpadding="6" border="1" style="border-collapse: collapse; width: 100%; margin: 16px 0;">
    <tbody>
        <tr>
            <th align="left" width="40%"><?php esc_html_e('Numer oświadczenia', 'polski'); ?></th>
            <td><strong><?php echo esc_html($declaration_id); ?></strong></td>
        </tr>
        <tr>
            <th align="left"><?php esc_html_e('Data rozliczenia', 'polski'); ?></th>
            <td><?php echo esc_html($completed_at); ?></td>
        </tr>
        <?php if ($request->refundAmount !== null) : ?>
            <tr>
                <th align="left"><?php esc_html_e('Kwota zwrotu', 'polski'); ?></th>
                <td>
                    <strong>
                        <?php echo wp_kses_post(wc_price((float) $request->refundAmount, ['currency' => $order->get_currency()])); ?>
                    </strong>
                </td>
            </tr>
        <?php endif; ?>
        <tr>
            <th align="left"><?php esc_html_e('Zamówienie', 'polski'); ?></th>
            <td>#<?php echo esc_html((string) $order->get_order_number()); ?></td>
        </tr>
    </tbody>
</table>

<p>
    <?php esc_html_e('Zwrot zostanie wykonany na pierwotną metodę płatności użytą przy zakupie. W zależności od banku środki mogą pojawić się na koncie w ciągu kilku dni roboczych.', 'polski'); ?>
</p>

<?php if ($additional_content) : ?>
    <p><?php echo wp_kses_post($additional_content); ?></p>
<?php endif; ?>

<?php
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking WooCommerce core email footer hook for template integration.
do_action('woocommerce_email_footer', $email);
