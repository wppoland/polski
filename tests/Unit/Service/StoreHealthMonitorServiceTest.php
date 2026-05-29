<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use Polski\Service\StoreHealthMonitorService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests for StoreHealthMonitorService decision logic.
 *
 * The sensor-folding, threshold, alert-cooldown and counter-pruning logic is
 * pure and depends only on options (fed through the bootstrap get_option stub)
 * and the clock, so it is exercised directly via reflection without a full
 * WordPress or WooCommerce load.
 */
final class StoreHealthMonitorServiceTest extends TestCase
{
    private StoreHealthMonitorService $service;

    protected function setUp(): void
    {
        $this->service = new StoreHealthMonitorService();
        $GLOBALS['polski_test_options'] = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['polski_test_options'] = [];
    }

    /**
     * @param array<int, mixed> $args
     */
    private function invoke(string $method, array $args = []): mixed
    {
        $ref = new ReflectionMethod($this->service, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($this->service, $args);
    }

    private function currentHourKey(): string
    {
        return gmdate('Y-m-d-H');
    }

    private function previousHourKey(): string
    {
        return gmdate('Y-m-d-H', time() - HOUR_IN_SECONDS);
    }

    public function testSeverityRankOrdering(): void
    {
        $this->assertSame(2, $this->invoke('severityRank', ['down']));
        $this->assertSame(1, $this->invoke('severityRank', ['degraded']));
        $this->assertSame(0, $this->invoke('severityRank', ['ok']));
        $this->assertSame(0, $this->invoke('severityRank', ['anything-else']));
    }

    public function testFatalSensorOkWhenNoneRecorded(): void
    {
        $result = $this->invoke('evaluateFatal');
        $this->assertSame('ok', $result['status']);
    }

    public function testFatalSensorDownWithinWindow(): void
    {
        $GLOBALS['polski_test_options']['polski_store_health_last_fatal'] = [
            'message' => 'Call to undefined function foo()',
            'file' => '/var/www/wp-content/plugins/x/x.php',
            'line' => 42,
            'at' => time(),
        ];

        $result = $this->invoke('evaluateFatal');
        $this->assertSame('down', $result['status']);
        $this->assertStringContainsString('x.php', $result['detail']);
    }

    public function testFatalSensorRecoversAfterWindow(): void
    {
        $GLOBALS['polski_test_options']['polski_store_health_last_fatal'] = [
            'message' => 'old error',
            'file' => 'x.php',
            'line' => 1,
            'at' => time() - 1000,
        ];

        $result = $this->invoke('evaluateFatal');
        $this->assertSame('ok', $result['status']);
    }

    public function testPaymentsInsufficientSampleStaysOk(): void
    {
        $GLOBALS['polski_test_options']['polski_store_health_counters'] = [
            'ok' => [$this->currentHourKey() => 2],
            'failed' => [$this->currentHourKey() => 1],
        ];

        $result = $this->invoke('evaluatePayments');
        $this->assertSame('ok', $result['status']);
    }

    public function testPaymentsDegradedAtThreshold(): void
    {
        $hour = $this->currentHourKey();
        $GLOBALS['polski_test_options']['polski_store_health_counters'] = [
            'ok' => [$hour => 10],
            'failed' => [$hour => 5],
        ];

        // 5/15 = 33% which is >= the 30% default threshold but below 1.5x (45%).
        $result = $this->invoke('evaluatePayments');
        $this->assertSame('degraded', $result['status']);
    }

    public function testPaymentsDownWhenFarOverThreshold(): void
    {
        $hour = $this->currentHourKey();
        $GLOBALS['polski_test_options']['polski_store_health_counters'] = [
            'ok' => [$hour => 1],
            'failed' => [$hour => 9],
        ];

        // 9/10 = 90% which is past 1.5x the 30% threshold.
        $result = $this->invoke('evaluatePayments');
        $this->assertSame('down', $result['status']);
    }

    public function testPaymentsCountsBothRecentHours(): void
    {
        $GLOBALS['polski_test_options']['polski_store_health_counters'] = [
            'ok' => [$this->previousHourKey() => 8, $this->currentHourKey() => 2],
            'failed' => [$this->currentHourKey() => 0],
        ];

        $result = $this->invoke('evaluatePayments');
        $this->assertSame('ok', $result['status']);
        $this->assertStringContainsString('0 of 10', $result['detail']);
    }

    public function testSumRecentIgnoresStaleHours(): void
    {
        $bucket = [
            $this->currentHourKey() => 4,
            $this->previousHourKey() => 3,
            '2000-01-01-00' => 999,
        ];

        $this->assertSame(7, $this->invoke('sumRecent', [$bucket]));
    }

    public function testPruneCountersDropsOldKeys(): void
    {
        $counters = [
            'ok' => [$this->currentHourKey() => 5, '2000-01-01-00' => 1],
            'failed' => [$this->previousHourKey() => 2, '1999-12-31-23' => 7],
        ];

        $pruned = $this->invoke('pruneCounters', [$counters]);

        $this->assertArrayHasKey($this->currentHourKey(), $pruned['ok']);
        $this->assertArrayNotHasKey('2000-01-01-00', $pruned['ok']);
        $this->assertArrayHasKey($this->previousHourKey(), $pruned['failed']);
        $this->assertArrayNotHasKey('1999-12-31-23', $pruned['failed']);
    }

    public function testShouldNotAlertWhenOk(): void
    {
        $this->assertFalse($this->invoke('shouldAlert', ['ok', 'down', '']));
    }

    public function testShouldAlertOnWorsening(): void
    {
        $this->assertTrue($this->invoke('shouldAlert', ['down', 'degraded', gmdate('Y-m-d H:i:s')]));
    }

    public function testShouldAlertWhenNoPreviousAlert(): void
    {
        $this->assertTrue($this->invoke('shouldAlert', ['degraded', 'degraded', '']));
    }

    public function testShouldNotReAlertWithinCooldown(): void
    {
        $recent = gmdate('Y-m-d H:i:s', time() - 60);
        $this->assertFalse($this->invoke('shouldAlert', ['degraded', 'degraded', $recent]));
    }

    public function testShouldReAlertAfterCooldown(): void
    {
        $old = gmdate('Y-m-d H:i:s', time() - (61 * MINUTE_IN_SECONDS));
        $this->assertTrue($this->invoke('shouldAlert', ['degraded', 'degraded', $old]));
    }
}
