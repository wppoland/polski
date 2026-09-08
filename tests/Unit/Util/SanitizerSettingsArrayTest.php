<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Util;

use PHPUnit\Framework\TestCase;
use Polski\Util\Sanitizer;

/**
 * A PUT to /polski/v1/settings/<group> used to fill every key the request left
 * out with the packaged default. For the text keys that default is a resolved
 * __() string, so one partial write stamped the request's locale into the
 * option and made the wording untranslatable again, which is the whole defect
 * the empty defaults exist to prevent.
 */
final class SanitizerSettingsArrayTest extends TestCase
{
    public function testAnOmittedKeyKeepsWhatIsStoredRatherThanTheDefault(): void
    {
        $result = Sanitizer::settingsArray(
            ['enabled' => true],
            ['enabled' => false, 'button_text' => 'Request a quote'],
            ['enabled' => false, 'button_text' => ''],
        );

        self::assertSame('', $result['button_text']);
        self::assertTrue($result['enabled']);
    }

    public function testAnOmittedKeyStillFallsBackToTheDefaultWhenNothingIsStored(): void
    {
        $result = Sanitizer::settingsArray([], ['count' => 3], []);

        self::assertSame(3, $result['count']);
    }

    public function testAMerchantsOwnWordingSurvivesAPartialWrite(): void
    {
        $result = Sanitizer::settingsArray(
            ['enabled' => true],
            ['enabled' => false, 'button_text' => 'Request a quote'],
            ['button_text' => 'Poproś o wycenę'],
        );

        self::assertSame('Poproś o wycenę', $result['button_text']);
    }

    public function testTypesStillComeFromTheDefaultsNotFromWhatIsStored(): void
    {
        $result = Sanitizer::settingsArray(
            ['count' => '7'],
            ['count' => 3],
            ['count' => 'not a number'],
        );

        self::assertSame(7, $result['count']);
    }
}
