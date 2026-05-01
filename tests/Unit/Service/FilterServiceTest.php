<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Service\FilterService;
use Polski\Util\TemplateLoader;

final class FilterServiceTest extends TestCase
{
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
}
