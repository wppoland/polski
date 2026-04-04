<?php

declare(strict_types=1);
namespace Polski\Compatibility\Elementor\Widgets;

defined('ABSPATH') || exit;
/**
 * Base class for all Polski Elementor product widgets.
 *
 * Each widget renders the same output as the corresponding PHP template
 * or shortcode, ensuring consistency between Elementor and non-Elementor pages.
 *
 * Ready for Elementor 4.0 migration - widgets use minimal controls
 * and delegate rendering to Polski services.
 */
abstract class BaseProductWidget extends BaseWidget
{
    /**
     * Resolve the current product for rendering.
     */
    protected function getProduct(): ?\WC_Product
    {
        global $product;

        if ($product instanceof \WC_Product) {
            return $product;
        }

        // Try to get from Elementor's current document context.
        $postId = get_the_ID();

        if ($postId > 0) {
            $p = wc_get_product($postId);
            if ($p instanceof \WC_Product) {
                return $p;
            }
        }

        return null;
    }
}
