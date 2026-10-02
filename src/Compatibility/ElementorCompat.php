<?php

declare(strict_types=1);
namespace Polski\Compatibility;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * Elementor compatibility layer.
 *
 * Registers the Polski widgets for the Elementor page builder. Each widget
 * renders through the same service as its shortcode.
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
     * Register the Polski widget category in Elementor.
     */
    public function registerCategory(\Elementor\Elements_Manager $elements): void
    {
        $elements->add_category('polski', [
            'title' => __('Polski for WooCommerce', 'polski'),
            'icon' => 'eicon-woocommerce',
        ]);
    }

    /**
     * Register Polski Elementor widgets.
     */
    public function registerWidgets(\Elementor\Widgets_Manager $widgets): void
    {
        $widgetClasses = [
            Elementor\Widgets\UnitPriceWidget::class,
            Elementor\Widgets\OmnibusPriceWidget::class,
            Elementor\Widgets\TaxInfoWidget::class,
            Elementor\Widgets\ShippingNoticeWidget::class,
            Elementor\Widgets\DeliveryTimeWidget::class,
            Elementor\Widgets\ManufacturerWidget::class,
            Elementor\Widgets\SafetyDocsWidget::class,
            Elementor\Widgets\SafetyInstructionsWidget::class,
            Elementor\Widgets\PowerSupplyWidget::class,
            Elementor\Widgets\DefectDescriptionWidget::class,
            Elementor\Widgets\IngredientsWidget::class,
            Elementor\Widgets\AllergensWidget::class,
            Elementor\Widgets\NutrientsWidget::class,
            Elementor\Widgets\NutriScoreWidget::class,
            Elementor\Widgets\FoodInfoWidget::class,
            Elementor\Widgets\SearchWidget::class,
            Elementor\Widgets\FiltersWidget::class,
            Elementor\Widgets\ProductSliderWidget::class,
        ];

        foreach ($widgetClasses as $className) {
            if (class_exists($className)) {
                $widgets->register(new $className());
            }
        }
    }
}
