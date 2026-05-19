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
    __('Dzień dobry %s,', 'polski'),
    (string) $order->get_billing_first_name(),
)) . "\n\n";
echo esc_html(sprintf(
    /* translators: 1: order number, 2: declaration id */
    __('Twoje oświadczenie o odstąpieniu (%2$s) dla zamówienia #%1$s zostało rozliczone.', 'polski'),
    (string) $order->get_order_number(),
    $declaration_id,
)) . "\n\n";

echo esc_html(str_repeat('-', 60)) . "\n";
echo esc_html__('Numer oświadczenia', 'polski') . ': ' . esc_html($declaration_id) . "\n";
echo esc_html__('Data rozliczenia', 'polski') . ': ' . esc_html($completed_at) . "\n";
if ($request->refundAmount !== null) {
    echo esc_html__('Kwota zwrotu', 'polski') . ': '
        . esc_html(wp_strip_all_tags(wc_price((float) $request->refundAmount, ['currency' => $order->get_currency()]))) . "\n";
}
echo esc_html__('Zamówienie', 'polski') . ': #' . esc_html((string) $order->get_order_number()) . "\n";
echo esc_html(str_repeat('-', 60)) . "\n\n";

echo esc_html__('Zwrot zostanie wykonany na pierwotną metodę płatności użytą przy zakupie. Środki mogą pojawić się na koncie w ciągu kilku dni roboczych.', 'polski') . "\n\n";

if ($additional_content) {
    echo esc_html(wp_strip_all_tags($additional_content)) . "\n";
}
