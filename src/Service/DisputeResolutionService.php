<?php

declare(strict_types=1);

namespace Spolszczony\Service;

use Spolszczony\Contract\HasHooks;

final class DisputeResolutionService implements HasHooks
{
    public function registerHooks(): void
    {
        $settings = get_option('spolszczony_general', []);

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

        $settings = get_option('spolszczony_general', []);
        $text = $settings['dispute_resolution_text'] ?? '';

        if ($text === '') {
            return;
        }

        printf(
            '<div class="spolszczony-dispute-resolution"><p>%s</p></div>',
            wp_kses_post($text),
        );
    }
}
