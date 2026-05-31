<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Service\FilterService;
use Polski\Util\TemplateLoader;

final class FilterServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['polski_test_options'] = [];
        $GLOBALS['polski_test_is_shop'] = false;
        $GLOBALS['polski_test_is_product_archive'] = false;
        $GLOBALS['polski_test_is_product_category'] = false;
        $GLOBALS['polski_test_is_product_tag'] = false;
        $GLOBALS['polski_test_is_product_taxonomy'] = false;
        $GLOBALS['polski_test_queried_object'] = null;
    }

    private function createService(): FilterService
    {
        return new FilterService(new TemplateLoader());
    }

    public function testBuildActiveFilterItemsReturnsExpectedChipsForKnownFilters(): void
    {
        $service = $this->createService();

        $items = $service->buildActiveFilterItems(
            [
                'polski_filter_category' => 'hoodies',
                'polski_filter_brand' => 'nike',
                'polski_filter_min_price' => '49.99',
                'polski_filter_stock' => 'instock',
                'polski_filter_sale' => '1',
                'polski_filter_pa_color' => 'red',
            ],
            ['pa_color'],
            static fn (string $taxonomy, string $slug): string => match ($taxonomy . ':' . $slug) {
                'product_cat:hoodies' => 'Bluzy',
                'polski_brand:nike' => 'Nike',
                'pa_color:red' => 'Czerwony',
                default => $slug,
            },
        );

        $this->assertSame(
            [
                [
                    'param' => 'polski_filter_category',
                    'label' => 'Kategoria',
                    'value' => 'Bluzy',
                    'raw_value' => 'hoodies',
                ],
                [
                    'param' => 'polski_filter_brand',
                    'label' => 'Marka',
                    'value' => 'Nike',
                    'raw_value' => 'nike',
                ],
                [
                    'param' => 'polski_filter_min_price',
                    'label' => 'Cena od',
                    'value' => '49.99',
                    'raw_value' => '49.99',
                ],
                [
                    'param' => 'polski_filter_stock',
                    'label' => 'Dostępność',
                    'value' => 'Dostępne od ręki',
                    'raw_value' => 'instock',
                ],
                [
                    'param' => 'polski_filter_sale',
                    'label' => 'Promocje',
                    'value' => 'Tylko promocje',
                    'raw_value' => '1',
                ],
                [
                    'param' => 'polski_filter_pa_color',
                    'label' => 'Kolor',
                    'value' => 'Czerwony',
                    'raw_value' => 'red',
                ],
            ],
            $items,
        );
    }

    public function testBuildActiveFilterItemsSkipsEmptyValues(): void
    {
        $service = $this->createService();

        $items = $service->buildActiveFilterItems(
            [
                'polski_filter_category' => '',
                'polski_filter_brand' => '',
                'polski_filter_min_price' => '',
                'polski_filter_stock' => '',
                'polski_filter_sale' => '',
                'polski_filter_pa_size' => '',
            ],
            ['pa_size'],
            static fn (string $taxonomy, string $slug): string => $slug,
        );

        $this->assertSame([], $items);
    }

    public function testBuildActiveFilterItemsReturnsSeparateItemsForMultiSelectedTaxonomyTerms(): void
    {
        $service = $this->createService();

        $items = $service->buildActiveFilterItems(
            [
                'polski_filter_category' => ['hoodies', 'tshirts'],
                'polski_filter_pa_size' => ['m', 'l'],
            ],
            ['pa_size'],
            static fn (string $taxonomy, string $slug): string => match ($taxonomy . ':' . $slug) {
                'product_cat:hoodies' => 'Bluzy',
                'product_cat:tshirts' => 'Koszulki',
                'pa_size:m' => 'M',
                'pa_size:l' => 'L',
                default => $slug,
            },
        );

        $this->assertSame(
            [
                [
                    'param' => 'polski_filter_category',
                    'label' => 'Kategoria',
                    'value' => 'Bluzy',
                    'raw_value' => 'hoodies',
                ],
                [
                    'param' => 'polski_filter_category',
                    'label' => 'Kategoria',
                    'value' => 'Koszulki',
                    'raw_value' => 'tshirts',
                ],
                [
                    'param' => 'polski_filter_pa_size',
                    'label' => 'Rozmiar',
                    'value' => 'M',
                    'raw_value' => 'm',
                ],
                [
                    'param' => 'polski_filter_pa_size',
                    'label' => 'Rozmiar',
                    'value' => 'L',
                    'raw_value' => 'l',
                ],
            ],
            $items,
        );
    }

    public function testGetAttributeTaxonomiesUsesExplicitConfiguredListWhenProvided(): void
    {
        $service = $this->createService();

        $result = $service->getAttributeTaxonomies([
            'show_attributes' => true,
            'attribute_taxonomies' => 'pa_size, pa_color, invalid_taxonomy',
            'max_attribute_taxonomies' => 1,
        ]);

        $this->assertSame(['pa_size', 'pa_color'], $result);
    }

    public function testGetAttributeTaxonomiesFallsBackToMaxCountWhenExplicitListIsMissing(): void
    {
        $service = $this->createService();

        $result = $service->getAttributeTaxonomies([
            'show_attributes' => true,
            'attribute_taxonomies' => '',
            'max_attribute_taxonomies' => 2,
        ]);

        $this->assertSame(['pa_color', 'pa_size'], $result);
    }

    public function testGetPresetReturnsAdminManagedJsonPreset(): void
    {
        $GLOBALS['polski_test_options']['polski_filters'] = [
            'presets_json' => '{"fashion":{"title":"Fashion filters","show_brands":false,"attribute_taxonomies":"pa_color,pa_size"}}',
        ];

        $service = $this->createService();
        $preset = $service->getPreset('fashion');

        $this->assertSame(
            [
                'show_brands' => false,
                'attribute_taxonomies' => 'pa_color,pa_size',
                'title' => 'Fashion filters',
            ],
            $preset,
        );
    }

    public function testGetPresetLetsAdminJsonOverrideLegacyPresetWithSameName(): void
    {
        $GLOBALS['polski_test_options']['polski_filter_presets'] = [
            'fashion' => [
                'title' => 'Legacy title',
                'show_brands' => true,
            ],
        ];
        $GLOBALS['polski_test_options']['polski_filters'] = [
            'presets_json' => '{"fashion":{"title":"Admin title","show_brands":false}}',
        ];

        $service = $this->createService();
        $preset = $service->getPreset('fashion');

        $this->assertSame(
            [
                'show_brands' => false,
                'title' => 'Admin title',
            ],
            $preset,
        );
    }

    public function testGetArchivePresetSlugReturnsMappedShopPreset(): void
    {
        $GLOBALS['polski_test_is_shop'] = true;
        $GLOBALS['polski_test_options']['polski_filters'] = [
            'archive_presets_json' => '{"shop":"default_shop"}',
        ];

        $service = $this->createService();

        $this->assertSame('default_shop', $service->getArchivePresetSlug());
    }

    public function testGetArchivePresetSlugReturnsMappedTermPreset(): void
    {
        $GLOBALS['polski_test_is_product_category'] = true;
        $GLOBALS['polski_test_queried_object'] = new \WP_Term(15, 'Bluzy', 'product_cat', 'hoodies');
        $GLOBALS['polski_test_options']['polski_filters'] = [
            'archive_presets_json' => '{"product_cat:hoodies":"fashion"}',
        ];

        $service = $this->createService();

        $this->assertSame('fashion', $service->getArchivePresetSlug());
    }

    public function testGetArchivePresetSlugFallsBackToTaxonomyWidePreset(): void
    {
        $GLOBALS['polski_test_is_product_taxonomy'] = true;
        $GLOBALS['polski_test_queried_object'] = new \WP_Term(33, 'Czerwony', 'pa_color', 'red');
        $GLOBALS['polski_test_options']['polski_filters'] = [
            'archive_presets_json' => '{"taxonomy:pa_color":"color_family"}',
        ];

        $service = $this->createService();

        $this->assertSame('color_family', $service->getArchivePresetSlug());
    }
}
