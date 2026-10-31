<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Enum\CheckboxContext;
use Polski\Enum\WithdrawalExemptionReason;
use Polski\Hook\CheckoutHooks;
use Polski\Service\CheckboxService;
use Polski\Service\ProductMarkingsService;
use Polski\Service\WithdrawalExemptionService;

/**
 * Two cart-driven checkout boxes:
 *  - service_waiver: the express request to start a service in the withdrawal
 *    period (Directive 2011/83/EU Art. 8(8), Art. 16(a); art. 38 ust. 1 pkt 1
 *    of the Consumer Rights Act), shown for products excluded with the Service
 *    reason. The exclusion holds only once the request is on the order.
 *  - age_verification: an 18+ confirmation for products marked adults only,
 *    with the minimum age stored on the order.
 *
 * Run: vendor/bin/phpunit --filter ServiceAndAgeCheckboxTest
 */
final class ServiceAndAgeCheckboxTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['polski_test_post_terms'] = [];
        $GLOBALS['polski_test_term_meta'] = [];
        \delete_option('polski_checkout');
        \delete_option('polski_checkbox_overrides');
    }

    protected function tearDown(): void
    {
        \delete_option('polski_checkout');
    }

    public function testBothBoxesAreCoreAndOffByDefault(): void
    {
        $service = new CheckboxService();
        $service->initCheckboxes();

        foreach (['service_waiver', 'age_verification'] as $id) {
            self::assertTrue($service->isCore($id), $id);
            self::assertNotNull($service->get($id), $id);
            self::assertFalse($service->get($id)->enabled, $id);
        }
    }

    public function testBoxesShowOnlyForTheirCart(): void
    {
        \update_option('polski_checkout', [
            'service_waiver_checkbox_enabled' => true,
            'age_verification_checkbox_enabled' => true,
        ]);

        $service = new CheckboxService();
        $service->initCheckboxes();

        $ids = static fn (array $types): array => array_map(
            static fn ($cb) => $cb->id,
            $service->getForContext(CheckboxContext::Checkout, ['product_types' => $types]),
        );

        self::assertNotContains('service_waiver', $ids(['simple']));
        self::assertNotContains('age_verification', $ids(['simple']));
        self::assertContains('service_waiver', $ids(['simple', CheckoutHooks::TYPE_SERVICE]));
        self::assertContains('age_verification', $ids(['simple', CheckoutHooks::TYPE_ADULTS_ONLY]));
    }

    public function testProductFlagsBecomeCartTokens(): void
    {
        $GLOBALS['polski_test_post_terms'][10]['product_cat'] = [5];
        $GLOBALS['polski_test_term_meta'][5] = [
            WithdrawalExemptionService::TERM_META => 'yes',
            WithdrawalExemptionService::TERM_REASON_META => WithdrawalExemptionReason::Service->value,
        ];

        self::assertSame([CheckoutHooks::TYPE_SERVICE], CheckoutHooks::flagTypes($this->product(10)));
        self::assertSame([CheckoutHooks::TYPE_ADULTS_ONLY], CheckoutHooks::flagTypes($this->product(11, adults: true)));
        self::assertSame([], CheckoutHooks::flagTypes($this->product(12)));
    }

    public function testServiceExclusionNeedsTheRequestAndFullPerformance(): void
    {
        $GLOBALS['polski_test_post_terms'][20]['product_cat'] = [6];
        $GLOBALS['polski_test_term_meta'][6] = [
            WithdrawalExemptionService::TERM_META => 'yes',
            WithdrawalExemptionService::TERM_REASON_META => WithdrawalExemptionReason::Service->value,
        ];
        $exemptions = new WithdrawalExemptionService();
        $product = $this->product(20);

        self::assertTrue($exemptions->filterOrderEligibility(true, $this->order($product, 'completed', [])), 'no request: still withdrawable');
        self::assertTrue($exemptions->filterOrderEligibility(true, $this->order($product, 'processing', [WithdrawalExemptionService::SERVICE_CONSENT_META => '1'])), 'not yet performed');
        self::assertFalse($exemptions->filterOrderEligibility(true, $this->order($product, 'completed', [WithdrawalExemptionService::SERVICE_CONSENT_META => '1'])), 'performed after the request');
    }

    public function testAgeConfirmationStoresTheMinimumAge(): void
    {
        $order = $this->order($this->product(30, adults: true), 'processing', []);

        CheckoutHooks::recordAgeConfirmation($order, ['age_verification' => false]);
        self::assertArrayNotHasKey('_polski_min_age', $order->meta);

        CheckoutHooks::recordAgeConfirmation($order, ['age_verification' => true]);
        self::assertSame('18', $order->meta['_polski_min_age']);
        self::assertCount(1, $order->notes);
    }

    public function testBlockRuleKeysOnTheCartFlag(): void
    {
        $rule = CheckoutHooks::cartFlagRule('age_verification', true);

        self::assertSame(
            ['const' => true],
            $rule['cart']['properties']['extensions']['properties']['polski_checkboxes']['properties']['age_verification'],
        );
    }

    private function product(int $id, bool $adults = false): \WC_Product
    {
        return new class ($id, $adults) extends \WC_Product {
            public function __construct(private readonly int $polskiId, private readonly bool $polskiAdults)
            {
            }
            public function get_id(): int
            {
                return $this->polskiId;
            }
            public function get_meta(string $key, bool $single = true): mixed
            {
                return $key === ProductMarkingsService::META_ADULTS_ONLY && $this->polskiAdults ? 'yes' : '';
            }
        };
    }

    /**
     * @param array<string, string> $meta
     */
    private function order(\WC_Product $product, string $status, array $meta): \WC_Order
    {
        return new class ($product, $status, $meta) extends \WC_Order {
            /** @var list<string> */
            public array $notes = [];

            /** @param array<string, mixed> $meta */
            public function __construct(private readonly \WC_Product $polskiProduct, private readonly string $polskiStatus, public array $meta)
            {
            }
            public function get_items(string $type = 'line_item'): array
            {
                return [1 => new class ($this->polskiProduct) extends \WC_Order_Item_Product {
                    public function __construct(private readonly \WC_Product $polskiProduct)
                    {
                    }
                    public function get_product(): \WC_Product
                    {
                        return $this->polskiProduct;
                    }
                }];
            }
            public function get_status(): string
            {
                return $this->polskiStatus;
            }
            public function get_meta(string $key, bool $single = true): mixed
            {
                return $this->meta[$key] ?? '';
            }
            public function update_meta_data(string $key, mixed $value): void
            {
                $this->meta[$key] = $value;
            }
            public function add_order_note(string $note, int $isCustomerNote = 0, bool $addedByUser = false): int
            {
                $this->notes[] = $note;

                return count($this->notes);
            }
        };
    }
}
