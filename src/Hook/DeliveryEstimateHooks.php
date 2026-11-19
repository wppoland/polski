<?php

declare(strict_types=1);

namespace Polski\Hook;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Service\DeliveryEstimateService;

/**
 * Prints the "Delivery by <date>" line under the price on the product page
 * and swaps it for the selected variation. The [polski_delivery_estimate]
 * shortcode lives in ShortcodeManager.
 *
 * existing-checked: ProductHooks shopmarks - they render at
 * woocommerce_single_product_summary 25, below the add-to-cart form; this line
 * belongs right under the price (priority 11) and needs its own variation
 * swap, so it is not a shopmark. ElementorCompat::appendOmnibusToPrice is the
 * model for the Elementor price widget path.
 */
final class DeliveryEstimateHooks implements HasHooks
{
    private bool $shown = false;

    public function __construct(
        private readonly DeliveryEstimateService $estimates,
    ) {
    }

    public function registerHooks(): void
    {
        if (! $this->estimates->isEnabled()) {
            return;
        }

        add_action('woocommerce_single_product_summary', [$this, 'renderUnderPrice'], 11);
        add_filter('woocommerce_available_variation', [$this, 'addVariationEstimate'], 10, 3);
        add_filter('elementor/widget/render_content', [$this, 'appendToElementorPrice'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueueScript'], 20);
    }

    public function renderUnderPrice(): void
    {
        global $product;

        if ($product instanceof \WC_Product && ! $this->shown) {
            $this->shown = true;
            echo wp_kses_post($this->estimates->getHtml($product));
        }
    }

    /**
     * An Elementor Pro product template replaces the summary, so the line
     * follows the price widget instead.
     */
    public function appendToElementorPrice(string $content, object $widget): string
    {
        if ($this->shown || ! method_exists($widget, 'get_name') || $widget->get_name() !== 'woocommerce-product-price'
            || ! is_product() || did_action('woocommerce_single_product_summary')) {
            return $content;
        }

        global $product;

        if (! $product instanceof \WC_Product || $product->get_id() !== get_queried_object_id()) {
            return $content;
        }

        $this->shown = true;

        return $content . wp_kses_post($this->estimates->getHtml($product));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function addVariationEstimate(array $data, \WC_Product $product, \WC_Product $variation): array
    {
        $data['polski_delivery_estimate'] = $this->estimates->getInnerHtml($variation);

        return $data;
    }

    /**
     * Swaps the line for the chosen variation and empties it once the
     * estimate has passed its cutoff in a cached page.
     */
    public function enqueueScript(): void
    {
        // Registered everywhere so the shortcode can enqueue it on any page.
        wp_register_script('polski-delivery-estimate', false, ['jquery'], \Polski\VERSION, ['in_footer' => true]);
        if (is_product()) {
            wp_enqueue_script('polski-delivery-estimate');
        }
        wp_add_inline_script(
            'polski-delivery-estimate',
            'jQuery(function($){var now=Date.now()/1000;'
            . '$(".polski-delivery-estimate").each(function(){var e=$(this),x=+e.attr("data-expires");'
            . 'e.data("stale",x>0&&now>x);if(e.data("stale")){e.empty();}e.data("html",e.html());});'
            . 'function line(f){return $(".polski-delivery-estimate[data-polski-estimate=\'"+f.data("product_id")+"\']");}'
            . '$(document.body).on("found_variation","form.variations_form",function(ev,v){var e=line($(this));'
            . 'e.html(e.data("stale")?"":(v.polski_delivery_estimate||""));})'
            . '.on("reset_data","form.variations_form",function(){var e=line($(this));e.html(e.data("html")||"");});});',
        );
    }
}
