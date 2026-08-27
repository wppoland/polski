<?php
/**
 * Withdrawal completed e-mail (plain text).
 *
 * @var WC_Order                          $order
 * @var \Polski\Model\WithdrawalRequest   $request
 * @var string                            $email_heading
 * @var string                            $additional_content
 *
 * @package Polski/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$declaration_id = sprintf('POL-WD-%06d', $request->id);
$completed_at = $request->completedAt?->format(get_option('date_format') . ' H:i') ?? '';

echo '= ' . esc_html(wp_strip_all_tags($email_heading)) . " =\n\n";
echo esc_html(sprintf(
    /* translators: %s = customer first name */
    __('Hello %s,', 'polski'),
    (string) $order->get_billing_first_name(),
)) . "\n\n";
echo esc_html(sprintf(
    /* translators: 1: order number, 2: declaration id */
    __('Your withdrawal declaration (%2$s) for order #%1$s has been settled.', 'polski'),
    (string) $order->get_order_number(),
    $declaration_id,
)) . "\n\n";

echo esc_html(str_repeat('-', 60)) . "\n";
echo esc_html__('Declaration number', 'polski') . ': ' . esc_html($declaration_id) . "\n";
echo esc_html__('Settlement date', 'polski') . ': ' . esc_html($completed_at) . "\n";
if ($request->refundAmount !== null) {
    echo esc_html__('Refund amount', 'polski') . ': '
        . esc_html(wp_strip_all_tags(wc_price((float) $request->refundAmount, ['currency' => $order->get_currency()]))) . "\n";
}
echo esc_html__('Order', 'polski') . ': #' . esc_html((string) $order->get_order_number()) . "\n";
echo esc_html(str_repeat('-', 60)) . "\n\n";

echo esc_html__('The refund will go back to the payment method used for the purchase. It may take a few working days to appear.', 'polski') . "\n\n";

if ($additional_content) {
    echo esc_html(wp_strip_all_tags($additional_content)) . "\n";
}
