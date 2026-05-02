<?php

declare(strict_types=1);

namespace Polski\AIFeed;

defined('ABSPATH') || exit;

use Polski\Service\DeliveryTimeService;
use Polski\Service\OmnibusService;
use Polski\Service\ProductInfoService;
use WC_Product;
use WP_Post;

/**
 * Builds a Markdown document for a WooCommerce product enriched with Polish-market data.
 *
 * Falls back to PostMarkdownBuilder when WooCommerce is unavailable or the post is not a product.
 */
final class ProductMarkdownBuilder
{
    public function __construct(
        private readonly MarkdownConverter $converter,
        private readonly PostMarkdownBuilder $postBuilder,
        private readonly OmnibusService $omnibus,
        private readonly DeliveryTimeService $deliveryTime,
        private readonly ProductInfoService $productInfo,
    ) {
    }

    public function build(WP_Post $postObject): string
    {
        if (! function_exists('wc_get_product')) {
            return $this->postBuilder->build($postObject);
        }

        $product = wc_get_product($postObject);
        if (! $product instanceof WC_Product) {
            return $this->postBuilder->build($postObject);
        }

        $frontMatter = $this->converter->frontMatter($this->frontMatterFields($product));
        $body = $this->buildBody($product);

        $sections = array_filter([$frontMatter, $body], static fn (string $part): bool => $part !== '');
        $document = implode("\n\n", $sections) . "\n";

        /**
         * Filter the Markdown document for a WooCommerce product.
         *
         * @param string     $document Final Markdown document.
         * @param WC_Product $product  Product being rendered.
         * @param WP_Post    $postObject Underlying WP_Post.
         */
        return (string) apply_filters('polski/ai_feed/product_markdown', $document, $product, $postObject);
    }

    /**
     * @return array<string, scalar|array<int, scalar>|null>
     */
    private function frontMatterFields(WC_Product $product): array
    {
        $fields = [
            'title' => trim(wp_strip_all_tags($product->get_name())),
            'permalink' => (string) ($product->get_permalink() ?: ''),
            'sku' => (string) $product->get_sku(),
            'gtin' => $this->productInfo->getGTIN($product),
            'product_type' => $product->get_type(),
            'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '',
            'price' => $this->priceText($product->get_price()),
            'regular_price' => $this->priceText($product->get_regular_price()),
            'sale_price' => $this->priceText($product->get_sale_price()),
            'in_stock' => $product->is_in_stock() ? 'true' : 'false',
            'on_sale' => $product->is_on_sale() ? 'true' : 'false',
        ];

        $modified = $product->get_date_modified();
        if ($modified !== null) {
            $fields['modified'] = $modified->format(DATE_ATOM);
        }

        $categories = $this->categoryNames($product);
        if ($categories !== []) {
            $fields['categories'] = $categories;
        }

        return $fields;
    }

    private function buildBody(WC_Product $product): string
    {
        $lines = [];
        $lines[] = '# ' . trim(wp_strip_all_tags($product->get_name()));
        $lines[] = '';

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WooCommerce filter, applied to mirror storefront short-description rendering.
        $shortDescriptionRaw = (string) apply_filters('woocommerce_short_description', $product->get_short_description());
        if (trim(wp_strip_all_tags($shortDescriptionRaw)) !== '') {
            $lines[] = trim($this->converter->htmlToMarkdown($shortDescriptionRaw));
            $lines[] = '';
        }

        $facts = $this->factRows($product);

        /**
         * Filter the bullet list of product facts shown in the Markdown body.
         *
         * @param array<int, array{0: string, 1: string}> $facts   List of [label, value] pairs.
         * @param WC_Product                              $product Product being rendered.
         */
        $facts = (array) apply_filters('polski/ai_feed/product_facts', $facts, $product);

        if ($facts !== []) {
            $lines[] = '## ' . __('Product details', 'polski');
            $lines[] = '';
            foreach ($facts as $row) {
                if (! is_array($row) || ! isset($row[0], $row[1])) {
                    continue;
                }
                $lines[] = '- **' . $row[0] . ':** ' . $row[1];
            }
            $lines[] = '';
        }

        $longDescriptionRaw = (string) apply_filters('the_content', $product->get_description());
        if (trim(wp_strip_all_tags($longDescriptionRaw)) !== '') {
            $lines[] = '## ' . __('Description', 'polski');
            $lines[] = '';
            $lines[] = trim($this->converter->htmlToMarkdown($longDescriptionRaw));
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function factRows(WC_Product $product): array
    {
        $rows = [];

        $sku = (string) $product->get_sku();
        if ($sku !== '') {
            $rows[] = [__('SKU', 'polski'), $sku];
        }

        $gtin = $this->productInfo->getGTIN($product);
        if ($gtin !== '') {
            $rows[] = [__('GTIN/EAN', 'polski'), $gtin];
        }

        $price = $this->priceText($product->get_price());
        if ($price !== '') {
            $rows[] = [__('Price', 'polski'), $price];
        }

        if ($product->is_on_sale()) {
            $regular = $this->priceText($product->get_regular_price());
            if ($regular !== '') {
                $rows[] = [__('Regular price', 'polski'), $regular];
            }
        }

        if (function_exists('wc_tax_enabled') && wc_tax_enabled()) {
            $taxClass = (string) $product->get_tax_class();
            $rows[] = [
                __('Tax class', 'polski'),
                $taxClass !== '' ? $taxClass : __('Standard', 'polski'),
            ];
        }

        $omnibusValue = $this->omnibusText($product);
        if ($omnibusValue !== '') {
            $rows[] = [__('Lowest price (last 30 days)', 'polski'), $omnibusValue];
        }

        $deliveryText = $this->deliveryTime->getDeliveryTimeText($product);
        if ($deliveryText !== '') {
            $rows[] = [__('Delivery time', 'polski'), $deliveryText];
        }

        $stockQuantity = $product->get_stock_quantity();
        if ($stockQuantity !== null) {
            $rows[] = [__('Stock quantity', 'polski'), (string) $stockQuantity];
        }
        $rows[] = [
            __('Availability', 'polski'),
            $product->is_in_stock() ? __('In stock', 'polski') : __('Out of stock', 'polski'),
        ];

        $weight = (string) $product->get_weight();
        if ($weight !== '') {
            $unit = (string) get_option('woocommerce_weight_unit', 'kg');
            $rows[] = [__('Weight', 'polski'), trim($weight . ' ' . $unit)];
        }

        if (function_exists('wc_format_dimensions')) {
            $dimensions = (string) wc_format_dimensions([
                'length' => $product->get_length(),
                'width' => $product->get_width(),
                'height' => $product->get_height(),
            ]);
            $dimensions = trim(wp_strip_all_tags($dimensions));
            if ($dimensions !== '' && $dimensions !== 'N/A') {
                $rows[] = [__('Dimensions', 'polski'), $dimensions];
            }
        }

        $brands = $this->productInfo->getBrands($product);
        if ($brands !== []) {
            $rows[] = [__('Brand', 'polski'), implode(', ', $brands)];
        }

        $manufacturer = $this->productInfo->getManufacturer($product);
        if ($manufacturer !== '') {
            $rows[] = [__('Manufacturer', 'polski'), $manufacturer];
        }

        $gpsr = $this->productInfo->getGPSRResponsible($product);
        if ($gpsr !== '') {
            $rows[] = [__('Responsible person (GPSR)', 'polski'), $gpsr];
        }

        return $rows;
    }

    /**
     * @return string[]
     */
    private function categoryNames(WC_Product $product): array
    {
        $terms = get_the_terms($product->get_id(), 'product_cat');
        if (! is_array($terms)) {
            return [];
        }

        $names = [];
        foreach ($terms as $term) {
            if ($term instanceof \WP_Term) {
                $names[] = $term->name;
            }
        }

        return $names;
    }

    private function priceText(mixed $amount): string
    {
        if ($amount === '' || $amount === null || ! is_numeric($amount)) {
            return '';
        }

        if (! function_exists('wc_price')) {
            return (string) $amount;
        }

        $formatted = (string) wc_price((float) $amount);

        return trim(html_entity_decode(wp_strip_all_tags($formatted), ENT_QUOTES));
    }

    private function omnibusText(WC_Product $product): string
    {
        if (! $this->omnibus->isEnabled()) {
            return '';
        }

        $lowest = $this->omnibus->getLowestPrice($product->get_id());
        if ($lowest === null) {
            return '';
        }

        return $this->priceText($lowest->effectivePrice());
    }
}
