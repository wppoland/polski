<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * Trust badges and conversion signals for product pages.
 *
 * Displays configurable trust signals: secure payment, fast delivery,
 * money-back guarantee, free returns, verified reviews count.
 * Pure CSS + inline SVG icons for zero performance impact.
 */
final class TrustBadgeService implements HasHooks
{
    private const OPTION = 'polski_trust_badges';

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('trust_badges')) {
            return;
        }

        add_action('woocommerce_single_product_summary', [$this, 'renderOnProduct'], 35);
        add_action('woocommerce_before_cart', [$this, 'renderOnCart']);
        add_action('woocommerce_before_checkout_form', [$this, 'renderOnCheckout'], 5);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        return wp_parse_args(
            get_option(self::OPTION, []),
            [
                'show_on_product' => true,
                'show_on_cart' => true,
                'show_on_checkout' => true,
                'badges' => [
                    ['icon' => 'lock', 'text' => __('Secure payment', 'polski'), 'enabled' => true],
                    ['icon' => 'truck', 'text' => __('Fast delivery', 'polski'), 'enabled' => true],
                    ['icon' => 'refresh', 'text' => __('14-day returns', 'polski'), 'enabled' => true],
                    ['icon' => 'shield', 'text' => __('Quality guarantee', 'polski'), 'enabled' => true],
                ],
            ],
        );
    }

    public function renderOnProduct(): void
    {
        if (! ($this->getSettings()['show_on_product'] ?? true)) {
            return;
        }

        $this->render();
    }

    public function renderOnCart(): void
    {
        if (! ($this->getSettings()['show_on_cart'] ?? true)) {
            return;
        }

        $this->render();
    }

    public function renderOnCheckout(): void
    {
        if (! ($this->getSettings()['show_on_checkout'] ?? true)) {
            return;
        }

        $this->render();
    }

    private function render(): void
    {
        $settings = $this->getSettings();
        $badges = $settings['badges'] ?? [];
        $activeBadges = array_filter($badges, fn ($b) => ! empty($b['enabled']));

        if (empty($activeBadges)) {
            return;
        }

        echo '<div class="polski-trust-badges" style="display:flex;flex-wrap:wrap;gap:12px;margin:16px 0;padding:12px 0;border-top:1px solid #f1f5f9;border-bottom:1px solid #f1f5f9">';

        foreach ($activeBadges as $badge) {
            $icon = $this->getSvgIcon($badge['icon'] ?? 'shield');
            $text = esc_html($badge['text'] ?? '');

            printf(
                '<div style="display:flex;align-items:center;gap:6px;font-size:12px;color:#475569">%s <span>%s</span></div>',
                $icon,
                $text,
            );
        }

        echo '</div>';
    }

    private function getSvgIcon(string $name): string
    {
        $icons = [
            'lock' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
            'truck' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0369a1" stroke-width="2"><path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
            'refresh' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2"><path d="M1 4v6h6"/><path d="M23 20v-6h-6"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/></svg>',
            'shield' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ca8a04" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>',
            'star' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="1"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            'check' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            'heart' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
        ];

        return $icons[$name] ?? $icons['shield'];
    }
}
