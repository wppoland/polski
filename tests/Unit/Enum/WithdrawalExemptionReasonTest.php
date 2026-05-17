<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use Polski\Enum\WithdrawalExemptionReason;

final class WithdrawalExemptionReasonTest extends TestCase
{
    public function testAllArt38CasesArePresent(): void
    {
        $values = array_map(static fn (WithdrawalExemptionReason $c) => $c->value, WithdrawalExemptionReason::cases());

        self::assertContains('art38_3', $values, 'Art. 38 pkt 3 (custom-made) missing');
        self::assertContains('art38_4', $values, 'Art. 38 pkt 4 (perishable) missing');
        self::assertContains('art38_5', $values, 'Art. 38 pkt 5 (sealed) missing');
        self::assertContains('art38_6', $values, 'Art. 38 pkt 6 (inseparable) missing');
        self::assertContains('art38_7', $values, 'Art. 38 pkt 7 (alcohol) missing');
        self::assertContains('art38_9', $values, 'Art. 38 pkt 9 (sealed audio/video) missing');
        self::assertContains('art38_13', $values, 'Art. 38 pkt 13 (digital content) missing');
        self::assertContains('custom', $values, 'Custom (free-text) missing');
    }

    public function testEveryCaseHasLabelAndShortLabel(): void
    {
        foreach (WithdrawalExemptionReason::cases() as $case) {
            self::assertNotSame('', $case->label(), $case->value . ' has empty label');
            self::assertNotSame('', $case->shortLabel(), $case->value . ' has empty short label');
        }
    }

    public function testChoicesReturnsArrayShapeSuitableForDropdowns(): void
    {
        $choices = WithdrawalExemptionReason::choices();

        self::assertNotEmpty($choices);
        foreach ($choices as $choice) {
            self::assertArrayHasKey('value', $choice);
            self::assertArrayHasKey('label', $choice);
            self::assertNotSame('', $choice['value']);
            self::assertNotSame('', $choice['label']);
        }
    }

    public function testTryFromAcceptsKnownValues(): void
    {
        self::assertSame(WithdrawalExemptionReason::Perishable, WithdrawalExemptionReason::tryFrom('art38_4'));
        self::assertSame(WithdrawalExemptionReason::CustomMade, WithdrawalExemptionReason::tryFrom('art38_3'));
    }

    public function testTryFromReturnsNullForUnknown(): void
    {
        self::assertNull(WithdrawalExemptionReason::tryFrom('art38_999'));
    }
}
