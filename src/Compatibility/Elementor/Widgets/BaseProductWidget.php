<?php

declare(strict_types=1);

namespace Spolszczony\Compatibility\Elementor\Widgets;

use Elementor\Widget_Base;

/**
 * Base class for all Spolszczony Elementor product widgets.
 *
 * Each widget renders the same output as the corresponding PHP template
 * or shortcode, ensuring consistency between Elementor and non-Elementor pages.
 *
 * Ready for Elementor 4.0 migration - widgets use minimal controls
 * and delegate rendering to Spolszczony services.
 */
abstract class BaseProductWidget extends Widget_Base
{
    public function get_categories(): array
    {
        return ['spolszczony'];
    }

    public function get_icon(): string
    {
        return 'eicon-woocommerce';
    }

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

    /**
     * Get the Spolszczony DI container.
     */
    protected function container(): \Spolszczony\Container
    {
        return \Spolszczony\Plugin::instance()->container();
    }
}
