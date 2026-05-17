<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Service\DigitalConsentService;

/**
 * Covers the Art. 16(m) eligibility filter: only orders that are entirely
 * digital AND carry a recorded consent become exempt from withdrawal.
 */
final class DigitalConsentServiceTest extends TestCase
{
    public function testAllDigitalOrderWithConsentBecomesIneligible(): void
    {
        $svc = new DigitalConsentService();
        $order = $this->makeOrder([
            $this->makeProduct(isDigital: true),
            $this->makeProduct(isDigital: true),
        ], consentRecord: ['accepted' => true]);

        self::assertFalse($svc->filterEligibility(true, $order));
    }

    public function testAllDigitalOrderWithoutConsentStaysEligible(): void
    {
        $svc = new DigitalConsentService();
        $order = $this->makeOrder([
            $this->makeProduct(isDigital: true),
        ], consentRecord: null);

        self::assertTrue($svc->filterEligibility(true, $order));
    }

    public function testMixedCartIsNeverExemptEvenWithConsent(): void
    {
        $svc = new DigitalConsentService();
        $order = $this->makeOrder([
            $this->makeProduct(isDigital: true),
            $this->makeProduct(isDigital: false),
        ], consentRecord: ['accepted' => true]);

        self::assertTrue($svc->filterEligibility(true, $order));
    }

    public function testAlreadyIneligibleStaysIneligible(): void
    {
        $svc = new DigitalConsentService();
        $order = $this->makeOrder([
            $this->makeProduct(isDigital: false),
        ]);

        self::assertFalse($svc->filterEligibility(false, $order));
    }

    public function testModeFallsBackToOptionalWhenSettingIsUnknown(): void
    {
        $GLOBALS['polski_test_options']['polski_withdrawal'] = ['digital_consent_mode' => 'gibberish'];
        $svc = new DigitalConsentService();

        self::assertSame(DigitalConsentService::MODE_OPTIONAL, $svc->mode());
    }

    public function testModeReadsRequiredWhenConfigured(): void
    {
        $GLOBALS['polski_test_options']['polski_withdrawal'] = ['digital_consent_mode' => DigitalConsentService::MODE_REQUIRED];
        $svc = new DigitalConsentService();

        self::assertSame(DigitalConsentService::MODE_REQUIRED, $svc->mode());
    }

    private function makeProduct(bool $isDigital): \WC_Product
    {
        return new class ($isDigital) extends \WC_Product {
            public function __construct(private readonly bool $polskiDigital)
            {
            }
            public function is_downloadable(): bool
            {
                return $this->polskiDigital;
            }
            public function is_virtual(): bool
            {
                return false;
            }
        };
    }

    /**
     * @param list<\WC_Product>      $products
     * @param array<string, mixed>|null $consentRecord
     */
    private function makeOrder(array $products, ?array $consentRecord = null): \WC_Order
    {
        return new class ($products, $consentRecord) extends \WC_Order {
            /** @var array<int, \WC_Order_Item_Product> */
            private array $polskiItems;
            /** @var array<string, mixed>|null */
            private readonly ?array $polskiConsent;

            /** @param list<\WC_Product> $products */
            public function __construct(array $products, ?array $consent)
            {
                $this->polskiConsent = $consent;
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

            public function get_meta(string $key, bool $single = true): mixed
            {
                if ($key === '_polski_digital_consent') {
                    return $this->polskiConsent ?? '';
                }
                return '';
            }
        };
    }
}
