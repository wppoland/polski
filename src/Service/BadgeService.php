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
 * Product badges for merchandising and conversion hints.
 */
final class BadgeService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_badges';

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
        add_action('woocommerce_before_single_product_summary', [$this, 'renderSingleBadges'], 6);
        add_action('woocommerce_before_shop_loop_item_title', [$this, 'renderLoopBadges'], 9);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('badge_management');
    }

    public function enqueueAssets(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        wp_enqueue_style(
            'polski-badges',
            \Polski\Plugin::instance()->url('assets/css/badges.css'),
            [],
            \Polski\VERSION,
        );
    }

    public function renderSingleBadges(): void
    {
        global $product;

        if (! $product instanceof \WC_Product || ! ($this->getSettings()['show_on_single'] ?? true)) {
            return;
        }

        $this->render($product, 'single');
    }

    public function renderLoopBadges(): void
    {
        global $product;

        if (! $product instanceof \WC_Product || ! ($this->getSettings()['show_on_loop'] ?? true)) {
            return;
        }

        $this->render($product, 'loop');
    }

    /**
     * @return list<array{text: string, style: string}>
     */
    public function getBadges(\WC_Product $product): array
    {
        $settings = $this->getSettings();
        $badges = [];

        $manual = trim((string) $product->get_meta('_polski_badge_text', true));
        $manualStyle = sanitize_key((string) $product->get_meta('_polski_badge_style', true));
        $secondary = trim((string) $product->get_meta('_polski_badge_secondary_text', true));

        if ((bool) ($settings['show_manual_badge'] ?? true) && $manual !== '') {
            $badges[] = ['text' => $manual, 'style' => $manualStyle !== '' ? $manualStyle : (string) ($settings['manual_badge_style'] ?? 'accent')];
        }

        if ((bool) ($settings['show_secondary_badge'] ?? true) && $secondary !== '') {
            $badges[] = ['text' => $secondary, 'style' => (string) ($settings['secondary_badge_style'] ?? 'neutral')];
        }

        if ((bool) ($settings['show_sale_badge'] ?? true) && $product->is_on_sale()) {
            $badges[] = ['text' => (string) ($settings['sale_badge_text'] ?? __('Sale', 'polski')), 'style' => 'warning'];
        }

        if ((bool) ($settings['show_new_badge'] ?? true) && $this->isNew($product)) {
            $badges[] = ['text' => (string) ($settings['new_badge_text'] ?? __('New', 'polski')), 'style' => 'success'];
        }

        if ((bool) ($settings['show_low_stock_badge'] ?? true) && $this->isLowStock($product)) {
            $badges[] = ['text' => (string) ($settings['low_stock_badge_text'] ?? __('Last items', 'polski')), 'style' => 'warning'];
        }

        if ((bool) ($settings['show_bestseller_badge'] ?? true) && $this->isBestseller($product)) {
            $badges[] = ['text' => (string) ($settings['bestseller_badge_text'] ?? __('Bestseller', 'polski')), 'style' => 'accent'];
        }

        $unique = [];

        foreach ($badges as $badge) {
            $key = $badge['text'] . '|' . $badge['style'];
            $unique[$key] = $badge;
        }

        $badges = array_values($unique);
        $limit = $this->getBadgeLimit();

        return array_slice($badges, 0, $limit);
    }

    private function render(\WC_Product $product, string $context): void
    {
        $badges = $this->getBadges($product);

        if ($badges === []) {
            return;
        }

        $this->templateLoader->include('shared/badges', [
            'badges' => $badges,
            'context' => $context,
            'product' => $product,
            'shape' => (string) ($this->getSettings()['shape'] ?? 'pill'),
            'uppercase' => (bool) ($this->getSettings()['uppercase'] ?? false),
        ]);
    }

    private function getBadgeLimit(): int
    {
        if (is_product()) {
            return max(1, (int) ($this->getSettings()['max_badges_single'] ?? 4));
        }

        return max(1, (int) ($this->getSettings()['max_badges_loop'] ?? 3));
    }

    private function isNew(\WC_Product $product): bool
    {
        $days = max(1, (int) ($this->getSettings()['newness_days'] ?? 30));
        $created = $product->get_date_created();

        if (! $created instanceof \WC_DateTime) {
            return false;
        }

        return $created->getTimestamp() >= strtotime('-' . $days . ' days');
    }

    private function isLowStock(\WC_Product $product): bool
    {
        if (! $product->managing_stock()) {
            return false;
        }

        $quantity = $product->get_stock_quantity();
        $threshold = max(1, (int) ($this->getSettings()['low_stock_threshold'] ?? 3));

        return $quantity !== null && $quantity > 0 && $quantity <= $threshold;
    }

    private function isBestseller(\WC_Product $product): bool
    {
        $threshold = max(1, (int) ($this->getSettings()['bestseller_threshold'] ?? 25));
        return (int) $product->get_total_sales() >= $threshold;
    }
}
