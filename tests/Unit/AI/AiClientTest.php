<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use Polski\AI\AiClient;

final class AiClientTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset any stub state between tests.
        $GLOBALS['polski_test_ai_stub'] = null;
    }

    public function testIsAvailableReturnsFalseWhenFunctionMissing(): void
    {
        // Default bootstrap state: function does not exist.
        self::assertFalse(AiClient::isAvailableForText());
    }

    public function testClassifyJsonShortCircuitsWhenUnavailable(): void
    {
        self::assertNull(
            AiClient::classifyJson(
                'system instruction',
                'arbitrary prompt',
                ['type' => 'object'],
            )
        );
    }
}
