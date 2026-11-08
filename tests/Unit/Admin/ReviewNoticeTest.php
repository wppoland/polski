<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Polski\Admin\AdminNotes;

final class ReviewNoticeTest extends TestCase
{
    public function testDueOnlyAfterFourteenDaysOnceAndAfterTheDisclaimer(): void
    {
        $day = 86400;
        $activated = 1_000_000;

        self::assertFalse(AdminNotes::reviewNoticeDue(false, true, $activated, $activated + 13 * $day));
        self::assertTrue(AdminNotes::reviewNoticeDue(false, true, $activated, $activated + 14 * $day));
        self::assertFalse(AdminNotes::reviewNoticeDue(true, true, $activated, $activated + 30 * $day));
        self::assertFalse(AdminNotes::reviewNoticeDue(false, false, $activated, $activated + 30 * $day));
        self::assertFalse(AdminNotes::reviewNoticeDue(false, true, 0, $activated + 30 * $day));
    }
}
