<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Repository\WithdrawalItemsRepository;
use Polski\Repository\WithdrawalRepository;
use Polski\Service\WithdrawalService;
use Polski\Util\TemplateLoader;

/**
 * Covers the private resolveSelection() — partial refund math, qty capping,
 * pro-rata totals, and the "entire remaining order" fallback. WithdrawalService
 * is final; we exercise it via Reflection, with a wpdb stub that reports
 * "nothing previously withdrawn" so the remaining-items computation is
 * deterministic from the order line items alone.
 */
final class WithdrawalServiceSelectionTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wpdb'] = new \wpdb();
    }

    public function testNullSelectionTakesEveryRemainingItemAtFullRemainingQty(): void
    {
        $items = [
            $this->makeItem(orderItemId: 11, productId: 100, qty: 4, total: 40.0, tax: 9.2),
            $this->makeItem(orderItemId: 12, productId: 101, qty: 2, total: 40.0, tax: 9.2),
        ];

        $resolved = $this->resolveSelection($items, null);

        self::assertCount(2, $resolved);
        self::assertSame(4.0, $resolved[0]['quantity']);
        self::assertSame(2.0, $resolved[1]['quantity']);
    }

    public function testExplicitSelectionIsCappedAtRemainingQty(): void
    {
        $items = [$this->makeItem(orderItemId: 20, productId: 200, qty: 3, total: 90.0, tax: 0.0)];

        $resolved = $this->resolveSelection($items, [20 => 5.0]);

        self::assertCount(1, $resolved);
        self::assertSame(3.0, $resolved[0]['quantity'], 'Qty must be capped at remaining');
        self::assertEqualsWithDelta(90.0, $resolved[0]['line_total'], 0.01);
    }

    public function testZeroOrNegativeQtyIsDropped(): void
    {
        $items = [
            $this->makeItem(orderItemId: 30, productId: 300, qty: 1, total: 10.0),
            $this->makeItem(orderItemId: 31, productId: 301, qty: 1, total: 10.0),
        ];

        $resolved = $this->resolveSelection($items, [30 => 0, 31 => -2]);

        self::assertSame([], $resolved);
    }

    public function testUnknownOrderItemIdIsIgnored(): void
    {
        $items = [$this->makeItem(orderItemId: 40, productId: 400, qty: 1, total: 10.0)];

        $resolved = $this->resolveSelection($items, [999 => 1.0]);

        self::assertSame([], $resolved);
    }

    public function testPartialQtyProratesLineTotalAndTaxProportionally(): void
    {
        $items = [$this->makeItem(orderItemId: 50, productId: 500, qty: 4, total: 100.0, tax: 23.0)];

        $resolved = $this->resolveSelection($items, [50 => 3.0]);

        self::assertCount(1, $resolved);
        self::assertSame(3.0, $resolved[0]['quantity']);
        self::assertEqualsWithDelta(75.0, $resolved[0]['line_total'], 0.01);
        self::assertEqualsWithDelta(17.25, $resolved[0]['line_tax'], 0.01);
    }

    public function testNoRemainingItemsReturnsEmptyEvenWithSelection(): void
    {
        $resolved = $this->resolveSelection([], [99 => 1.0]);

        self::assertSame([], $resolved);
    }

    private function makeItem(int $orderItemId, int $productId, int $qty, float $total, float $tax = 0.0): array
    {
        // Order::get_items() returns [itemId => WC_Order_Item_Product]; emulate via simple object.
        return [
            'order_item_id' => $orderItemId,
            'product' => new class ($productId) extends \WC_Product {
                public function __construct(private readonly int $polskiId)
                {
                }
                public function get_id(): int
                {
                    return $this->polskiId;
                }
                public function get_sku(): string
                {
                    return 'SKU' . $this->polskiId;
                }
                public function is_type(string|array $type): bool
                {
                    return false;
                }
            },
            'qty' => $qty,
            'total' => $total,
            'tax' => $tax,
        ];
    }

    /**
     * @param list<array{order_item_id: int, product: \WC_Product, qty: int, total: float, tax: float}> $items
     * @param array<int, float|int>|null                                                                $selection
     * @return list<array<string, mixed>>
     */
    private function resolveSelection(array $items, ?array $selection): array
    {
        $service = $this->makeService();
        $order = $this->makeOrder($items);

        $method = (new \ReflectionClass(WithdrawalService::class))->getMethod('resolveSelection');
        $method->setAccessible(true);

        return $method->invoke($service, $order, $selection);
    }

    private function makeService(): WithdrawalService
    {
        $repo = (new \ReflectionClass(WithdrawalRepository::class))->newInstanceWithoutConstructor();
        $itemsRepo = (new \ReflectionClass(WithdrawalItemsRepository::class))->newInstanceWithoutConstructor();
        $loader = (new \ReflectionClass(TemplateLoader::class))->newInstanceWithoutConstructor();

        // Hydrate the typed readonly wpdb property on both repositories so that
        // global-style $wpdb access inside their methods does not blow up. The
        // global stub returns empty results, so partial-refund math is driven
        // entirely by the supplied order.
        $this->injectWpdb($repo, 'wpdb');
        $this->injectWpdb($itemsRepo, 'wpdb');

        return new WithdrawalService($repo, $loader, $itemsRepo);
    }

    private function injectWpdb(object $target, string $property): void
    {
        $refProp = (new \ReflectionClass($target))->getProperty($property);
        $refProp->setAccessible(true);
        $refProp->setValue($target, $GLOBALS['wpdb']);
    }

    /**
     * @param list<array{order_item_id: int, product: \WC_Product, qty: int, total: float, tax: float}> $items
     */
    private function makeOrder(array $items): \WC_Order
    {
        return new class ($items) extends \WC_Order {
            /** @var array<int, \WC_Order_Item_Product> */
            private array $polskiItems;

            /** @param list<array{order_item_id: int, product: \WC_Product, qty: int, total: float, tax: float}> $items */
            public function __construct(array $items)
            {
                $this->polskiItems = [];
                foreach ($items as $row) {
                    $this->polskiItems[$row['order_item_id']] = new class ($row['product'], $row['qty'], $row['total'], $row['tax']) extends \WC_Order_Item_Product {
                        public function __construct(
                            private readonly \WC_Product $polskiProduct,
                            private readonly int $polskiQty,
                            private readonly float $polskiTotal,
                            private readonly float $polskiTax,
                        ) {
                        }
                        public function get_product(): \WC_Product
                        {
                            return $this->polskiProduct;
                        }
                        public function get_quantity(): int
                        {
                            return $this->polskiQty;
                        }
                        public function get_total(): string
                        {
                            return (string) $this->polskiTotal;
                        }
                        public function get_total_tax(): string
                        {
                            return (string) $this->polskiTax;
                        }
                        public function get_name(): string
                        {
                            return 'Item ' . $this->polskiProduct->get_id();
                        }
                        public function get_variation_id(): int
                        {
                            return 0;
                        }
                        public function get_product_id(): int
                        {
                            return $this->polskiProduct->get_id();
                        }
                    };
                }
            }

            public function get_id(): int
            {
                return 1;
            }
            public function get_items(string $type = 'line_item'): array
            {
                return $this->polskiItems;
            }
            public function get_currency(): string
            {
                return 'PLN';
            }
        };
    }
}
