<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Service\DoubleOptInService;
use Polski\Service\OrderExportService;

/**
 * Pin the paging of the two queries that used to load their whole result set.
 *
 * The stubs in tests/bootstrap.php slice their fixtures the way WP_User_Query
 * and wc_get_orders do, so a caller that drops the batch arguments gets
 * everything back in a single query and these assertions fail.
 */
final class UnboundedQueryBatchingTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['polski_test_orders'] = [];
        $GLOBALS['polski_test_order_queries'] = [];
        $GLOBALS['polski_test_users'] = [];
        $GLOBALS['polski_test_user_queries'] = [];
        $GLOBALS['polski_test_customer_order_counts'] = [];
        $GLOBALS['polski_test_deleted_users'] = [];
    }

    public function testOrderExportPagesThroughOrdersAndKeepsTheirOrder(): void
    {
        $orders = [];

        for ($i = 1; $i <= 450; ++$i) {
            $orders[] = new FakeExportOrder($i);
        }

        $GLOBALS['polski_test_orders'] = $orders;

        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);

        (new OrderExportService())->writeCsv($stream, ['order_id'], ['wc-completed'], '2026-01-01', '2026-01-31');

        rewind($stream);
        $lines = array_values(array_filter(explode("\n", (string) stream_get_contents($stream))));
        fclose($stream);

        $queries = $GLOBALS['polski_test_order_queries'];

        self::assertGreaterThan(1, count($queries), 'The export must ask for more than one page.');

        foreach ($queries as $index => $args) {
            self::assertGreaterThan(0, $args['limit'], 'Every export query must carry a positive limit.');
            self::assertSame($index + 1, $args['page'], 'Export pages must be requested in sequence.');
        }

        // Header row plus every order, still in the order the query returned them.
        self::assertCount(451, $lines);
        self::assertSame('1', $lines[1]);
        self::assertSame('450', $lines[450]);
    }

    public function testDoubleOptInCleanupPagesPastAccountsItKeeps(): void
    {
        $GLOBALS['polski_test_users'] = range(1, 450);

        // Two accounts have orders, so they survive and the offset must step
        // over them instead of re-reading them forever.
        $GLOBALS['polski_test_customer_order_counts'] = [7 => 3, 300 => 1];

        $service = new DoubleOptInService();
        $service->cleanupUnactivated();

        $queries = $GLOBALS['polski_test_user_queries'];

        self::assertGreaterThan(1, count($queries), 'The cleanup must ask for more than one batch.');

        foreach ($queries as $args) {
            self::assertGreaterThan(0, $args['number'], 'Every cleanup query must carry a positive number.');
            self::assertArrayHasKey('offset', $args);
        }

        self::assertCount(448, $GLOBALS['polski_test_deleted_users']);
        self::assertNotContains(7, $GLOBALS['polski_test_deleted_users']);
        self::assertNotContains(300, $GLOBALS['polski_test_deleted_users']);
    }
}

final class FakeExportOrder extends \WC_Order
{
    public function __construct(private int $id)
    {
    }

    public function get_id(): int
    {
        return $this->id;
    }
}
