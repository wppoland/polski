<?php

declare(strict_types=1);
namespace Polski\Admin;

defined('ABSPATH') || exit;

use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;

/**
 * Registers custom taxonomies for the plugin.
 *
 * No custom post types are needed - all data uses WooCommerce products,
 * custom tables, and product meta.
 */
final class PostTypes implements Bootable, HasHooks
{
    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        add_action('init', [$this, 'registerTaxonomies']);
    }

    public function registerTaxonomies(): void
    {
        $this->registerDeliveryTimeTaxonomy();
        $this->registerManufacturerTaxonomy();
        $this->registerBrandTaxonomy();
        $this->registerUnitTaxonomy();
        $this->registerAllergenTaxonomy();
        $this->registerNutrientTaxonomy();
    }

    private function registerDeliveryTimeTaxonomy(): void
    {
        register_taxonomy('polski_delivery_time', ['product', 'product_variation'], [
            'labels' => [
                'name' => __('Delivery Times', 'polski'),
                'singular_name' => __('Delivery Time', 'polski'),
                'add_new_item' => __('Add Delivery Time', 'polski'),
                'edit_item' => __('Edit Delivery Time', 'polski'),
                'search_items' => __('Search Delivery Times', 'polski'),
            ],
            'hierarchical' => false,
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_admin_column' => false,
            'rewrite' => false,
            'capabilities' => [
                'manage_terms' => 'manage_product_terms',
                'edit_terms' => 'edit_product_terms',
                'delete_terms' => 'delete_product_terms',
                'assign_terms' => 'assign_product_terms',
            ],
        ]);
    }

    private function registerManufacturerTaxonomy(): void
    {
        register_taxonomy('polski_manufacturer', ['product', 'product_variation'], [
            'labels' => [
                'name' => __('Manufacturers', 'polski'),
                'singular_name' => __('Manufacturer', 'polski'),
                'add_new_item' => __('Add Manufacturer', 'polski'),
                'edit_item' => __('Edit Manufacturer', 'polski'),
                'search_items' => __('Search Manufacturers', 'polski'),
            ],
            'hierarchical' => false,
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_admin_column' => false,
            'rewrite' => false,
            'capabilities' => [
                'manage_terms' => 'manage_product_terms',
                'edit_terms' => 'edit_product_terms',
                'delete_terms' => 'delete_product_terms',
                'assign_terms' => 'assign_product_terms',
            ],
        ]);
    }

    private function registerUnitTaxonomy(): void
    {
        register_taxonomy('polski_unit', ['product'], [
            'labels' => [
                'name' => __('Units', 'polski'),
                'singular_name' => __('Unit', 'polski'),
                'add_new_item' => __('Add Unit', 'polski'),
                'edit_item' => __('Edit Unit', 'polski'),
                'search_items' => __('Search Units', 'polski'),
            ],
            'hierarchical' => false,
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_admin_column' => false,
            'rewrite' => false,
            'capabilities' => [
                'manage_terms' => 'manage_product_terms',
                'edit_terms' => 'edit_product_terms',
                'delete_terms' => 'delete_product_terms',
                'assign_terms' => 'assign_product_terms',
            ],
        ]);
    }

    private function registerBrandTaxonomy(): void
    {
        register_taxonomy('polski_brand', ['product'], [
            'labels' => [
                'name' => __('Brands', 'polski'),
                'singular_name' => __('Brand', 'polski'),
                'add_new_item' => __('Add Brand', 'polski'),
                'edit_item' => __('Edit Brand', 'polski'),
                'search_items' => __('Search Brands', 'polski'),
            ],
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_admin_column' => true,
            'rewrite' => ['slug' => 'marka'],
            'capabilities' => [
                'manage_terms' => 'manage_product_terms',
                'edit_terms' => 'edit_product_terms',
                'delete_terms' => 'delete_product_terms',
                'assign_terms' => 'assign_product_terms',
            ],
        ]);
    }

    private function registerAllergenTaxonomy(): void
    {
        register_taxonomy('polski_allergen', ['product'], [
            'labels' => [
                'name' => __('Allergens', 'polski'),
                'singular_name' => __('Allergen', 'polski'),
                'add_new_item' => __('Add Allergen', 'polski'),
                'edit_item' => __('Edit Allergen', 'polski'),
                'search_items' => __('Search Allergens', 'polski'),
            ],
            'hierarchical' => false,
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_admin_column' => false,
            'rewrite' => false,
            'capabilities' => [
                'manage_terms' => 'manage_product_terms',
                'edit_terms' => 'edit_product_terms',
                'delete_terms' => 'delete_product_terms',
                'assign_terms' => 'assign_product_terms',
            ],
        ]);
    }

    private function registerNutrientTaxonomy(): void
    {
        register_taxonomy('polski_nutrient', ['product'], [
            'labels' => [
                'name' => __('Nutrients', 'polski'),
                'singular_name' => __('Nutrient', 'polski'),
                'add_new_item' => __('Add Nutrient', 'polski'),
                'edit_item' => __('Edit Nutrient', 'polski'),
                'search_items' => __('Search Nutrients', 'polski'),
            ],
            'hierarchical' => true,
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_admin_column' => false,
            'rewrite' => false,
            'capabilities' => [
                'manage_terms' => 'manage_product_terms',
                'edit_terms' => 'edit_product_terms',
                'delete_terms' => 'delete_product_terms',
                'assign_terms' => 'assign_product_terms',
            ],
        ]);
    }
}
