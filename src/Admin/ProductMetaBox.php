<?php

declare(strict_types=1);
namespace Polski\Admin;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * WooCommerce product edit screen meta boxes for Polski fields.
 *
 * Adds a "Polski" tab to the product data panel with:
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
     * Add "Polski" tab to product data panel.
     *
     * @param array<string, array<string, mixed>> $tabs
     * @return array<string, array<string, mixed>>
     */
    public function addProductTab(array $tabs): array
    {
        $tabs['polski'] = [
            'label' => __('Polski', 'polski'),
            'target' => 'polski_product_data',
            'class' => ['show_if_simple', 'show_if_variable', 'show_if_external'],
            'priority' => 80,
        ];

        return $tabs;
    }

    /**
     * Render the Polski product data panel.
     */
    public function renderProductPanel(): void
    {
        global $post;

        $productId = $post->ID ?? 0;

        echo '<div id="polski_product_data" class="panel woocommerce_options_panel">';

        // --- Unit Price Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Unit price', 'polski') . '</h4>';

        woocommerce_wp_text_input([
            'id' => '_polski_unit_price_product_amount',
            'label' => __('Product quantity', 'polski'),
            'description' => __('Product quantity in package (e.g. 500 for 500g).', 'polski'),
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_unit_price_base',
            'label' => __('Base quantity', 'polski'),
            'description' => __('Base reference quantity (e.g. 1 for "per 1 kg", 100 for "per 100 ml").', 'polski'),
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
        ]);

        // Unit selector (from taxonomy).
        $units = get_terms([
            'taxonomy' => 'polski_unit',
            'hide_empty' => false,
        ]);

        $unitOptions = ['' => __('- Select unit -', 'polski')];

        if (is_array($units)) {
            foreach ($units as $term) {
                if ($term instanceof \WP_Term) {
                    $unitOptions[$term->slug] = $term->name;
                }
            }
        }

        woocommerce_wp_select([
            'id' => '_polski_unit_price_unit',
            'label' => __('Unit', 'polski'),
            'options' => $unitOptions,
            'description' => __('Unit of measure for unit price display.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Delivery Time Section ---
        echo '<div class="options_group">';

        $deliveryTimes = get_terms([
            'taxonomy' => 'polski_delivery_time',
            'hide_empty' => false,
        ]);

        $dtOptions = ['' => __('- Use default -', 'polski')];

        if (is_array($deliveryTimes)) {
            foreach ($deliveryTimes as $term) {
                if ($term instanceof \WP_Term) {
                    $dtOptions[(string) $term->term_id] = $term->name;
                }
            }
        }

        woocommerce_wp_select([
            'id' => '_polski_delivery_time_id',
            'label' => __('Delivery time', 'polski'),
            'options' => $dtOptions,
            'description' => __('Estimated delivery time displayed on the product page.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Withdrawal Section ---
        echo '<div class="options_group">';

        woocommerce_wp_checkbox([
            'id' => '_polski_withdrawal_exempt',
            'label' => __('Right of withdrawal exemption', 'polski'),
            'description' => __('This product is exempt from the 14-day right of withdrawal (e.g. digital content, perishable goods).', 'polski'),
        ]);

        echo '</div>';

        // --- Badge Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Badge Management', 'polski') . '</h4>';

        woocommerce_wp_text_input([
            'id' => '_polski_badge_text',
            'label' => __('Main badge', 'polski'),
            'description' => __('Manual badge displayed independently of automatic conditions.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_select([
            'id' => '_polski_badge_style',
            'label' => __('Badge style', 'polski'),
            'options' => [
                '' => __('Default', 'polski'),
                'accent' => __('Accent', 'polski'),
                'success' => __('Success', 'polski'),
                'warning' => __('Warning', 'polski'),
                'neutral' => __('Neutral', 'polski'),
            ],
            'description' => __('Manual badge style.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_badge_secondary_text',
            'label' => __('Secondary badge', 'polski'),
            'description' => __('Optional second badge, e.g. Polish brand, Eco, Sale hit.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Tab Manager Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Tab Manager', 'polski') . '</h4>';

        woocommerce_wp_text_input([
            'id' => '_polski_tab_1_title',
            'label' => __('Tab 1 title', 'polski'),
            'description' => __('Optional additional tab for this product.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_tab_1_content',
            'label' => __('Tab 1 content', 'polski'),
            'description' => __('HTML/text content for the first additional tab.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_tab_2_title',
            'label' => __('Tab 2 title', 'polski'),
            'description' => __('Second optional product tab.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_tab_2_content',
            'label' => __('Tab 2 content', 'polski'),
            'description' => __('HTML/text content for the second additional tab.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Featured Video Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Featured Video', 'polski') . '</h4>';

        woocommerce_wp_text_input([
            'id' => '_polski_featured_video_url',
            'label' => __('Video URL', 'polski'),
            'description' => __('Supports YouTube, Vimeo, and direct MP4 file links.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_featured_video_title',
            'label' => __('Video section heading', 'polski'),
            'description' => __('Optional heading only for this product.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- GPSR Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('GPSR - Product Safety', 'polski') . '</h4>';

        woocommerce_wp_text_input([
            'id' => '_polski_gpsr_manufacturer_name',
            'label' => __('Manufacturer name', 'polski'),
            'description' => __('Full manufacturer name required by GPSR.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_gpsr_manufacturer_address',
            'label' => __('Manufacturer address', 'polski'),
            'description' => __('Full postal address of the manufacturer.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_gpsr_importer_name',
            'label' => __('Importer name', 'polski'),
            'description' => __('Full importer name (if applicable).', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_gpsr_importer_address',
            'label' => __('Importer address', 'polski'),
            'description' => __('Full postal address of the importer.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_gpsr_responsible_person',
            'label' => __('Responsible person', 'polski'),
            'description' => __('Person responsible in the EU for product compliance with GPSR.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_gpsr_product_identifier',
            'label' => __('Product identifier', 'polski'),
            'description' => __('Batch number, serial number, or other product identifier.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_gpsr_safety_warnings',
            'label' => __('Safety warnings', 'polski'),
            'description' => __('Safety warnings regarding the product.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_gpsr_instructions',
            'label' => __('Safety instructions', 'polski'),
            'description' => __('Instructions for safe use of the product.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Anti-greenwashing Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Environmental claims (Anti-greenwashing)', 'polski') . '</h4>';

        woocommerce_wp_textarea_input([
            'id' => '_polski_green_claim_basis',
            'label' => __('Environmental claim basis', 'polski'),
            'description' => __('Scientific or legal basis for the environmental claim (required by the anti-greenwashing directive).', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_green_claim_cert_url',
            'label' => __('Certificate link', 'polski'),
            'description' => __('URL to the official certificate supporting the environmental claim.', 'polski'),
            'desc_tip' => true,
            'type' => 'url',
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_green_claim_expiry',
            'label' => __('Certificate expiry date (YYYY-MM-DD)', 'polski'),
            'description' => __('Environmental certificate expiry date in YYYY-MM-DD format.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        echo '</div>';
    }

    /**
     * Save Polski product meta fields.
     */
    public function saveProductMeta(int $productId): void
    {
        $product = wc_get_product($productId);

        if (! $product instanceof \WC_Product) {
            return;
        }

        $fields = [
            '_polski_unit_price_product_amount' => 'float',
            '_polski_unit_price_base' => 'float',
            '_polski_unit_price_unit' => 'string',
            '_polski_delivery_time_id' => 'string',
            '_polski_withdrawal_exempt' => 'checkbox',
            '_polski_badge_text' => 'string',
            '_polski_badge_style' => 'string',
            '_polski_badge_secondary_text' => 'string',
            '_polski_tab_1_title' => 'string',
            '_polski_tab_1_content' => 'textarea',
            '_polski_tab_2_title' => 'string',
            '_polski_tab_2_content' => 'textarea',
            '_polski_featured_video_url' => 'string',
            '_polski_featured_video_title' => 'string',
            '_polski_gpsr_manufacturer_name' => 'string',
            '_polski_gpsr_manufacturer_address' => 'textarea',
            '_polski_gpsr_importer_name' => 'string',
            '_polski_gpsr_importer_address' => 'textarea',
            '_polski_gpsr_responsible_person' => 'string',
            '_polski_gpsr_product_identifier' => 'string',
            '_polski_gpsr_safety_warnings' => 'textarea',
            '_polski_gpsr_instructions' => 'textarea',
            '_polski_green_claim_basis' => 'textarea',
            '_polski_green_claim_cert_url' => 'url',
            '_polski_green_claim_expiry' => 'string',
        ];

        foreach ($fields as $key => $type) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies nonce/capability before this hook fires.
            if (! isset($_POST[$key])) {
                $sanitized = $type === 'checkbox' ? 'no' : '';
            } else {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $rawValue = sanitize_text_field((string) wp_unslash($_POST[$key]));
                $sanitized = match ($type) {
                    'float' => (string) (float) $rawValue,
                    'textarea' => sanitize_textarea_field($rawValue),
                    'url' => esc_url_raw($rawValue),
                    'string' => $rawValue,
                    'checkbox' => $rawValue === 'yes' ? 'yes' : 'no',
                };
            }

            $product->update_meta_data($key, $sanitized);
        }

        $product->save_meta_data();
    }

    /**
     * Render Polski fields for variable product variations.
     *
     * @param int                  $loop
     * @param array<string, mixed> $variationData
     * @param \WP_Post             $variation
     */
    public function renderVariationFields(int $loop, array $variationData, \WP_Post $variation): void
    {
        $variationId = $variation->ID;

        echo '<div class="polski-variation-fields">';
        echo '<p class="form-row form-row-full"><strong>' . esc_html__('Polski', 'polski') . '</strong></p>';

        woocommerce_wp_text_input([
            'id' => "_polski_unit_price_product_amount_{$loop}",
            'name' => "_polski_variation_unit_price_product_amount[{$loop}]",
            'label' => __('Product quantity', 'polski'),
            'value' => get_post_meta($variationId, '_polski_unit_price_product_amount', true),
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
            'wrapper_class' => 'form-row form-row-first',
        ]);

        woocommerce_wp_text_input([
            'id' => "_polski_unit_price_base_{$loop}",
            'name' => "_polski_variation_unit_price_base[{$loop}]",
            'label' => __('Base quantity', 'polski'),
            'value' => get_post_meta($variationId, '_polski_unit_price_base', true),
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
            'wrapper_class' => 'form-row form-row-last',
        ]);

        echo '</div>';
    }

    /**
     * Save Polski variation meta.
     */
    public function saveVariationMeta(int $variationId, int $loop): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies nonce/capability before this hook fires.
        $productAmount = isset($_POST['_polski_variation_unit_price_product_amount'][$loop])
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            ? sanitize_text_field((string) wp_unslash($_POST['_polski_variation_unit_price_product_amount'][$loop]))
            : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $baseAmount = isset($_POST['_polski_variation_unit_price_base'][$loop])
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            ? sanitize_text_field((string) wp_unslash($_POST['_polski_variation_unit_price_base'][$loop]))
            : '';

        update_post_meta($variationId, '_polski_unit_price_product_amount', (string) (float) $productAmount);
        update_post_meta($variationId, '_polski_unit_price_base', (string) (float) $baseAmount);
    }
}
