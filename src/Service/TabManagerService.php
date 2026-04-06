<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Util\SettingsCacheable;

/**
 * Adds extra product tabs from global and product-level content.
 */
final class TabManagerService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_tabs';

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        add_filter('woocommerce_product_tabs', [$this, 'filterTabs'], 30);
    }

    /**
     * @param array<string, mixed> $tabs
     * @return array<string, mixed>
     */
    public function filterTabs(array $tabs): array
    {
        if (! ModulesPage::isModuleEnabled('tab_manager')) {
            return $tabs;
        }

        global $product;

        if (! $product instanceof \WC_Product) {
            return $tabs;
        }

        $customTabs = $this->getCustomTabs($product);

        foreach ($customTabs as $key => $tab) {
            $tabs[$key] = $tab;
        }

        return $tabs;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getCustomTabs(\WC_Product $product): array
    {
        $tabs = [];
        $settings = $this->getSettings();

        $tab1Title = trim((string) $product->get_meta('_polski_tab_1_title', true));
        $tab1Content = trim((string) $product->get_meta('_polski_tab_1_content', true));

        if ((bool) ($settings['enable_product_tab_1'] ?? true) && $tab1Title !== '' && $tab1Content !== '') {
            $tabs['polski_tab_1'] = $this->buildTab($tab1Title, $tab1Content, max(1, (int) ($settings['product_tab_1_priority'] ?? 45)));
        }

        $tab2Title = trim((string) $product->get_meta('_polski_tab_2_title', true));
        $tab2Content = trim((string) $product->get_meta('_polski_tab_2_content', true));

        if ((bool) ($settings['enable_product_tab_2'] ?? true) && $tab2Title !== '' && $tab2Content !== '') {
            $tabs['polski_tab_2'] = $this->buildTab($tab2Title, $tab2Content, max(1, (int) ($settings['product_tab_2_priority'] ?? 46)));
        }

        if ((bool) ($settings['enable_global_shipping_tab'] ?? false) && trim((string) ($settings['shipping_tab_content'] ?? '')) !== '') {
            $tabs['polski_shipping_tab'] = $this->buildTab(
                (string) ($settings['shipping_tab_title'] ?? __('Shipping and Payment', 'polski')),
                (string) ($settings['shipping_tab_content'] ?? ''),
                max(1, (int) ($settings['shipping_tab_priority'] ?? 47)),
            );
        }

        if ((bool) ($settings['enable_global_returns_tab'] ?? false) && trim((string) ($settings['returns_tab_content'] ?? '')) !== '') {
            $tabs['polski_returns_tab'] = $this->buildTab(
                (string) ($settings['returns_tab_title'] ?? __('Returns and Complaints', 'polski')),
                (string) ($settings['returns_tab_content'] ?? ''),
                max(1, (int) ($settings['returns_tab_priority'] ?? 48)),
            );
        }

        return $tabs;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTab(string $title, string $content, int $priority): array
    {
        return [
            'title' => $title,
            'priority' => $priority,
            'callback' => static function () use ($content): void {
                echo wp_kses_post(wpautop(wp_kses_post($content)));
            },
        ];
    }
}
