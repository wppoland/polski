<?php

declare(strict_types=1);
namespace Polski\Email;

defined('ABSPATH') || exit;

use Polski\Model\WithdrawalRequest;

/**
 * E-mail sent to the consumer when their withdrawal request is rejected
 * (typically because the 14-day window expired or the goods are excluded
 * under Art. 38). Carries the rejection reason and links back to the order
 * so the customer can challenge the decision if needed.
 */
class WithdrawalRejectedEmail extends \WC_Email
{
    public ?WithdrawalRequest $request = null;

    public function __construct()
    {
        $this->id = 'polski_withdrawal_rejected';
        $this->customer_email = true;
        $this->title = 'Withdrawal rejected';
        $this->description = 'Sent to the customer when their withdrawal cannot be honoured (e.g. past the window, exempt category).';
        $this->template_base = \Polski\PLUGIN_DIR . '/templates/';
        $this->template_html = 'emails/withdrawal-rejected.php';
        $this->template_plain = 'emails/plain/withdrawal-rejected.php';
        $this->placeholders = [
            '{order_number}' => '',
            '{declaration_id}' => '',
            '{rejection_reason}' => '',
        ];

        add_action('polski/withdrawal/rejected', [$this, 'trigger']);

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
        $this->placeholders['{rejection_reason}'] = (string) ($request->rejectedReason ?? '');

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
        return 'Twoje odstąpienie dla zamówienia #{order_number} nie zostało zaakceptowane.';
    }

    public function get_default_heading(): string
    {
        return 'Odstąpienie nie zostało zaakceptowane';
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
        return 'Jeśli uważasz, że ta decyzja jest błędna, odpowiedz na ten e-mail lub skontaktuj się ze sklepem.';
    }
}
