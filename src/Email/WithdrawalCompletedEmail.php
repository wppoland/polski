<?php

declare(strict_types=1);
namespace Polski\Email;

defined('ABSPATH') || exit;

use Polski\Model\WithdrawalRequest;

/**
 * E-mail sent to the consumer once a withdrawal request has been completed
 * (refund processed, return logged). Closes the loop on the durable-medium
 * proof emitted at filing time.
 */
class WithdrawalCompletedEmail extends \WC_Email
{
    public ?WithdrawalRequest $request = null;

    public function __construct()
    {
        $this->id = 'polski_withdrawal_completed';
        $this->customer_email = true;
        $this->title = 'Withdrawal completed';
        $this->description = 'Sent to the customer when their withdrawal has been completed (refund processed).';
        $this->template_base = \Polski\PLUGIN_DIR . '/templates/';
        $this->template_html = 'emails/withdrawal-completed.php';
        $this->template_plain = 'emails/plain/withdrawal-completed.php';
        $this->placeholders = [
            '{order_number}' => '',
            '{declaration_id}' => '',
            '{refund_amount}' => '',
        ];

        add_action('polski/withdrawal/completed', [$this, 'trigger']);

        parent::__construct();
    }

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
        $this->placeholders['{declaration_id}'] = sprintf('POL-WD-%06d', $request->id);
        $this->placeholders['{refund_amount}'] = $request->refundAmount !== null
            ? wp_strip_all_tags(wc_price((float) $request->refundAmount, ['currency' => $order->get_currency()]))
            : '';

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
        return 'Twoje odstąpienie dla zamówienia #{order_number} zostało rozliczone.';
    }

    public function get_default_heading(): string
    {
        return 'Odstąpienie rozliczone';
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
        return 'Dziękujemy za skorzystanie z prawa odstąpienia. Jeśli masz pytania, skontaktuj się ze sklepem.';
    }
}
