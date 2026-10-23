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
    /** @var array<int, true> Product ids whose Omnibus notice is already on the page. */
    private array $omnibusShown = [];

    public function registerHooks(): void
    {
        if (! did_action('elementor/loaded')) {
            return;
        }

        add_action('elementor/widgets/register', [$this, 'registerWidgets']);
        add_action('elementor/elements/categories_registered', [$this, 'registerCategory']);
        add_filter('elementor/widget/render_content', [$this, 'appendOmnibusToPrice'], 10, 2);
    }

    /**
     * Put the Omnibus notice under the price of an Elementor Pro single product
     * template.
     *
     * Such a template replaces the WooCommerce summary, so the notice hooked to
     * woocommerce_single_product_summary never prints and the sale price stands
     * alone. A template that already carries the Polski Omnibus widget keeps
     * exactly one notice: whichever renders first marks the product as shown.
     */
    public function appendOmnibusToPrice(string $content, \Elementor\Widget_Base $widget): string
    {
        $name = $widget->get_name();

        if (($name !== 'woocommerce-product-price' && $name !== 'polski-omnibus-price') || ! is_product()) {
            return $content;
        }

        global $product;

        // Only the product the page is about, not one in a loop grid on it.
        if (! $product instanceof \WC_Product || $product->get_id() !== get_queried_object_id()) {
            return $content;
        }

        $id = $product->get_id();

        if ($name === 'polski-omnibus-price') {
            if (isset($this->omnibusShown[$id])) {
                return '';
            }

            $this->omnibusShown[$id] = true;

            return $content;
        }

        // The summary hook ran, so ProductHooks already printed the notice.
        if (isset($this->omnibusShown[$id]) || did_action('woocommerce_single_product_summary')) {
            return $content;
        }

        $settings = \Polski\Util\OptionCache::get('polski_omnibus', []);

        if (is_array($settings) && ! ($settings['show_on_single'] ?? true)) {
            return $content;
        }

        $html = \Polski\Plugin::instance()->container()->get(\Polski\Service\PriceDisplayService::class)->getOmnibusPriceHtml($product);

        if ($html === '') {
            return $content;
        }

        $this->omnibusShown[$id] = true;

        return $content . wp_kses_post($html);
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
