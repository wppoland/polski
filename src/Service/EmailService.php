<?php

declare(strict_types=1);

namespace Spolszczony\Service;

use Spolszczony\Contract\HasHooks;
use Spolszczony\Enum\LegalPageType;

/**
 * Manages email enhancements: legal page attachments, custom email registration.
 */
final class EmailService implements HasHooks
{
    public function registerHooks(): void
    {
        // Register custom email classes.
        add_filter('woocommerce_email_classes', [$this, 'registerEmails']);

        // Append legal page content to order emails.
        add_action('woocommerce_email_after_order_table', [$this, 'appendLegalAttachments'], 10, 4);
    }

    /**
     * Register Spolszczony email classes.
     *
     * @param array<string, \WC_Email> $emails
     * @return array<string, \WC_Email>
     */
    public function registerEmails(array $emails): array
    {
        $emails['spolszczony_withdrawal_confirmation'] = new \Spolszczony\Email\WithdrawalConfirmationEmail();
        $emails['spolszczony_double_opt_in'] = new \Spolszczony\Email\DoubleOptInEmail();

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

        $legalService = \Spolszczony\Plugin::instance()->container()->get(LegalPageService::class);
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

                echo "\n" . strtoupper($label) . "\n";
                echo "----------------------------------------\n";
                echo $content . "\n";
            }
        } else {
            echo '<div class="spolszczony-email-legal-attachments" style="margin-top:30px;padding-top:20px;border-top:1px solid #e0e0e0;">';

            foreach ($attachments as $type => $content) {
                $pageType = LegalPageType::tryFrom($type);
                $label = $pageType?->label() ?? $type;

                printf(
                    '<div class="spolszczony-email-legal-attachment" style="margin-bottom:20px;"><h3 style="font-size:14px;margin-bottom:10px;">%s</h3><div style="font-size:12px;line-height:1.5;color:#666;">%s</div></div>',
                    esc_html($label),
                    wp_kses_post(nl2br($content)),
                );
            }

            echo '</div>';
        }
    }
}
