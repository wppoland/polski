<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Polski\Service\DeliveryEstimateService;

final class DeliveryEstimateServiceTest extends TestCase
{
    private const WEEKEND = [6, 0];

    private static function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable($time, new \DateTimeZone('Europe/Warsaw'));
    }

    /**
     * @return iterable<string, array{string, int, list<string>, string}>
     */
    public static function cases(): iterable
    {
        // 2026-11-16 is a Monday.
        yield 'before cutoff counts today' => ['2026-11-16 09:00', 2, [], '2026-11-18'];
        yield 'after cutoff starts tomorrow' => ['2026-11-16 15:30', 2, [], '2026-11-19'];
        yield 'exactly at cutoff is too late' => ['2026-11-16 14:00', 2, [], '2026-11-19'];
        yield 'friday after cutoff skips the weekend' => ['2026-11-20 16:00', 1, [], '2026-11-24'];
        yield 'saturday order dispatches monday' => ['2026-11-21 10:00', 2, [], '2026-11-25'];
        yield 'sunday order dispatches monday' => ['2026-11-22 08:00', 0, [], '2026-11-23'];
        yield 'delivery never lands on a weekend' => ['2026-11-19 10:00', 1, [], '2026-11-20'];
        yield 'holiday is skipped in transit' => ['2026-11-10 10:00', 1, ['2026-11-11'], '2026-11-12'];
        yield 'holiday on order day dispatches next day' => ['2026-11-11 09:00', 1, ['2026-11-11'], '2026-11-13'];
        yield 'christmas block' => ['2026-12-23 15:00', 1, ['2026-12-24', '2026-12-25', '2026-12-26'], '2026-12-29'];
        yield 'same day delivery with zero lead time' => ['2026-11-16 09:00', 0, [], '2026-11-16'];
    }

    /**
     * @param list<string> $holidays
     */
    #[DataProvider('cases')]
    public function testEstimate(string $now, int $lead, array $holidays, string $expected): void
    {
        $date = DeliveryEstimateService::estimate(self::at($now), '14:00', $lead, self::WEEKEND, $holidays);

        $this->assertNotNull($date);
        $this->assertSame($expected, $date->format('Y-m-d'));
    }

    public function testNoCutoffMeansTheWholeDayCounts(): void
    {
        $date = DeliveryEstimateService::estimate(self::at('2026-11-16 23:30'), '', 1, self::WEEKEND, []);

        $this->assertSame('2026-11-17', $date?->format('Y-m-d'));
    }

    public function testEveryWeekdayBlockedGivesNoDate(): void
    {
        $this->assertNull(DeliveryEstimateService::estimate(self::at('2026-11-16 09:00'), '14:00', 1, [0, 1, 2, 3, 4, 5, 6], []));
    }

    public function testPickerEarliestDateIsRespected(): void
    {
        // Lead time alone gives Tuesday; the picker allows nothing before Thursday.
        $date = DeliveryEstimateService::estimate(self::at('2026-11-16 09:00'), '14:00', 1, self::WEEKEND, [], 3);

        $this->assertSame('2026-11-19', $date?->format('Y-m-d'));
    }

    public function testExpiresAtNextCutoff(): void
    {
        $this->assertSame('2026-11-16 14:00', DeliveryEstimateService::expiresAt(self::at('2026-11-16 09:00'), '14:00')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-17 14:00', DeliveryEstimateService::expiresAt(self::at('2026-11-16 15:00'), '14:00')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-17 00:00', DeliveryEstimateService::expiresAt(self::at('2026-11-16 15:00'), '')->format('Y-m-d H:i'));
    }

    public function testPolishHolidays2026(): void
    {
        $holidays = DeliveryEstimateService::polishHolidays(2026);

        foreach (['2026-01-01', '2026-01-06', '2026-04-05', '2026-04-06', '2026-05-01', '2026-05-03', '2026-05-24', '2026-06-04', '2026-08-15', '2026-11-01', '2026-11-11', '2026-12-24', '2026-12-25', '2026-12-26'] as $day) {
            $this->assertContains($day, $holidays);
        }

        $this->assertCount(14, $holidays);
        $this->assertNotContains('2024-12-24', DeliveryEstimateService::polishHolidays(2024));
        $this->assertContains('2027-03-29', DeliveryEstimateService::polishHolidays(2027), 'Easter Monday 2027');
    }

    public function testParsers(): void
    {
        $this->assertSame([6, 0], DeliveryEstimateService::parseWeekdays('6, 0, x'));
        $this->assertSame([0], DeliveryEstimateService::parseWeekdays('7'));
        $this->assertSame([1, 2], DeliveryEstimateService::parseWeekdays(['1', 2]));
        $this->assertSame(['2026-11-11', '2026-12-31'], DeliveryEstimateService::parseDates('2026-11-11, 31.12.2026 2026-12-31'));
        $this->assertSame(['paleta' => 5, 'small-parcel' => 1], DeliveryEstimateService::parseClassLeadDays("paleta: 5\nSmall parcel = 1\nbroken"));
    }

    public function testLeadDaysPrecedence(): void
    {
        $GLOBALS['polski_test_options']['polski_delivery_estimate'] = ['default_lead_days' => 3, 'class_lead_days' => 'paleta: 6'];
        $service = new DeliveryEstimateService(static fn (): \DateTimeImmutable => self::at('2026-11-16 09:00'));

        $this->assertSame(3, $service->leadDaysFor(new EstimateTestProduct()));
        $this->assertSame(6, $service->leadDaysFor(new EstimateTestProduct(shippingClass: 'paleta')));
        $this->assertSame(1, $service->leadDaysFor(new EstimateTestProduct(meta: '1', shippingClass: 'paleta')));

        unset($GLOBALS['polski_test_options']['polski_delivery_estimate']);
    }

    public function testLineReservesSpaceAndCarriesAccessibleDate(): void
    {
        $GLOBALS['polski_test_options']['polski_modules'] = ['delivery_estimate' => true];
        $GLOBALS['polski_test_options']['polski_delivery_estimate'] = ['default_lead_days' => 2, 'polish_holidays' => false, 'display_format' => 'Delivery by {date}', 'date_format' => 'Y-m-d'];
        $service = new DeliveryEstimateService(static fn (): \DateTimeImmutable => self::at('2026-11-16 09:00'));

        $html = $service->getHtml(new EstimateTestProduct());
        $this->assertStringContainsString('min-height:1.5em', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('Delivery by <time datetime="2026-11-18">2026-11-18</time>', $html);

        // Out of stock: no promise, but the reserved line stays.
        $empty = $service->getHtml(new EstimateTestProduct(stock: 'outofstock'));
        $this->assertStringContainsString('class="polski-delivery-estimate"', $empty);
        $this->assertStringNotContainsString('<time', $empty);

        unset($GLOBALS['polski_test_options']['polski_modules'], $GLOBALS['polski_test_options']['polski_delivery_estimate']);
    }
}

/**
 * Minimal product for the estimate service.
 */
final class EstimateTestProduct extends \WC_Product
{
    public function __construct(
        private readonly string $meta = '',
        private readonly string $shippingClass = '',
        private readonly string $stock = 'instock',
    ) {
    }

    public function get_id(): int
    {
        return 7;
    }

    public function get_meta(string $key, bool $single = true): mixed
    {
        return $key === DeliveryEstimateService::META_LEAD_DAYS ? $this->meta : '';
    }

    public function get_shipping_class(): string
    {
        return $this->shippingClass;
    }

    public function is_purchasable(): bool
    {
        return true;
    }

    public function get_stock_status(): string
    {
        return $this->stock;
    }
}
