<?php

declare(strict_types=1);
namespace Polski\Migration;

use Polski\Contract\Migration;

defined('ABSPATH') || exit;
/**
 * Initial migration: seed default taxonomy terms for Polish market.
 */
final class Migration_1_0_0 implements Migration
{
    public const VERSION = '1.0.0';

    public function run(): void
    {
        $this->seedDeliveryTimes();
        $this->seedUnits();
    }

    private function seedDeliveryTimes(): void
    {
        $defaults = [
            '1-2-dni-robocze' => __('1-2 working days', 'polski'),
            '2-4-dni-robocze' => __('2-4 working days', 'polski'),
            '3-5-dni-roboczych' => __('3-5 working days', 'polski'),
            '5-7-dni-roboczych' => __('5-7 working days', 'polski'),
            '7-14-dni-roboczych' => __('7-14 working days', 'polski'),
            'do-24h' => __('Within 24 hours', 'polski'),
        ];

        $this->seed('polski_delivery_time', ['product', 'product_variation'], $defaults);
    }

    private function seedUnits(): void
    {
        $defaults = [
            'szt' => __('pcs.', 'polski'),
            'kg' => __('kg', 'polski'),
            'g' => __('g', 'polski'),
            'l' => __('l', 'polski'),
            'ml' => __('ml', 'polski'),
            'm' => __('m', 'polski'),
            'cm' => __('cm', 'polski'),
            'm2' => __('m²', 'polski'),
            'm3' => __('m³', 'polski'),
        ];

        $this->seed('polski_unit', ['product'], $defaults);
    }

    /**
     * Insert the missing default terms.
     *
     * Migrations run on activation and on plugins_loaded, both before init, so
     * the taxonomy is not registered yet and wp_insert_term() would refuse every
     * term. Register it for the duration of the seed, then hand it back to
     * PostTypes, which registers it on init only when its module is on.
     *
     * @param list<string>          $objectTypes
     * @param array<string, string> $defaults slug => name
     */
    private function seed(string $taxonomy, array $objectTypes, array $defaults): void
    {
        $registeredHere = ! taxonomy_exists($taxonomy);

        if ($registeredHere) {
            register_taxonomy($taxonomy, $objectTypes, ['public' => false, 'rewrite' => false]);
        }

        foreach ($defaults as $slug => $name) {
            if (! term_exists($slug, $taxonomy)) {
                wp_insert_term($name, $taxonomy, ['slug' => $slug]);
            }
        }

        if ($registeredHere) {
            unregister_taxonomy($taxonomy);
        }
    }
}
