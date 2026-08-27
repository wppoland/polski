<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Enum\LegalPageType;

/**
 * Manages email enhancements: legal page attachments, custom email registration.
 */
final class EmailService implements HasHooks
{
    /**
     * Actions whose listeners live inside WC_Email constructors, so the mailer
     * has to exist before they fire.
     *
     * @var list<string>
     */
    private const MAILER_BOOT_ACTIONS = [
        'polski/withdrawal/requested',
        'polski/withdrawal/guest_requested',
        'polski/withdrawal/manual_registered',
        'polski/withdrawal/completed',
        'polski/withdrawal/rejected',
        'polski/doi/email_sent',
    ];

    public function registerHooks(): void
    {
        // Register custom email classes.
        add_filter('woocommerce_email_classes', [$this, 'registerEmails']);

        // WooCommerce builds its WC_Email objects lazily, only when something
        // calls WC()->mailer(). Our withdrawal emails register their own
        // listeners from their constructors, so on a storefront request (the
        // withdrawal form posts on template_redirect, the guest flow posts to
        // REST) those constructors never run and the withdrawal actions fire
        // with no email listener attached at all. Loading the mailer at
        // priority 1 puts the classes in place before their own priority-10
        // handlers run on the same action.
        foreach (self::MAILER_BOOT_ACTIONS as $action) {
            add_action($action, [$this, 'loadMailer'], 1);
        }

        // Append legal page content to order emails.
        add_action('woocommerce_email_after_order_table', [$this, 'appendLegalAttachments'], 10, 4);
    }

    /**
     * Instantiate the WooCommerce mailer so the registerEmails() filter runs.
     */
    public function loadMailer(): void
    {
        if (function_exists('WC')) {
            WC()->mailer();
        }
    }

    /**
     * Register Polski email classes.
     *
     * @param array<string, \WC_Email> $emails
     * @return array<string, \WC_Email>
     */
    public function registerEmails(array $emails): array
    {
        $emails['polski_withdrawal_confirmation'] = new \Polski\Email\WithdrawalConfirmationEmail();
        $emails['polski_withdrawal_completed'] = new \Polski\Email\WithdrawalCompletedEmail();
        $emails['polski_withdrawal_rejected'] = new \Polski\Email\WithdrawalRejectedEmail();
        $emails['polski_double_opt_in'] = new \Polski\Email\DoubleOptInEmail();

        return $emails;
    }

    /**
     * Append legal page content after the order table in emails.
     */
    public function appendLegalAttachments(\WC_Order $order, bool $sentToAdmin, bool $plainText, \WC_Email $email): void
    {
        if ($sentToAdmin) {
            return;
        }

        // Guarded here rather than in registerHooks, which also registers four
        // WC_Email classes belonging to the withdrawal and double opt-in
        // modules. Note that switching this module off removes legally required
        // documents from customer emails; that is the merchant's call to make,
        // but it should be their call and not something the toggle ignores.
        if (! \Polski\Admin\ModulesPage::isModuleEnabled('email_attachments')) {
            return;
        }

        // Only attach to specific email types.
        $attachTo = [
            'customer_processing_order',
            'customer_completed_order',
            'customer_on_hold_order',
            'customer_invoice',
        ];

        if (! in_array($email->id, $attachTo, true)) {
            return;
        }

        $legalService = \Polski\Plugin::instance()->container()->get(LegalPageService::class);
        $attachments = $legalService->getEmailAttachments();

        if (empty($attachments)) {
            return;
        }

        if ($plainText) {
            echo "\n\n";
            echo "========================================\n";

            foreach ($attachments as $type => $content) {
                $pageType = LegalPageType::tryFrom($type);
                $label = $pageType?->label() ?? $type;

                echo "\n" . esc_html(strtoupper($label)) . "\n";
                echo "----------------------------------------\n";
                echo wp_kses_post($content) . "\n";
            }
        } else {
            echo '<div class="polski-email-legal-attachments" style="margin-top:30px;padding-top:20px;border-top:1px solid #e0e0e0;">';

            foreach ($attachments as $type => $content) {
                $pageType = LegalPageType::tryFrom($type);
                $label = $pageType?->label() ?? $type;

                printf(
                    '<div class="polski-email-legal-attachment" style="margin-bottom:20px;"><h3 style="font-size:14px;margin-bottom:10px;">%s</h3><div style="font-size:12px;line-height:1.5;color:#666;">%s</div></div>',
                    esc_html($label),
                    wp_kses_post(nl2br($content)),
                );
            }

            echo '</div>';
        }
    }
}
