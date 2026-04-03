<?php

declare(strict_types=1);

namespace Polski\Email;

/**
 * Email sent to new customers for account activation (Double Opt-In).
 */
class DoubleOptInEmail extends \WC_Email
{
    public ?int $activationUserId = null;
    public string $activationUrl = '';

    public function __construct()
    {
        $this->id = 'polski_double_opt_in';
        $this->customer_email = true;
        $this->title = __('Aktywacja konta (Podwójna weryfikacja)', 'polski');
        $this->description = __('Ciepłe powitanie wysyłane do Twoich nowych klientów, by zweryfikowali swój adres e-mail.', 'polski');
        $this->template_base = \Polski\PLUGIN_DIR . '/templates/';
        $this->template_html = 'emails/double-opt-in.php';
        $this->template_plain = 'emails/plain/double-opt-in.php';
        $this->placeholders = [
            '{site_title}' => $this->get_blogname(),
        ];

        // Trigger on custom action.
        add_action('polski/doi/email_sent', [$this, 'trigger'], 10, 3);

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
        $settings = get_option('polski_doi', []);

        return is_array($settings)
            ? (string) ($settings['email_subject'] ?? __('Aktywuj swoje konto w {site_title}', 'polski'))
            : __('Aktywuj swoje konto w {site_title}', 'polski');
    }

    public function get_default_heading(): string
    {
        $settings = get_option('polski_doi', []);

        return is_array($settings)
            ? (string) ($settings['email_heading'] ?? __('Potwierdź swój adres email', 'polski'))
            : __('Potwierdź swój adres email', 'polski');
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
        $settings = get_option('polski_doi', []);

        return is_array($settings)
            ? (string) ($settings['additional_content'] ?? __('Jeśli to nie Ty zakładałeś/-aś u nas konto, nie przejmuj się i po prostu wykasuj tę wiadomość.', 'polski'))
            : __('Jeśli to nie Ty zakładałeś/-aś u nas konto, nie przejmuj się i po prostu wykasuj tę wiadomość.', 'polski');
    }
}
