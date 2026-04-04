<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

final class DisputeResolutionService implements HasHooks
{
    public function registerHooks(): void
    {
        $settings = get_option('polski_general', []);

        if (! ($settings['dispute_resolution_enabled'] ?? true)) {
            return;
        }

        add_action('wp_footer', [$this, 'renderNotice']);
    }

    public function renderNotice(): void
    {
        if (! is_checkout() && ! is_cart()) {
            return;
        }

        $settings = get_option('polski_general', []);
        $text = $settings['dispute_resolution_text'] ?? '';

        if ($text === '') {
            return;
        }

        printf(
            '<div class="polski-dispute-resolution"><p>%s</p></div>',
            wp_kses_post($text),
        );
    }
}
