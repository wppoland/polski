<?php

declare(strict_types=1);

namespace Polski\Migration;

use Polski\Contract\Migration;

defined('ABSPATH') || exit;

/**
 * Seeds the fourteen allergens of Annex II to Regulation (EU) 1169/2011.
 *
 * The taxonomy was registered empty, so every shop typed the list in by hand
 * and owned the spelling. Seeding them makes the list consistent across shops
 * and makes the allergen field usable straight after activation. Terms the shop
 * already created are left alone: matching is by slug, and a shop that named a
 * term differently keeps its own name.
 */
final class Migration_2_6_0 implements Migration
{
    public const VERSION = '2.6.0';

    public function run(): void
    {
        $this->seedAllergens();
    }

    private function seedAllergens(): void
    {
        // Slug => name. The order follows Annex II itself.
        $defaults = [
            'zboza-zawierajace-gluten' => __('Cereals containing gluten', 'polski'),
            'skorupiaki'               => __('Crustaceans', 'polski'),
            'jaja'                     => __('Eggs', 'polski'),
            'ryby'                     => __('Fish', 'polski'),
            'orzeszki-ziemne'          => __('Peanuts', 'polski'),
            'soja'                     => __('Soybeans', 'polski'),
            'mleko'                    => __('Milk', 'polski'),
            'orzechy'                  => __('Nuts', 'polski'),
            'seler'                    => __('Celery', 'polski'),
            'gorczyca'                 => __('Mustard', 'polski'),
            'nasiona-sezamu'           => __('Sesame seeds', 'polski'),
            'dwutlenek-siarki-i-siarczyny' => __('Sulphur dioxide and sulphites', 'polski'),
            'lubin'                    => __('Lupin', 'polski'),
            'mieczaki'                 => __('Molluscs', 'polski'),
        ];

        foreach ($defaults as $slug => $name) {
            if (! term_exists($slug, 'polski_allergen')) {
                wp_insert_term($name, 'polski_allergen', ['slug' => $slug]);
            }
        }
    }
}
