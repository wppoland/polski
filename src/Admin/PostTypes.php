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

    /**
     * Taxonomy registration methods keyed by the module that owns them.
     *
     * A taxonomy whose module is off must not register, otherwise its admin
     * screen shows up under Products even though the feature is disabled.
     *
     * @var array<string, list<string>>
     */
    private const TAXONOMY_MODULES = [
        'delivery_time' => ['registerDeliveryTimeTaxonomy'],
        'manufacturer' => ['registerManufacturerTaxonomy'],
        'brands' => ['registerBrandTaxonomy'],
        'unit_price' => ['registerUnitTaxonomy'],
        'food_module' => ['registerAllergenTaxonomy', 'registerNutrientTaxonomy'],
    ];

    public function registerTaxonomies(): void
    {
        foreach (self::TAXONOMY_MODULES as $moduleId => $methods) {
            if (! ModulesPage::isModuleEnabled($moduleId)) {
                continue;
            }

            foreach ($methods as $method) {
                $this->{$method}();
            }
        }
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

        $this->seedAllergens();
    }

    /**
     * Seeds the fourteen allergens of Annex II to Regulation (EU) 1169/2011 the
     * first time the taxonomy registers. Seeding has to wait for registration:
     * wp_insert_term() refuses a taxonomy that does not exist yet, which is why
     * the old activation migration never created a single term. Runs once per
     * site, so terms a merchant deletes stay deleted, and a shop that already
     * typed its own list is left alone rather than given near-duplicates.
     */
    private function seedAllergens(): void
    {
        if (get_option('polski_allergens_seeded') !== false) {
            return;
        }

        update_option('polski_allergens_seeded', '1');

        $existing = wp_count_terms(['taxonomy' => 'polski_allergen', 'hide_empty' => false]);

        if (! is_wp_error($existing) && (int) $existing > 0) {
            return;
        }

        // Slug => name. The order follows Annex II itself.
        $defaults = [
            'zboza-zawierajace-gluten' => __('Cereals containing gluten', 'polski'),
            'skorupiaki' => __('Crustaceans', 'polski'),
            'jaja' => __('Eggs', 'polski'),
            'ryby' => __('Fish', 'polski'),
            'orzeszki-ziemne' => __('Peanuts', 'polski'),
            'soja' => __('Soybeans', 'polski'),
            'mleko' => __('Milk', 'polski'),
            'orzechy' => __('Nuts', 'polski'),
            'seler' => __('Celery', 'polski'),
            'gorczyca' => __('Mustard', 'polski'),
            'nasiona-sezamu' => __('Sesame seeds', 'polski'),
            'dwutlenek-siarki-i-siarczyny' => __('Sulphur dioxide and sulphites', 'polski'),
            'lubin' => __('Lupin', 'polski'),
            'mieczaki' => __('Molluscs', 'polski'),
        ];

        foreach ($defaults as $slug => $name) {
            wp_insert_term($name, 'polski_allergen', ['slug' => $slug]);
        }
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
