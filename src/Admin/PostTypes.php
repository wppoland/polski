<?php

declare(strict_types=1);

namespace Spolszczony\Admin;

use Spolszczony\Contract\Bootable;

/**
 * Registers custom taxonomies for the plugin.
 *
 * No custom post types are needed — all data uses WooCommerce products,
 * custom tables, and product meta.
 */
final class PostTypes implements Bootable
{
    public function boot(): void
    {
        add_action('init', [$this, 'registerTaxonomies']);
    }

    public function registerTaxonomies(): void
    {
        $this->registerDeliveryTimeTaxonomy();
        $this->registerManufacturerTaxonomy();
        $this->registerUnitTaxonomy();
        $this->registerAllergenTaxonomy();
        $this->registerNutrientTaxonomy();
    }

    private function registerDeliveryTimeTaxonomy(): void
    {
        register_taxonomy('spolszczony_delivery_time', ['product', 'product_variation'], [
            'labels' => [
                'name' => __('Delivery Times', 'spolszczony'),
                'singular_name' => __('Delivery Time', 'spolszczony'),
                'add_new_item' => __('Add Delivery Time', 'spolszczony'),
                'edit_item' => __('Edit Delivery Time', 'spolszczony'),
                'search_items' => __('Search Delivery Times', 'spolszczony'),
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
        register_taxonomy('spolszczony_manufacturer', ['product', 'product_variation'], [
            'labels' => [
                'name' => __('Manufacturers', 'spolszczony'),
                'singular_name' => __('Manufacturer', 'spolszczony'),
                'add_new_item' => __('Add Manufacturer', 'spolszczony'),
                'edit_item' => __('Edit Manufacturer', 'spolszczony'),
                'search_items' => __('Search Manufacturers', 'spolszczony'),
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
        register_taxonomy('spolszczony_unit', ['product'], [
            'labels' => [
                'name' => __('Units', 'spolszczony'),
                'singular_name' => __('Unit', 'spolszczony'),
                'add_new_item' => __('Add Unit', 'spolszczony'),
                'edit_item' => __('Edit Unit', 'spolszczony'),
                'search_items' => __('Search Units', 'spolszczony'),
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

    private function registerAllergenTaxonomy(): void
    {
        register_taxonomy('spolszczony_allergen', ['product'], [
            'labels' => [
                'name' => __('Allergens', 'spolszczony'),
                'singular_name' => __('Allergen', 'spolszczony'),
                'add_new_item' => __('Add Allergen', 'spolszczony'),
                'edit_item' => __('Edit Allergen', 'spolszczony'),
                'search_items' => __('Search Allergens', 'spolszczony'),
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
        register_taxonomy('spolszczony_nutrient', ['product'], [
            'labels' => [
                'name' => __('Nutrients', 'spolszczony'),
                'singular_name' => __('Nutrient', 'spolszczony'),
                'add_new_item' => __('Add Nutrient', 'spolszczony'),
                'edit_item' => __('Edit Nutrient', 'spolszczony'),
                'search_items' => __('Search Nutrients', 'spolszczony'),
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
