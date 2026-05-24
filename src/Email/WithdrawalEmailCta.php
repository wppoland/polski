<?php

declare(strict_types=1);
namespace Polski\Email;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Service\WithdrawalService;

/**
 * Adds a clear "Withdraw from this contract here" call to action to the
 * standard WooCommerce customer order emails (processing / completed).
 *
 * Customers should not have to log in and dig into My Account > Orders to
 * exercise the EU Article 11a right of withdrawal. A direct, per-order link
 * straight from the order email closes that gap; logged-in customers land
 * on the My Account withdrawal screen pre-bound to the order, guests get
 * the lookup form with the order number pre-filled.
 *
 * The CTA is rendered only on emails sent to the customer for orders that
 * are currently eligible (status, clock window, exemption rules).
 */
final class WithdrawalEmailCta implements HasHooks
{
    private const CUSTOMER_EMAIL_IDS = [
        'customer_processing_order',
        'customer_completed_order',
        'customer_on_hold_order',
    ];

    public function __construct(
        private readonly WithdrawalService $withdrawal,
    ) {
    }

    public function registerHooks(): void
    {
        // Fires inside every WC email after the order item table; we filter
        // to customer-facing IDs in the callback so admin-new-order is not
        // polluted with a customer-facing CTA.
        add_action('woocommerce_email_order_details', [$this, 'renderCta'], 35, 4);
    }

    public function renderCta(\WC_Order $order, bool $sentToAdmin, bool $plainText, \WC_Email $email): void
    {
        if ($sentToAdmin) {
            return;
        }

        if (! in_array($email->id, self::CUSTOMER_EMAIL_IDS, true)) {
            return;
        }

        if (! $this->withdrawal->isEligible($order)) {
            return;
        }

        $url = $this->buildWithdrawalUrl($order);

        if ($url === '') {
            return;
        }

        $headline = __('Right of withdrawal', 'polski');
        $cta = __('Withdraw from this contract here', 'polski');
        $intro = __('You have 14 days from delivery to exercise the right of withdrawal (EU Directive 2011/83/EU, as amended by 2023/2673, Article 11a).', 'polski');

        if ($plainText) {
            echo "\n" . esc_html($headline) . "\n";
            echo esc_html($intro) . "\n";
            echo esc_html($cta) . ': ' . esc_url($url) . "\n";
            return;
        }

        printf(
            '<div style="margin-top:30px;padding:18px 22px;border:1px solid #e5e7eb;border-radius:6px;background:#f8fafc;">'
            . '<h2 style="margin:0 0 6px;font-size:16px;color:#0f172a;">%1$s</h2>'
            . '<p style="margin:0 0 12px;color:#334155;line-height:1.5;">%2$s</p>'
            . '<p style="margin:0;"><a href="%3$s" style="display:inline-block;padding:10px 18px;background:#0f172a;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:600;">%4$s</a></p>'
            . '</div>',
            esc_html($headline),
            esc_html($intro),
            esc_url($url),
            esc_html($cta),
        );
    }

    private function buildWithdrawalUrl(\WC_Order $order): string
    {
        $customerId = (int) $order->get_customer_id();

        if ($customerId > 0) {
            return (string) add_query_arg(
                'polski_withdrawal',
                $order->get_id(),
                wc_get_account_endpoint_url('orders'),
            );
        }

        // Guest order: link to the configured public lookup page with the
        // order number pre-filled. The customer still proves they own the
        // billing email via the magic-link flow once they hit Submit.
        $lookupPageId = (int) (get_option('polski_withdrawal', [])['lookup_page_id'] ?? 0);
        $lookupUrl = $lookupPageId > 0 ? (string) get_permalink($lookupPageId) : '';

        if ($lookupUrl === '') {
            return '';
        }

        return (string) add_query_arg(
            'polski_order_number',
            (string) $order->get_order_number(),
            $lookupUrl,
        );
    }
}
