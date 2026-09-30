<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Service\ViesService;

/**
 * Parsing is where this can go quietly wrong: send VIES the wrong member state
 * and a perfectly good VAT number comes back invalid, which on an intra-EU sale
 * is the difference between charging 0% and not.
 */
final class ViesServiceTest extends TestCase
{
    public function testPrefixedIdIsSplitIntoStateAndNumber(): void
    {
        self::assertSame(['DE', '123456789'], ViesService::parse('DE123456789'));
        self::assertSame(['PL', '5260250274'], ViesService::parse('PL 526-025-02-74'));
        self::assertSame(['NL', '123456789B01'], ViesService::parse('nl123456789b01'));
    }

    public function testBareNumberUsesTheBillingCountry(): void
    {
        self::assertSame(['PL', '5260250274'], ViesService::parse('5260250274', 'PL'));
        self::assertSame(['IT', '00743110157'], ViesService::parse('00743110157', 'it'));
    }

    public function testGreeceIsTranslatedToTheCodeViesActuallyUses(): void
    {
        // WooCommerce and ISO say GR; VIES says EL. Sending GR fails every Greek
        // customer.
        self::assertSame(['EL', '123456789'], ViesService::parse('123456789', 'GR'));
        self::assertSame(['EL', '123456789'], ViesService::parse('EL123456789'));
    }

    public function testNorthernIrelandIsAMemberStateForVatPurposes(): void
    {
        self::assertSame(['XI', '123456789'], ViesService::parse('XI123456789'));
    }

    public function testNonEuOrUnknownCountryIsRejectedRatherThanGuessed(): void
    {
        self::assertNull(ViesService::parse('123456789', 'US'));
        self::assertNull(ViesService::parse('123456789'));
        self::assertNull(ViesService::parse(''));
        self::assertNull(ViesService::parse('   '));
    }

    public function testACountryCodeAloneIsNotAVatId(): void
    {
        self::assertNull(ViesService::parse('DE'));
    }

    public function testAWordThatLooksLikeAPrefixIsNotMistakenForOne(): void
    {
        // "ATTN12345" starts with AT, a real member state, and must still be read
        // as an Austrian number rather than silently rejected.
        self::assertSame(['AT', 'TN12345'], ViesService::parse('ATTN12345'));
    }

    public function testWellFormedFollowsEachMemberStatesShape(): void
    {
        foreach (['DE811569869', 'de 811 569 869', 'CZ12345678', 'FR40303265045', 'ATU12345678', 'NL123456789B01', 'ESX1234567X', 'PL5260250274'] as $ok) {
            self::assertTrue(ViesService::isWellFormed($ok), $ok);
        }

        foreach (['DE81156986', 'CZ1234567', 'AT12345678', 'US123456789', '811569869', 'DE', ''] as $bad) {
            self::assertFalse(ViesService::isWellFormed($bad), $bad);
        }
    }
}
