<?php

declare(strict_types=1);

namespace Polski\Gateway;

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
        $this->id = 'polski_invoice';
        $this->method_title = 'Przelew bankowy / Faktura';
        $this->method_description = 'Klient płaci przelewem bankowym po otrzymaniu faktury. Zamówienie jest wstrzymane do momentu otrzymania wpłaty.';
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
                'title' => 'Włącz/Wyłącz',
                'type' => 'checkbox',
                'label' => 'Włącz płatność przelewem bankowym / faktura',
                'default' => 'no',
            ],
            'title' => [
                'title' => 'Tytuł',
                'type' => 'text',
                'description' => 'Tytuł metody płatności wyświetlany przy kasie.',
                'default' => 'Przelew bankowy',
                'desc_tip' => true,
            ],
            'description' => [
                'title' => 'Opis',
                'type' => 'textarea',
                'description' => 'Opis metody płatności wyświetlany przy kasie.',
                'default' => 'Zapłać przelewem bankowym. Twoje zamówienie zostanie zrealizowane po otrzymaniu wpłaty.',
                'desc_tip' => true,
            ],
            'instructions' => [
                'title' => 'Instrukcje',
                'type' => 'textarea',
                'description' => 'Wyświetlane na stronie podziękowania i w emailach. Podaj tutaj dane konta bankowego.',
                'default' => '',
                'desc_tip' => true,
            ],
            'account_number' => [
                'title' => 'Numer konta bankowego',
                'type' => 'text',
                'description' => 'Numer konta bankowego (zalecany format IBAN).',
                'default' => '',
                'desc_tip' => true,
            ],
            'bank_name' => [
                'title' => 'Nazwa banku',
                'type' => 'text',
                'description' => 'Nazwa Twojego banku.',
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

        $order->update_status('on-hold', __('Oczekiwanie na przelew bankowy.', 'polski'));
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
                echo __('Numer konta:', 'polski') . ' ' . $accountNumber . "\n";
            }
            if ($bankName !== '') {
                echo __('Bank:', 'polski') . ' ' . $bankName . "\n";
            }

            echo __('Tytuł przelewu:', 'polski') . ' ' . sprintf(
                __('Zamówienie %s', 'polski'),
                $order->get_order_number(),
            ) . "\n";
            echo __('Kwota:', 'polski') . ' ' . $order->get_formatted_order_total() . "\n";
        } else {
            echo '<div class="polski-invoice-instructions">';

            if ($instructions !== '') {
                echo '<p>' . wp_kses_post($instructions) . '</p>';
            }

            echo '<h3>' . esc_html__('Dane do przelewu', 'polski') . '</h3>';
            echo '<table class="shop_table">';

            if ($accountNumber !== '') {
                printf(
                    '<tr><th>%s</th><td><strong>%s</strong></td></tr>',
                    esc_html__('Numer konta', 'polski'),
                    esc_html($accountNumber),
                );
            }

            if ($bankName !== '') {
                printf(
                    '<tr><th>%s</th><td>%s</td></tr>',
                    esc_html__('Bank', 'polski'),
                    esc_html($bankName),
                );
            }

            printf(
                '<tr><th>%s</th><td>%s</td></tr>',
                esc_html__('Tytuł przelewu', 'polski'),
                esc_html(sprintf(__('Zamówienie %s', 'polski'), $order->get_order_number())),
            );

            printf(
                '<tr><th>%s</th><td><strong>%s</strong></td></tr>',
                esc_html__('Kwota', 'polski'),
                wp_kses_post($order->get_formatted_order_total()),
            );

            echo '</table></div>';
        }
    }
}
