<?php

declare(strict_types=1);

namespace Spolszczony\Service;

use Spolszczony\Model\UnitPrice;
use Spolszczony\Util\Formatter;

/**
 * Orchestrates all price display elements: unit prices, VAT notices, Omnibus prices.
 */
final class PriceDisplayService
{
    public function __construct(
        private readonly TaxDisplayService $taxDisplay,
        private readonly OmnibusService $omnibus,
    ) {
    }

    /**
     * Calculate the unit price for a product.
     */
    public function getUnitPrice(\WC_Product $product): ?UnitPrice
    {
        $baseAmount = (float) $product->get_meta('_spolszczony_unit_price_base', true);
        $productAmount = (float) $product->get_meta('_spolszczony_unit_price_product_amount', true);
        $unitSlug = (string) $product->get_meta('_spolszczony_unit_price_unit', true);

        if ($baseAmount <= 0 || $productAmount <= 0 || $unitSlug === '') {
            return null;
        }

        $price = (float) $product->get_price();

        if ($price <= 0) {
            return null;
        }

        return UnitPrice::calculate(
            productPrice: $price,
            productAmount: $productAmount,
            baseAmount: $baseAmount,
            unit: $unitSlug,
            currency: get_woocommerce_currency(),
        );
    }

    /**
     * Get formatted HTML for the unit price.
     */
    public function getUnitPriceHtml(\WC_Product $product): string
    {
        $settings = get_option('spolszczony_prices', []);

        if (! is_array($settings) || ! ($settings['unit_price_enabled'] ?? true)) {
            return '';
        }

        $unitPrice = $this->getUnitPrice($product);

        if ($unitPrice === null) {
            return '';
        }

        $priceFormatted = wc_price($unitPrice->pricePerUnit, ['currency' => $unitPrice->currency]);
        $unitLabel = $this->getUnitLabel($unitPrice->unit);

        $template = $settings['unit_price_text'] ?? '{price} / {unit}';

        $text = Formatter::interpolate($template, [
            'price' => wp_strip_all_tags($priceFormatted),
            'unit' => $unitLabel,
        ]);

        $html = sprintf(
            '<div class="spolszczony-unit-price"><span class="spolszczony-unit-price__text">%s</span></div>',
            esc_html($text),
        );

        /**
         * Filter the unit price HTML.
         *
         * @param string      $html      The HTML output.
         * @param UnitPrice   $unitPrice The calculated unit price.
         * @param \WC_Product $product   The product.
         */
        return (string) apply_filters('spolszczony/price/unit_price_html', $html, $unitPrice, $product);
    }

    /**
     * Get the VAT notice for a product.
     */
    public function getVatNoticeHtml(\WC_Product $product): string
    {
        return $this->taxDisplay->getVatNoticeHtml($product);
    }

    /**
     * Get the shipping costs notice.
     */
    public function getShippingNoticeHtml(): string
    {
        return $this->taxDisplay->getShippingNoticeHtml();
    }

    /**
     * Get the Omnibus lowest price notice for a product.
     */
    public function getOmnibusPriceHtml(\WC_Product $product): string
    {
        if (! $this->omnibus->isEnabled()) {
            return '';
        }

        return $this->omnibus->getLowestPriceHtml($product->get_id());
    }

    /**
     * Get a human-readable label for a unit slug.
     */
    private function getUnitLabel(string $slug): string
    {
        $term = get_term_by('slug', $slug, 'spolszczony_unit');

        if ($term instanceof \WP_Term) {
            return $term->name;
        }

        return $slug;
    }
}
