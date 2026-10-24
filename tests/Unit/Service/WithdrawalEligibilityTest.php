<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Repository\WithdrawalItemsRepository;
use Polski\Repository\WithdrawalRepository;
use Polski\Service\WithdrawalService;
use Polski\Util\TemplateLoader;

/**
 * Art. 11a Directive 2011/83/EU: the withdrawal function must be available for
 * the whole withdrawal period, which starts once the contract is concluded.
 *
 * Before 1.43.0 the window ran from the order's creation date until the order
 * was completed, so a paid order still in processing after 14 days (slow
 * shipping, a preorder) lost the function before the goods even arrived, and a
 * cancelled or failed order was offered it.
 */
final class WithdrawalEligibilityTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wpdb'] = new \wpdb();
    }

    public function testProcessingOrderOlderThanTheWindowIsStillEligible(): void
    {
        $order = $this->makeOrder('processing', createdDaysAgo: 20, completedDaysAgo: null);

        self::assertTrue($this->makeService()->isEligible($order));
        self::assertNull($this->makeService()->getDeadline($order), 'The countdown has not started before completion.');
    }

    public function testFreshProcessingOrderIsEligible(): void
    {
        $order = $this->makeOrder('processing', createdDaysAgo: 0, completedDaysAgo: null);

        self::assertTrue($this->makeService()->isEligible($order));
    }

    public function testOnHoldOrderIsEligible(): void
    {
        // A bank-transfer order waits on-hold for the money, but the contract
        // was concluded when the customer placed it.
        $order = $this->makeOrder('on-hold', createdDaysAgo: 1, completedDaysAgo: null);

        self::assertTrue($this->makeService()->isEligible($order));
    }

    /**
     * @return list<array{string}>
     */
    public static function deadStatuses(): array
    {
        return [['pending'], ['cancelled'], ['refunded'], ['failed'], ['checkout-draft'], ['trash']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('deadStatuses')]
    public function testOrderWithoutAConcludedContractIsNotEligible(string $status): void
    {
        $order = $this->makeOrder($status, createdDaysAgo: 2, completedDaysAgo: null);

        self::assertFalse($this->makeService()->isEligible($order));
    }

    public function testCompletedOrderInsideTheWindowIsEligible(): void
    {
        $order = $this->makeOrder('completed', createdDaysAgo: 30, completedDaysAgo: 10);

        self::assertTrue($this->makeService()->isEligible($order));
    }

    public function testCompletedOrderPastTheWindowIsNotEligible(): void
    {
        $order = $this->makeOrder('completed', createdDaysAgo: 30, completedDaysAgo: 15);

        self::assertFalse($this->makeService()->isEligible($order));
    }

    public function testOrderWithAFiledDeclarationStaysEligibleForTheRest(): void
    {
        $order = $this->makeOrder('withdrawal-req', createdDaysAgo: 30, completedDaysAgo: 5);

        self::assertTrue($this->makeService()->isEligible($order));
    }

    private function makeService(): WithdrawalService
    {
        $repo = (new \ReflectionClass(WithdrawalRepository::class))->newInstanceWithoutConstructor();
        $itemsRepo = (new \ReflectionClass(WithdrawalItemsRepository::class))->newInstanceWithoutConstructor();
        $loader = (new \ReflectionClass(TemplateLoader::class))->newInstanceWithoutConstructor();

        foreach ([$repo, $itemsRepo] as $target) {
            $prop = (new \ReflectionClass($target))->getProperty('wpdb');
            $prop->setAccessible(true);
            $prop->setValue($target, $GLOBALS['wpdb']);
        }

        return new WithdrawalService($repo, $loader, $itemsRepo);
    }

    private function makeOrder(string $status, int $createdDaysAgo, ?int $completedDaysAgo): \WC_Order
    {
        $product = new class extends \WC_Product {
            public function __construct()
            {
            }
            public function get_id(): int
            {
                return 7;
            }
            public function get_sku(): string
            {
                return 'SKU7';
            }
            public function is_type(string|array $type): bool
            {
                return false;
            }
        };

        $item = new class ($product) extends \WC_Order_Item_Product {
            public function __construct(private readonly \WC_Product $polskiProduct)
            {
            }
            public function get_product(): \WC_Product
            {
                return $this->polskiProduct;
            }
            public function get_product_id(): int
            {
                return 7;
            }
            public function get_variation_id(): int
            {
                return 0;
            }
            public function get_quantity(): int
            {
                return 1;
            }
            public function get_total(): string
            {
                return '10';
            }
            public function get_total_tax(): string
            {
                return '0';
            }
            public function get_name(): string
            {
                return 'Item 7';
            }
        };

        $created = new \WC_DateTime('-' . $createdDaysAgo . ' days');
        $completed = $completedDaysAgo === null ? null : new \WC_DateTime('-' . $completedDaysAgo . ' days');

        return new class ($status, $item, $created, $completed) extends \WC_Order {
            public function __construct(
                private readonly string $polskiStatus,
                private readonly \WC_Order_Item_Product $polskiItem,
                private readonly \WC_DateTime $polskiCreated,
                private readonly ?\WC_DateTime $polskiCompleted,
            ) {
            }
            public function get_id(): int
            {
                return 99;
            }
            public function get_status(): string
            {
                return $this->polskiStatus;
            }
            public function get_items(string $type = 'line_item'): array
            {
                return [501 => $this->polskiItem];
            }
            public function get_date_created(): ?object
            {
                return $this->polskiCreated;
            }
            public function get_date_completed(): ?object
            {
                return $this->polskiCompleted;
            }
        };
    }
}
