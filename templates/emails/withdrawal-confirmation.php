<?php
/**
 * Withdrawal confirmation email (HTML).
 *
 * @var WC_Order                              $order
 * @var \Polski\Model\WithdrawalRequest  $request
 * @var string                                $email_heading
 * @var string                                $additional_content
 * @var bool                                  $sent_to_admin
 * @var bool                                  $plain_text
 * @var WC_Email                              $email
 *
 * @package Polski/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
$settings = get_option('polski_withdrawal', []);
$settings = is_array($settings) ? $settings : [];
$greeting = str_replace('{name}', (string) $order->get_billing_first_name(), (string) ($settings['email_greeting'] ?? __('Dzień dobry {name},', 'polski')));
$intro = str_replace('{order_number}', (string) $order->get_order_number(), (string) ($settings['email_intro_text'] ?? __('Twój wniosek o odstąpienie dla zamówienia #{order_number} został potwierdzony.', 'polski')));

do_action('woocommerce_email_header', $email_heading, $email);
?>

<p>
    <?php echo esc_html($greeting); ?>
</p>

<p>
    <?php echo esc_html($intro); ?>
</p>

<?php if ($request->reason) : ?>
<p>
    <strong><?php echo esc_html((string) ($settings['email_reason_label'] ?? __('Twój powód', 'polski'))); ?>:</strong><br />
    <?php echo esc_html($request->reason); ?>
</p>
<?php endif; ?>

<p>
    <?php echo esc_html((string) ($settings['email_return_instruction'] ?? __('Odeślij produkty na poniższy adres w ciągu 14 dni:', 'polski'))); ?>
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
