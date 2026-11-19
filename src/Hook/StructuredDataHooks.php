<?php

declare(strict_types=1);

namespace Polski\Hook;

use Polski\Contract\HasHooks;
use Polski\Service\MerchantPolicySchema;
use Polski\Service\OmnibusService;
use Polski\Service\ProductGroupSchema;

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
        private readonly MerchantPolicySchema $merchant,
        private readonly ProductGroupSchema $productGroup,
    ) {
    }

    public function registerHooks(): void
    {
        add_filter('woocommerce_structured_data_product', [$this, 'augmentProduct'], 10, 2);
        add_filter('polski/structured_data/variant', [$this, 'augmentProduct'], 10, 2);

        // After every priority-10 enrichment of the AggregateOffer, so the
        // group is built from the finished node.
        add_filter('woocommerce_structured_data_product', [$this, 'convertVariableProduct'], 20, 2);

        // WooCommerce prints only the node types it expects on a page, keyed by
        // lowercase @type. Without this the ProductGroup would be dropped.
        add_filter('woocommerce_structured_data_type_for_page', [$this, 'allowProductGroup']);

        // Before WooCommerce prints its data at wp_footer 10.
        add_action('wp_footer', [$this, 'ensureProductData'], 5);
    }

    /**
     * @param array<string,mixed> $markup
     * @return array<string,mixed>
     */
    public function convertVariableProduct(array $markup, \WC_Product $product): array
    {
        $settings = MerchantPolicySchema::schemaSettings();

        if ($settings === null || ! ($settings['schema_product_group'] ?? true)) {
            return $markup;
        }

        return $this->productGroup->convert($markup, $product);
    }

    /**
     * @param array<int,string> $types
     * @return array<int,string>
     */
    public function allowProductGroup(array $types): array
    {
        if (in_array('product', $types, true)) {
            $types[] = 'productgroup';
        }

        return $types;
    }

    /**
     * WooCommerce builds the Product node on woocommerce_single_product_summary.
     * A page builder template (Elementor Pro and others) never fires that
     * action, so the product page goes out with no Product data at all. Build
     * it here when nothing did.
     */
    public function ensureProductData(): void
    {
        // The summary ran: whatever product data it left (none, when an SEO
        // plugin took the node over) is deliberate, so nothing is added.
        // A password-protected product skips the summary on purpose.
        if (did_action('woocommerce_single_product_summary') || ! function_exists('is_product') || ! is_product() || post_password_required() || MerchantPolicySchema::schemaSettings() === null) {
            return;
        }

        /** Whether Polski may build the product node a page template skipped. */
        if (! apply_filters('polski/structured_data/ensure_product', true)) {
            return;
        }

        $structured = function_exists('WC') ? WC()->structured_data : null;

        if (! $structured instanceof \WC_Structured_Data) {
            return;
        }

        foreach ($structured->get_data() as $node) {
            $type = is_array($node) ? (array) ($node['@type'] ?? []) : [];
            if (array_intersect(['Product', 'ProductGroup'], $type) !== []) {
                return;
            }
        }

        $product = wc_get_product((int) get_queried_object_id());

        if ($product instanceof \WC_Product) {
            $structured->generate_product_data($product);
        }
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

        // Omnibus: the lowest price of the last 30 days is the reference a
        // reduction is measured against, so it goes in as the strikethrough
        // price. It is added next to WooCommerce's own sale and list price
        // entries, never in their place: replacing them dropped the real price.
        if ($this->omnibus->isEnabled() && $product->is_on_sale()) {
            $lowest = $this->omnibus->getLowestPrice($product->get_id());

            if ($lowest !== null && $lowest->effectivePrice() > (float) $product->get_price()) {
                $specs = $markup['offers'][0]['priceSpecification'] ?? [];
                $specs = isset($specs['@type']) ? [$specs] : (array) $specs;
                $specs[] = [
                    '@type' => 'UnitPriceSpecification',
                    'priceType' => 'https://schema.org/StrikethroughPrice',
                    'name' => __('Lowest price in the last 30 days', 'polski'),
                    'price' => wc_format_decimal($lowest->effectivePrice(), wc_get_price_decimals()),
                    'priceCurrency' => get_woocommerce_currency(),
                ];
                $markup['offers'][0]['priceSpecification'] = $specs;
            }
        }

        // Both are null while the Structured Data module or its switch is off.
        $shipping = $this->merchant->shippingDetails($product);
        if ($shipping !== null) {
            $markup['offers'][0]['shippingDetails'] = $shipping;
        }

        $returns = $this->merchant->returnPolicy();
        if ($returns !== null) {
            $markup['offers'][0]['hasMerchantReturnPolicy'] = $returns;
        }

        return $markup;
    }
}
