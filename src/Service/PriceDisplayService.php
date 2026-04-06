<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Model\UnitPrice;
use Polski\Util\Formatter;

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
        $baseAmount = (float) $product->get_meta('_polski_unit_price_base', true);
        $productAmount = (float) $product->get_meta('_polski_unit_price_product_amount', true);
        $unitSlug = (string) $product->get_meta('_polski_unit_price_unit', true);

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
        $settings = get_option('polski_prices', []);

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
            '<div class="polski-unit-price"><span class="polski-unit-price__text">%s</span></div>',
            esc_html($text),
        );

        /**
         * Filter the unit price HTML.
         *
         * @param string      $html      The HTML output.
         * @param UnitPrice   $unitPrice The calculated unit price.
         * @param \WC_Product $product   The product.
         */
        return (string) apply_filters('polski/price/unit_price_html', $html, $unitPrice, $product);
    }

    /**
     * Get "From {price}" HTML for variable products.
     *
     * Replaces the default price range (e.g. "19.99 - 49.99") with
     * a cleaner "od 19.99" format on archives and single product pages.
     */
    public function getFromPriceHtml(string $priceHtml, \WC_Product $product): string
    {
        if (! $product instanceof \WC_Product_Variable) {
            return $priceHtml;
        }

        $settings = get_option('polski_prices', []);

        if (! is_array($settings) || ! ($settings['from_price_enabled'] ?? true)) {
            return $priceHtml;
        }

        $prices = $product->get_variation_prices(true);

        if (empty($prices['price'])) {
            return $priceHtml;
        }

        $minPrice = current($prices['price']);
        $maxPrice = end($prices['price']);

        // Only show "from" when there is an actual price range.
        if ((float) $minPrice === (float) $maxPrice) {
            return $priceHtml;
        }

        $template = $settings['from_price_text'] ?? __('from {price}', 'polski');
        $formattedPrice = wc_price((float) $minPrice);

        $text = Formatter::interpolate($template, [
            'price' => $formattedPrice,
        ]);

        $html = sprintf(
            '<span class="polski-from-price">%s</span>',
            $text,
        );

        /**
         * Filter the "from price" HTML.
         *
         * @param string      $html    The HTML output.
         * @param \WC_Product $product The product.
         */
        return (string) apply_filters('polski/price/from_price_html', $html, $product);
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
        $term = get_term_by('slug', $slug, 'polski_unit');

        if ($term instanceof \WP_Term) {
            return $term->name;
        }

        return $slug;
    }
}
