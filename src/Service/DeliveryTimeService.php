<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Util\Formatter;

/**
 * Delivery time display: per-product, per-variation, default fallback.
 */
final class DeliveryTimeService
{
    /**
     * Get the delivery time text for a product.
     */
    public function getDeliveryTimeText(\WC_Product $product): string
    {
        $termId = (int) $product->get_meta('_polski_delivery_time_id', true);

        // Per-product override.
        if ($termId > 0) {
            $term = get_term($termId, 'polski_delivery_time');
            if ($term instanceof \WP_Term) {
                return $term->name;
            }
        }

        // Check product taxonomy assignment.
        $terms = get_the_terms($product->get_id(), 'polski_delivery_time');
        if (is_array($terms) && ! empty($terms)) {
            return $terms[0]->name;
        }

        // Default fallback.
        $settings = get_option('polski_delivery', []);
        $defaultTermId = is_array($settings) ? (int) ($settings['default_delivery_time'] ?? 0) : 0;

        if ($defaultTermId > 0) {
            $term = get_term($defaultTermId, 'polski_delivery_time');
            if ($term instanceof \WP_Term) {
                return $term->name;
            }
        }

        return '';
    }

    /**
     * Get formatted delivery time HTML for display.
     */
    public function getDeliveryTimeHtml(\WC_Product $product): string
    {
        $timeText = $this->getDeliveryTimeText($product);

        if ($timeText === '') {
            return '';
        }

        $settings = get_option('polski_delivery', []);
        $format = is_array($settings) ? ($settings['display_format'] ?? 'Czas dostawy: {time}') : 'Czas dostawy: {time}';

        $text = Formatter::interpolate($format, ['time' => $timeText]);

        $html = sprintf(
            '<div class="polski-delivery-time"><span class="polski-delivery-time__text">%s</span></div>',
            esc_html($text),
        );

        /**
         * @param string      $html    The delivery time HTML.
         * @param string      $time    The delivery time text.
         * @param \WC_Product $product The product.
         */
        return (string) apply_filters('polski/delivery_time/display', $html, $timeText, $product);
    }
}
