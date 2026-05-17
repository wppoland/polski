<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * Enqueues the withdrawal-flow CSS + JS exactly on pages that need them:
 *  - the configured lookup page,
 *  - the WC My Account orders endpoint (where the one-click / two-step form lives),
 *  - any post/page that contains one of the withdrawal shortcodes.
 *
 * This avoids loading the assets globally on every shop page.
 */
final class WithdrawalAssetsService implements HasHooks
{
    private const HANDLE = 'polski-withdrawal';

    public function registerHooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'maybeEnqueue']);
    }

    public function maybeEnqueue(): void
    {
        if (! $this->shouldEnqueue()) {
            return;
        }

        $version = defined('Polski\\VERSION') ? \Polski\VERSION : '1.0.0';
        $base = plugins_url('', \Polski\PLUGIN_FILE);

        wp_enqueue_style(
            self::HANDLE,
            $base . '/assets/css/withdrawal.css',
            [],
            $version,
        );

        wp_enqueue_script(
            self::HANDLE,
            $base . '/assets/js/withdrawal-form.js',
            [],
            $version,
            ['in_footer' => true, 'strategy' => 'defer'],
        );
    }

    private function shouldEnqueue(): bool
    {
        // My Account & order pages always need it.
        if (function_exists('is_account_page') && is_account_page()) {
            return true;
        }

        // Configured lookup page.
        $settings = get_option('polski_withdrawal', []);
        $settings = is_array($settings) ? $settings : [];
        $lookupId = (int) ($settings['lookup_page_id'] ?? 0);
        if ($lookupId > 0 && is_page($lookupId)) {
            return true;
        }

        // Pages that embed a withdrawal shortcode.
        global $post;
        if ($post instanceof \WP_Post) {
            $content = (string) $post->post_content;
            if (
                has_shortcode($content, 'polski_withdrawal_lookup')
                || has_shortcode($content, 'polski_withdrawal_info')
                || has_shortcode($content, 'polski_withdrawal_form_template')
            ) {
                return true;
            }
        }

        return false;
    }
}
