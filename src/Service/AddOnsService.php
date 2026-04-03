<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Util\SettingsCacheable;
use Polski\Util\TemplateLoader;

/**
 * Product add-ons with cart and order propagation.
 */
final class AddOnsService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_addons';

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
        add_action('woocommerce_before_add_to_cart_button', [$this, 'renderAddOns'], 8);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validateAddOns'], 10, 3);
        add_filter('woocommerce_add_cart_item_data', [$this, 'addCartItemData'], 10, 3);
        add_action('woocommerce_before_calculate_totals', [$this, 'applyAddOnPrices'], 20);
        add_filter('woocommerce_get_item_data', [$this, 'renderItemData'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'addOrderItemMeta'], 10, 4);
    }

    public function renderAddOns(): void
    {
        global $product;

        if (! $product instanceof \WC_Product || ! ModulesPage::isModuleEnabled('product_add_ons') || ! ($this->getSettings()['show_on_single'] ?? true)) {
            return;
        }

        $addOns = $this->getAddOns($product);

        if ($addOns === []) {
            return;
        }

        $this->templateLoader->include('single-product/addons', [
            'add_ons' => $addOns,
            'section_title' => (string) ($this->getSettings()['section_title'] ?? ''),
            'section_intro' => (string) ($this->getSettings()['section_intro'] ?? ''),
            'settings' => $this->getSettings(),
            'product' => $product,
        ]);
    }

    public function enqueueAssets(): void
    {
        if (! ModulesPage::isModuleEnabled('product_add_ons') || ! is_product()) {
            return;
        }

        wp_enqueue_style(
            'polski-addons',
            \Polski\Plugin::instance()->url('assets/css/addons.css'),
            [],
            \Polski\VERSION,
        );
    }

    public function validateAddOns(bool $passed, int $productId, int $quantity): bool
    {
        $product = wc_get_product($productId);

        if (! $product instanceof \WC_Product) {
            return $passed;
        }

        foreach ($this->getAddOns($product) as $addOn) {
            $fieldKey = $this->fieldKey($addOn['index']);
            $value = wp_unslash($_POST[$fieldKey] ?? '');

            if (! $addOn['required']) {
                continue;
            }

            if ($addOn['type'] === 'checkbox' && $value !== '1') {
                wc_add_notice(sprintf(__('Wybierz opcję: %s.', 'polski'), $addOn['label']), 'error');
                return false;
            }

            if (in_array($addOn['type'], ['select', 'text', 'textarea'], true) && trim((string) $value) === '') {
                wc_add_notice(sprintf(__('Uzupełnij pole: %s.', 'polski'), $addOn['label']), 'error');
                return false;
            }

            if (
                in_array($addOn['type'], ['text', 'textarea'], true)
                && $addOn['max_length'] > 0
                && mb_strlen(trim((string) $value)) > $addOn['max_length']
            ) {
                wc_add_notice(
                    sprintf(
                        __('Pole %1$s może mieć maksymalnie %2$d znaków.', 'polski'),
                        $addOn['label'],
                        $addOn['max_length'],
                    ),
                    'error',
                );
                return false;
            }
        }

        return $passed;
    }

    /**
     * @param array<string, mixed> $cartItemData
     * @return array<string, mixed>
     */
    public function addCartItemData(array $cartItemData, int $productId, int $variationId): array
    {
        $product = wc_get_product($variationId > 0 ? $variationId : $productId);

        if (! $product instanceof \WC_Product) {
            return $cartItemData;
        }

        $selected = [];

        foreach ($this->getAddOns($product) as $addOn) {
            $fieldKey = $this->fieldKey($addOn['index']);
            $raw = wp_unslash($_POST[$fieldKey] ?? '');
            $selection = $this->normalizeSelection($addOn, $raw);

            if ($selection === null) {
                continue;
            }

            $selected[] = $selection;
        }

        if ($selected !== []) {
            $cartItemData['polski_addons'] = $selected;
            $cartItemData['polski_addons_base_price'] = (float) $product->get_price('edit');
            $cartItemData['polski_addons_hash'] = md5(wp_json_encode($selected) ?: '');
        }

        return $cartItemData;
    }

    public function applyAddOnPrices(\WC_Cart $cart): void
    {
        if (is_admin() && ! defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $item) {
            if (
                empty($item['polski_addons'])
                || ! isset($item['data'])
                || ! $item['data'] instanceof \WC_Product
                || ! array_key_exists('polski_addons_base_price', $item)
            ) {
                continue;
            }

            $extra = 0.0;

            foreach ($item['polski_addons'] as $addOn) {
                $extra += (float) ($addOn['price'] ?? 0);
            }

            if ($extra > 0) {
                $item['data']->set_price(max(0.0, (float) $item['polski_addons_base_price'] + $extra));
            }
        }
    }

    /**
     * @param list<array{name: string, value: string}> $itemData
     * @param array<string, mixed>                     $cartItem
     * @return list<array{name: string, value: string}>
     */
    public function renderItemData(array $itemData, array $cartItem): array
    {
        if (empty($cartItem['polski_addons']) || ! is_array($cartItem['polski_addons'])) {
            return $itemData;
        }

        foreach ($cartItem['polski_addons'] as $addOn) {
            $label = (string) ($addOn['label'] ?? '');
            $value = (string) ($addOn['value_label'] ?? '');

            if ($label === '' || $value === '') {
                continue;
            }

            $itemData[] = [
                'name' => $label,
                'value' => $value,
            ];
        }

        return $itemData;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function addOrderItemMeta(\WC_Order_Item_Product $item, string $cartItemKey, array $values, \WC_Order $order): void
    {
        if (empty($values['polski_addons']) || ! is_array($values['polski_addons'])) {
            return;
        }

        foreach ($values['polski_addons'] as $addOn) {
            $label = (string) ($addOn['label'] ?? '');
            $value = (string) ($addOn['value_label'] ?? '');

            if ($label === '' || $value === '') {
                continue;
            }

            $item->add_meta_data($label, $value, true);
        }
    }

    /**
     * @return list<array{index:int,type:string,label:string,description:string,placeholder:string,price:float,required:bool,max_length:int,options:array<string,float>}>
     */
    public function getAddOns(\WC_Product $product): array
    {
        $raw = trim((string) $product->get_meta('_polski_addons_config', true));

        if ($raw === '') {
            return [];
        }

        $rows = preg_split('/\R+/', $raw) ?: [];
        $addOns = [];

        foreach ($rows as $index => $row) {
            $row = trim($row);

            if ($row === '') {
                continue;
            }

            [$type, $label, $price, $required, $options, $description, $placeholder, $maxLength] = array_pad(array_map('trim', explode('|', $row, 8)), 8, '');
            $type = sanitize_key($type);

            if (! in_array($type, ['checkbox', 'select', 'text', 'textarea'], true) || $label === '') {
                continue;
            }

            $addOns[] = [
                'index' => $index,
                'type' => $type,
                'label' => $label,
                'description' => $description,
                'placeholder' => $placeholder,
                'price' => (float) str_replace(',', '.', $price),
                'required' => in_array(strtolower($required), ['1', 'yes', 'true', 'required'], true),
                'max_length' => max(0, (int) $maxLength),
                'options' => $type === 'select' ? $this->parseOptions($options) : [],
            ];
        }

        return $addOns;
    }

    /**
     * @return array<string,float>
     */
    private function parseOptions(string $raw): array
    {
        $options = [];

        foreach (preg_split('/\s*;\s*/', $raw) ?: [] as $option) {
            if ($option === '') {
                continue;
            }

            [$label, $price] = array_pad(array_map('trim', explode('=', $option, 2)), 2, '0');

            if ($label === '') {
                continue;
            }

            $options[$label] = (float) str_replace(',', '.', $price);
        }

        return $options;
    }

    private function fieldKey(int $index): string
    {
        return 'polski_addon_' . $index;
    }

    /**
     * @param array{index:int,type:string,label:string,description:string,placeholder:string,price:float,required:bool,max_length:int,options:array<string,float>} $addOn
     * @return array<string,mixed>|null
     */
    private function normalizeSelection(array $addOn, mixed $raw): ?array
    {
        if ($addOn['type'] === 'checkbox') {
            if ((string) $raw !== '1') {
                return null;
            }

            return [
                'label' => $addOn['label'],
                'value' => '1',
                'value_label' => __('Tak', 'polski'),
                'price' => $addOn['price'],
            ];
        }

        if ($addOn['type'] === 'select') {
            $value = sanitize_text_field((string) $raw);

            if ($value === '' || ! array_key_exists($value, $addOn['options'])) {
                return null;
            }

            return [
                'label' => $addOn['label'],
                'value' => $value,
                'value_label' => $value,
                'price' => (float) $addOn['options'][$value],
            ];
        }

        $value = $addOn['type'] === 'textarea'
            ? sanitize_textarea_field((string) $raw)
            : sanitize_text_field((string) $raw);
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if ($addOn['max_length'] > 0 && mb_strlen($value) > $addOn['max_length']) {
            $value = mb_substr($value, 0, $addOn['max_length']);
        }

        return [
            'label' => $addOn['label'],
            'value' => $value,
            'value_label' => $value,
            'price' => $addOn['price'],
        ];
    }
}
