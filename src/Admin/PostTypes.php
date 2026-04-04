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
                'name' => __('Czasy dostawy', 'polski'),
                'singular_name' => __('Czas dostawy', 'polski'),
                'add_new_item' => __('Dodaj czas dostawy', 'polski'),
                'edit_item' => __('Edytuj czas dostawy', 'polski'),
                'search_items' => __('Szukaj czasów dostawy', 'polski'),
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
                'name' => __('Producenci', 'polski'),
                'singular_name' => __('Producent', 'polski'),
                'add_new_item' => __('Dodaj producenta', 'polski'),
                'edit_item' => __('Edytuj producenta', 'polski'),
                'search_items' => __('Szukaj producentów', 'polski'),
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
                'name' => __('Jednostki', 'polski'),
                'singular_name' => __('Jednostka', 'polski'),
                'add_new_item' => __('Dodaj jednostkę', 'polski'),
                'edit_item' => __('Edytuj jednostkę', 'polski'),
                'search_items' => __('Szukaj jednostek', 'polski'),
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
                'name' => __('Alergeny', 'polski'),
                'singular_name' => __('Alergen', 'polski'),
                'add_new_item' => __('Dodaj alergen', 'polski'),
                'edit_item' => __('Edytuj alergen', 'polski'),
                'search_items' => __('Szukaj alergenów', 'polski'),
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
                'name' => __('Składniki odżywcze', 'polski'),
                'singular_name' => __('Składnik odżywczy', 'polski'),
                'add_new_item' => __('Dodaj składnik odżywczy', 'polski'),
                'edit_item' => __('Edytuj składnik odżywczy', 'polski'),
                'search_items' => __('Szukaj składników odżywczych', 'polski'),
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
