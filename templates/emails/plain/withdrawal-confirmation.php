<?php
/**
 * Withdrawal confirmation email (plain text).
 *
 * Mirror of the HTML template — captures the same frozen declaration snapshot so
 * the message can serve as a record on a durable medium.
 *
 * @var WC_Order                          $polski_order
 * @var \Polski\Model\WithdrawalRequest   $polski_request
 * @var string                            $polski_email_heading
 * @var string                            $polski_additional_content
 *
 * @package Polski/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$polski_settings = get_option('polski_withdrawal', []);
$polski_settings = is_array($polski_settings) ? $polski_settings : [];

$polski_greeting = str_replace(
    '{name}',
    (string) $polski_order->get_billing_first_name(),
    (string) ($polski_settings['email_greeting'] ?? __('Dzień dobry {name},', 'polski')),
);
$polski_intro = str_replace(
    '{order_number}',
    (string) $polski_order->get_order_number(),
    (string) ($polski_settings['email_intro_text'] ?? __('Twój wniosek o odstąpienie dla zamówienia #{order_number} został zarejestrowany.', 'polski')),
);

$polski_declaration_id = sprintf('POL-WD-%06d', $polski_request->id);
$polski_filed_at = $polski_request->requestedAt->date_i18n(get_option('date_format') . ' H:i');
$polski_currency = $polski_order->get_currency();
$polski_order_date = $polski_order->get_date_created();
$polski_order_date_str = $polski_order_date !== null ? $polski_order_date->date_i18n(get_option('date_format')) : '';

echo "= " . esc_html(wp_strip_all_tags($polski_email_heading)) . " =\n\n";
echo esc_html($polski_greeting) . "\n\n";
echo esc_html($polski_intro) . "\n\n";

echo esc_html(str_repeat('-', 60)) . "\n";
echo esc_html__('Declaration ID', 'polski') . ': ' . esc_html($polski_declaration_id) . "\n";
echo esc_html__('Filed at', 'polski') . ': ' . esc_html($polski_filed_at) . "\n";
echo esc_html__('Order', 'polski') . ': #' . esc_html((string) $polski_order->get_order_number()) . "\n";
echo esc_html__('Order date', 'polski') . ': ' . esc_html($polski_order_date_str) . "\n";
echo esc_html__('Buyer', 'polski') . ': ' . esc_html(trim($polski_order->get_billing_first_name() . ' ' . $polski_order->get_billing_last_name())) . "\n";
echo '         ' . esc_html((string) $polski_order->get_billing_email()) . "\n";
echo esc_html(str_repeat('-', 60)) . "\n\n";

if ($polski_request->reason) {
    echo esc_html((string) ($polski_settings['email_reason_label'] ?? __('Twój powód', 'polski'))) . ":\n";
    echo esc_html($polski_request->reason) . "\n\n";
}

echo esc_html__('Items covered by this declaration', 'polski') . ":\n";
foreach ($polski_order->get_items() as $polski_item) {
    if (! $polski_item instanceof \WC_Order_Item_Product) {
        continue;
    }
    $polski_product = $polski_item->get_product();
    $polski_attrs = '';
    if ($polski_product instanceof \WC_Product && $polski_product->is_type('variation')) {
        $polski_attrs = wc_get_formatted_variation($polski_product, true, true, false);
    }
    echo '- ' . esc_html((string) $polski_item->get_name());
    if ($polski_attrs !== '') {
        echo ' (' . esc_html($polski_attrs) . ')';
    }
    echo ' x ' . esc_html((string) $polski_item->get_quantity());
    echo ' = ' . esc_html(wp_strip_all_tags(wc_price((float) $polski_item->get_total(), ['currency' => $polski_currency])));
    echo "\n";
}

echo "\n";
echo esc_html__('Order total', 'polski') . ': ' . esc_html(wp_strip_all_tags(wc_price((float) $polski_order->get_total(), ['currency' => $polski_currency]))) . "\n\n";

echo esc_html((string) ($polski_settings['email_return_instruction'] ?? __('Odeślij produkty na poniższy adres w ciągu 14 dni od dnia złożenia oświadczenia:', 'polski'))) . "\n";
echo esc_html(wp_strip_all_tags((string) get_option('woocommerce_store_address', ''))) . "\n";
echo esc_html(wp_strip_all_tags((string) get_option('woocommerce_store_address_2', ''))) . "\n";
echo esc_html(wp_strip_all_tags((string) get_option('woocommerce_store_postcode', '') . ' ' . (string) get_option('woocommerce_store_city', ''))) . "\n\n";

echo esc_html((string) ($polski_settings['email_durable_medium_notice'] ?? __(
    'Zachowaj tę wiadomość jako potwierdzenie złożenia oświadczenia. Zawiera ona niezbędne dane oświadczenia (numer, datę i czas złożenia, podsumowanie zamówienia).',
    'polski',
))) . "\n\n";

if ($polski_additional_content) {
    echo esc_html(wp_strip_all_tags($polski_additional_content)) . "\n";
}
