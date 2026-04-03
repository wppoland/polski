<?php

declare(strict_types=1);

namespace Polski\Email;

use Polski\Model\WithdrawalRequest;

/**
 * Email sent to the customer when a withdrawal request is confirmed.
 */
class WithdrawalConfirmationEmail extends \WC_Email
{
    public ?WithdrawalRequest $request = null;

    /**
     * @return array<string, mixed>
     */
    private function getWithdrawalSettings(): array
    {
        $settings = get_option('polski_withdrawal', []);

        return is_array($settings) ? $settings : [];
    }

    public function __construct()
    {
        $this->id = 'polski_withdrawal_confirmation';
        $this->customer_email = true;
        $this->title = 'Potwierdzenie odstąpienia';
        $this->description = 'Ta wiadomość trafi do Twojego klienta z miłą informacją, gdy tylko zatwierdzisz jego zwrot.';
        $this->template_base = \Polski\PLUGIN_DIR . '/templates/';
        $this->template_html = 'emails/withdrawal-confirmation.php';
        $this->template_plain = 'emails/plain/withdrawal-confirmation.php';
        $this->placeholders = [
            '{order_number}' => '',
            '{order_date}' => '',
            '{withdrawal_date}' => '',
        ];

        // Trigger on withdrawal confirmed action.
        add_action('polski/withdrawal/confirmed', [$this, 'trigger']);

        parent::__construct();
    }

    /**
     * Trigger the email.
     */
    public function trigger(WithdrawalRequest $request): void
    {
        $this->request = $request;
        $order = wc_get_order($request->orderId);

        if (! $order instanceof \WC_Order) {
            return;
        }

        $this->object = $order;
        $this->recipient = $order->get_billing_email();

        $this->placeholders['{order_number}'] = $order->get_order_number();
        $this->placeholders['{order_date}'] = wc_format_datetime($order->get_date_created());
        $this->placeholders['{withdrawal_date}'] = $request->requestedAt->format(get_option('date_format'));

        if (! $this->is_enabled() || ! $this->get_recipient()) {
            return;
        }

        $this->send(
            $this->get_recipient(),
            $this->get_subject(),
            $this->get_content(),
            $this->get_headers(),
            $this->get_attachments(),
        );
    }

    public function get_default_subject(): string
    {
        return (string) ($this->getWithdrawalSettings()['email_subject'] ?? 'Dobra wiadomość! Twój wniosek o zwrot (zamówienie #{order_number}) został pomyślnie potwierdzony.');
    }

    public function get_default_heading(): string
    {
        return (string) ($this->getWithdrawalSettings()['email_heading'] ?? 'Odstąpienie potwierdzone');
    }

    public function get_content_html(): string
    {
        return wc_get_template_html(
            $this->template_html,
            [
                'order' => $this->object,
                'request' => $this->request,
                'email_heading' => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin' => false,
                'plain_text' => false,
                'email' => $this,
            ],
            '',
            $this->template_base,
        );
    }

    public function get_content_plain(): string
    {
        return wc_get_template_html(
            $this->template_plain,
            [
                'order' => $this->object,
                'request' => $this->request,
                'email_heading' => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin' => false,
                'plain_text' => true,
                'email' => $this,
            ],
            '',
            $this->template_base,
        );
    }

    public function get_default_additional_content(): string
    {
        return (string) ($this->getWithdrawalSettings()['email_additional_content'] ?? 'Zwrot środków zostanie zrealizowany w ciągu 14 dni od daty otrzymania zwróconych produktów.');
    }
}
