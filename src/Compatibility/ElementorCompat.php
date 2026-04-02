<?php

declare(strict_types=1);

namespace Spolszczony\Compatibility;

use Spolszczony\Contract\HasHooks;

/**
 * Elementor compatibility layer.
 *
 * Registers Spolszczony widgets for the Elementor page builder.
 * This is a stub that will be expanded with full widget implementations
 * when the Elementor integration is prioritized.
 */
final class ElementorCompat implements HasHooks
{
    public function registerHooks(): void
    {
        if (! did_action('elementor/loaded')) {
            return;
        }

        add_action('elementor/widgets/register', [$this, 'registerWidgets']);
        add_action('elementor/elements/categories_registered', [$this, 'registerCategory']);
    }

    /**
     * Register the Spolszczony widget category in Elementor.
     */
    public function registerCategory(\Elementor\Elements_Manager $elements): void
    {
        $elements->add_category('spolszczony', [
            'title' => __('Spolszczony', 'spolszczony'),
            'icon' => 'eicon-woocommerce',
        ]);
    }

    /**
     * Register Spolszczony Elementor widgets.
     *
     * Widget implementations will be added in src/Compatibility/Elementor/Widgets/.
     */
    public function registerWidgets(\Elementor\Widgets_Manager $widgets): void
    {
        // Widgets will be registered here when implemented.
        // Each widget extends \Elementor\Widget_Base and renders
        // the same output as the corresponding Spolszczony shortcode.
    }
}
