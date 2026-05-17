<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Enum\WithdrawalExemptionReason;
use Polski\Service\WithdrawalExemptionService;

/**
 * Covers product-level vs category-level exemption resolution, the Art. 38
 * reasons enum, and the order-level eligibility filter.
 */
final class WithdrawalExemptionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['polski_test_post_terms'] = [];
        $GLOBALS['polski_test_term_meta'] = [];
    }

    public function testProductLevelExemptionWins(): void
    {
        $service = new WithdrawalExemptionService();
        $product = $this->makeProduct(productId: 100, exempt: 'yes');

        self::assertTrue($service->isProductExempt($product));
    }

    public function testCategoryLevelExemptionAppliesWhenProductIsNotExempt(): void
    {
        $service = new WithdrawalExemptionService();
        $GLOBALS['polski_test_post_terms'][200]['product_cat'] = [50];
        $GLOBALS['polski_test_term_meta'][50] = [
            WithdrawalExemptionService::TERM_META => 'yes',
            WithdrawalExemptionService::TERM_REASON_META => WithdrawalExemptionReason::Perishable->value,
        ];

        $product = $this->makeProduct(productId: 200, exempt: '');

        self::assertTrue($service->isProductExempt($product));
        self::assertSame(WithdrawalExemptionReason::Perishable->label(), $service->getProductExemptionReason($product));
    }

    public function testReturnsEmptyReasonWhenProductIsNotExempt(): void
    {
        $service = new WithdrawalExemptionService();
        $product = $this->makeProduct(productId: 300, exempt: '');

        self::assertFalse($service->isProductExempt($product));
        self::assertSame('', $service->getProductExemptionReason($product));
    }

    public function testProductReasonOverridesCategoryReason(): void
    {
        $service = new WithdrawalExemptionService();
        $GLOBALS['polski_test_post_terms'][400]['product_cat'] = [60];
        $GLOBALS['polski_test_term_meta'][60] = [
            WithdrawalExemptionService::TERM_META => 'yes',
            WithdrawalExemptionService::TERM_REASON_META => WithdrawalExemptionReason::Perishable->value,
        ];

        $product = $this->makeProduct(
            productId: 400,
            exempt: 'yes',
            productReason: WithdrawalExemptionReason::CustomMade->value,
        );

        self::assertSame(WithdrawalExemptionReason::CustomMade->label(), $service->getProductExemptionReason($product));
    }

    public function testCustomReasonFallsBackToFreeTextWhenProvided(): void
    {
        $service = new WithdrawalExemptionService();
        $product = $this->makeProduct(
            productId: 500,
            exempt: 'yes',
            productReason: WithdrawalExemptionReason::Custom->value,
            productReasonCustom: 'Made from rare alpaca wool',
        );

        self::assertSame('Made from rare alpaca wool', $service->getProductExemptionReason($product));
    }

    public function testVariationLooksUpParentCategories(): void
    {
        $service = new WithdrawalExemptionService();
        $GLOBALS['polski_test_post_terms'][600]['product_cat'] = [70];
        $GLOBALS['polski_test_term_meta'][70] = [
            WithdrawalExemptionService::TERM_META => 'yes',
            WithdrawalExemptionService::TERM_REASON_META => WithdrawalExemptionReason::Sealed->value,
        ];

        $variation = $this->makeProduct(productId: 601, exempt: '', parentId: 600, isVariation: true);

        self::assertTrue($service->isProductExempt($variation));
        self::assertSame(WithdrawalExemptionReason::Sealed->label(), $service->getProductExemptionReason($variation));
    }

    public function testOrderEligibilityIsFalseWhenAllItemsAreExempt(): void
    {
        $service = new WithdrawalExemptionService();
        $product = $this->makeProduct(productId: 700, exempt: 'yes');
        $order = $this->makeOrder([$product]);

        self::assertFalse($service->filterOrderEligibility(true, $order));
    }

    public function testOrderEligibilityRemainsTrueWhenAtLeastOneItemIsNotExempt(): void
    {
        $service = new WithdrawalExemptionService();
        $exempt = $this->makeProduct(productId: 800, exempt: 'yes');
        $regular = $this->makeProduct(productId: 801, exempt: '');
        $order = $this->makeOrder([$exempt, $regular]);

        self::assertTrue($service->filterOrderEligibility(true, $order));
    }

    public function testOrderEligibilityShortcircuitsWhenAlreadyFalse(): void
    {
        $service = new WithdrawalExemptionService();
        $product = $this->makeProduct(productId: 900, exempt: '');
        $order = $this->makeOrder([$product]);

        self::assertFalse($service->filterOrderEligibility(false, $order));
    }

    private function makeProduct(
        int $productId,
        string $exempt,
        ?string $productReason = null,
        string $productReasonCustom = '',
        ?int $parentId = null,
        bool $isVariation = false,
    ): \WC_Product {
        return new class ($productId, $exempt, $productReason, $productReasonCustom, $parentId, $isVariation) extends \WC_Product {
            public function __construct(
                private readonly int $polskiId,
                private readonly string $polskiExempt,
                private readonly ?string $polskiReason,
                private readonly string $polskiReasonCustom,
                private readonly ?int $polskiParentId,
                private readonly bool $polskiIsVariation,
            ) {
            }
            public function get_id(): int
            {
                return $this->polskiId;
            }
            public function get_parent_id(): int
            {
                return $this->polskiParentId ?? 0;
            }
            public function get_meta(string $key, bool $single = true): mixed
            {
                return match ($key) {
                    WithdrawalExemptionService::PRODUCT_META => $this->polskiExempt,
                    WithdrawalExemptionService::PRODUCT_REASON_META => (string) ($this->polskiReason ?? ''),
                    WithdrawalExemptionService::PRODUCT_REASON_CUSTOM_META => $this->polskiReasonCustom,
                    default => '',
                };
            }
            public function is_type(string|array $type): bool
            {
                if (is_string($type)) {
                    return $type === 'variation' && $this->polskiIsVariation;
                }
                return in_array('variation', $type, true) && $this->polskiIsVariation;
            }
        };
    }

    /**
     * @param list<\WC_Product> $products
     */
    private function makeOrder(array $products): \WC_Order
    {
        return new class ($products) extends \WC_Order {
            /** @var array<int, \WC_Order_Item_Product> */
            private array $polskiItems;

            /** @param list<\WC_Product> $products */
            public function __construct(array $products)
            {
                $this->polskiItems = [];
                foreach ($products as $idx => $product) {
                    $this->polskiItems[$idx + 1] = new class ($product) extends \WC_Order_Item_Product {
                        public function __construct(private readonly \WC_Product $polskiProduct)
                        {
                        }
                        public function get_product(): \WC_Product
                        {
                            return $this->polskiProduct;
                        }
                    };
                }
            }
            public function get_items(string $type = 'line_item'): array
            {
                return $this->polskiItems;
            }
        };
    }
}
