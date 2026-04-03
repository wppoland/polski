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
 * Configurable product bundles with shared package discount.
 */
final class ProductBundlesService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_bundles';

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
        add_action('woocommerce_after_single_product_summary', [$this, 'renderSection'], 17);
        add_action('wp_loaded', [$this, 'handleAddBundle']);
        add_action('woocommerce_before_calculate_totals', [$this, 'applyBundleDiscounts'], 25);
        add_filter('woocommerce_get_item_data', [$this, 'renderItemData'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'addOrderItemMeta'], 10, 4);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('product_bundles');
    }

    public function enqueueAssets(): void
    {
        if (! $this->isEnabled() || ! is_product()) {
            return;
        }

        wp_enqueue_style(
            'polski-bundles',
            \Polski\Plugin::instance()->url('assets/css/bundles.css'),
            [],
            \Polski\VERSION,
        );
    }

    public function renderSection(): void
    {
        global $product;

        if (! $product instanceof \WC_Product || ! $this->isEnabled() || ! ($this->getSettings()['show_on_single'] ?? true)) {
            return;
        }

        $bundleItems = $this->getBundleItems($product);

        if ($bundleItems === []) {
            return;
        }

        $this->templateLoader->include('single-product/bundles', [
            'service' => $this,
            'product' => $product,
            'bundle_items' => $bundleItems,
            'settings' => $this->getSettings(),
            'title' => $this->getSectionTitle($product),
            'intro_text' => (string) ($this->getSettings()['intro_text'] ?? ''),
            'button_text' => $this->getButtonText($product),
            'show_total' => (bool) ($this->getSettings()['show_total'] ?? true),
            'show_quantities' => (bool) ($this->getSettings()['show_quantities'] ?? true),
            'pricing' => $this->getBundlePricing($product, $bundleItems, null),
        ]);
    }

    public function handleAddBundle(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (($_POST['polski_bundle_action'] ?? '') !== 'add_bundle') {
            return;
        }

        check_admin_referer('polski_bundle_add', 'polski_bundle_nonce');

        $productId = (int) wp_unslash($_POST['polski_bundle_product_id'] ?? 0);
        $product = wc_get_product($productId);

        if (! $product instanceof \WC_Product) {
            return;
        }

        $bundleItems = $this->getBundleItems($product);

        if ($bundleItems === []) {
            return;
        }

        $selectedItems = [];

        foreach ($bundleItems as $item) {
            $include = $item['required'];

            if (! $include) {
                $field = 'polski_bundle_include_' . $item['product']->get_id();
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $include = wp_unslash($_POST[$field] ?? '') === '1';
            }

            if (! $include) {
                continue;
            }

            $selectedItems[] = $item;
        }

        if ($selectedItems === []) {
            wc_add_notice((string) ($this->getSettings()['selection_required_text'] ?? __('Wybierz przynajmniej jeden dodatkowy produkt do zestawu.', 'polski')), 'error');
            return;
        }

        array_unshift($selectedItems, [
            'product' => $product,
            'quantity' => 1,
            'required' => true,
        ]);

        $pricing = $this->getBundlePricing($product, $selectedItems, null);
        $bundleKey = wp_generate_uuid4();
        $label = $this->getSectionTitle($product);
        $added = 0;

        foreach ($selectedItems as $item) {
            $bundleProduct = $item['product'];

            if (! $bundleProduct instanceof \WC_Product || ! $bundleProduct->is_purchasable()) {
                continue;
            }

            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $basePrice = (float) $bundleProduct->get_price('edit');
            $lineBase = $basePrice * $quantity;
            $share = $pricing['base_total'] > 0 ? ($lineBase / $pricing['base_total']) * $pricing['discount_total'] : 0.0;

            $cartItemData = [
                'polski_bundle' => [
                    'key' => $bundleKey,
                    'label' => $label,
                    'discount_share' => $share,
                    'bundle_product_id' => $product->get_id(),
                ],
                'polski_bundle_base_price' => $basePrice,
                'polski_bundle_hash' => md5($bundleKey . ':' . $bundleProduct->get_id()),
            ];

            if ($bundleProduct->is_type('variation')) {
                $variationId = $bundleProduct->get_id();
                $parentId = $bundleProduct->get_parent_id();
                $attributes = $bundleProduct->get_variation_attributes();

                if (WC()->cart->add_to_cart($parentId, $quantity, $variationId, $attributes, $cartItemData)) {
                    $added++;
                }

                continue;
            }

            if (WC()->cart->add_to_cart($bundleProduct->get_id(), $quantity, 0, [], $cartItemData)) {
                $added++;
            }
        }

        if ($added > 0) {
            wc_add_notice((string) ($this->getSettings()['success_text'] ?? __('Dodano zestaw do koszyka.', 'polski')), 'success');
        }

        wp_safe_redirect(get_permalink($product->get_id()) ?: wc_get_cart_url());
        exit;
    }

    public function applyBundleDiscounts(\WC_Cart $cart): void
    {
        if (is_admin() && ! defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $item) {
            if (
                empty($item['polski_bundle'])
                || empty($item['polski_bundle_base_price'])
                || ! isset($item['data'])
                || ! $item['data'] instanceof \WC_Product
            ) {
                continue;
            }

            $basePrice = (float) $item['polski_bundle_base_price'];
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $share = (float) ($item['polski_bundle']['discount_share'] ?? 0);
            $perUnitDiscount = $share > 0 ? $share / $quantity : 0.0;
            $item['data']->set_price(max(0.0, $basePrice - $perUnitDiscount));
        }
    }

    /**
     * @param list<array{name: string, value: string}> $itemData
     * @param array<string, mixed>                     $cartItem
     * @return list<array{name: string, value: string}>
     */
    public function renderItemData(array $itemData, array $cartItem): array
    {
        if (empty($cartItem['polski_bundle']) || ! is_array($cartItem['polski_bundle'])) {
            return $itemData;
        }

        $label = (string) ($cartItem['polski_bundle']['label'] ?? '');

        if ($label !== '') {
            $itemData[] = [
                'name' => (string) ($this->getSettings()['title'] ?? __('Zestaw', 'polski')),
                'value' => $label,
            ];
        }

        return $itemData;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function addOrderItemMeta(\WC_Order_Item_Product $item, string $cartItemKey, array $values, \WC_Order $order): void
    {
        if (empty($values['polski_bundle']) || ! is_array($values['polski_bundle'])) {
            return;
        }

        $label = (string) ($values['polski_bundle']['label'] ?? '');

        if ($label !== '') {
            $item->add_meta_data((string) ($this->getSettings()['title'] ?? __('Zestaw', 'polski')), $label, true);
        }
    }

    /**
     * @return list<array{product:\WC_Product,quantity:int,required:bool}>
     */
    public function getBundleItems(\WC_Product $product): array
    {
        $raw = trim((string) $product->get_meta('_polski_bundle_items', true));

        if ($raw === '') {
            return [];
        }

        $rows = preg_split('/\R+/', $raw) ?: [];

        if (count($rows) === 1 && ! str_contains($raw, '|') && str_contains($raw, ',')) {
            $rows = array_map('trim', explode(',', $raw));
        }

        $items = [];

        foreach ($rows as $row) {
            $row = trim($row);

            if ($row === '') {
                continue;
            }

            if (! str_contains($row, '|')) {
                $row .= '|1|';
            }

            [$relatedId, $quantity, $required] = array_pad(array_map('trim', explode('|', $row, 3)), 3, '');
            $relatedProduct = wc_get_product((int) $relatedId);

            if (! $relatedProduct instanceof \WC_Product) {
                continue;
            }

            $items[] = [
                'product' => $relatedProduct,
                'quantity' => max(1, (int) $quantity),
                'required' => in_array(strtolower($required), ['yes', '1', 'true', 'required'], true),
            ];
        }

        return $items;
    }

    public function getSectionTitle(\WC_Product $product): string
    {
        $custom = trim((string) $product->get_meta('_polski_bundle_title', true));

        if ($custom !== '') {
            return $custom;
        }

        return (string) ($this->getSettings()['title'] ?? __('Kup w zestawie', 'polski'));
    }

    public function getButtonText(\WC_Product $product): string
    {
        $custom = trim((string) $product->get_meta('_polski_bundle_button_text', true));

        if ($custom !== '') {
            return $custom;
        }

        return (string) ($this->getSettings()['button_text'] ?? __('Dodaj zestaw do koszyka', 'polski'));
    }

    /**
     * @param list<array{product:\WC_Product,quantity:int,required:bool}>      $items
     * @param array<int, bool>|null                                             $selectedMap
     * @return array{base_total:float,discount_total:float,final_total:float,savings_html:string,total_html:string}
     */
    public function getBundlePricing(\WC_Product $product, array $items, ?array $selectedMap): array
    {
        $baseTotal = (float) wc_get_price_to_display($product);

        foreach ($items as $item) {
            $bundleProduct = $item['product'];

            if (! $bundleProduct instanceof \WC_Product || $bundleProduct->get_id() === $product->get_id()) {
                continue;
            }

            if ($selectedMap !== null && ! ($selectedMap[$bundleProduct->get_id()] ?? $item['required'])) {
                continue;
            }

            $baseTotal += (float) wc_get_price_to_display($bundleProduct) * max(1, (int) ($item['quantity'] ?? 1));
        }

        $discountType = (string) $product->get_meta('_polski_bundle_discount_type', true);
        $discountValue = (float) $product->get_meta('_polski_bundle_discount_value', true);
        $discountTotal = 0.0;

        if ($discountType === 'fixed') {
            $discountTotal = min($baseTotal, max(0.0, $discountValue));
        } elseif ($discountType === 'percent') {
            $discountTotal = min($baseTotal, max(0.0, $baseTotal * ($discountValue / 100)));
        }

        $finalTotal = max(0.0, $baseTotal - $discountTotal);

        return [
            'base_total' => $baseTotal,
            'discount_total' => $discountTotal,
            'final_total' => $finalTotal,
            'savings_html' => Formatter::price($discountTotal),
            'total_html' => Formatter::price($finalTotal),
        ];
    }
}
