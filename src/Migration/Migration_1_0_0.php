<?php

declare(strict_types=1);

namespace Spolszczony\Migration;

/**
 * Initial migration: seed default taxonomy terms for Polish market.
 */
final class Migration_1_0_0
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
            '1-2-dni-robocze' => __('1-2 business days', 'spolszczony'),
            '2-4-dni-robocze' => __('2-4 business days', 'spolszczony'),
            '3-5-dni-roboczych' => __('3-5 business days', 'spolszczony'),
            '5-7-dni-roboczych' => __('5-7 business days', 'spolszczony'),
            '7-14-dni-roboczych' => __('7-14 business days', 'spolszczony'),
            'do-24h' => __('Up to 24 hours', 'spolszczony'),
        ];

        foreach ($defaults as $slug => $name) {
            if (! term_exists($slug, 'spolszczony_delivery_time')) {
                wp_insert_term($name, 'spolszczony_delivery_time', ['slug' => $slug]);
            }
        }
    }

    private function seedUnits(): void
    {
        $defaults = [
            'szt' => __('pcs (szt.)', 'spolszczony'),
            'kg' => __('kg', 'spolszczony'),
            'g' => __('g', 'spolszczony'),
            'l' => __('l', 'spolszczony'),
            'ml' => __('ml', 'spolszczony'),
            'm' => __('m', 'spolszczony'),
            'cm' => __('cm', 'spolszczony'),
            'm2' => __('m²', 'spolszczony'),
            'm3' => __('m³', 'spolszczony'),
        ];

        foreach ($defaults as $slug => $name) {
            if (! term_exists($slug, 'spolszczony_unit')) {
                wp_insert_term($name, 'spolszczony_unit', ['slug' => $slug]);
            }
        }
    }
}
