<?php

declare(strict_types=1);

namespace Polski\Hook;

use Polski\Contract\HasHooks;
use Polski\Service\OmnibusService;

defined('ABSPATH') || exit;

/**
 * Storefront structured data (SEO / GEO / AEO).
 *
 * Augments WooCommerce's own Product/Offer JSON-LD via the
 * `woocommerce_structured_data_product` filter - it never prints a second
 * Product graph (duplicate graphs hurt SEO). Adds a truthful priceValidUntil
 * and, when a product is on sale, the Omnibus lowest 30-day price as a
 * MinimumPrice specification (a citable, machine-readable fact for GEO).
 */
final class StructuredDataHooks implements HasHooks
{
    public function __construct(
        private readonly OmnibusService $omnibus,
    ) {
    }

    public function registerHooks(): void
    {
        add_filter('woocommerce_structured_data_product', [$this, 'augmentProduct'], 10, 2);
    }

    /**
     * @param array<string,mixed> $markup WooCommerce's Product JSON-LD array.
     * @param \WC_Product $product
     * @return array<string,mixed>
     */
    public function augmentProduct(array $markup, \WC_Product $product): array
    {
        if (! isset($markup['offers'][0]) || ! is_array($markup['offers'][0])) {
            return $markup;
        }

        // priceValidUntil keeps the Offer eligible for rich results.
        if (empty($markup['offers'][0]['priceValidUntil'])) {
            $saleTo = $product->get_date_on_sale_to();
            $markup['offers'][0]['priceValidUntil'] = $saleTo
                ? $saleTo->date('Y-m-d')
                : gmdate('Y-m-d', strtotime('+1 year'));
        }

        // Omnibus: surface the truthful lowest price of the last 30 days, only
        // when the product is actually on sale and the module is enabled.
        if ($this->omnibus->isEnabled() && $product->is_on_sale()) {
            $lowest = $this->omnibus->getLowestPrice($product->get_id());

            if ($lowest !== null) {
                $markup['offers'][0]['priceSpecification'] = [
                    '@type' => 'UnitPriceSpecification',
                    'priceType' => 'https://schema.org/MinimumPrice',
                    'name' => __('Najniższa cena z 30 dni', 'polski'),
                    'price' => wc_format_decimal($lowest->effectivePrice(), wc_get_price_decimals()),
                    'priceCurrency' => get_woocommerce_currency(),
                ];
            }
        }

        return $markup;
    }
}
