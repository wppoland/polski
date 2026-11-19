<?php

declare(strict_types=1);

namespace Polski\Service;

defined('ABSPATH') || exit;

/**
 * Turns WooCommerce's Product node of a variable product into a ProductGroup.
 *
 * WooCommerce describes a variable product as one Product with an
 * AggregateOffer price range. Google's product variant markup wants a
 * ProductGroup with productGroupID, variesBy and one Product per variant in
 * hasVariant, each with its own offer, SKU and GTIN. The node is rewritten in
 * place, so the page still carries exactly one product entity.
 *
 * existing-checked: Hook/StructuredDataHooks and ProductHooks::enrichStructuredData
 * - both only add fields to the single offer WooCommerce built; neither knows
 * the variations, and the conversion needs its own collaborators and tests.
 *
 * @see https://developers.google.com/search/docs/appearance/structured-data/product-variants
 */
final class ProductGroupSchema
{
    /**
     * Variant properties written as plain values on the variant. Google's
     * suggestedAge and suggestedGender belong in a PeopleAudience node, so
     * they are not offered.
     */
    public const PROPERTIES = ['color', 'size', 'material', 'pattern'];

    /**
     * Attribute name fragments that say which Schema.org property an attribute
     * means. A length or width is a size: Google has no separate length value.
     */
    private const KEYWORDS = [
        'color' => ['color', 'colour', 'kolor', 'barwa'],
        'size' => ['size', 'rozmiar', 'dlugosc', 'length', 'wymiar', 'szerokosc', 'width', 'grubosc', 'thickness', 'wysokosc', 'height'],
        'material' => ['material', 'tworzywo'],
        'pattern' => ['pattern', 'wzor'],
    ];

    /**
     * Rewrite the markup of a variable product. Other products pass through.
     *
     * @param array<string, mixed> $markup
     * @return array<string, mixed>
     */
    public function convert(array $markup, \WC_Product $product): array
    {
        // An SEO plugin that took the product node over (Yoast WooCommerce SEO)
        // leaves an empty array: a group built from it would be a second product.
        if (! $product instanceof \WC_Product_Variable || ! isset($markup['@type'])) {
            return $markup;
        }

        $map = self::parseMap((string) (MerchantPolicySchema::schemaSettings()['variant_attribute_map'] ?? ''));
        $attributes = array_keys($product->get_variation_attributes());

        /** Maximum number of variants described in hasVariant. */
        $max = (int) apply_filters('polski/structured_data/max_variants', 100, $product);
        $seller = $markup['offers'][0]['seller'] ?? null;

        $variants = [];
        $variesBy = [];

        foreach (array_slice($product->get_visible_children(), 0, max(1, $max)) as $childId) {
            $variation = wc_get_product($childId);

            if (! $variation instanceof \WC_Product_Variation || ! $variation->variation_is_visible() || $variation->get_price() === '') {
                continue;
            }

            [$node, $used] = $this->variantNode($variation, $product, $attributes, $map);

            if (is_array($seller)) {
                $node['offers'][0]['seller'] = $seller;
            }

            /**
             * Filters one variant Product node of a ProductGroup. Polski's own
             * enrichment (GTIN, unit price, Omnibus, shipping, returns) runs here.
             *
             * @param array<string, mixed>  $node
             * @param \WC_Product_Variation $variation
             * @param \WC_Product_Variable  $product
             */
            $node = apply_filters('polski/structured_data/variant', $node, $variation, $product);

            if (is_array($node)) {
                $variants[] = $node;
                $variesBy += array_flip($used);
            }
        }

        if ($variants === []) {
            return $markup;
        }

        $groupId = $product->get_sku() !== '' ? $product->get_sku() : (string) $product->get_id();

        return self::buildGroup($markup, $groupId, $variants, array_keys($variesBy));
    }

    /**
     * @param array<string, mixed>            $markup   The product node WooCommerce built.
     * @param list<array<string, mixed>>      $variants Variant Product nodes.
     * @param list<string>                    $variesBy Schema.org property names.
     * @return array<string, mixed>
     */
    public static function buildGroup(array $markup, string $groupId, array $variants, array $variesBy): array
    {
        // The group has no price or identifier of its own: those live on the
        // variants. A parent GTIN would claim the trade item of every variant.
        unset(
            $markup['offers'],
            $markup['sku'],
            $markup['gtin'],
            $markup['gtin8'],
            $markup['gtin12'],
            $markup['gtin13'],
            $markup['gtin14'],
            $markup['mpn'],
            $markup['inProductGroupWithID'],
        );

        $markup['@type'] = 'ProductGroup';
        $markup['productGroupID'] = $groupId;

        $variesBy = array_values(array_intersect(self::PROPERTIES, $variesBy));
        if ($variesBy !== []) {
            $markup['variesBy'] = array_map(static fn (string $p): string => 'https://schema.org/' . $p, $variesBy);
        }

        $markup['hasVariant'] = $variants;

        return $markup;
    }

    /**
     * Schema.org property an attribute stands for: the merchant's mapping
     * first, then the name. Null means "no Google variant property".
     *
     * @param array<string, string> $map
     */
    public static function propertyFor(string $attribute, array $map = []): ?string
    {
        $slug = self::normalise($attribute);

        if (isset($map[$slug])) {
            return in_array($map[$slug], self::PROPERTIES, true) ? $map[$slug] : null;
        }

        foreach (self::KEYWORDS as $property => $words) {
            foreach ($words as $word) {
                if (str_contains($slug, $word)) {
                    return $property;
                }
            }
        }

        return null;
    }

    /**
     * Parse "pa_dlugosc = size" lines. "none" maps an attribute to nothing.
     *
     * @return array<string, string>
     */
    public static function parseMap(string $raw): array
    {
        $map = [];

        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            if (! str_contains($line, '=')) {
                continue;
            }

            [$attribute, $property] = array_map('trim', explode('=', $line, 2));
            $match = array_values(array_filter(self::PROPERTIES, static fn (string $p): bool => strcasecmp($p, $property) === 0));

            if ($attribute !== '') {
                $map[self::normalise($attribute)] = $match[0] ?? 'none';
            }
        }

        return $map;
    }

    private static function normalise(string $attribute): string
    {
        $slug = strtolower(remove_accents($attribute));
        $slug = (string) preg_replace('/^(attribute_)?(pa_)?/', '', $slug);

        return (string) preg_replace('/[^a-z0-9]+/', '', $slug);
    }

    /**
     * @param list<string>          $attributes Variation attribute names of the parent.
     * @param array<string, string> $map
     * @return array{0: array<string, mixed>, 1: list<string>} The node and the properties it set.
     */
    private function variantNode(\WC_Product_Variation $variation, \WC_Product_Variable $parent, array $attributes, array $map): array
    {
        $currency = get_woocommerce_currency();
        $decimals = wc_get_price_decimals();
        $url = $variation->get_permalink();
        // The view context inherits the parent SKU, which every SKU-less variant
        // would then share with the group.
        $sku = $variation->get_sku('edit');

        $node = [
            '@type' => 'Product',
            // Per variation: with "any" attributes the permalink is the parent's.
            '@id' => $url . '#product-' . $variation->get_id(),
            'name' => $variation->get_name(),
            'url' => $url,
            'sku' => $sku !== '' ? $sku : (string) $variation->get_id(),
        ];

        $description = wp_strip_all_tags($variation->get_description());
        if ($description !== '') {
            $node['description'] = $description;
        }

        $image = wp_get_attachment_url((int) $variation->get_image_id());
        if (is_string($image) && $image !== '') {
            $node['image'] = $image;
        }

        $used = [];
        $values = [];

        foreach ($attributes as $attribute) {
            $value = trim($variation->get_attribute($attribute));

            if ($value === '') {
                continue;
            }

            $property = self::propertyFor($attribute, $map);

            if ($property === null) {
                $node['additionalProperty'][] = [
                    '@type' => 'PropertyValue',
                    'name' => wc_attribute_label($attribute, $parent),
                    'value' => $value,
                ];
                continue;
            }

            $values[$property][] = $value;
            $used[] = $property;
        }

        foreach ($values as $property => $list) {
            $node[$property] = implode(', ', $list);
        }

        $saleTo = $variation->get_date_on_sale_to();
        $offer = [
            '@type' => 'Offer',
            'price' => wc_format_decimal(wc_get_price_to_display($variation), $decimals),
            'priceCurrency' => $currency,
            'priceValidUntil' => $saleTo ? $saleTo->date('Y-m-d') : gmdate('Y-12-31', time() + YEAR_IN_SECONDS),
            'availability' => 'https://schema.org/' . match (true) {
                ! $variation->is_in_stock() => 'OutOfStock',
                $variation->get_stock_status() === 'onbackorder' => 'BackOrder',
                default => 'InStock',
            },
            'url' => $url,
        ];

        if ($variation->is_on_sale() && $variation->get_regular_price() !== '') {
            $offer['priceSpecification'] = [[
                '@type' => 'UnitPriceSpecification',
                'priceType' => 'https://schema.org/ListPrice',
                'price' => wc_format_decimal(wc_get_price_to_display($variation, ['price' => $variation->get_regular_price()]), $decimals),
                'priceCurrency' => $currency,
            ]];
        }

        $node['offers'] = [$offer];

        return [$node, array_values(array_unique($used))];
    }
}
