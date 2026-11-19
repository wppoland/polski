<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Service\ProductGroupSchema;

final class ProductGroupSchemaTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['polski_test_products'], $GLOBALS['polski_test_options']['polski_seo']);
    }

    public function testAttributeNamesMapToGoogleVariantProperties(): void
    {
        $this->assertSame('size', ProductGroupSchema::propertyFor('pa_dlugosc'));
        $this->assertSame('size', ProductGroupSchema::propertyFor('attribute_pa_dlugosc-deski'));
        $this->assertSame('size', ProductGroupSchema::propertyFor('Długość'));
        $this->assertSame('color', ProductGroupSchema::propertyFor('pa_kolor'));
        $this->assertSame('material', ProductGroupSchema::propertyFor('pa_material'));
        $this->assertNull(ProductGroupSchema::propertyFor('pa_profil'));
    }

    public function testMerchantMappingWins(): void
    {
        $map = ProductGroupSchema::parseMap("pa_profil = pattern\npa_dlugosc = none\nbroken line\npa_x = height");

        $this->assertSame(['profil' => 'pattern', 'dlugosc' => 'none', 'x' => 'none'], $map);
        $this->assertSame('pattern', ProductGroupSchema::propertyFor('pa_profil', $map));
        $this->assertNull(ProductGroupSchema::propertyFor('pa_dlugosc', $map));
    }

    public function testBuildGroupMovesPriceAndIdentifiersToVariants(): void
    {
        $group = ProductGroupSchema::buildGroup(
            ['@type' => 'Product', '@id' => 'https://example.test/p/#product', 'name' => 'Deska', 'sku' => 'P', 'gtin13' => '5901234123457', 'offers' => [['@type' => 'AggregateOffer']], 'brand' => ['@type' => 'Brand', 'name' => 'X']],
            'P',
            [['@type' => 'Product', 'sku' => 'P-300']],
            ['size', 'length'],
        );

        $this->assertSame('ProductGroup', $group['@type']);
        $this->assertSame('https://example.test/p/#product', $group['@id']);
        $this->assertSame('P', $group['productGroupID']);
        $this->assertSame(['https://schema.org/size'], $group['variesBy']);
        $this->assertArrayNotHasKey('offers', $group);
        $this->assertArrayNotHasKey('sku', $group);
        $this->assertArrayNotHasKey('gtin13', $group);
        $this->assertSame('X', $group['brand']['name']);
        $this->assertCount(1, $group['hasVariant']);
    }

    /**
     * The variable-product path end to end, with the 3tarasy shape: parent is
     * model and colour, variations are lengths.
     */
    public function testVariableProductBecomesProductGroup(): void
    {
        $parent = new VariableTestProduct();
        $GLOBALS['polski_test_products'] = [
            11 => new VariationTestProduct(11, 'GD-300', '300 cm', '189.00', true),
            12 => new VariationTestProduct(12, 'GD-400', '400 cm', '249.00', true, sale: '229.00'),
            13 => new VariationTestProduct(13, 'GD-500', '500 cm', '299.00', false),
        ];

        $group = (new ProductGroupSchema())->convert([
            '@type' => 'Product',
            '@id' => 'https://example.test/produkt/gardin/#product',
            'name' => 'Gardin Deco 2.0 Winchester',
            'url' => 'https://example.test/produkt/gardin/',
            'sku' => 'GD',
            'offers' => [[
                '@type' => 'AggregateOffer',
                'lowPrice' => '189.00',
                'highPrice' => '299.00',
                'offerCount' => 3,
                'seller' => ['@type' => 'Organization', 'name' => 'Shop'],
            ]],
        ], $parent);

        $this->assertSame('ProductGroup', $group['@type']);
        $this->assertSame('GD', $group['productGroupID']);
        $this->assertSame(['https://schema.org/size'], $group['variesBy']);
        $this->assertCount(3, $group['hasVariant']);

        [$short, $onSale, $soldOut] = $group['hasVariant'];

        $this->assertSame('Product', $short['@type']);
        $this->assertSame('GD-300', $short['sku']);
        $this->assertSame('300 cm', $short['size']);
        $this->assertSame('https://example.test/produkt/gardin/?attribute_pa_dlugosc=300-cm', $short['offers'][0]['url']);
        $this->assertSame('189.00', $short['offers'][0]['price']);
        $this->assertSame('PLN', $short['offers'][0]['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $short['offers'][0]['availability']);
        $this->assertSame('Shop', $short['offers'][0]['seller']['name']);
        $this->assertSame('https://example.test/img-11.jpg', $short['image']);

        $this->assertSame('229.00', $onSale['offers'][0]['price']);
        $this->assertSame('https://schema.org/ListPrice', $onSale['offers'][0]['priceSpecification'][0]['priceType']);
        $this->assertSame('249.00', $onSale['offers'][0]['priceSpecification'][0]['price']);

        $this->assertSame('https://schema.org/OutOfStock', $soldOut['offers'][0]['availability']);

        // Exactly one product entity: the group itself, no stray Product beside it.
        $this->assertArrayNotHasKey('offers', $group);
        $this->assertSame([3], [count(array_unique(array_column($group['hasVariant'], '@id')))]);
    }

    public function testSimpleProductPassesThrough(): void
    {
        $markup = ['@type' => 'Product', 'offers' => [['@type' => 'Offer']]];

        $this->assertSame($markup, (new ProductGroupSchema())->convert($markup, new \WC_Product()));
    }
}

class VariableTestProduct extends \WC_Product_Variable
{
    public function get_id(): int
    {
        return 10;
    }

    public function get_sku(string $context = 'view'): string
    {
        return 'GD';
    }

    /** @return array<string, list<string>> */
    public function get_variation_attributes(): array
    {
        return ['pa_dlugosc' => ['300-cm', '400-cm', '500-cm']];
    }

    /** @return list<int> */
    public function get_visible_children(): array
    {
        return [11, 12, 13];
    }
}

class VariationTestProduct extends \WC_Product_Variation
{
    public function __construct(
        private readonly int $id,
        private readonly string $sku,
        private readonly string $length,
        private readonly string $regular,
        private readonly bool $inStock,
        private readonly string $sale = '',
    ) {
    }

    public function get_id(): int
    {
        return $this->id;
    }

    public function get_sku(string $context = 'view'): string
    {
        return $this->sku;
    }

    public function get_name(string $context = 'view'): string
    {
        return 'Gardin Deco 2.0 Winchester - ' . $this->length;
    }

    public function get_price(string $context = 'view'): string
    {
        return $this->sale !== '' ? $this->sale : $this->regular;
    }

    public function get_regular_price(string $context = 'view'): string
    {
        return $this->regular;
    }

    public function is_on_sale(string $context = 'view'): bool
    {
        return $this->sale !== '';
    }

    public function variation_is_visible(): bool
    {
        return true;
    }

    public function get_permalink(): string
    {
        return 'https://example.test/produkt/gardin/?attribute_pa_dlugosc=' . str_replace(' ', '-', $this->length);
    }

    public function get_description(string $context = 'view'): string
    {
        return '';
    }

    public function get_image_id(string $context = 'view'): int
    {
        return $this->id;
    }

    public function get_attribute(string $attribute): string
    {
        return $attribute === 'pa_dlugosc' ? $this->length : '';
    }

    public function get_date_on_sale_to(string $context = 'view'): ?\WC_DateTime
    {
        return null;
    }

    public function is_in_stock(): bool
    {
        return $this->inStock;
    }

    public function get_stock_status(string $context = 'view'): string
    {
        return $this->inStock ? 'instock' : 'outofstock';
    }
}
