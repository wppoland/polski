<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Hook\StructuredDataHooks;
use Polski\Repository\OmnibusPriceRepository;
use Polski\Service\DeliveryTimeService;
use Polski\Service\MerchantPolicySchema;
use Polski\Service\OmnibusService;
use Polski\Service\ProductGroupSchema;

final class MerchantPolicySchemaTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['polski_test_options']['polski_seo'], $GLOBALS['polski_test_options']['woocommerce_default_country']);
    }

    public function testFlatRateWithClassCostsIsNoPublishableRate(): void
    {
        $this->assertFalse(MerchantPolicySchema::hasClassCosts(['cost' => '15', 'class_cost_12' => '', 'no_class_cost' => '']));
        $this->assertTrue(MerchantPolicySchema::hasClassCosts(['cost' => '15', 'class_cost_12' => '30']));
        $this->assertTrue(MerchantPolicySchema::hasClassCosts(['cost' => '15', 'no_class_cost' => '5']));
    }

    public function testDefaultReturnPolicyIsFourteenDaysByMailPaidByCustomer(): void
    {
        $this->assertSame([
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => 'PL',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => 14,
            'returnMethod' => 'https://schema.org/ReturnByMail',
            'returnFees' => 'https://schema.org/ReturnFeesCustomerResponsibility',
        ], MerchantPolicySchema::buildReturnPolicy([], 'PL', 'PLN'));
    }

    public function testReturnShippingFeeNeedsAnAmount(): void
    {
        $withFee = MerchantPolicySchema::buildReturnPolicy(['return_fees' => 'shipping_fee', 'return_shipping_fee' => '19,90', 'return_country' => 'pl, de'], 'PL', 'PLN');

        $this->assertSame(['PL', 'DE'], $withFee['applicableCountry'] ?? null);
        $this->assertSame('https://schema.org/ReturnShippingFees', $withFee['returnFees'] ?? null);
        $this->assertSame(['@type' => 'MonetaryAmount', 'value' => 19.9, 'currency' => 'PLN'], $withFee['returnShippingFeesAmount'] ?? null);

        $withoutFee = MerchantPolicySchema::buildReturnPolicy(['return_fees' => 'shipping_fee'], 'PL', 'PLN');
        $this->assertSame('https://schema.org/ReturnFeesCustomerResponsibility', $withoutFee['returnFees'] ?? null);
        $this->assertArrayNotHasKey('returnShippingFeesAmount', (array) $withoutFee);
    }

    public function testNoReturnsAndUnlimitedWindow(): void
    {
        $none = MerchantPolicySchema::buildReturnPolicy(['return_category' => 'not_permitted'], 'PL', 'PLN');
        $this->assertSame(['@type', 'applicableCountry', 'returnPolicyCategory'], array_keys((array) $none));

        $unlimited = MerchantPolicySchema::buildReturnPolicy(['return_category' => 'unlimited', 'return_fees' => 'free'], 'PL', 'PLN');
        $this->assertArrayNotHasKey('merchantReturnDays', (array) $unlimited);
        $this->assertSame('https://schema.org/FreeReturn', $unlimited['returnFees'] ?? null);
    }

    public function testNoCountryNoPolicy(): void
    {
        $this->assertNull(MerchantPolicySchema::buildReturnPolicy([], '', 'PLN'));
    }

    public function testShippingDetailsNeedARate(): void
    {
        $this->assertNull(MerchantPolicySchema::buildShippingDetails([], null, 'PL', 'PLN'));

        $details = MerchantPolicySchema::buildShippingDetails(['handling_min' => '1', 'handling_max' => '2', 'transit_min' => '2', 'transit_max' => '4'], 149.0, 'PL', 'PLN');

        $this->assertSame([
            '@type' => 'OfferShippingDetails',
            'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => 149.0, 'currency' => 'PLN'],
            'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'PL'],
            'deliveryTime' => [
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => ['@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => 2, 'unitCode' => 'DAY'],
                'transitTime' => ['@type' => 'QuantitativeValue', 'minValue' => 2, 'maxValue' => 4, 'unitCode' => 'DAY'],
            ],
        ], $details);
    }

    public function testTransitFallsBackToDeliveryTimeText(): void
    {
        $range = MerchantPolicySchema::buildShippingDetails([], 0.0, 'PL', 'PLN', [2, 3]);
        $this->assertSame([2, 3], [$range['deliveryTime']['transitTime']['minValue'] ?? null, $range['deliveryTime']['transitTime']['maxValue'] ?? null]);
        $this->assertSame(['minValue' => 0, 'maxValue' => 1], array_intersect_key($range['deliveryTime']['handlingTime'] ?? [], ['minValue' => 0, 'maxValue' => 0]));

        $single = MerchantPolicySchema::buildShippingDetails([], 0.0, 'PL', 'PLN', [5]);
        $this->assertSame([1, 5], [$single['deliveryTime']['transitTime']['minValue'] ?? null, $single['deliveryTime']['transitTime']['maxValue'] ?? null]);
    }

    public function testParseAmount(): void
    {
        $this->assertSame(49.9, MerchantPolicySchema::parseAmount('49,90'));
        $this->assertSame(1299.0, MerchantPolicySchema::parseAmount('1 299.00'));
        $this->assertSame(0.0, MerchantPolicySchema::parseAmount('0'));
        $this->assertNull(MerchantPolicySchema::parseAmount(''));
        $this->assertNull(MerchantPolicySchema::parseAmount('-5'));
        $this->assertNull(MerchantPolicySchema::parseAmount('free'));
    }

    /**
     * The simple-product path end to end: WooCommerce's Product node goes in,
     * the offer comes out with shipping details and the return policy.
     */
    public function testSimpleProductOfferGetsShippingAndReturns(): void
    {
        $GLOBALS['polski_test_options']['woocommerce_default_country'] = 'PL:MA';
        $GLOBALS['polski_test_options']['polski_seo'] = [
            'schema_shipping' => true,
            'schema_return_policy' => true,
            'schema_delivery_time' => false,
            'shipping_rate' => '99',
            'transit_min' => '1',
            'transit_max' => '2',
        ];

        $markup = $this->hooks()->augmentProduct([
            '@type' => 'Product',
            '@id' => 'https://example.test/produkt/deska/#product',
            'name' => 'Deska',
            'sku' => 'D-1',
            'offers' => [[
                '@type' => 'Offer',
                'price' => '199.00',
                'priceCurrency' => 'PLN',
                'priceValidUntil' => '2027-12-31',
                'availability' => 'https://schema.org/InStock',
            ]],
        ], new SimpleTestProduct());

        $offer = $markup['offers'][0];
        $this->assertSame('Product', $markup['@type']);
        $this->assertSame(99.0, $offer['shippingDetails']['shippingRate']['value']);
        $this->assertSame('PL', $offer['shippingDetails']['shippingDestination']['addressCountry']);
        $this->assertSame('https://example.test/#merchant-return-policy', $offer['hasMerchantReturnPolicy']['@id']);
        $this->assertSame(14, $offer['hasMerchantReturnPolicy']['merchantReturnDays']);
    }

    public function testClaimsAreOffByDefault(): void
    {
        $markup = $this->hooks()->augmentProduct([
            '@type' => 'Product',
            'offers' => [['@type' => 'Offer', 'price' => '1.00', 'priceValidUntil' => '2027-12-31']],
        ], new SimpleTestProduct());

        $this->assertArrayNotHasKey('shippingDetails', $markup['offers'][0]);
        $this->assertArrayNotHasKey('hasMerchantReturnPolicy', $markup['offers'][0]);
    }

    private function hooks(): StructuredDataHooks
    {
        $repo = (new \ReflectionClass(OmnibusPriceRepository::class))->newInstanceWithoutConstructor();

        return new StructuredDataHooks(
            new OmnibusService($repo),
            new MerchantPolicySchema(new DeliveryTimeService()),
            new ProductGroupSchema(),
        );
    }
}

final class SimpleTestProduct extends \WC_Product
{
    public function is_on_sale(string $context = 'view'): bool
    {
        return false;
    }
}
