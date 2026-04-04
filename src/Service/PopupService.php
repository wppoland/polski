<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Util\SettingsCacheable;
use Polski\Util\TemplateLoader;

/**
 * Lightweight popup for lead and promo messaging.
 */
final class PopupService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_popup';

    public function __construct(
        private readonly TemplateLoader $templateLoader,
    ) {
    }

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_footer', [$this, 'renderPopup'], 25);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('popup');
    }

    public function enqueueAssets(): void
    {
        if (! $this->shouldRender()) {
            return;
        }

        wp_enqueue_style(
            'polski-popup',
            \Polski\Plugin::instance()->url('assets/css/popup.css'),
            [],
            \Polski\VERSION,
        );

        wp_enqueue_script(
            'polski-popup',
            \Polski\Plugin::instance()->url('assets/js/popup.js'),
            [],
            \Polski\VERSION,
            true,
        );

        wp_localize_script('polski-popup', 'polskiPopup', [
            'delaySeconds' => max(0, (int) ($this->getSettings()['delay_seconds'] ?? 4)),
            'frequencyDays' => max(1, (int) ($this->getSettings()['frequency_days'] ?? 7)),
            'showBackdropClose' => (bool) ($this->getSettings()['show_backdrop_close'] ?? true),
        ]);
    }

    public function renderPopup(): void
    {
        if (! $this->shouldRender()) {
            return;
        }

        $this->templateLoader->include('shared/popup', [
            'settings' => $this->getSettings(),
            'cta_url' => $this->getCtaUrl(),
            'close_label' => (string) ($this->getSettings()['close_label'] ?? __('Zamknij popup', 'polski')),
            'dialog_label' => (string) ($this->getSettings()['dialog_label'] ?? __('Popup promocyjny', 'polski')),
            'show_cta' => (bool) ($this->getSettings()['show_cta'] ?? true),
            'show_title' => (bool) ($this->getSettings()['show_title'] ?? true),
            'show_close_button' => (bool) ($this->getSettings()['show_close_button'] ?? true),
            'cta_target' => (string) ($this->getSettings()['cta_target'] ?? 'same_tab'),
        ]);
    }

    private function shouldRender(): bool
    {
        if (! $this->isEnabled() || is_admin()) {
            return false;
        }

        $settings = $this->getSettings();

        if (is_front_page() && ($settings['show_on_home'] ?? true)) {
            return true;
        }

        if ((is_shop() || is_product_taxonomy()) && ($settings['show_on_shop'] ?? true)) {
            return true;
        }

        if (is_product() && ($settings['show_on_product'] ?? false)) {
            return true;
        }

        if (is_cart() && ($settings['show_on_cart'] ?? false)) {
            return true;
        }

        if (is_checkout() && ($settings['show_on_checkout'] ?? false)) {
            return true;
        }

        return false;
    }

    private function getCtaUrl(): string
    {
        $url = trim((string) ($this->getSettings()['cta_url'] ?? ''));

        if ($url !== '') {
            return $url;
        }

        $fallback = (string) ($this->getSettings()['fallback_cta_url'] ?? 'account');

        if ($fallback === 'shop' && function_exists('wc_get_page_permalink')) {
            $shopUrl = wc_get_page_permalink('shop');

            if (is_string($shopUrl) && $shopUrl !== '') {
                return $shopUrl;
            }
        }

        if ($fallback === 'account' && function_exists('wc_get_page_permalink')) {
            $accountUrl = wc_get_page_permalink('myaccount');

            if (is_string($accountUrl) && $accountUrl !== '') {
                return $accountUrl;
            }
        }

        return home_url('/');
    }
}
