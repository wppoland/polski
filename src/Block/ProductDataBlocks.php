<?php

declare(strict_types=1);

namespace Polski\Block;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * Registers product-data blocks for block themes (FSE) and the post/page editor,
 * mirroring the Polski Elementor widgets. Each block renders through its existing
 * shortcode, which resolves the current product from context and self-skips when
 * there is no product or the field is empty - so the blocks are safe to register
 * unconditionally and render nothing outside a product context.
 *
 * Block metadata (icon, category, supports) comes from blocks/<slug>/block.json;
 * the translated title/description and the render callback are supplied here.
 */
final class ProductDataBlocks implements HasHooks
{
    public function registerHooks(): void
    {
        add_action('init', [$this, 'registerBlocks']);
    }

    public function registerBlocks(): void
    {
        if (! function_exists('register_block_type')) {
            return;
        }

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/unit-price', [
            'title' => __('Unit Price', 'polski'),
            'description' => __('Displays the price per unit (per kg, litre, piece, etc.) for the current product.', 'polski'),
            'render_callback' => static fn (): string => do_shortcode('[polski_unit_price]'),
        ]);

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/delivery-time', [
            'title' => __('Delivery Time', 'polski'),
            'description' => __('Displays the estimated delivery time for the current product.', 'polski'),
            'render_callback' => static fn (): string => do_shortcode('[polski_delivery_time]'),
        ]);

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/omnibus-price', [
            'title' => __('Lowest Price (Omnibus)', 'polski'),
            'description' => __('Displays the lowest price from the last 30 days (Omnibus) for the current product.', 'polski'),
            'render_callback' => static fn (): string => do_shortcode('[polski_omnibus_price]'),
        ]);

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/tax-notice', [
            'title' => __('Tax Information', 'polski'),
            'description' => __('Displays the gross/net price and VAT tax information for the current product.', 'polski'),
            'render_callback' => static fn (): string => do_shortcode('[polski_tax_notice]'),
        ]);

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/shipping-notice', [
            'title' => __('Shipping costs', 'polski'),
            'description' => __('Displays the shipping cost notice for the current product.', 'polski'),
            'render_callback' => static fn (): string => do_shortcode('[polski_shipping_notice]'),
        ]);

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/manufacturer', [
            'title' => __('Manufacturer', 'polski'),
            'description' => __('Displays the manufacturer and EU responsible person details for the current product.', 'polski'),
            'render_callback' => static fn (): string => do_shortcode('[polski_manufacturer]'),
        ]);

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/safety-info', [
            'title' => __('Safety Instructions', 'polski'),
            'description' => __('Displays the product safety instructions for the current product.', 'polski'),
            'render_callback' => static fn (): string => do_shortcode('[polski_safety_info]'),
        ]);

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/safety-docs', [
            'title' => __('Safety Documents', 'polski'),
            'description' => __('Displays the safety documents and declarations for the current product.', 'polski'),
            'render_callback' => static fn (): string => do_shortcode('[polski_safety_docs]'),
        ]);

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/power-supply', [
            'title' => __('Power Supply', 'polski'),
            'description' => __('Displays the power supply information for the current product.', 'polski'),
            'render_callback' => static fn (): string => do_shortcode('[polski_power_supply]'),
        ]);

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/defect-description', [
            'title' => __('Defect Description', 'polski'),
            'description' => __('Displays the description of defects for the current product.', 'polski'),
            'render_callback' => static fn (): string => do_shortcode('[polski_defect_description]'),
        ]);

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/nutrients', [
            'title' => __('Nutritional Information', 'polski'),
            'description' => __('Displays the nutritional information for the current product.', 'polski'),
            'render_callback' => static fn (): string => do_shortcode('[polski_nutrients]'),
        ]);

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/allergens', [
            'title' => __('Allergens', 'polski'),
            'description' => __('Displays the allergen declaration for the current product.', 'polski'),
            'render_callback' => static fn (): string => do_shortcode('[polski_allergens]'),
        ]);

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/ingredients', [
            'title' => __('Ingredients', 'polski'),
            'description' => __('Displays the ingredients list for the current product.', 'polski'),
            'render_callback' => static fn (): string => do_shortcode('[polski_ingredients]'),
        ]);

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/nutri-score', [
            'title' => __('Nutri-Score', 'polski'),
            'description' => __('Displays the Nutri-Score label for the current product.', 'polski'),
            'render_callback' => static fn (): string => do_shortcode('[polski_nutri_score]'),
        ]);

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/food-info', [
            'title' => __('Food Information', 'polski'),
            'description' => __('Displays food and grocery information for the current product.', 'polski'),
            'render_callback' => static fn (): string => do_shortcode('[polski_food_info]'),
        ]);

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/gpsr', [
            'title' => __('GPSR safety information', 'polski'),
            'description' => __('Displays the GPSR product safety information for the current product.', 'polski'),
            'render_callback' => static fn (): string => do_shortcode('[polski_gpsr]'),
        ]);
    }
}
