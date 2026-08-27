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

        foreach ($defaults as $slug => $name) {
            if (! term_exists($slug, 'polski_delivery_time')) {
                wp_insert_term($name, 'polski_delivery_time', ['slug' => $slug]);
            }
        }
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

        foreach ($defaults as $slug => $name) {
            if (! term_exists($slug, 'polski_unit')) {
                wp_insert_term($name, 'polski_unit', ['slug' => $slug]);
            }
        }
    }
}
