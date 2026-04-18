<?php
/**
 * Withdrawal confirmation email (plain text).
 *
 * @var WC_Order                              $polski_order
 * @var \Polski\Model\WithdrawalRequest  $polski_request
 * @var string                                $polski_email_heading
 * @var string                                $polski_additional_content
 *
 * @package Polski/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
$polski_settings = get_option('polski_withdrawal', []);
$polski_settings = is_array($polski_settings) ? $polski_settings : [];
$polski_greeting = str_replace('{name}', (string) $polski_order->get_billing_first_name(), (string) ($polski_settings['email_greeting'] ?? __('Dzień dobry {name},', 'polski')));
$polski_intro = str_replace('{order_number}', (string) $polski_order->get_order_number(), (string) ($polski_settings['email_intro_text'] ?? __('Twój wniosek o odstąpienie dla zamówienia #{order_number} został potwierdzony.', 'polski')));

echo "= " . esc_html(wp_strip_all_tags($polski_email_heading)) . " =\n\n";
echo esc_html($polski_greeting) . "\n\n";
echo esc_html($polski_intro) . "\n\n";

if ($polski_request->reason) {
    echo esc_html((string) ($polski_settings['email_reason_label'] ?? __('Twój powód', 'polski'))) . "\n";
    echo esc_html($polski_request->reason) . "\n\n";
}

echo esc_html((string) ($polski_settings['email_return_instruction'] ?? __('Odeślij produkty na poniższy adres w ciągu 14 dni:', 'polski'))) . "\n";
echo esc_html(wp_strip_all_tags(get_option('woocommerce_store_address', ''))) . "\n";
echo esc_html(wp_strip_all_tags(get_option('woocommerce_store_address_2', ''))) . "\n";
echo esc_html(wp_strip_all_tags(get_option('woocommerce_store_postcode', '') . ' ' . get_option('woocommerce_store_city', ''))) . "\n\n";

if ($polski_additional_content) {
    echo esc_html(wp_strip_all_tags($polski_additional_content)) . "\n";
}
