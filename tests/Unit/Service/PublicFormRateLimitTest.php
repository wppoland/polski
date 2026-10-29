<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Service\DSAService;
use Polski\Service\NipLookupService;
use Polski\Util\TemplateLoader;

/**
 * Without a persistent object cache a transient reads back from the options
 * table as a string. The DSA limiter tested is_int() on it, so the count never
 * rose past one and the limit never applied on an ordinary host.
 */
final class PublicFormRateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['polski_test_transients'] = [];
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
    }

    private function call(object $service, string $method): bool
    {
        return (bool) (new \ReflectionMethod($service, $method))->invoke($service);
    }

    public function testDsaLimitHoldsWhenTheCountReadsBackAsAString(): void
    {
        $service = (new \ReflectionClass(DSAService::class))->newInstanceWithoutConstructor();
        $key = 'polski_dsa_rl_' . md5('203.0.113.7');
        set_transient($key, '5', HOUR_IN_SECONDS);

        self::assertTrue($this->call($service, 'isRateLimited'));
    }

    public function testNipLookupStopsAfterTenPerIp(): void
    {
        $service = (new \ReflectionClass(NipLookupService::class))->newInstanceWithoutConstructor();

        for ($i = 0; $i < 10; $i++) {
            self::assertFalse($this->call($service, 'lookupLimitReached'), 'lookup ' . ($i + 1));
            // Same shape as the options table returns.
            $key = 'polski_nip_rl_' . md5('203.0.113.7');
            set_transient($key, (string) get_transient($key), HOUR_IN_SECONDS);
        }

        self::assertTrue($this->call($service, 'lookupLimitReached'));
    }
}
