<?php

declare(strict_types=1);

namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;
use Polski\Util\Formatter;
use Polski\Util\SettingsCacheable;

/**
 * Sends automated review request emails after order completion.
 *
 * Waits a configurable number of days after the order status changes
 * to "completed", then sends an email with links to review each
 * purchased product. Respects opt-out and avoids duplicate sends.
 */
final class ReviewRequestService implements HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_review_requests';
    private const META_KEY = '_polski_review_request_sent';
    private const OPTOUT_META = '_polski_review_optout';

    public function registerHooks(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        // Schedule review request when order is completed.
        add_action('woocommerce_order_status_completed', [$this, 'scheduleReviewRequest']);

        // Process scheduled requests via daily cron.
        add_action('polski_daily_maintenance', [$this, 'processScheduledRequests']);

        // Opt-out handler.
        add_action('template_redirect', [$this, 'handleOptOut']);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('review_requests');
    }

    /**
     * Mark order for review request after configured delay.
     */
    public function scheduleReviewRequest(int $orderId): void
    {
        $order = wc_get_order($orderId);

        if (! $order instanceof \WC_Order) {
            return;
        }

        // Skip if already scheduled or sent.
        if ($order->get_meta(self::META_KEY, true) !== '') {
            return;
        }

        $settings = $this->getSettings();
        $delayDays = max(1, (int) ($settings['delay_days'] ?? 7));
        $sendAt = (new \DateTimeImmutable('now', wp_timezone()))->modify('+' . $delayDays . ' days');

        $order->update_meta_data(self::META_KEY, 'scheduled:' . $sendAt->format('Y-m-d'));
        $order->save();
    }

    /**
     * Process all orders that are due for review request emails.
     */
    public function processScheduledRequests(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $today = (new \DateTimeImmutable('now', wp_timezone()))->format('Y-m-d');

        $orders = wc_get_orders([
            'status' => 'completed',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for cron: pick orders scheduled for review request (meta key).
            'meta_key' => self::META_KEY,
            'meta_compare' => 'LIKE',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required for cron: pick orders scheduled for review request (meta value).
            'meta_value' => 'scheduled:',
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'ASC',
        ]);
        $orders = is_array($orders) ? $orders : [];

        foreach ($orders as $order) {
            if (! $order instanceof \WC_Order) {
                continue;
            }

            $metaValue = (string) $order->get_meta(self::META_KEY, true);
            $email = $order->get_billing_email();

            if ($email === '') {
                continue;
            }

            // Check opt-out.
            $userId = $order->get_customer_id();
            if ($userId > 0 && get_user_meta($userId, self::OPTOUT_META, true) === 'yes') {
                $order->update_meta_data(self::META_KEY, 'skipped:optout');
                $order->save();
                continue;
            }

            // First email: scheduled:{date}.
            if (str_starts_with($metaValue, 'scheduled:')) {
                $scheduledDate = substr($metaValue, 10);

                if ($scheduledDate > $today) {
                    continue;
                }

                if ($this->sendReviewRequestEmail($order)) {
                    $order->update_meta_data(self::META_KEY, 'sent1:' . $today);
                } else {
                    $order->update_meta_data(self::META_KEY, 'failed:' . $today);
                }

                $order->save();
                continue;
            }

            // Second reminder: sent1:{date} + reminder_delay days later.
            if (str_starts_with($metaValue, 'sent1:')) {
                $settings = $this->getSettings();
                $reminderDelay = max(3, (int) ($settings['reminder_delay_days'] ?? 7));
                $firstSentDate = substr($metaValue, 6);

                try {
                    $reminderDue = (new \DateTimeImmutable($firstSentDate))->modify('+' . $reminderDelay . ' days')->format('Y-m-d');
                } catch (\Exception) {
                    continue;
                }

                if ($reminderDue > $today) {
                    continue;
                }

                if ($this->sendReviewRequestEmail($order, true)) {
                    $order->update_meta_data(self::META_KEY, 'sent2:' . $today);
                }

                $order->save();
                continue;
            }
        }
    }

    /**
     * Handle opt-out link: ?polski_review_optout={nonce}
     */
    public function handleOptOut(): void
    {
        if (! isset($_GET['polski_review_optout'])) {
            return;
        }

        $userId = get_current_user_id();

        if ($userId <= 0) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash((string) $_GET['polski_review_optout']));

        if (! wp_verify_nonce($nonce, 'polski_review_optout_' . $userId)) {
            return;
        }

        update_user_meta($userId, self::OPTOUT_META, 'yes');

        wc_add_notice(
            (string) ($this->getSettings()['optout_success_text'] ?? __('You have been unsubscribed from review request emails.', 'polski')),
            'success',
        );

        wp_safe_redirect(wc_get_page_permalink('myaccount'));
        exit;
    }

    private function sendReviewRequestEmail(\WC_Order $order, bool $isReminder = false): bool
    {
        $settings = $this->getSettings();
        $email = $order->get_billing_email();
        $firstName = $order->get_billing_first_name();
        $tokens = ['first_name' => $firstName, 'order_number' => $order->get_order_number()];

        if ($isReminder) {
            $subject = Formatter::interpolate(
                (string) ($settings['reminder_subject'] ?? __('We still want to hear from you, {first_name}!', 'polski')),
                $tokens,
            );
            $intro = Formatter::interpolate(
                (string) ($settings['reminder_intro'] ?? __('Hi {first_name}, you have not yet reviewed your recent purchase. Your opinion helps other customers and helps us improve.', 'polski')),
                $tokens,
            );
        } else {
            $subject = Formatter::interpolate(
                (string) ($settings['email_subject'] ?? __('How was your purchase? Leave a review', 'polski')),
                $tokens,
            );
            $intro = Formatter::interpolate(
                (string) ($settings['email_intro'] ?? __('Hi {first_name}, thank you for your recent purchase. We would love to hear your feedback.', 'polski')),
                $tokens,
            );
        }

        $productRows = $this->buildProductReviewLinks($order);

        if ($productRows === '') {
            return false; // No reviewable products.
        }

        $optoutUrl = $this->buildOptOutUrl($order);
        $optoutText = (string) ($settings['optout_link_text'] ?? __('Unsubscribe from review requests', 'polski'));

        $message = '<div style="max-width:600px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;color:#1e293b;">';
        $message .= '<p>' . $intro . '</p>';
        $message .= $productRows;
        $message .= '<p style="font-size:12px;color:#94a3b8;margin-top:24px;"><a href="' . $optoutUrl . '" style="color:#94a3b8;">' . $optoutText . '</a></p>';
        $message .= '</div>';

        return wp_mail($email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
    }

    private function buildProductReviewLinks(\WC_Order $order): string
    {
        $rows = '';

        foreach ($order->get_items('line_item') as $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $product = $item->get_product();

            if (! $product instanceof \WC_Product) {
                continue;
            }

            // Skip products that already have a review from this customer.
            $existingReview = get_comments([
                'post_id' => $product->get_id(),
                'author_email' => $order->get_billing_email(),
                'type' => 'review',
                'count' => true,
            ]);

            if ((int) $existingReview > 0) {
                continue;
            }

            $name = esc_html($product->get_name());
            $image = wp_get_attachment_image_url((int) $product->get_image_id(), 'woocommerce_thumbnail') ?: '';
            $reviewUrl = esc_url($product->get_permalink() . '#reviews');
            $ctaText = (string) ($this->getSettings()['review_cta_text'] ?? __('Leave a review', 'polski'));

            $imgHtml = $image !== '' ? "<img src=\"{$image}\" width=\"64\" height=\"64\" style=\"border-radius:8px;\" alt=\"\">" : '';

            $rows .= '<div style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #e2e8f0;">';
            $rows .= $imgHtml;
            $rows .= '<div style="flex:1;">';
            $rows .= '<div style="font-weight:600;">' . $name . '</div>';
            $rows .= '<a href="' . $reviewUrl . '" style="display:inline-block;margin-top:4px;padding:6px 16px;background:#0ea5a4;color:#fff;text-decoration:none;border-radius:6px;font-size:13px;">' . esc_html($ctaText) . '</a>';
            $rows .= '</div>';
            $rows .= '</div>';
        }

        return $rows;
    }

    private function buildOptOutUrl(\WC_Order $order): string
    {
        $userId = $order->get_customer_id();

        if ($userId <= 0) {
            return '';
        }

        return wp_nonce_url(
            add_query_arg('polski_review_optout', '1', wc_get_page_permalink('myaccount')),
            'polski_review_optout_' . $userId,
            'polski_review_optout',
        );
    }
}
