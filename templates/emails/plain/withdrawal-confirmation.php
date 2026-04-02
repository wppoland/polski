<?php
/**
 * Withdrawal confirmation email (plain text).
 *
 * @var WC_Order                              $order
 * @var \Spolszczony\Model\WithdrawalRequest  $request
 * @var string                                $email_heading
 * @var string                                $additional_content
 *
 * @package Spolszczony/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

echo "= " . wp_strip_all_tags($email_heading) . " =\n\n";

printf(
    esc_html__('Dear %s,', 'spolszczony') . "\n\n",
    esc_html($order->get_billing_first_name()),
);

printf(
    esc_html__('Your withdrawal request for order #%s has been confirmed.', 'spolszczony') . "\n\n",
    esc_html($order->get_order_number()),
);

if ($request->reason) {
    echo esc_html__('Your reason:', 'spolszczony') . "\n";
    echo esc_html($request->reason) . "\n\n";
}

echo esc_html__('Please return the items to the following address within 14 days:', 'spolszczony') . "\n";
echo wp_strip_all_tags(get_option('woocommerce_store_address', '')) . "\n";
echo wp_strip_all_tags(get_option('woocommerce_store_address_2', '')) . "\n";
echo wp_strip_all_tags(get_option('woocommerce_store_postcode', '') . ' ' . get_option('woocommerce_store_city', '')) . "\n\n";

if ($additional_content) {
    echo wp_strip_all_tags($additional_content) . "\n";
}
