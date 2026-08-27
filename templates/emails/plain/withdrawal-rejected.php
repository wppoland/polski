<?php
/**
 * Withdrawal rejected e-mail (plain text).
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
$rejected_at = $request->rejectedAt?->format(get_option('date_format') . ' H:i') ?? '';
$reason = (string) ($request->rejectedReason ?? '');

echo '= ' . esc_html(wp_strip_all_tags($email_heading)) . " =\n\n";
echo esc_html(sprintf(
    /* translators: %s = customer first name */
    __('Hello %s,', 'polski'),
    (string) $order->get_billing_first_name(),
)) . "\n\n";
echo esc_html(sprintf(
    /* translators: 1: declaration id, 2: order number */
    __('We cannot accept your withdrawal declaration (%1$s) for order #%2$s.', 'polski'),
    $declaration_id,
    (string) $order->get_order_number(),
)) . "\n\n";

if ($reason !== '') {
    echo esc_html__('Reason for the refusal:', 'polski') . "\n";
    echo esc_html($reason) . "\n\n";
}

echo esc_html(str_repeat('-', 60)) . "\n";
echo esc_html__('Declaration number', 'polski') . ': ' . esc_html($declaration_id) . "\n";
echo esc_html__('Decision date', 'polski') . ': ' . esc_html($rejected_at) . "\n";
echo esc_html__('Order', 'polski') . ': #' . esc_html((string) $order->get_order_number()) . "\n";
echo esc_html(str_repeat('-', 60)) . "\n\n";

echo esc_html__('If you think this decision is wrong, reply to this email or contact the shop.', 'polski') . "\n\n";

if ($additional_content) {
    echo esc_html(wp_strip_all_tags($additional_content)) . "\n";
}
