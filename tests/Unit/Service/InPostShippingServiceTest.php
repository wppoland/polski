<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

// existing-checked: tests/Unit/Service/*Test.php - no test covers InPostShippingService, which is new.

use PHPUnit\Framework\TestCase;
use Polski\Service\InPostShippingService as S;

final class InPostShippingServiceTest extends TestCase
{
    public function testOfferedInPolandOnly(): void
    {
        self::assertTrue(S::isAvailableFor('PL', 1.0, ''));
        self::assertTrue(S::isAvailableFor('pl', 1.0, ''));
        self::assertFalse(S::isAvailableFor('DE', 1.0, ''));
        self::assertFalse(S::isAvailableFor('', 1.0, ''));
    }

    public function testEmptyWeightLimitMeansNoLimit(): void
    {
        self::assertTrue(S::isAvailableFor('PL', 999.0, ''));
        self::assertTrue(S::isAvailableFor('PL', 999.0, '  '));
    }

    public function testWeightLimitIsInclusive(): void
    {
        self::assertTrue(S::isAvailableFor('PL', 25.0, '25'));
        self::assertFalse(S::isAvailableFor('PL', 25.01, '25'));
        self::assertTrue(S::isAvailableFor('PL', 0.0, '0'));
        self::assertFalse(S::isAvailableFor('PL', 0.1, '0'));
    }

    public function testEmptyThresholdNeverMakesShippingFree(): void
    {
        self::assertSame(12.99, S::rateCost('12.99', '', 10000.0));
    }

    public function testFreeFromThresholdUpward(): void
    {
        self::assertSame(12.99, S::rateCost('12.99', '200', 199.99));
        self::assertSame(0.0, S::rateCost('12.99', '200', 200.0));
        self::assertSame(0.0, S::rateCost('12.99', '200', 250.0));
    }

    public function testZeroAndBlankCost(): void
    {
        self::assertSame(0.0, S::rateCost('0', '', 10.0));
        self::assertSame(0.0, S::rateCost('', '', 10.0));
        self::assertSame(0.0, S::rateCost('-5', '', 10.0));
    }

    /**
     * Real codes taken from the InPost Points API.
     */
    public function testRealLockerCodesAreValid(): void
    {
        foreach (['GDY144M', 'GDY06BAPP', 'ANTN01HP', 'BB011M', 'ANTX01NG', 'WAW01M'] as $code) {
            self::assertTrue(S::isValidCode($code), $code);
        }
    }

    public function testInvalidCodesAreRejected(): void
    {
        foreach (['', 'G1', '1234', 'GDY', 'GDY-144M', 'GDY144M<', 'gdy144m', 'ABCDE1', 'GDY144MXXXXXX'] as $code) {
            self::assertFalse(S::isValidCode($code), $code);
        }
    }

    public function testCodeIsUpperCasedAndTrimmed(): void
    {
        self::assertSame('GDY144M', S::normalizeCode(' gdy 144m '));
        self::assertTrue(S::isValidCode(S::normalizeCode("gdy144m\n")));
    }

    public function testPostcodeNormalisation(): void
    {
        self::assertSame('81-001', S::normalizePostcode('81-001'));
        self::assertSame('81-001', S::normalizePostcode(' 81001 '));
        self::assertNull(S::normalizePostcode('8100'));
        self::assertNull(S::normalizePostcode('81-0011'));
        self::assertNull(S::normalizePostcode('AB-123'));
        self::assertNull(S::normalizePostcode(''));
    }

    /**
     * The lookup decides whether checkout accepts a code: found, not found
     * (InPost answers 200 with point_not_found) and unreachable must stay
     * apart, and an unreachable InPost must not be cached as "not found".
     */
    public function testLookupTellsFoundNotFoundAndUnreachableApart(): void
    {
        $GLOBALS['polski_test_transients'] = [];
        $GLOBALS['polski_http_calls'] = [];
        $service = new S();
        $ok = static fn (string $body): array => ['response' => ['code' => 200], 'body' => $body];

        $GLOBALS['polski_http_queue'] = [
            $ok('{"name":"GDY144M","location_description":"Przy posesji","opening_hours":null,"address":{"line1":"Kartuska 43","line2":"81-002 Gdynia"}}'),
        ];
        self::assertSame(
            ['code' => 'GDY144M', 'address' => 'Kartuska 43, 81-002 Gdynia', 'description' => 'Przy posesji', 'hours' => ''],
            $service->lookupPoint('GDY144M'),
        );
        // Second read comes from the cache.
        self::assertIsArray($service->lookupPoint('GDY144M'));
        self::assertCount(1, $GLOBALS['polski_http_calls']);

        $GLOBALS['polski_http_queue'] = [$ok('{"status":404,"key":"point_not_found","error":"Point not found."}')];
        self::assertFalse($service->lookupPoint('XXX999'));

        $down = new \WP_Error('http_request_failed', 'timeout');
        $GLOBALS['polski_http_queue'] = [$down, $down];
        $calls = count($GLOBALS['polski_http_calls']);
        self::assertNull($service->lookupPoint('ABC123M'));
        // One checkout asks again; the outage is not waited out twice.
        self::assertNull($service->lookupPoint('ABC123M'));
        self::assertCount($calls + 2, $GLOBALS['polski_http_calls'], 'one lookup, one retry, then memoised');

        // The next request (a new instance) asks again: an outage is not cached.
        $GLOBALS['polski_http_queue'] = [$ok('{"name":"ABC123M","address":{"line1":"A 1","line2":"00-001 Warszawa"}}')];
        self::assertIsArray((new S())->lookupPoint('ABC123M'), 'an outage must not be remembered');

        self::assertFalse($service->lookupPoint('not a code'));
    }
}
