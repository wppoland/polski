<?php

declare(strict_types=1);

namespace Spolszczony\Email;

/**
 * Email sent to new customers for account activation (Double Opt-In).
 */
class DoubleOptInEmail extends \WC_Email
{
    public ?int $activationUserId = null;
    public string $activationUrl = '';

    public function __construct()
    {
        $this->id = 'spolszczony_double_opt_in';
        $this->customer_email = true;
        $this->title = __('Account Activation (Double Opt-In)', 'spolszczony');
        $this->description = __('Sent to new customers to verify their email address.', 'spolszczony');
        $this->template_base = \Spolszczony\PLUGIN_DIR . '/templates/';
        $this->template_html = 'emails/double-opt-in.php';
        $this->template_plain = 'emails/plain/double-opt-in.php';
        $this->placeholders = [
            '{site_title}' => $this->get_blogname(),
        ];

        // Trigger on custom action.
        add_action('spolszczony/doi/email_sent', [$this, 'trigger'], 10, 3);

        parent::__construct();
    }

    /**
     * Trigger the email.
     *
     * @param int    $userId
     * @param string $email
     * @param string $activationUrl
     */
    public function trigger(int $userId, string $email, string $activationUrl): void
    {
        $this->activationUserId = $userId;
        $this->activationUrl = $activationUrl;
        $this->recipient = $email;

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
        return __('Activate your account at {site_title}', 'spolszczony');
    }

    public function get_default_heading(): string
    {
        return __('Confirm your email address', 'spolszczony');
    }

    public function get_content_html(): string
    {
        return wc_get_template_html(
            $this->template_html,
            [
                'activation_url' => $this->activationUrl,
                'user_id' => $this->activationUserId,
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
                'activation_url' => $this->activationUrl,
                'user_id' => $this->activationUserId,
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
        return __('If you did not create an account, you can safely ignore this email.', 'spolszczony');
    }
}
