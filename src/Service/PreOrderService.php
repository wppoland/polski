<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Util\Formatter;
use Polski\Util\SettingsCacheable;
use Polski\Util\TemplateLoader;

/**
 * Pre-order product flow.
 */
final class PreOrderService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_preorder';

    public function __construct(
        private readonly TemplateLoader $templateLoader,
    ) {
    }

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        add_filter('woocommerce_product_single_add_to_cart_text', [$this, 'filterButtonText'], 20, 2);
        add_filter('woocommerce_product_add_to_cart_text', [$this, 'filterButtonText'], 20, 2);
        add_filter('woocommerce_get_availability_text', [$this, 'filterAvailabilityText'], 20, 2);
        add_action('woocommerce_single_product_summary', [$this, 'renderNotice'], 31);
        add_action('woocommerce_before_calculate_totals', [$this, 'validateMixedCart']);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('pre_order');
    }

    public function isPreOrder(\WC_Product $product): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return $product->get_meta('_polski_preorder_enabled', true) === 'yes'
            && $this->getReleaseDate($product) !== null;
    }

    public function filterButtonText(string $text, \WC_Product $product): string
    {
        if (! $this->isPreOrder($product)) {
            return $text;
        }

        $custom = trim((string) $product->get_meta('_polski_preorder_button_text', true));

        if ($custom !== '') {
            return $custom;
        }

        return (string) ($this->getSettings()['button_text'] ?? __('Zamów w przedsprzedaży', 'polski'));
    }

    public function filterAvailabilityText(string $text, \WC_Product $product): string
    {
        if (! $this->isPreOrder($product)) {
            return $text;
        }

        $date = $this->getReleaseDate($product);

        if ($date === null) {
            return $text;
        }

        $template = (string) ($this->getSettings()['availability_text'] ?? 'Przedsprzedaż, wysyłka od {date}');

        return Formatter::interpolate($template, [
            'date' => $this->formatDate($date),
        ]);
    }

    public function renderNotice(): void
    {
        global $product;

        if (! $product instanceof \WC_Product || ! ($this->getSettings()['show_on_single'] ?? true) || ! ($this->getSettings()['show_notice'] ?? true) || ! $this->isPreOrder($product)) {
            return;
        }

        $date = $this->getReleaseDate($product);

        if ($date === null) {
            return;
        }

        $template = (string) ($this->getSettings()['notice_text'] ?? '');
        $notice = Formatter::interpolate($template, [
            'date' => $this->formatDate($date),
        ]);

        $this->templateLoader->include('single-product/preorder-notice', [
            'notice' => $notice,
            'product' => $product,
            'title' => (string) ($this->getSettings()['notice_title'] ?? __('Przedsprzedaż', 'polski')),
            'show_title' => (bool) ($this->getSettings()['show_notice_title'] ?? false),
        ]);
    }

    public function validateMixedCart(\WC_Cart $cart): void
    {
        if (is_admin() && ! defined('DOING_AJAX')) {
            return;
        }

        if (! $this->isEnabled() || ($this->getSettings()['allow_mixed_cart'] ?? true)) {
            return;
        }

        $containsPreOrder = false;
        $containsRegular = false;

        foreach ($cart->get_cart() as $item) {
            $product = $item['data'] ?? null;

            if (! $product instanceof \WC_Product) {
                continue;
            }

            if ($this->isPreOrder($product)) {
                $containsPreOrder = true;
            } else {
                $containsRegular = true;
            }
        }

        if ($containsPreOrder && $containsRegular) {
            wc_add_notice((string) ($this->getSettings()['mixed_cart_error_text'] ?? __('Produkty z przedsprzedaży nie mogą być łączone z innymi produktami w tym samym koszyku.', 'polski')), 'error');
        }
    }

    private function getReleaseDate(\WC_Product $product): ?\DateTimeImmutable
    {
        $raw = trim((string) $product->get_meta('_polski_preorder_date', true));

        if ($raw === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return null;
        }
    }

    private function formatDate(\DateTimeImmutable $date): string
    {
        $format = (string) ($this->getSettings()['date_format'] ?? 'd.m.Y');

        return wp_date($format, $date->getTimestamp());
    }
}
