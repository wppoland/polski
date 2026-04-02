<?php

declare(strict_types=1);

namespace Spolszczony\Service;

use Spolszczony\Contract\Bootable;
use Spolszczony\Contract\HasHooks;

/**
 * Contract helper: manages delayed payment flow where the order confirmation
 * is sent before payment is collected.
 *
 * In Polish law, an order confirmation constitutes a binding contract.
 * This service ensures the correct flow:
 * 1. Customer places order
 * 2. Order confirmation email sent (contract formed)
 * 3. Customer redirected to payment
 *
 * For certain gateways (bank transfer, invoice), payment happens after order.
 */
final class ContractService implements Bootable, HasHooks
{
    private bool $enabled = false;

    public function boot(): void
    {
        $settings = get_option('spolszczony_checkout', []);
        $this->enabled = is_array($settings) && (bool) ($settings['delayed_payment_enabled'] ?? false);
    }

    public function registerHooks(): void
    {
        if (! $this->enabled) {
            return;
        }

        // Suppress payment redirect for delayed payment gateways.
        add_filter('woocommerce_payment_successful_result', [$this, 'handleDelayedPayment'], 10, 2);

        // Add "Pay Now" button on thank-you page for unpaid orders.
        add_action('woocommerce_thankyou', [$this, 'renderPayNowButton'], 5);

        // Ensure order confirmation email fires before payment.
        add_filter('woocommerce_payment_complete_order_status', [$this, 'filterOrderStatus'], 10, 3);
    }

    /**
     * For delayed payment gateways, redirect to thank-you page instead of payment.
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function handleDelayedPayment(array $result, int $orderId): array
    {
        $order = wc_get_order($orderId);

        if (! $order instanceof \WC_Order) {
            return $result;
        }

        if (! $this->isDelayedPaymentGateway($order->get_payment_method())) {
            return $result;
        }

        // Store flag for thank-you page.
        $order->update_meta_data('_spolszczony_delayed_payment', 'yes');
        $order->save();

        return $result;
    }

    /**
     * Show "Pay Now" button on thank-you page for unpaid orders.
     */
    public function renderPayNowButton(int $orderId): void
    {
        $order = wc_get_order($orderId);

        if (! $order instanceof \WC_Order) {
            return;
        }

        if ($order->is_paid() || $order->get_meta('_spolszczony_delayed_payment') !== 'yes') {
            return;
        }

        $payUrl = $order->get_checkout_payment_url();

        printf(
            '<div class="spolszczony-pay-now"><a href="%s" class="button alt">%s</a></div>',
            esc_url($payUrl),
            esc_html__('Pay Now', 'spolszczony'),
        );
    }

    /**
     * Filter order status for delayed payment orders.
     *
     * @param string    $status
     * @param int       $orderId
     * @param \WC_Order $order
     */
    public function filterOrderStatus(string $status, int $orderId, \WC_Order $order): string
    {
        if ($this->isDelayedPaymentGateway($order->get_payment_method())) {
            return 'on-hold';
        }

        return $status;
    }

    /**
     * Check if a payment gateway supports delayed payment.
     */
    private function isDelayedPaymentGateway(string $gatewayId): bool
    {
        $delayed = ['bacs', 'cheque', 'spolszczony_invoice'];

        /**
         * Filter the list of payment gateways that support delayed payment.
         *
         * @param list<string> $delayed Gateway IDs.
         */
        $delayed = apply_filters('spolszczony/contract/delayed_gateways', $delayed);

        return in_array($gatewayId, $delayed, true);
    }
}
