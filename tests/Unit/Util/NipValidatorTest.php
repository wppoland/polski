<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Util;

use PHPUnit\Framework\TestCase;
use Polski\Util\NipValidator;

final class NipValidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function nipProvider(): iterable
    {
        // Valid Polish NIPs (real, public examples used by Polish tax administration for testing).
        yield 'valid plain 10 digits' => ['5260250274', true];
        yield 'valid with hyphens' => ['526-025-02-74', true];
        yield 'valid with spaces' => ['526 025 02 74', true];
        yield 'valid with PL prefix' => ['PL5260250274', true];
        yield 'valid mixed case prefix' => ['pl5260250274', true];

        // Invalid.
        yield 'invalid checksum' => ['5260250275', false];
        yield 'too short' => ['526025027', false];
        yield 'too long' => ['52602502745', false];
        yield 'non-digits' => ['526025027A', false];
        yield 'empty string' => ['', false];
        yield 'all zeros' => ['0000000000', true]; // 0*weights = 0, %11 = 0, matches last digit.
    }

    /**
     * @dataProvider nipProvider
     */
    public function testIsValid(string $nip, bool $expected): void
    {
        self::assertSame($expected, NipValidator::isValid($nip));
    }

    public function testNormalizeStripsWhitespaceHyphensAndPlPrefix(): void
    {
        self::assertSame('5260250274', NipValidator::normalize('PL 526-025-02-74'));
        self::assertSame('5260250274', NipValidator::normalize('  526 025 02 74  '));
    }

    public function testFormatProducesCanonicalForm(): void
    {
        self::assertSame('526-025-02-74', NipValidator::format('5260250274'));
        self::assertSame('526-025-02-74', NipValidator::format('PL526-025-02-74'));
    }

    public function testFormatReturnsInputUnchangedWhenInvalidLength(): void
    {
        self::assertSame('123', NipValidator::format('123'));
        self::assertSame('not-a-nip', NipValidator::format('not-a-nip'));
    }
}
