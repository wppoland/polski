<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * Displays a "Verified Purchase" badge on WooCommerce product reviews
 * when the reviewer has actually bought the product.
 */
final class VerifiedReviewService implements HasHooks
{
    public function registerHooks(): void
    {
        add_filter('comment_text', [$this, 'appendBadge'], 20, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueueStyles']);
    }

    /**
     * Prepend verified-purchase badge to review text when applicable.
     */
    public function appendBadge(string $text, ?\WP_Comment $comment = null): string
    {
        if (! $comment || ! ModulesPage::isModuleEnabled('verified_review')) {
            return $text;
        }

        if (get_post_type((int) $comment->comment_post_ID) !== 'product') {
            return $text;
        }

        $userId = (int) $comment->user_id;
        $email = sanitize_email((string) $comment->comment_author_email);
        $productId = (int) $comment->comment_post_ID;

        if ($productId <= 0 || $email === '') {
            return $text;
        }

        if (wc_customer_bought_product($email, $userId, $productId)) {
            $settings  = $this->getSettings();
            $badgeText = $settings['badge_text'] ?? __('Verified purchase', 'polski');
            $badge     = '<span class="polski-verified-badge">' . esc_html($badgeText) . '</span>';

            return $badge . $text;
        }

        return $text;
    }

    /**
     * Enqueue inline styles for the verified badge on product pages.
     */
    public function enqueueStyles(): void
    {
        if (! ModulesPage::isModuleEnabled('verified_review') || ! is_product()) {
            return;
        }

        wp_add_inline_style(
            'woocommerce-general',
            '.polski-verified-badge{display:inline-block;background:#46b450;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;margin-bottom:6px;}'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        $settings = get_option('polski_verified_review', []);

        return is_array($settings) ? $settings : [];
    }
}
