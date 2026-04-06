<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Util\Formatter;

/**
 * Tests for ReviewRequestService patterns.
 */
final class ReviewRequestServiceTest extends TestCase
{
    public function testScheduleMetaFormat(): void
    {
        $delayDays = 7;
        $now = new \DateTimeImmutable('2026-04-06', new \DateTimeZone('Europe/Warsaw'));
        $sendAt = $now->modify('+' . $delayDays . ' days');

        $metaValue = 'scheduled:' . $sendAt->format('Y-m-d');
        $this->assertSame('scheduled:2026-04-13', $metaValue);
    }

    public function testScheduledDateParsing(): void
    {
        $metaValue = 'scheduled:2026-04-13';
        $isScheduled = str_starts_with($metaValue, 'scheduled:');
        $this->assertTrue($isScheduled);

        $scheduledDate = substr($metaValue, 10);
        $this->assertSame('2026-04-13', $scheduledDate);
    }

    public function testSentMetaFormat(): void
    {
        $today = '2026-04-13';
        $metaValue = 'sent:' . $today;

        $this->assertSame('sent:2026-04-13', $metaValue);
        $this->assertFalse(str_starts_with($metaValue, 'scheduled:'));
    }

    public function testSubjectTokenInterpolation(): void
    {
        $subject = Formatter::interpolate(
            'How was your purchase? Leave a review',
            ['first_name' => 'Jan', 'order_number' => '1234'],
        );

        // No tokens in default subject, so it stays the same.
        $this->assertSame('How was your purchase? Leave a review', $subject);
    }

    public function testIntroTokenInterpolation(): void
    {
        $intro = Formatter::interpolate(
            'Hi {first_name}, thank you for your recent purchase.',
            ['first_name' => 'Jan'],
        );

        $this->assertSame('Hi Jan, thank you for your recent purchase.', $intro);
    }

    public function testDelayDaysMinimumIsOne(): void
    {
        $configured = 0;
        $delayDays = max(1, $configured);
        $this->assertSame(1, $delayDays);
    }
}
