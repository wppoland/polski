<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Hook\CheckoutHooks;
use Polski\Service\B2BCheckoutService;
use Polski\Service\NipLookupService;

/**
 * Block checkout options added in 1.50.0: the "Buying as" choice, the NIP
 * label, the "Accept all" box and the marketing box next to the e-mail. Each
 * is off or unchanged by default.
 *
 * existing-checked: tests/Unit/Service/B2BCheckoutServiceTest.php - that file
 * covers IBAN and the legacy meta mirror; these options span three services.
 *
 * Run: vendor/bin/phpunit --filter BlockCheckoutChoicesTest
 */
final class BlockCheckoutChoicesTest extends TestCase
{
    protected function setUp(): void
    {
        foreach (['polski_b2b', 'polski_nip', 'polski_checkout', 'polski_modules'] as $option) {
            \delete_option($option);
        }
    }

    protected function tearDown(): void
    {
        $this->setUp();
    }

    public function testEverythingIsOffByDefault(): void
    {
        self::assertFalse((new B2BCheckoutService())->buyerTypeChoiceEnabled());
        self::assertFalse(CheckoutHooks::acceptAllEnabled());
        self::assertFalse(CheckoutHooks::marketingNextToEmail());
        self::assertSame('NIP', (new NipLookupService())->fieldLabel());
    }

    public function testBuyerTypeNeedsItsOwnSwitchAndTheCompanyToggle(): void
    {
        \update_option('polski_b2b', ['block_buyer_type' => true]);
        self::assertTrue((new B2BCheckoutService())->buyerTypeChoiceEnabled());

        \update_option('polski_b2b', ['block_buyer_type' => true, 'show_company_toggle' => false]);
        self::assertFalse((new B2BCheckoutService())->buyerTypeChoiceEnabled());
    }

    public function testCompanyRuleKeysOnTheContactChoice(): void
    {
        $required = B2BCheckoutService::companyOnlyRule(false);
        $choice = $required['customer']['properties']['additional_fields'];

        self::assertSame('object', $choice['type']);
        self::assertSame(['const' => 'company'], $choice['properties'][B2BCheckoutService::BUYER_TYPE_FIELD]);
        self::assertSame([B2BCheckoutService::BUYER_TYPE_FIELD], $choice['required']);

        // Hidden is the exact negation, so a missing choice hides the fields.
        self::assertSame(['not' => ['properties' => $required]], B2BCheckoutService::companyOnlyRule(true));
    }

    public function testNipLabelSetting(): void
    {
        \update_option('polski_nip', ['field_label' => '  ']);
        self::assertSame('NIP', (new NipLookupService())->fieldLabel());

        \update_option('polski_nip', ['field_label' => 'VAT ID / NIP']);
        self::assertSame('VAT ID / NIP', (new NipLookupService())->fieldLabel());
    }

    public function testAcceptAllLabelFallsBackWhenEmpty(): void
    {
        \update_option('polski_checkout', ['accept_all_checkbox_enabled' => true, 'accept_all_checkbox_label' => '']);
        self::assertTrue(CheckoutHooks::acceptAllEnabled());
        self::assertSame('Accept all', CheckoutHooks::acceptAllLabel());

        \update_option('polski_checkout', ['accept_all_checkbox_label' => 'Select all']);
        self::assertSame('Select all', CheckoutHooks::acceptAllLabel());
    }

    public function testPersonalOrderLosesCompanyDataAndCompanyOrderGetsItsName(): void
    {
        \update_option('polski_b2b', ['block_buyer_type' => true]);
        $service = new B2BCheckoutService();

        $personal = $this->order([
            '_wc_other/polski/buyer_type' => 'personal',
            '_wc_other/polski/company_name' => 'Typed by mistake',
            '_wc_other/polski/nip' => '5260250274',
            '_billing_nip' => '5260250274',
            '_polski_billing_nip' => '5260250274',
        ], 'Core company');
        $service->applyBuyerTypeToOrder($personal);

        self::assertSame('', $personal->company);
        self::assertSame('no', $personal->meta['_polski_buying_as_company']);
        foreach (['_wc_other/polski/company_name', '_wc_other/polski/nip', '_billing_nip', '_polski_billing_nip'] as $key) {
            self::assertArrayNotHasKey($key, $personal->meta, $key);
        }

        $company = $this->order([
            '_wc_other/polski/buyer_type' => 'company',
            '_wc_other/polski/company_name' => 'Test Sp. z o.o.',
            '_billing_nip' => '5260250274',
        ], '');
        $service->applyBuyerTypeToOrder($company);

        self::assertSame('Test Sp. z o.o.', $company->company);
        self::assertSame('yes', $company->meta['_polski_buying_as_company']);
        self::assertSame('5260250274', $company->meta['_billing_nip']);
    }

    public function testOrderIsUntouchedWithTheChoiceOff(): void
    {
        $order = $this->order(['_wc_other/polski/nip' => '5260250274'], 'Core company');
        (new B2BCheckoutService())->applyBuyerTypeToOrder($order);

        self::assertSame('Core company', $order->company);
        self::assertArrayNotHasKey('_polski_buying_as_company', $order->meta);
    }

    /**
     * @param array<string, string> $meta
     */
    private function order(array $meta, string $company): \WC_Order
    {
        return new class ($meta, $company) extends \WC_Order {
            /**
             * @param array<string, string> $meta
             */
            public function __construct(public array $meta, public string $company)
            {
            }

            public function get_meta(string $key, bool $single = true): mixed
            {
                return $this->meta[$key] ?? '';
            }

            public function update_meta_data(string $key, mixed $value): void
            {
                $this->meta[$key] = $value;
            }

            public function delete_meta_data(string $key): void
            {
                unset($this->meta[$key]);
            }

            public function set_billing_company(string $value): void
            {
                $this->company = $value;
            }
        };
    }
}
