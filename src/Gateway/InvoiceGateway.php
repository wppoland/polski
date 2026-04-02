<?php

declare(strict_types=1);

namespace Spolszczony\Gateway;

/**
 * Invoice / Bank Transfer payment gateway (Przelew / Faktura).
 *
 * A simple payment gateway where the customer pays via bank transfer
 * after receiving the order confirmation with invoice details.
 */
class InvoiceGateway extends \WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'spolszczony_invoice';
        $this->method_title = __('Invoice / Bank Transfer', 'spolszczony');
        $this->method_description = __('Customer pays via bank transfer after receiving an invoice. Order is set to on-hold until payment is received.', 'spolszczony');
        $this->has_fields = false;
        $this->icon = '';

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions', '');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'thankyouPage']);
        add_action('woocommerce_email_before_order_table', [$this, 'emailInstructions'], 10, 3);
    }

    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'spolszczony'),
                'type' => 'checkbox',
                'label' => __('Enable Invoice / Bank Transfer payment', 'spolszczony'),
                'default' => 'no',
            ],
            'title' => [
                'title' => __('Title', 'spolszczony'),
                'type' => 'text',
                'description' => __('Payment method title displayed at checkout.', 'spolszczony'),
                'default' => __('Bank Transfer (Przelew)', 'spolszczony'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'spolszczony'),
                'type' => 'textarea',
                'description' => __('Payment method description displayed at checkout.', 'spolszczony'),
                'default' => __('Pay via bank transfer. Your order will be processed after we receive payment.', 'spolszczony'),
                'desc_tip' => true,
            ],
            'instructions' => [
                'title' => __('Instructions', 'spolszczony'),
                'type' => 'textarea',
                'description' => __('Displayed on the thank-you page and in confirmation emails. Include your bank account details here.', 'spolszczony'),
                'default' => '',
                'desc_tip' => true,
            ],
            'account_number' => [
                'title' => __('Bank Account Number', 'spolszczony'),
                'type' => 'text',
                'description' => __('Your bank account number (IBAN format recommended).', 'spolszczony'),
                'default' => '',
                'desc_tip' => true,
            ],
            'bank_name' => [
                'title' => __('Bank Name', 'spolszczony'),
                'type' => 'text',
                'description' => __('Name of your bank.', 'spolszczony'),
                'default' => '',
                'desc_tip' => true,
            ],
        ];
    }

    /**
     * Process the payment - set order to on-hold.
     *
     * @param int $orderId
     * @return array{result: string, redirect: string}
     */
    public function process_payment($orderId): array
    {
        $order = wc_get_order($orderId);

        if (! $order instanceof \WC_Order) {
            return ['result' => 'failure'];
        }

        $order->update_status('on-hold', __('Awaiting bank transfer payment.', 'spolszczony'));
        wc_reduce_stock_levels($orderId);
        WC()->cart->empty_cart();

        return [
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }

    /**
     * Display payment instructions on thank-you page.
     */
    public function thankyouPage(int $orderId): void
    {
        $this->outputInstructions($orderId);
    }

    /**
     * Add payment instructions to emails.
     */
    public function emailInstructions(\WC_Order $order, bool $sentToAdmin, bool $plainText): void
    {
        if ($sentToAdmin || $order->get_payment_method() !== $this->id || ! $order->has_status('on-hold')) {
            return;
        }

        $this->outputInstructions($order->get_id(), $plainText);
    }

    /**
     * Output bank details and instructions.
     */
    private function outputInstructions(int $orderId, bool $plainText = false): void
    {
        $order = wc_get_order($orderId);
        if (! $order instanceof \WC_Order) {
            return;
        }

        $accountNumber = $this->get_option('account_number', '');
        $bankName = $this->get_option('bank_name', '');
        $instructions = $this->instructions;

        if ($plainText) {
            if ($instructions !== '') {
                echo wp_strip_all_tags($instructions) . "\n\n";
            }

            if ($accountNumber !== '') {
                echo __('Bank account:', 'spolszczony') . ' ' . $accountNumber . "\n";
            }
            if ($bankName !== '') {
                echo __('Bank:', 'spolszczony') . ' ' . $bankName . "\n";
            }

            echo __('Transfer title:', 'spolszczony') . ' ' . sprintf(
                __('Order %s', 'spolszczony'),
                $order->get_order_number(),
            ) . "\n";
            echo __('Amount:', 'spolszczony') . ' ' . $order->get_formatted_order_total() . "\n";
        } else {
            echo '<div class="spolszczony-invoice-instructions">';

            if ($instructions !== '') {
                echo '<p>' . wp_kses_post($instructions) . '</p>';
            }

            echo '<h3>' . esc_html__('Bank Transfer Details', 'spolszczony') . '</h3>';
            echo '<table class="shop_table">';

            if ($accountNumber !== '') {
                printf(
                    '<tr><th>%s</th><td><strong>%s</strong></td></tr>',
                    esc_html__('Account number', 'spolszczony'),
                    esc_html($accountNumber),
                );
            }

            if ($bankName !== '') {
                printf(
                    '<tr><th>%s</th><td>%s</td></tr>',
                    esc_html__('Bank', 'spolszczony'),
                    esc_html($bankName),
                );
            }

            printf(
                '<tr><th>%s</th><td>%s</td></tr>',
                esc_html__('Transfer title', 'spolszczony'),
                esc_html(sprintf(__('Order %s', 'spolszczony'), $order->get_order_number())),
            );

            printf(
                '<tr><th>%s</th><td><strong>%s</strong></td></tr>',
                esc_html__('Amount', 'spolszczony'),
                wp_kses_post($order->get_formatted_order_total()),
            );

            echo '</table></div>';
        }
    }
}
