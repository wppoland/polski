<?php

declare(strict_types=1);

namespace Spolszczony\Service;

use Spolszczony\Admin\ModulesPage;
use Spolszczony\Contract\Bootable;
use Spolszczony\Contract\HasHooks;
use Spolszczony\Util\TemplateLoader;

/**
 * B2B catalog mode for hiding prices and purchase actions.
 */
final class CatalogModeService implements Bootable, HasHooks
{
    private const OPTION = 'spolszczony_catalog';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $settings = null;

    public function __construct(
        private readonly TemplateLoader $templateLoader,
        private readonly QuoteService $quoteService,
    ) {
    }

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        add_filter('woocommerce_is_purchasable', [$this, 'filterPurchasable'], 30, 2);
        add_filter('woocommerce_get_price_html', [$this, 'filterPriceHtml'], 30, 2);
        add_filter('woocommerce_loop_add_to_cart_link', [$this, 'filterLoopAddToCartLink'], 30, 3);
        add_action('woocommerce_single_product_summary', [$this, 'renderSingleNotice'], 30);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $defaults = require \Spolszczony\PLUGIN_DIR . '/config/defaults.php';
        $defaultSettings = is_array($defaults[self::OPTION] ?? null) ? $defaults[self::OPTION] : [];
        $saved = get_option(self::OPTION, []);
        $saved = is_array($saved) ? $saved : [];

        $this->settings = wp_parse_args($saved, $defaultSettings);

        return $this->settings;
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('catalog_mode');
    }

    public function filterPurchasable(bool $purchasable, \WC_Product $product): bool
    {
        if (is_admin() && ! wp_doing_ajax()) {
            return $purchasable;
        }

        if (! $this->shouldHideAddToCart($product)) {
            return $purchasable;
        }

        return false;
    }

    public function filterPriceHtml(string $priceHtml, \WC_Product $product): string
    {
        if (is_admin() && ! wp_doing_ajax()) {
            return $priceHtml;
        }

        if (! $this->shouldHidePrices($product)) {
            return $priceHtml;
        }

        return sprintf(
            '<span class="spolszczony-catalog-mode-price">%s</span>',
            esc_html($this->getHiddenPriceText($product)),
        );
    }

    /**
     * @param array<string, mixed> $args
     */
    public function filterLoopAddToCartLink(string $html, \WC_Product $product, array $args): string
    {
        if (! $this->shouldHideAddToCart($product)) {
            return $html;
        }

        $cta = $this->getCtaData($product, false);

        return $this->templateLoader->render('loop/catalog-mode-cta', [
            'cta' => $cta,
            'product' => $product,
            'notice' => (string) ($this->getSettings()['loop_notice'] ?? ''),
        ]);
    }

    public function renderSingleNotice(): void
    {
        global $product;

        if (! $product instanceof \WC_Product || ! $this->isActiveForProduct($product)) {
            return;
        }

        if (! $this->shouldHidePrices($product) && ! $this->shouldHideAddToCart($product)) {
            return;
        }

        $this->templateLoader->include('single-product/catalog-mode-notice', [
            'product' => $product,
            'notice' => $this->getSingleNotice($product),
            'cta' => $this->getCtaData($product, true),
            'hide_price' => $this->shouldHidePrices($product),
            'hide_cart' => $this->shouldHideAddToCart($product),
        ]);
    }

    public function isActiveForProduct(\WC_Product $product): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if (! $this->matchesAudience()) {
            return false;
        }

        $enabled = $this->getProductSetting($product, '_spolszczony_catalog_enabled');

        if ($enabled === 'yes') {
            return true;
        }

        return ($this->getSettings()['availability'] ?? 'selected') === 'all_products';
    }

    public function shouldHidePrices(\WC_Product $product): bool
    {
        if (! $this->isActiveForProduct($product)) {
            return false;
        }

        $productSetting = $this->getProductSetting($product, '_spolszczony_catalog_hide_price');

        if ($productSetting !== '') {
            return $productSetting === 'yes';
        }

        return (bool) ($this->getSettings()['hide_prices'] ?? true);
    }

    public function shouldHideAddToCart(\WC_Product $product): bool
    {
        if (! $this->isActiveForProduct($product)) {
            return false;
        }

        $productSetting = $this->getProductSetting($product, '_spolszczony_catalog_hide_cart');

        if ($productSetting !== '') {
            return $productSetting === 'yes';
        }

        return (bool) ($this->getSettings()['hide_add_to_cart'] ?? true);
    }

    /**
     * @return array{mode: string, label: string, url: string}|null
     */
    public function getCtaData(\WC_Product $product, bool $single): ?array
    {
        $settings = $this->getSettings();
        $mode = (string) ($settings['replacement_mode'] ?? 'quote');

        if (! $this->shouldHideAddToCart($product) || $mode === 'none') {
            return null;
        }

        $label = $this->getCtaText($product);

        if ($mode === 'login') {
            return [
                'mode' => $mode,
                'label' => $label,
                'url' => wc_get_page_permalink('myaccount'),
            ];
        }

        if ($mode === 'custom_url') {
            $url = esc_url_raw((string) ($settings['custom_url'] ?? ''));

            if ($url === '') {
                return null;
            }

            return [
                'mode' => $mode,
                'label' => $label,
                'url' => $url,
            ];
        }

        if ($mode === 'quote' && $this->quoteService->isAvailableForProduct($product)) {
            return [
                'mode' => $mode,
                'label' => $single ? $this->quoteService->getButtonText($product) : $label,
                'url' => $this->quoteService->getLoopButtonUrl($product),
            ];
        }

        return null;
    }

    private function matchesAudience(): bool
    {
        $settings = $this->getSettings();
        $audience = (string) ($settings['audience'] ?? 'guests_only');

        return match ($audience) {
            'all_users' => true,
            'logged_in' => is_user_logged_in(),
            'selected_roles' => $this->currentUserHasSelectedRole((string) ($settings['selected_roles'] ?? '')),
            default => ! is_user_logged_in(),
        };
    }

    private function currentUserHasSelectedRole(string $rolesList): bool
    {
        if (! is_user_logged_in()) {
            return false;
        }

        $roles = $this->parseRoles($rolesList);

        if ($roles === []) {
            return false;
        }

        $user = wp_get_current_user();

        return array_intersect($roles, (array) $user->roles) !== [];
    }

    /**
     * @return list<string>
     */
    private function parseRoles(string $rolesList): array
    {
        $roles = preg_split('/[\s,]+/', $rolesList) ?: [];
        $roles = array_map('sanitize_key', $roles);

        return array_values(array_filter(array_unique($roles)));
    }

    private function getProductSetting(\WC_Product $product, string $metaKey): string
    {
        $value = (string) $product->get_meta($metaKey, true);

        if ($value !== '') {
            return $value;
        }

        if ($product->is_type('variation')) {
            $parentId = $product->get_parent_id();

            if ($parentId > 0) {
                return (string) get_post_meta($parentId, $metaKey, true);
            }
        }

        return '';
    }

    private function getHiddenPriceText(\WC_Product $product): string
    {
        $message = $this->getProductSetting($product, '_spolszczony_catalog_message');

        if ($message !== '') {
            return $message;
        }

        return (string) ($this->getSettings()['hidden_price_text'] ?? '');
    }

    private function getSingleNotice(\WC_Product $product): string
    {
        $message = $this->getProductSetting($product, '_spolszczony_catalog_message');

        if ($message !== '') {
            return $message;
        }

        return (string) ($this->getSettings()['single_notice'] ?? '');
    }

    private function getCtaText(\WC_Product $product): string
    {
        $label = $this->getProductSetting($product, '_spolszczony_catalog_cta_text');

        if ($label !== '') {
            return $label;
        }

        return (string) ($this->getSettings()['cta_text'] ?? __('Zapytaj o warunki handlowe', 'spolszczony'));
    }
}
