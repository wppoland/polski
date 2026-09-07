<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Service\DepositService;

/**
 * The deposit is money added to every covered order, so the arithmetic is worth
 * pinning: a wrong unit count or a missed parent lookup overcharges or
 * undercharges a real shopper on every checkout.
 */
final class DepositServiceTest extends TestCase
{
    private DepositService $service;

    protected function setUp(): void
    {
        $GLOBALS['polski_test_options'] = [];
        $this->service = new DepositService();
    }

    protected function tearDown(): void
    {
        $GLOBALS['polski_test_options'] = [];
    }

    private function product(string $type, mixed $units = '', int $parentId = 0, string $parentType = '', mixed $parentUnits = ''): \WC_Product
    {
        return new class ($type, $units, $parentId, $parentType, $parentUnits) extends \WC_Product {
            public function __construct(
                private string $type,
                private mixed $units,
                private int $parentId,
                public string $parentType,
                public mixed $parentUnits,
            ) {
            }

            public function get_parent_id(): int
            {
                return $this->parentId;
            }

            public function get_meta(string $key, bool $single = true): mixed
            {
                if ($key === DepositService::META_TYPE) {
                    return $this->type;
                }
                if ($key === DepositService::META_UNITS) {
                    return $this->units;
                }

                return '';
            }
        };
    }

    public function testStatutoryDefaultsApplyWhenNothingIsConfigured(): void
    {
        self::assertSame(0.5, $this->service->amountForType('pet'));
        self::assertSame(0.5, $this->service->amountForType('can'));
        self::assertSame(1.0, $this->service->amountForType('glass'));
    }

    public function testUncoveredPackagingCostsNothing(): void
    {
        self::assertSame(0.0, $this->service->amountForType(''));
        self::assertSame(0.0, $this->service->amountForType('cardboard'));
        self::assertSame(0.0, $this->service->depositFor($this->product('')));
    }

    public function testConfiguredAmountOverridesTheDefault(): void
    {
        $GLOBALS['polski_test_options']['polski_deposit'] = ['amount_pet' => '0.75'];

        self::assertSame(0.75, $this->service->amountForType('pet'));
        // Untouched types keep the statutory figure.
        self::assertSame(1.0, $this->service->amountForType('glass'));
    }

    public function testAnEmptyConfiguredAmountFallsBackRatherThanChargingZero(): void
    {
        $GLOBALS['polski_test_options']['polski_deposit'] = ['amount_pet' => ''];

        self::assertSame(0.5, $this->service->amountForType('pet'));
    }

    public function testUnitsMultiplyTheDeposit(): void
    {
        // A six-pack of cans is six deposits, not one.
        self::assertSame(3.0, $this->service->depositFor($this->product('can', 6)));
    }

    public function testMissingOrNonsenseUnitCountIsTreatedAsOne(): void
    {
        self::assertSame(0.5, $this->service->depositFor($this->product('can')));
        self::assertSame(0.5, $this->service->depositFor($this->product('can', 0)));
        self::assertSame(0.5, $this->service->depositFor($this->product('can', -3)));
    }

    public function testNegativeConfiguredAmountCannotCreditTheShopper(): void
    {
        $GLOBALS['polski_test_options']['polski_deposit'] = ['amount_glass' => '-2'];

        self::assertSame(0.0, $this->service->amountForType('glass'));
    }
}
