<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use Polski\AI\AnnexTranslator;

final class AnnexTranslatorTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['polski_test_options'] = [];
        $GLOBALS['polski_test_transients'] = [];
    }

    public function testReturnsNullWhenFeatureDisabled(): void
    {
        $GLOBALS['polski_test_options']['polski_ai_features_enabled'] = 'no';

        $service = new AnnexTranslator();

        self::assertNull($service->translate('<p>treść formularza</p>', 'cs'));
    }

    public function testReturnsNullForSourceLocale(): void
    {
        $GLOBALS['polski_test_options']['polski_ai_features_enabled'] = 'yes';

        $service = new AnnexTranslator();

        self::assertNull($service->translate('<p>treść formularza</p>', 'pl'));
        self::assertNull($service->translate('<p>treść formularza</p>', 'pl_PL'));
    }

    public function testReturnsNullWhenAiClientUnavailable(): void
    {
        $GLOBALS['polski_test_options']['polski_ai_features_enabled'] = 'yes';

        $service = new AnnexTranslator();

        // function_exists('wp_ai_client_prompt') is false in unit tests.
        self::assertNull($service->translate('<p>treść formularza</p>', 'cs'));
    }

    public function testNormalisesLocaleVariantsToBaseCode(): void
    {
        $GLOBALS['polski_test_options']['polski_ai_features_enabled'] = 'yes';

        $service = new AnnexTranslator();

        // de, de_DE, de-DE all normalise to "de" and bypass the early-pl shortcut.
        // We can not verify the AI call here (it's unavailable in unit tests), but
        // we can verify the method does not error on common locale shapes.
        self::assertNull($service->translate('<p>x</p>', 'de_DE'));
        self::assertNull($service->translate('<p>x</p>', 'DE'));
        self::assertNull($service->translate('<p>x</p>', 'de-AT'));
    }

    public function testEmptyOrInvalidLocaleReturnsNull(): void
    {
        $GLOBALS['polski_test_options']['polski_ai_features_enabled'] = 'yes';

        $service = new AnnexTranslator();

        self::assertNull($service->translate('<p>x</p>', ''));
        self::assertNull($service->translate('<p>x</p>', '123'));
        self::assertNull($service->translate('<p>x</p>', 'not-a-locale-1234'));
    }
}
