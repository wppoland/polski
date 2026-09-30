<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

final class DisputeResolutionService implements HasHooks
{
    public function registerHooks(): void
    {
        if (! \Polski\Admin\ModulesPage::isModuleEnabled('dispute_resolution')) {
            return;
        }

        $settings = get_option('polski_general', []);

        if (! ($settings['dispute_resolution_enabled'] ?? true)) {
            return;
        }

        add_action('wp_footer', [$this, 'renderNotice']);
    }

    /**
     * The saved notice, or '' when it still points to the EU ODR platform,
     * which closed on 20 July 2025 (Regulation (EU) 2024/3228): the old stock
     * text would send customers to a dead service.
     */
    public static function usableText(string $text): string
    {
        return str_contains($text, 'ec.europa.eu/consumers/odr') ? '' : $text;
    }

    public function renderNotice(): void
    {
        if (! is_checkout() && ! is_cart()) {
            return;
        }

        $settings = get_option('polski_general', []);
        $text = self::usableText((string) ($settings['dispute_resolution_text'] ?? ''));

        if ($text === '') {
            return;
        }

        printf(
            '<div class="polski-dispute-resolution"><p>%s</p></div>',
            wp_kses_post($text),
        );
    }
}
