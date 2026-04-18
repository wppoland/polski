<?php
/**
 * Withdrawal confirmation email (HTML).
 *
 * @var WC_Order                              $polski_order
 * @var \Polski\Model\WithdrawalRequest  $polski_request
 * @var string                                $polski_email_heading
 * @var string                                $polski_additional_content
 * @var bool                                  $polski_sent_to_admin
 * @var bool                                  $polski_plain_text
 * @var WC_Email                              $polski_email
 *
 * @package Polski/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
$polski_settings = get_option('polski_withdrawal', []);
$polski_settings = is_array($polski_settings) ? $polski_settings : [];
$polski_greeting = str_replace('{name}', (string) $polski_order->get_billing_first_name(), (string) ($polski_settings['email_greeting'] ?? __('Dzień dobry {name},', 'polski')));
$polski_intro = str_replace('{order_number}', (string) $polski_order->get_order_number(), (string) ($polski_settings['email_intro_text'] ?? __('Twój wniosek o odstąpienie dla zamówienia #{order_number} został potwierdzony.', 'polski')));

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking WooCommerce core email header hook for template integration.
do_action('woocommerce_email_header', $polski_email_heading, $polski_email);
?>

<p>
    <?php echo esc_html($polski_greeting); ?>
</p>

<p>
    <?php echo esc_html($polski_intro); ?>
</p>

<?php if ($polski_request->reason) : ?>
<p>
    <strong><?php echo esc_html((string) ($polski_settings['email_reason_label'] ?? __('Twój powód', 'polski'))); ?>:</strong><br />
    <?php echo esc_html($polski_request->reason); ?>
</p>
<?php endif; ?>

<p>
    <?php echo esc_html((string) ($polski_settings['email_return_instruction'] ?? __('Odeślij produkty na poniższy adres w ciągu 14 dni:', 'polski'))); ?>
</p>

<p>
    <?php echo wp_kses_post(get_option('woocommerce_store_address', '')); ?><br />
    <?php echo wp_kses_post(get_option('woocommerce_store_address_2', '')); ?><br />
    <?php echo wp_kses_post(get_option('woocommerce_store_postcode', '') . ' ' . get_option('woocommerce_store_city', '')); ?>
</p>

<?php if ($polski_additional_content) : ?>
    <p><?php echo wp_kses_post($polski_additional_content); ?></p>
<?php endif; ?>

<?php
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking WooCommerce core email footer hook for template integration.
do_action('woocommerce_email_footer', $polski_email);
