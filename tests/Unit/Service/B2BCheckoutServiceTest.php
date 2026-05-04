<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Service\B2BCheckoutService;
use ReflectionMethod;

final class B2BCheckoutServiceTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function ibanProvider(): iterable
    {
        // Valid IBANs from public ECBS test vectors.
        yield 'valid PL' => ['PL61109010140000071219812874', true];
        yield 'valid DE' => ['DE89370400440532013000', true];
        yield 'valid GB' => ['GB82WEST12345698765432', true];
        yield 'valid FR' => ['FR1420041010050500013M02606', true];

        // Mod-97 failures.
        yield 'PL with wrong check digits' => ['PL00109010140000071219812874', false];
        yield 'DE with corrupted body' => ['DE89370400440532013001', false];

        // Country-length mismatch.
        yield 'PL too short' => ['PL6110901014000007121981287', false];
        yield 'DE too long' => ['DE893704004405320130000', false];

        // Format failures.
        yield 'lowercase letters' => ['pl61109010140000071219812874', false];
        yield 'too short overall' => ['PL611090', false];
        yield 'empty' => ['', false];
        yield 'no country code' => ['1234567890ABCDEFGHIJKLMN', false];
    }

    /**
     * @dataProvider ibanProvider
     */
    public function testIbanValidation(string $iban, bool $expected): void
    {
        $service = new B2BCheckoutService();
        $method = new ReflectionMethod(B2BCheckoutService::class, 'isPlausibleIban');

        self::assertSame($expected, $method->invoke($service, $iban));
    }
}
