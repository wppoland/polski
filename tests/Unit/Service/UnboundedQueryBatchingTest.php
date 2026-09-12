<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Service\DoubleOptInService;
use Polski\Service\OrderExportService;
use Polski\Service\StockExportService;

/**
 * Pin the two queries that used to load their whole result set, and the paging
 * that replaced them.
 *
 * The stubs in tests/bootstrap.php hold their fixtures as LIVE sets: a test can
 * add or remove a row between queries, which is what a checkout, a trashed
 * order or a refused user delete do to a store while these loops run. Offset
 * and page counters count positions in that moving set, so a caller that goes
 * back to them repeats a row, skips a row, or never finishes, and these
 * assertions say so.
 */
final class UnboundedQueryBatchingTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['polski_test_orders'] = [];
        $GLOBALS['polski_test_order_queries'] = [];
        $GLOBALS['polski_test_order_mutation'] = null;
        $GLOBALS['polski_test_users'] = [];
        $GLOBALS['polski_test_user_queries'] = [];
        $GLOBALS['polski_test_customer_order_counts'] = [];
        $GLOBALS['polski_test_deleted_users'] = [];
        $GLOBALS['polski_test_undeletable_users'] = [];
        $GLOBALS['polski_test_products'] = [];
        $GLOBALS['polski_test_product_queries'] = [];
    }

    /**
     * Seed more products than one batch holds, newest first, with names that
     * sort the other way round. A hydration that does not really ask by ID gets
     * the newest products back, which these names make impossible to miss.
     */
    private function seedProducts(int $count): void
    {
        $products = [];

        for ($id = $count; $id >= 1; --$id) {
            $products[] = new FakeStockProduct($id, sprintf('P%04d', $id), $id);
        }

        $GLOBALS['polski_test_products'] = $products;
    }

    /**
     * @return list<int>
     */
    private function stockExportIds(bool $managedOnly = false, string $stockCompare = '', int $stockValue = 0): array
    {
        // eachProduct() is the whole export minus the headers and the file, and
        // it is private on purpose: the test reaches it rather than the service
        // growing a public method for the test's benefit.
        $method = new \ReflectionMethod(StockExportService::class, 'eachProduct');
        $method->setAccessible(true);

        $ids = [];

        /** @var iterable<\WC_Product> $products */
        $products = $method->invoke(new StockExportService(), $managedOnly, false, $stockCompare, $stockValue);

        foreach ($products as $product) {
            $ids[] = (int) $product->get_id();
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function exportIds(): array
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);

        $service = new OrderExportService();
        $ids = $service->collectOrderIds(['wc-completed'], '2026-01-01', '2026-01-31');
        $service->writeCsv($stream, ['order_id'], $ids);

        rewind($stream);
        $lines = array_values(array_filter(explode("\n", (string) stream_get_contents($stream)), 'strlen'));
        fclose($stream);

        // Drop the header row, keep the ID column.
        array_shift($lines);

        return array_map(static fn (string $line): int => (int) trim($line, "\r"), $lines);
    }

    /**
     * Run $change once the export has read its first batch of order rows.
     *
     * Keyed off the result, not off a query number, so it lands at the same
     * point in the export whether the rows come from a page counter or from a
     * list of IDs fixed up front.
     */
    private function mutateAfterTheFirstRowsAreRead(callable $change): void
    {
        $fired = false;

        $GLOBALS['polski_test_order_mutation'] = static function (array $args, array $result) use ($change, &$fired): void {
            if ($fired || ($args['return'] ?? 'objects') === 'ids' || $result === []) {
                return;
            }

            $fired = true;
            $change();
        };
    }

    /**
     * Newest first, the way the query returns them.
     */
    private function seedOrders(int $count): void
    {
        $orders = [];

        for ($id = $count; $id >= 1; --$id) {
            $orders[] = new FakeExportOrder($id);
        }

        $GLOBALS['polski_test_orders'] = $orders;
    }

    public function testExportReadsIdsFirstThenHydratesInBatches(): void
    {
        $this->seedOrders(450);

        self::assertSame(range(450, 1), $this->exportIds());

        $queries = $GLOBALS['polski_test_order_queries'];

        self::assertGreaterThan(1, count($queries), 'The export must hydrate in more than one batch.');

        $snapshot = array_shift($queries);
        self::assertSame('ids', $snapshot['return'] ?? '', 'The first query must ask for IDs, not hydrated orders.');

        foreach ($queries as $args) {
            self::assertArrayHasKey('post__in', $args, 'Orders must be hydrated by ID, not by page.');
            self::assertLessThanOrEqual(200, count($args['post__in']), 'A batch must stay bounded.');
            self::assertArrayNotHasKey('page', $args, 'A page counter cannot survive a write mid-export.');
            self::assertArrayNotHasKey('offset', $args, 'An offset cannot survive a write mid-export.');
        }
    }

    public function testAnOrderPlacedDuringTheExportRepeatsNothing(): void
    {
        $this->seedOrders(450);

        // A checkout completes once the export has read its first rows. With the
        // defaults (processing plus completed, dateTo today) an ordinary order
        // lands at the top of a date DESC result set and pushes every row one
        // place down, so the next page hands back the last row of the one before.
        $this->mutateAfterTheFirstRowsAreRead(static function (): void {
            array_unshift($GLOBALS['polski_test_orders'], new FakeExportOrder(451));
        });

        $written = $this->exportIds();

        self::assertSame(range(450, 1), $written, 'The CSV must be exactly the orders the export started with.');
        self::assertSame(count($written), count(array_unique($written)), 'No order may appear twice.');
        self::assertNotContains(451, $written, 'An order placed after the export started is not in its range.');
    }

    public function testTrashingAnOrderAlreadyReadCostsNoOtherOrderItsRow(): void
    {
        $this->seedOrders(450);

        // Order 400 is trashed once the export has read its first rows, so its
        // own row is already written. It leaves the result set behind the
        // export, and a page counter then pulls every later row one place
        // forward and loses whichever row falls on the next page boundary.
        $this->mutateAfterTheFirstRowsAreRead(static function (): void {
            $GLOBALS['polski_test_orders'] = array_values(array_filter(
                $GLOBALS['polski_test_orders'],
                static fn ($order): bool => (int) $order->get_id() !== 400,
            ));
        });

        $written = $this->exportIds();

        self::assertSame(range(450, 1), $written, 'No order may lose its row to a neighbour being trashed.');
        self::assertContains(250, $written, 'The row on the first batch boundary must still be written.');
    }

    public function testCleanupExcludesAccountsItKeepsInsteadOfCountingPositions(): void
    {
        $GLOBALS['polski_test_users'] = range(1, 450);

        // Two accounts have orders, so they stay in the result set and every
        // later query has to step over them.
        $GLOBALS['polski_test_customer_order_counts'] = [7 => 3, 300 => 1];

        (new DoubleOptInService())->cleanupUnactivated();

        $queries = $GLOBALS['polski_test_user_queries'];

        self::assertGreaterThan(1, count($queries), 'The cleanup must ask for more than one batch.');

        foreach ($queries as $args) {
            self::assertGreaterThan(0, $args['number'], 'Every cleanup query must carry a positive number.');
            self::assertSame(0, (int) ($args['offset'] ?? 0), 'An offset counts positions in a set the loop is shrinking.');
        }

        self::assertCount(448, $GLOBALS['polski_test_deleted_users']);
        self::assertNotContains(7, $GLOBALS['polski_test_deleted_users']);
        self::assertNotContains(300, $GLOBALS['polski_test_deleted_users']);
    }

    public function testCleanupFinishesWhenWordPressRefusesToDeleteAnAccount(): void
    {
        $GLOBALS['polski_test_users'] = range(1, 400);

        // wp_delete_user() returns false for a whole batch worth of accounts,
        // which is what a filter that protects a role looks like from here.
        // They have no orders, so a loop that only steps past the accounts it
        // decided to keep never steps at all: it re-reads the same first batch
        // and re-attempts the same deletes forever. The get_users() stub throws
        // once the query count says the loop stopped making progress.
        $GLOBALS['polski_test_undeletable_users'] = range(1, 200);

        (new DoubleOptInService())->cleanupUnactivated();

        self::assertSame(range(201, 400), $GLOBALS['polski_test_deleted_users']);
        self::assertLessThanOrEqual(5, count($GLOBALS['polski_test_user_queries']));
    }

    public function testStockExportHydratesTheBatchItAskedForNotTheNewestProducts(): void
    {
        // 450 products, so the run needs three batches of 200. One batch cannot
        // show the difference: a hydration that asks by an argument the product
        // query drops still returns the right rows while everything fits in one.
        $this->seedProducts(450);

        $written = $this->stockExportIds();

        self::assertSame(range(1, 450), $written, 'Every batch must be hydrated from its own IDs, in title order.');
        self::assertSame(count($written), count(array_unique($written)), 'No product may appear twice.');

        $queries = $GLOBALS['polski_test_product_queries'];
        $idQuery = array_shift($queries);

        self::assertSame('ids', $idQuery['return'] ?? '', 'The first query must ask for IDs, not hydrated products.');
        self::assertCount(3, $queries, 'A catalogue of 450 is three batches of 200.');

        foreach ($queries as $args) {
            self::assertArrayHasKey('include', $args, "A batch must be hydrated with 'include'.");
            self::assertArrayNotHasKey(
                'post__in',
                $args,
                "'post__in' is not a product query var: WooCommerce overwrites it with the empty 'include' default and drops it.",
            );
            self::assertLessThanOrEqual(200, count($args['include']), 'A batch must stay bounded.');
        }
    }

    public function testStockExportFiltersOnTheStockOfTheProductsItAskedFor(): void
    {
        // Stock equals the ID, so the rows that pass "10 or fewer" all sit in
        // the first batch. Hydrating the newest 200 instead would hand the
        // filter products 450 down to 251 three times over and write no row.
        $this->seedProducts(450);

        self::assertSame(range(1, 10), $this->stockExportIds(false, 'lte', 10));
    }
}

final class FakeStockProduct extends \WC_Product
{
    public function __construct(private int $id, private string $name, private int $stock)
    {
    }

    public function get_id(): int
    {
        return $this->id;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function managing_stock(): bool
    {
        return true;
    }

    public function get_stock_quantity(): int
    {
        return $this->stock;
    }

    public function is_type(string|array $type): bool
    {
        return false;
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
