<?php

declare(strict_types=1);

namespace Spolszczony\Admin;

use Spolszczony\Contract\HasHooks;

/**
 * WooCommerce product edit screen meta boxes for Spolszczony fields.
 *
 * Adds a "Spolszczony" tab to the product data panel with:
 * - Unit price fields (base amount, unit, product amount)
 * - Delivery time selector
 * - Manufacturer selector
 */
final class ProductMetaBox implements HasHooks
{
    public function registerHooks(): void
    {
        // Add tab to WooCommerce product data tabs.
        add_filter('woocommerce_product_data_tabs', [$this, 'addProductTab']);

        // Render tab content.
        add_action('woocommerce_product_data_panels', [$this, 'renderProductPanel']);

        // Save meta on product save.
        add_action('woocommerce_process_product_meta', [$this, 'saveProductMeta']);

        // Variable product fields.
        add_action('woocommerce_product_after_variable_attributes', [$this, 'renderVariationFields'], 10, 3);
        add_action('woocommerce_save_product_variation', [$this, 'saveVariationMeta'], 10, 2);
    }

    /**
     * Add "Spolszczony" tab to product data panel.
     *
     * @param array<string, array<string, mixed>> $tabs
     * @return array<string, array<string, mixed>>
     */
    public function addProductTab(array $tabs): array
    {
        $tabs['spolszczony'] = [
            'label' => __('Spolszczony', 'spolszczony'),
            'target' => 'spolszczony_product_data',
            'class' => ['show_if_simple', 'show_if_variable', 'show_if_external'],
            'priority' => 80,
        ];

        return $tabs;
    }

    /**
     * Render the Spolszczony product data panel.
     */
    public function renderProductPanel(): void
    {
        global $post;

        $productId = $post->ID ?? 0;

        echo '<div id="spolszczony_product_data" class="panel woocommerce_options_panel">';

        // --- Unit Price Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Unit Price (Cena jednostkowa)', 'spolszczony') . '</h4>';

        woocommerce_wp_text_input([
            'id' => '_spolszczony_unit_price_product_amount',
            'label' => __('Product amount', 'spolszczony'),
            'description' => __('Amount of product in the package (e.g., 500 for 500g).', 'spolszczony'),
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
        ]);

        woocommerce_wp_text_input([
            'id' => '_spolszczony_unit_price_base',
            'label' => __('Base amount', 'spolszczony'),
            'description' => __('The reference base amount (e.g., 1 for "per 1 kg", 100 for "per 100 ml").', 'spolszczony'),
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
        ]);

        // Unit selector (from taxonomy).
        $units = get_terms([
            'taxonomy' => 'spolszczony_unit',
            'hide_empty' => false,
        ]);

        $unitOptions = ['' => __('— Select unit —', 'spolszczony')];

        if (is_array($units)) {
            foreach ($units as $term) {
                if ($term instanceof \WP_Term) {
                    $unitOptions[$term->slug] = $term->name;
                }
            }
        }

        woocommerce_wp_select([
            'id' => '_spolszczony_unit_price_unit',
            'label' => __('Unit', 'spolszczony'),
            'options' => $unitOptions,
            'description' => __('Measurement unit for the unit price display.', 'spolszczony'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Delivery Time Section ---
        echo '<div class="options_group">';

        $deliveryTimes = get_terms([
            'taxonomy' => 'spolszczony_delivery_time',
            'hide_empty' => false,
        ]);

        $dtOptions = ['' => __('— Use default —', 'spolszczony')];

        if (is_array($deliveryTimes)) {
            foreach ($deliveryTimes as $term) {
                if ($term instanceof \WP_Term) {
                    $dtOptions[(string) $term->term_id] = $term->name;
                }
            }
        }

        woocommerce_wp_select([
            'id' => '_spolszczony_delivery_time_id',
            'label' => __('Delivery time', 'spolszczony'),
            'options' => $dtOptions,
            'description' => __('Estimated delivery time displayed on product page.', 'spolszczony'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Withdrawal Section ---
        echo '<div class="options_group">';

        woocommerce_wp_checkbox([
            'id' => '_spolszczony_withdrawal_exempt',
            'label' => __('Withdrawal exempt', 'spolszczony'),
            'description' => __('This product is exempt from the 14-day withdrawal right (e.g., digital content, perishable goods).', 'spolszczony'),
        ]);

        echo '</div>';

        echo '</div>';
    }

    /**
     * Save Spolszczony product meta fields.
     */
    public function saveProductMeta(int $productId): void
    {
        $product = wc_get_product($productId);

        if (! $product instanceof \WC_Product) {
            return;
        }

        $fields = [
            '_spolszczony_unit_price_product_amount' => 'float',
            '_spolszczony_unit_price_base' => 'float',
            '_spolszczony_unit_price_unit' => 'string',
            '_spolszczony_delivery_time_id' => 'string',
            '_spolszczony_withdrawal_exempt' => 'checkbox',
        ];

        foreach ($fields as $key => $type) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce.
            $value = $_POST[$key] ?? '';

            $sanitized = match ($type) {
                'float' => (string) (float) $value,
                'string' => sanitize_text_field((string) $value),
                'checkbox' => ($value === 'yes') ? 'yes' : 'no',
            };

            $product->update_meta_data($key, $sanitized);
        }

        $product->save_meta_data();
    }

    /**
     * Render Spolszczony fields for variable product variations.
     *
     * @param int                  $loop
     * @param array<string, mixed> $variationData
     * @param \WP_Post             $variation
     */
    public function renderVariationFields(int $loop, array $variationData, \WP_Post $variation): void
    {
        $variationId = $variation->ID;

        echo '<div class="spolszczony-variation-fields">';
        echo '<p class="form-row form-row-full"><strong>' . esc_html__('Spolszczony', 'spolszczony') . '</strong></p>';

        woocommerce_wp_text_input([
            'id' => "_spolszczony_unit_price_product_amount_{$loop}",
            'name' => "_spolszczony_variation_unit_price_product_amount[{$loop}]",
            'label' => __('Product amount', 'spolszczony'),
            'value' => get_post_meta($variationId, '_spolszczony_unit_price_product_amount', true),
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
            'wrapper_class' => 'form-row form-row-first',
        ]);

        woocommerce_wp_text_input([
            'id' => "_spolszczony_unit_price_base_{$loop}",
            'name' => "_spolszczony_variation_unit_price_base[{$loop}]",
            'label' => __('Base amount', 'spolszczony'),
            'value' => get_post_meta($variationId, '_spolszczony_unit_price_base', true),
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
            'wrapper_class' => 'form-row form-row-last',
        ]);

        echo '</div>';
    }

    /**
     * Save Spolszczony variation meta.
     */
    public function saveVariationMeta(int $variationId, int $loop): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce.
        $productAmount = $_POST['_spolszczony_variation_unit_price_product_amount'][$loop] ?? '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $baseAmount = $_POST['_spolszczony_variation_unit_price_base'][$loop] ?? '';

        update_post_meta($variationId, '_spolszczony_unit_price_product_amount', (string) (float) $productAmount);
        update_post_meta($variationId, '_spolszczony_unit_price_base', (string) (float) $baseAmount);
    }
}
