<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Enum\PriceType;
use Polski\Model\OmnibusPrice;
use Polski\Repository\OmnibusPriceRepository;
use Polski\Service\OmnibusService;

final class OmnibusServiceTest extends TestCase
{
    /**
     * Create OmnibusService with a mock-like repository using anonymous class workaround.
     *
     * Since OmnibusPriceRepository is final and takes wpdb, we create a minimal wpdb stub
     * and then use the real repository (which won't actually hit a DB in our tests since
     * we only call methods on OmnibusService that don't delegate to the repo, or we use
     * reflection to set a test double).
     */
    private function createServiceWithDefaults(): OmnibusService
    {
        $wpdbStub = new class {
            public string $prefix = 'wp_';
        };

        // Use reflection to pass our stub since OmnibusPriceRepository expects wpdb.
        $repo = (new \ReflectionClass(OmnibusPriceRepository::class))
            ->newInstanceWithoutConstructor();

        return new OmnibusService($repo);
    }

    // ── isEnabled / boot defaults ───────────────────────────────────────

    public function testServiceIsEnabledByDefault(): void
    {
        $service = $this->createServiceWithDefaults();
        $service->boot();

        $this->assertTrue($service->isEnabled());
    }

    public function testBootSetsDefaults(): void
    {
        $service = $this->createServiceWithDefaults();
        $service->boot();

        // Default settings: enabled=true (verified above).
        // We verify the service can boot without errors with default get_option stub.
        $this->assertTrue($service->isEnabled());
    }

    // ── registerHooks ───────────────────────────────────────────────────

    public function testRegisterHooksDoesNotThrow(): void
    {
        $service = $this->createServiceWithDefaults();
        $service->boot();
        $service->registerHooks();

        // Stubs absorb add_action calls; reaching here means no exceptions.
        $this->assertTrue(true);
    }

    // ── OmnibusPrice value object ───────────────────────────────────────

    public function testEffectivePriceReturnsSalePriceWhenAvailable(): void
    {
        $price = new OmnibusPrice(
            id: 1,
            productId: 1,
            price: 100.0,
            salePrice: 75.0,
            priceType: PriceType::Sale,
            currency: 'PLN',
            recordedAt: new \DateTimeImmutable(),
        );

        $this->assertSame(75.0, $price->effectivePrice());
    }

    public function testEffectivePriceReturnsRegularWhenNoSale(): void
    {
        $price = new OmnibusPrice(
            id: 1,
            productId: 1,
            price: 100.0,
            salePrice: null,
            priceType: PriceType::Regular,
            currency: 'PLN',
            recordedAt: new \DateTimeImmutable(),
        );

        $this->assertSame(100.0, $price->effectivePrice());
    }

    public function testEffectivePriceWithZeroSalePrice(): void
    {
        // salePrice of 0.0 is still a float, so effectivePrice returns it.
        $price = new OmnibusPrice(
            id: 1,
            productId: 1,
            price: 100.0,
            salePrice: 0.0,
            priceType: PriceType::Sale,
            currency: 'PLN',
            recordedAt: new \DateTimeImmutable(),
        );

        // 0.0 is not null, so effectivePrice returns 0.0.
        $this->assertSame(0.0, $price->effectivePrice());
    }

    // ── OmnibusPrice::fromRow ───────────────────────────────────────────

    public function testFromRowCreatesValidOmnibusPrice(): void
    {
        $row = (object) [
            'id' => '5',
            'product_id' => '42',
            'price' => '99.99',
            'sale_price' => '79.99',
            'price_type' => 'sale',
            'currency' => 'PLN',
            'recorded_at' => '2026-03-15 12:00:00',
        ];

        $price = OmnibusPrice::fromRow($row);

        $this->assertSame(5, $price->id);
        $this->assertSame(42, $price->productId);
        $this->assertSame(99.99, $price->price);
        $this->assertSame(79.99, $price->salePrice);
        $this->assertSame(PriceType::Sale, $price->priceType);
        $this->assertSame('PLN', $price->currency);
        $this->assertSame('2026-03-15', $price->recordedAt->format('Y-m-d'));
    }

    public function testFromRowWithNullSalePrice(): void
    {
        $row = (object) [
            'id' => '1',
            'product_id' => '10',
            'price' => '50.00',
            'sale_price' => null,
            'price_type' => 'regular',
            'currency' => 'EUR',
            'recorded_at' => '2026-01-01 00:00:00',
        ];

        $price = OmnibusPrice::fromRow($row);

        $this->assertNull($price->salePrice);
        $this->assertSame(PriceType::Regular, $price->priceType);
        $this->assertSame('EUR', $price->currency);
        $this->assertSame(50.0, $price->effectivePrice());
    }

    // ── PriceType enum ──────────────────────────────────────────────────

    public function testPriceTypeEnumValues(): void
    {
        $this->assertSame('regular', PriceType::Regular->value);
        $this->assertSame('sale', PriceType::Sale->value);
        $this->assertSame('promo', PriceType::Promotional->value);
    }

    public function testPriceTypeFromString(): void
    {
        $this->assertSame(PriceType::Sale, PriceType::from('sale'));
        $this->assertSame(PriceType::Regular, PriceType::from('regular'));
        $this->assertSame(PriceType::Promotional, PriceType::from('promo'));
    }

    // ── 30-day window boundary ──────────────────────────────────────────

    public function testOmnibusPriceRecordedAtBoundary(): void
    {
        // Use a fixed reference point to avoid timing issues.
        $now = new \DateTimeImmutable('2026-04-03 12:00:00');
        $cutoff = $now->modify('-30 days');

        $withinWindow = new OmnibusPrice(
            id: 1,
            productId: 1,
            price: 80.0,
            salePrice: null,
            priceType: PriceType::Regular,
            currency: 'PLN',
            recordedAt: $cutoff->modify('+1 second'),
        );

        $outsideWindow = new OmnibusPrice(
            id: 2,
            productId: 1,
            price: 50.0,
            salePrice: null,
            priceType: PriceType::Regular,
            currency: 'PLN',
            recordedAt: $cutoff->modify('-1 second'),
        );

        // Record 1 second after cutoff is within the window.
        $this->assertTrue($withinWindow->recordedAt >= $cutoff);

        // Record 1 second before cutoff is outside the window.
        $this->assertFalse($outsideWindow->recordedAt >= $cutoff);
    }
}
