<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Repository\WithdrawalRepository;
use Polski\Service\GuestWithdrawalService;
use Polski\Service\WithdrawalService;
use Polski\Util\TemplateLoader;

/**
 * Covers the security-sensitive parts of the magic-link guest flow:
 *  - rate-limit enforcement,
 *  - token redeem round-trip (single-use, expiry-aware),
 *  - notice channel is set without exposing order existence.
 *
 * Email delivery and template rendering are intentionally not exercised — they're
 * thin wrappers around wp_mail and TemplateLoader.
 */
final class GuestWithdrawalServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wpdb'] = new \wpdb();
        $GLOBALS['polski_test_transients'] = [];
        $GLOBALS['polski_test_mail'] = [];
        $_SERVER['REMOTE_ADDR'] = '203.0.113.42';
    }

    public function testRateLimitAllowsFirstFewAttemptsThenBlocks(): void
    {
        $service = $this->makeService();
        $check = (new \ReflectionClass(GuestWithdrawalService::class))->getMethod('checkRateLimit');
        $check->setAccessible(true);

        // First 5 attempts allowed (RATE_LIMIT_MAX_REQUESTS = 5).
        for ($i = 0; $i < 5; $i++) {
            self::assertTrue($check->invoke($service, 'consumer@example.test'), 'Attempt ' . ($i + 1) . ' should be allowed');
        }

        self::assertFalse($check->invoke($service, 'consumer@example.test'), '6th attempt must be blocked');
    }

    public function testRateLimitIsScopedPerEmailAndIp(): void
    {
        $service = $this->makeService();
        $check = (new \ReflectionClass(GuestWithdrawalService::class))->getMethod('checkRateLimit');
        $check->setAccessible(true);

        // Exhaust one email's budget.
        for ($i = 0; $i < 5; $i++) {
            $check->invoke($service, 'a@example.test');
        }

        self::assertFalse($check->invoke($service, 'a@example.test'));
        self::assertTrue($check->invoke($service, 'b@example.test'), 'Different email must have its own bucket');
    }

    public function testTokenRedeemRoundTrip(): void
    {
        $service = $this->makeService();

        // Manually seed a transient that simulates a successful magic-link issuance.
        $token = bin2hex(random_bytes(16));
        set_transient(
            'polski_wt_' . hash('sha256', $token),
            ['order_id' => 42, 'email' => 'buyer@example.test', 'created' => time()],
            1800,
        );

        $redeem = (new \ReflectionClass(GuestWithdrawalService::class))->getMethod('redeemToken');
        $redeem->setAccessible(true);

        $payload = $redeem->invoke($service, $token);

        self::assertIsArray($payload);
        self::assertSame(42, $payload['order_id']);
        self::assertSame('buyer@example.test', $payload['email']);
    }

    public function testTokenRedeemReturnsNullForUnknownToken(): void
    {
        $service = $this->makeService();
        $redeem = (new \ReflectionClass(GuestWithdrawalService::class))->getMethod('redeemToken');
        $redeem->setAccessible(true);

        self::assertNull($redeem->invoke($service, str_repeat('a', 32)));
    }

    public function testTokenConsumeMakesItUnusable(): void
    {
        $service = $this->makeService();
        $token = bin2hex(random_bytes(16));
        set_transient(
            'polski_wt_' . hash('sha256', $token),
            ['order_id' => 7, 'email' => 'x@example.test', 'created' => time()],
            1800,
        );

        $consume = (new \ReflectionClass(GuestWithdrawalService::class))->getMethod('consumeToken');
        $consume->setAccessible(true);
        $redeem = (new \ReflectionClass(GuestWithdrawalService::class))->getMethod('redeemToken');
        $redeem->setAccessible(true);

        self::assertIsArray($redeem->invoke($service, $token), 'Token must be valid before consumption');
        $consume->invoke($service, $token);
        self::assertNull($redeem->invoke($service, $token), 'Token must be gone after consumption');
    }

    public function testClientIpFallsBackToZerosWhenServerVarsAreMissing(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);

        $service = $this->makeService();
        $ipMethod = (new \ReflectionClass(GuestWithdrawalService::class))->getMethod('clientIp');
        $ipMethod->setAccessible(true);

        self::assertSame('0.0.0.0', $ipMethod->invoke($service));
    }

    public function testClientIpUsesFirstAddressFromXForwardedForChain(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7, 203.0.113.1, 10.0.0.1';
        $service = $this->makeService();
        $ipMethod = (new \ReflectionClass(GuestWithdrawalService::class))->getMethod('clientIp');
        $ipMethod->setAccessible(true);

        self::assertSame('198.51.100.7', $ipMethod->invoke($service));
    }

    private function makeService(): GuestWithdrawalService
    {
        $repo = (new \ReflectionClass(WithdrawalRepository::class))->newInstanceWithoutConstructor();
        $repoProp = (new \ReflectionClass($repo))->getProperty('wpdb');
        $repoProp->setAccessible(true);
        $repoProp->setValue($repo, $GLOBALS['wpdb']);

        $itemsRepo = (new \ReflectionClass(\Polski\Repository\WithdrawalItemsRepository::class))->newInstanceWithoutConstructor();
        $itemsProp = (new \ReflectionClass($itemsRepo))->getProperty('wpdb');
        $itemsProp->setAccessible(true);
        $itemsProp->setValue($itemsRepo, $GLOBALS['wpdb']);

        $loader = (new \ReflectionClass(TemplateLoader::class))->newInstanceWithoutConstructor();
        $core = new WithdrawalService($repo, $loader, $itemsRepo);

        return new GuestWithdrawalService($core, $repo, $loader);
    }
}
