<?php

declare(strict_types=1);
namespace Polski\Email;

defined('ABSPATH') || exit;

use Polski\Model\WithdrawalRequest;

/**
 * Email sent to the store administrator when a new withdrawal declaration is registered.
 */
class WithdrawalAdminNotificationEmail extends \WC_Email
{
    public ?WithdrawalRequest $request = null;

    public function __construct()
    {
        $this->id = 'polski_withdrawal_admin_notification';
        $this->customer_email = false;
        $this->title = 'Nowe zgłoszenie odstąpienia od umowy (Administrator)';
        $this->description = 'Powiadomienie wysyłane do administratora sklepu po zarejestrowaniu nowego oświadczenia o odstąpieniu od umowy.';
        $this->template_base = \Polski\PLUGIN_DIR . '/templates/';
        $this->template_html = 'emails/admin-withdrawal-notification.php';
        $this->template_plain = 'emails/plain/admin-withdrawal-notification.php';
        $this->placeholders = [
            '{order_number}' => '',
            '{declaration_id}' => '',
            '{customer_name}' => '',
            '{withdrawal_date}' => '',
        ];

        $this->recipient = $this->get_option('recipient', get_option('admin_email'));

        add_action('polski/withdrawal/requested', [$this, 'trigger']);
        add_action('polski/withdrawal/guest_requested', [$this, 'triggerFromGuest'], 10, 3);
        add_action('polski/withdrawal/manual_registered', [$this, 'triggerFromManual'], 10, 3);

        parent::__construct();
    }

    /**
     * Adapter for the guest_requested action signature.
     */
    public function triggerFromGuest(int $withdrawalId, \WC_Order $order, string $email): void
    {
        unset($order, $email);
        $request = \Polski\Plugin::instance()->container()
            ->get(\Polski\Repository\WithdrawalRepository::class)
            ->findById($withdrawalId);
        if ($request !== null) {
            $this->trigger($request);
        }
    }

    /**
     * Adapter for the manual_registered action signature.
     */
    public function triggerFromManual(int $withdrawalId, \WC_Order $order, string $channel): void
    {
        unset($order, $channel);
        $request = \Polski\Plugin::instance()->container()
            ->get(\Polski\Repository\WithdrawalRepository::class)
            ->findById($withdrawalId);
        if ($request !== null) {
            $this->trigger($request);
        }
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
        $this->recipient = $this->get_option('recipient', get_option('admin_email'));

        $this->placeholders['{order_number}'] = $order->get_order_number();
        $this->placeholders['{declaration_id}'] = sprintf('POL-WD-%06d', $request->id);
        $this->placeholders['{customer_name}'] = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
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
        return '[{site_title}] Nowe zgłoszenie odstąpienia od umowy dla zamówienia #{order_number} ({declaration_id})';
    }

    public function get_default_heading(): string
    {
        return 'Nowe zgłoszenie odstąpienia od umowy';
    }

    public function get_default_additional_content(): string
    {
        return 'Możesz zarządzać tym zgłoszeniem bezpośrednio w edycji zamówienia w panelu WordPress.';
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
                'sent_to_admin' => true,
                'plain_text' => false,
                'email' => $this,
                'polski_order' => $this->object,
                'polski_request' => $this->request,
                'polski_email_heading' => $this->get_heading(),
                'polski_additional_content' => $this->get_additional_content(),
                'polski_sent_to_admin' => true,
                'polski_plain_text' => false,
                'polski_email' => $this,
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
                'sent_to_admin' => true,
                'plain_text' => true,
                'email' => $this,
                'polski_order' => $this->object,
                'polski_request' => $this->request,
                'polski_email_heading' => $this->get_heading(),
                'polski_additional_content' => $this->get_additional_content(),
                'polski_sent_to_admin' => true,
                'polski_plain_text' => true,
                'polski_email' => $this,
            ],
            '',
            $this->template_base,
        );
    }
}
