<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Model\UnitPrice;
use Polski\Util\Formatter;

/**
 * Tests for PriceDisplayService and its dependencies (UnitPrice, Formatter).
 *
 * PriceDisplayService depends heavily on WC_Product, TaxDisplayService, and OmnibusService.
 * To keep these as true unit tests without mocking frameworks, we test the underlying
 * calculations (UnitPrice::calculate) and template interpolation (Formatter::interpolate)
 * that PriceDisplayService delegates to.
 */
final class PriceDisplayServiceTest extends TestCase
{
    // ── UnitPrice::calculate ────────────────────────────────────────────

    public function testUnitPriceCalculationStandard(): void
    {
        // Product: 500g for 6.25 PLN -> unit price per 1 kg = 12.50 PLN.
        $unit = UnitPrice::calculate(
            productPrice: 6.25,
            productAmount: 500.0,
            baseAmount: 1000.0,
            unit: 'g',
            currency: 'PLN',
        );

        $this->assertNotNull($unit);
        $this->assertSame(12.5, $unit->pricePerUnit);
        $this->assertSame('g', $unit->unit);
        $this->assertSame('PLN', $unit->currency);
    }

    public function testUnitPriceCalculationWithBaseAmountOne(): void
    {
        // Product: 2 litres for 10 PLN -> unit price per 1 litre = 5.00 PLN.
        $unit = UnitPrice::calculate(
            productPrice: 10.0,
            productAmount: 2.0,
            baseAmount: 1.0,
            unit: 'l',
        );

        $this->assertNotNull($unit);
        $this->assertSame(5.0, $unit->pricePerUnit);
    }

    public function testUnitPriceReturnsNullForZeroProductAmount(): void
    {
        $unit = UnitPrice::calculate(
            productPrice: 10.0,
            productAmount: 0.0,
            baseAmount: 1.0,
            unit: 'kg',
        );

        $this->assertNull($unit);
    }

    public function testUnitPriceReturnsNullForZeroBaseAmount(): void
    {
        $unit = UnitPrice::calculate(
            productPrice: 10.0,
            productAmount: 1.0,
            baseAmount: 0.0,
            unit: 'kg',
        );

        $this->assertNull($unit);
    }

    public function testUnitPriceReturnsNullForZeroPrice(): void
    {
        $unit = UnitPrice::calculate(
            productPrice: 0.0,
            productAmount: 1.0,
            baseAmount: 1.0,
            unit: 'kg',
        );

        $this->assertNull($unit);
    }

    public function testUnitPriceReturnsNullForNegativeProductAmount(): void
    {
        $unit = UnitPrice::calculate(
            productPrice: 10.0,
            productAmount: -1.0,
            baseAmount: 1.0,
            unit: 'kg',
        );

        $this->assertNull($unit);
    }

    public function testUnitPriceRoundsToFourDecimalPlaces(): void
    {
        // 7.99 / 750 * 1000 = 10.6533...
        $unit = UnitPrice::calculate(
            productPrice: 7.99,
            productAmount: 750.0,
            baseAmount: 1000.0,
            unit: 'ml',
        );

        $this->assertNotNull($unit);
        $this->assertSame(10.6533, $unit->pricePerUnit);
    }

    // ── Formatter::interpolate (used by PriceDisplayService) ────────────

    public function testInterpolatePriceAndUnitPlaceholders(): void
    {
        $template = '{price} / {unit}';
        $result = Formatter::interpolate($template, [
            'price' => '12,50 PLN',
            'unit' => 'kg',
        ]);

        $this->assertSame('12,50 PLN / kg', $result);
    }

    public function testInterpolateOmnibusTemplate(): void
    {
        $template = 'Najnizsza cena z ostatnich {days} dni: {price}';
        $result = Formatter::interpolate($template, [
            'days' => '30',
            'price' => '79,99 PLN',
        ]);

        $this->assertSame('Najnizsza cena z ostatnich 30 dni: 79,99 PLN', $result);
    }

    public function testInterpolateIgnoresUnknownPlaceholders(): void
    {
        $template = '{price} / {unit} ({unknown})';
        $result = Formatter::interpolate($template, [
            'price' => '5,00 PLN',
            'unit' => 'l',
        ]);

        $this->assertSame('5,00 PLN / l ({unknown})', $result);
    }

    public function testInterpolateWithEmptyReplacementsReturnsTemplate(): void
    {
        $template = '{price} / {unit}';
        $result = Formatter::interpolate($template, []);

        $this->assertSame('{price} / {unit}', $result);
    }

    // ── Formatter::vatRate ──────────────────────────────────────────────

    public function testVatRateFormatsStandardRate(): void
    {
        $this->assertSame('23%', Formatter::vatRate(23.0));
    }

    public function testVatRateFormatsReducedRate(): void
    {
        $this->assertSame('8%', Formatter::vatRate(8.0));
        $this->assertSame('5%', Formatter::vatRate(5.0));
    }

    public function testVatRateFormatsZeroRate(): void
    {
        $this->assertSame('0%', Formatter::vatRate(0.0));
    }

    public function testVatRateFormatsDecimalRate(): void
    {
        $this->assertSame('7,5%', Formatter::vatRate(7.5));
    }

    // ── wc_price stub produces expected format ──────────────────────────

    public function testWcPriceStubFormatsCorrectly(): void
    {
        $html = wc_price(12.50, ['currency' => 'PLN']);

        $this->assertStringContainsString('12,50', $html);
        $this->assertStringContainsString('PLN', $html);
    }
}
