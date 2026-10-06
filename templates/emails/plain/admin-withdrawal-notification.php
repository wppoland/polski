<?php
/**
 * Admin notification email for a new withdrawal declaration (plain text).
 *
 * @var \WC_Order|null                       $order
 * @var \Polski\Model\WithdrawalRequest|null $request
 * @var string                              $email_heading
 * @var string                              $additional_content
 * @var bool                                $sent_to_admin
 * @var bool                                $plain_text
 * @var \WC_Email|null                      $email
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

$declaration_id = sprintf('POL-WD-%06d', $request->id);
$filed_at = wp_date((string) get_option('date_format') . ' H:i', $request->requestedAt->getTimestamp());
$currency = $order->get_currency();

echo "= " . esc_html(wp_strip_all_tags($email_heading)) . " =\n\n";

printf(
    /* translators: %s = declaration id */
    esc_html__('A new withdrawal declaration %s has been registered in your store.', 'polski'),
    $declaration_id
);
echo "\n\n";

echo esc_html(str_repeat('-', 60)) . "\n";
echo esc_html__('Declaration ID', 'polski') . ': ' . esc_html($declaration_id) . "\n";
echo esc_html__('Filed at', 'polski') . ': ' . esc_html($filed_at) . "\n";
echo esc_html__('Order', 'polski') . ': #' . esc_html((string) $order->get_order_number()) . "\n";
if (method_exists($order, 'get_edit_order_url')) {
    echo esc_html__('Order URL', 'polski') . ': ' . esc_url($order->get_edit_order_url()) . "\n";
}
echo esc_html__('Buyer', 'polski') . ': ' . esc_html(trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name())) . "\n";
echo '         ' . esc_html((string) $order->get_billing_email()) . "\n";
if (method_exists($order, 'get_billing_phone') && $order->get_billing_phone()) {
    echo '         ' . esc_html((string) $order->get_billing_phone()) . "\n";
}
echo esc_html(str_repeat('-', 60)) . "\n\n";

if ($request->reason) {
    echo esc_html__('Reason provided by customer:', 'polski') . "\n";
    echo esc_html($request->reason) . "\n\n";
}

echo esc_html__('Items covered by this declaration', 'polski') . ":\n";
$polski_lines = \Polski\Email\WithdrawalConfirmationEmail::declaredLines($order, $request);
foreach ($polski_lines as $polski_line) {
    $item = $polski_line['item'];
    $product = $item->get_product();
    $attrs = '';
    if ($product instanceof \WC_Product && $product->is_type('variation')) {
        $attrs = wc_get_formatted_variation($product, true, true, false);
    }
    echo '- ' . esc_html((string) $item->get_name());
    if ($attrs !== '') {
        echo ' (' . esc_html($attrs) . ')';
    }
    echo ' x ' . esc_html((string) wc_stock_amount($polski_line['quantity']));
    echo ' = ' . esc_html(wp_strip_all_tags(wc_price($polski_line['total'], ['currency' => $currency])));
    echo "\n";
}

echo "\n";
echo esc_html__('Total', 'polski') . ': ' . esc_html(wp_strip_all_tags(wc_price(array_sum(array_column($polski_lines, 'total')), ['currency' => $currency]))) . "\n\n";

if ($additional_content) {
    echo esc_html(wp_strip_all_tags($additional_content)) . "\n\n";
}

echo esc_html(str_repeat('-', 60)) . "\n";
