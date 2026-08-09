<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Service\FoodService;

/**
 * Tests the CSV round trip for _polski_nutrients.
 *
 * The meta key had two readers and no writer before this: the storefront table
 * wanted [slug => [value, unit]] and the schema output wanted [slug => scalar].
 * These tests pin the one shape both now agree on, and the spreadsheet form the
 * importer accepts.
 */
final class FoodServiceNutrientsTest extends TestCase
{
    public function testParsesTheSpreadsheetForm(): void
    {
        $json = FoodService::parseNutrientsCsv('energy_kcal:250|fat:12.3|salt:0.9');

        // Whole numbers come back as int: json_encode writes 250.0 as "250".
        // Harmless, because getNutrients casts to float on read, but pin it so
        // nobody "fixes" it into a JSON_PRESERVE_ZERO_FRACTION change later.
        $this->assertSame(
            [
                'energy_kcal' => ['value' => 250, 'unit' => 'kcal'],
                'fat' => ['value' => 12.3, 'unit' => 'g'],
                'salt' => ['value' => 0.9, 'unit' => 'g'],
            ],
            json_decode($json, true),
        );
    }

    public function testDropsUnknownSlugsAndNonNumericValues(): void
    {
        $json = FoodService::parseNutrientsCsv('fat:10|unicorns:5|salt:plenty|sugars:');

        $this->assertSame(['fat' => ['value' => 10, 'unit' => 'g']], json_decode($json, true));
    }

    public function testReturnsAnEmptyStringWhenNothingSurvives(): void
    {
        $this->assertSame('', FoodService::parseNutrientsCsv('nonsense'));
        $this->assertSame('', FoodService::parseNutrientsCsv(''));
    }

    public function testRoundTripsThroughExportAndBack(): void
    {
        $input = 'energy_kj:1046|energy_kcal:250|fat:12.3|salt:0.9';
        $stored = FoodService::parseNutrientsCsv($input);

        $this->assertSame($input, FoodService::formatNutrientsCsv($stored));
        $this->assertSame($stored, FoodService::parseNutrientsCsv(FoodService::formatNutrientsCsv($stored)));
    }

    public function testExportEmitsSlugsInDeclarationOrderRegardlessOfInputOrder(): void
    {
        $stored = FoodService::parseNutrientsCsv('salt:0.9|fat:12.3|energy_kcal:250');

        $this->assertSame('energy_kcal:250|fat:12.3|salt:0.9', FoodService::formatNutrientsCsv($stored));
    }

    public function testExportToleratesTheScalarShape(): void
    {
        $this->assertSame('fat:10|salt:1', FoodService::formatNutrientsCsv(['fat' => 10, 'salt' => 1]));
    }

    public function testExportOfAnEmptyOrBrokenValueIsAnEmptyString(): void
    {
        $this->assertSame('', FoodService::formatNutrientsCsv(''));
        $this->assertSame('', FoodService::formatNutrientsCsv('not json'));
        $this->assertSame('', FoodService::formatNutrientsCsv(null));
    }
}
