<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Util;

use PHPUnit\Framework\TestCase;
use Polski\Util\SafeHttp;

/**
 * A retry that repeats a write, or that repeats a 4xx, is worse than no retry.
 * These pin down which failures are repeated and which are not.
 */
final class SafeHttpTest extends TestCase
{
    public function testATimeoutIsWorthRepeating(): void
    {
        self::assertTrue(SafeHttp::isTransientFailure(new \WP_Error('http_request_failed', 'cURL error 28')));
    }

    public function testTheServerSayingItBrokeIsWorthRepeating(): void
    {
        foreach ([500, 502, 503, 504] as $code) {
            self::assertTrue(
                SafeHttp::isTransientFailure(['response' => ['code' => $code]]),
                sprintf('HTTP %d should be retried', $code),
            );
        }
    }

    public function testAnAnswerIsNotRepeated(): void
    {
        // 404 and 400 are answers. So is 429, which means slow down: repeating
        // it immediately is the opposite of what the service asked for.
        foreach ([200, 204, 301, 400, 401, 403, 404, 422, 429] as $code) {
            self::assertFalse(
                SafeHttp::isTransientFailure(['response' => ['code' => $code]]),
                sprintf('HTTP %d should not be retried', $code),
            );
        }
    }

    public function testTheSecondFailureIsTheOneReported(): void
    {
        $GLOBALS['polski_http_calls'] = [];
        $GLOBALS['polski_http_queue'] = [
            new \WP_Error('http_request_failed', 'first attempt died'),
            ['response' => ['code' => 500], 'body' => 'register is down'],
        ];

        $result = SafeHttp::get('https://example.test/lookup');

        self::assertSame(2, count($GLOBALS['polski_http_calls']), 'the call is made twice');
        self::assertIsArray($result);
        self::assertSame('register is down', $result['body']);
    }

    public function testASuccessfulRetryWins(): void
    {
        $GLOBALS['polski_http_calls'] = [];
        $GLOBALS['polski_http_queue'] = [
            new \WP_Error('http_request_failed', 'cURL error 28'),
            ['response' => ['code' => 200], 'body' => 'company data'],
        ];

        $result = SafeHttp::get('https://example.test/lookup');

        self::assertIsArray($result);
        self::assertSame('company data', $result['body']);
    }

    public function testAFirstSuccessIsNotRepeated(): void
    {
        $GLOBALS['polski_http_calls'] = [];
        $GLOBALS['polski_http_queue'] = [
            ['response' => ['code' => 200], 'body' => 'company data'],
            ['response' => ['code' => 200], 'body' => 'should never be reached'],
        ];

        $result = SafeHttp::get('https://example.test/lookup');

        self::assertSame(1, count($GLOBALS['polski_http_calls']), 'one answer is enough');
        self::assertIsArray($result);
        self::assertSame('company data', $result['body']);
    }
}
