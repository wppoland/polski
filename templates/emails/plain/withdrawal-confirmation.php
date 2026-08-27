<?php
/**
 * Withdrawal confirmation email (plain text).
 *
 * Mirror of the HTML template - captures the same frozen declaration snapshot so
 * the message can serve as a record on a durable medium.
 *
 * @var \WC_Order|null                     $order
 * @var \Polski\Model\WithdrawalRequest|null $request
 * @var string                            $email_heading
 * @var string                            $additional_content
 * @var bool                              $sent_to_admin
 * @var bool                              $plain_text
 * @var \WC_Email|null                    $email
 *
 * @package Polski/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$order = $order ?? $polski_order ?? null;
$request = $request ?? $polski_request ?? null;
$email_heading = $email_heading ?? $polski_email_heading ?? '';
$additional_content = $additional_content ?? $polski_additional_content ?? '';
$email = $email ?? $polski_email ?? null;

if (! $order instanceof \WC_Order || ! $request instanceof \Polski\Model\WithdrawalRequest) {
    return;
}

$polski_settings = get_option('polski_withdrawal', []);
$polski_settings = is_array($polski_settings) ? $polski_settings : [];

$greeting = str_replace(
    '{name}',
    (string) $order->get_billing_first_name(),
    (string) ($polski_settings['email_greeting'] ?? __('Hello {name},', 'polski')),
);
$intro = str_replace(
    '{order_number}',
    (string) $order->get_order_number(),
    (string) ($polski_settings['email_intro_text'] ?? __('Your withdrawal declaration for order #{order_number} has been registered.', 'polski')),
);

$declaration_id = sprintf('POL-WD-%06d', $request->id);
$filed_at = wp_date((string) get_option('date_format') . ' H:i', $request->requestedAt->getTimestamp());
$currency = $order->get_currency();
$order_date = $order->get_date_created();
$order_date_str = $order_date instanceof \WC_DateTime ? wp_date((string) get_option('date_format'), $order_date->getTimestamp()) : '';

echo "= " . esc_html(wp_strip_all_tags($email_heading)) . " =\n\n";
echo esc_html($greeting) . "\n\n";
echo esc_html($intro) . "\n\n";

echo esc_html(str_repeat('-', 60)) . "\n";
echo esc_html__('Declaration ID', 'polski') . ': ' . esc_html($declaration_id) . "\n";
echo esc_html__('Filed at', 'polski') . ': ' . esc_html($filed_at) . "\n";
echo esc_html__('Order', 'polski') . ': #' . esc_html((string) $order->get_order_number()) . "\n";
echo esc_html__('Order date', 'polski') . ': ' . esc_html($order_date_str) . "\n";
echo esc_html__('Buyer', 'polski') . ': ' . esc_html(trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name())) . "\n";
echo '         ' . esc_html((string) $order->get_billing_email()) . "\n";
echo esc_html(str_repeat('-', 60)) . "\n\n";

if ($request->reason) {
    echo esc_html((string) ($polski_settings['email_reason_label'] ?? __('Your reason', 'polski'))) . ":\n";
    echo esc_html($request->reason) . "\n\n";
}

echo esc_html__('Items covered by this declaration', 'polski') . ":\n";
foreach ($order->get_items() as $item) {
    if (! $item instanceof \WC_Order_Item_Product) {
        continue;
    }
    $product = $item->get_product();
    $attrs = '';
    if ($product instanceof \WC_Product && $product->is_type('variation')) {
        $attrs = wc_get_formatted_variation($product, true, true, false);
    }
    echo '- ' . esc_html((string) $item->get_name());
    if ($attrs !== '') {
        echo ' (' . esc_html($attrs) . ')';
    }
    echo ' x ' . esc_html((string) $item->get_quantity());
    echo ' = ' . esc_html(wp_strip_all_tags(wc_price((float) $item->get_total(), ['currency' => $currency])));
    echo "\n";
}

echo "\n";
echo esc_html__('Order total', 'polski') . ': ' . esc_html(wp_strip_all_tags(wc_price((float) $order->get_total(), ['currency' => $currency]))) . "\n\n";

echo esc_html((string) ($polski_settings['email_return_instruction'] ?? __('Send the goods back to the address below within 14 days of filing the declaration:', 'polski'))) . "\n";
echo esc_html(wp_strip_all_tags((string) get_option('woocommerce_store_address', ''))) . "\n";
echo esc_html(wp_strip_all_tags((string) get_option('woocommerce_store_address_2', ''))) . "\n";
echo esc_html(wp_strip_all_tags((string) get_option('woocommerce_store_postcode', '') . ' ' . (string) get_option('woocommerce_store_city', ''))) . "\n\n";

echo esc_html((string) ($polski_settings['email_durable_medium_notice'] ?? __(
    'Keep this message as proof that the declaration was filed. It holds everything the declaration needs: the number, the date and time it was filed, and a summary of the order.',
    'polski',
))) . "\n\n";

if ($additional_content) {
    echo esc_html(wp_strip_all_tags($additional_content)) . "\n";
}
