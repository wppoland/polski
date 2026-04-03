<?php
/**
 * Withdrawal confirmation email (plain text).
 *
 * @var WC_Order                              $order
 * @var \Polski\Model\WithdrawalRequest  $request
 * @var string                                $email_heading
 * @var string                                $additional_content
 *
 * @package Polski/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$settings = get_option('polski_withdrawal', []);
$settings = is_array($settings) ? $settings : [];
$greeting = str_replace('{name}', (string) $order->get_billing_first_name(), (string) ($settings['email_greeting'] ?? __('Dzień dobry {name},', 'polski')));
$intro = str_replace('{order_number}', (string) $order->get_order_number(), (string) ($settings['email_intro_text'] ?? __('Twój wniosek o odstąpienie dla zamówienia #{order_number} został potwierdzony.', 'polski')));

echo "= " . wp_strip_all_tags($email_heading) . " =\n\n";
echo esc_html($greeting) . "\n\n";
echo esc_html($intro) . "\n\n";

if ($request->reason) {
    echo esc_html((string) ($settings['email_reason_label'] ?? __('Twój powód', 'polski'))) . "\n";
    echo esc_html($request->reason) . "\n\n";
}

echo esc_html((string) ($settings['email_return_instruction'] ?? __('Odeślij produkty na poniższy adres w ciągu 14 dni:', 'polski'))) . "\n";
echo wp_strip_all_tags(get_option('woocommerce_store_address', '')) . "\n";
echo wp_strip_all_tags(get_option('woocommerce_store_address_2', '')) . "\n";
echo wp_strip_all_tags(get_option('woocommerce_store_postcode', '') . ' ' . get_option('woocommerce_store_city', '')) . "\n\n";

if ($additional_content) {
    echo wp_strip_all_tags($additional_content) . "\n";
}
