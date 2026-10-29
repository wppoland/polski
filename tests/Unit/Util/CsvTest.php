<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Util;

use PHPUnit\Framework\TestCase;
use Polski\Util\Csv;

final class CsvTest extends TestCase
{
    public function testFormulaCellsAreNeutralised(): void
    {
        self::assertSame(
            ["'=HYPERLINK(\"x\")", "'+1+1", "'@SUM(A1)", "'-2+3", "'\tx", 'Jan', '', '-12.50', '42'],
            Csv::row(['=HYPERLINK("x")', '+1+1', '@SUM(A1)', '-2+3', "\tx", 'Jan', null, '-12.50', 42]),
        );
    }
}
