<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Util;

use PHPUnit\Framework\TestCase;
use Polski\Util\GtinValidator;

final class GtinValidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function gtinProvider(): iterable
    {
        yield 'valid GTIN-8' => ['96385074', true];
        yield 'valid GTIN-12' => ['036000291452', true];
        yield 'valid GTIN-13' => ['4006381333931', true];
        yield 'valid GTIN-14' => ['10012345000017', true];
        yield 'valid supplied EAN-13 correction' => ['5901234567893', true];
        yield 'invalid supplied EAN-13 example' => ['5901234567890', false];
        yield 'wrong length' => ['123456789', false];
        yield 'non-numeric' => ['400638133393X', false];
        yield 'blank is not a GTIN' => ['', false];
    }

    /**
     * @dataProvider gtinProvider
     */
    public function testIsValid(string $value, bool $expected): void
    {
        self::assertSame($expected, GtinValidator::isValid($value));
    }
}
