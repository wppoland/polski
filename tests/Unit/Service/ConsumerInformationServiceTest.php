<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Service\ConsumerInformationService;

/**
 * Tests the Directive (EU) 2024/825 consumer information block.
 *
 * The module is off by default, so the first thing worth pinning is that a shop
 * which never enables it sees nothing at all.
 */
final class ConsumerInformationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['polski_test_options'] = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['polski_test_options'] = [];
    }

    private function enableModule(bool $on = true): void
    {
        $GLOBALS['polski_test_options']['polski_modules'] = ['consumer_information' => $on];
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function product(array $meta = []): \WC_Product
    {
        return new class ($meta) extends \WC_Product {
            /** @param array<string, mixed> $meta */
            public function __construct(private array $meta)
            {
            }

            public function get_meta(string $key, bool $single = true): mixed
            {
                return $this->meta[$key] ?? '';
            }
        };
    }

    public function testRendersNothingWhileTheModuleIsOff(): void
    {
        $this->assertSame('', (new ConsumerInformationService())->getHtml($this->product([
            '_polski_durability_guarantee_months' => 36,
        ])));
    }

    public function testShowsTheLegalGuaranteeReminderOnceEnabled(): void
    {
        $this->enableModule();

        $html = (new ConsumerInformationService())->getHtml($this->product());

        $this->assertStringContainsString('polski-consumer-info__legal', $html);
        $this->assertStringContainsString('statutory guarantee of conformity', $html);
    }

    public function testTheReminderCanBeTurnedOffAndThenNothingRendersWithoutProductData(): void
    {
        $this->enableModule();
        $GLOBALS['polski_test_options']['polski_consumer_info'] = ['show_legal_guarantee' => false];

        $this->assertSame('', (new ConsumerInformationService())->getHtml($this->product()));
    }

    public function testRendersDurabilityUpdatesAndRepairRows(): void
    {
        $this->enableModule();

        $html = (new ConsumerInformationService())->getHtml($this->product([
            '_polski_durability_guarantee_months' => 36,
            '_polski_update_period_months' => 1,
            '_polski_repair_info' => 'Spare parts for 7 years, repairs@example.com',
        ]));

        $this->assertStringContainsString('36 months', $html);
        $this->assertStringContainsString('1 month', $html, 'singular must not read "1 months"');
        $this->assertStringContainsString('Spare parts for 7 years', $html);
    }

    public function testOmitsRowsThatAreEmptyOrZero(): void
    {
        $this->enableModule();

        $html = (new ConsumerInformationService())->getHtml($this->product([
            '_polski_durability_guarantee_months' => 0,
            '_polski_update_period_months' => '',
        ]));

        $this->assertStringNotContainsString('polski-consumer-info__list', $html);
        $this->assertStringContainsString('polski-consumer-info__legal', $html);
    }

    public function testANegativeStoredValueIsNeverRendered(): void
    {
        $this->enableModule();

        $html = (new ConsumerInformationService())->getHtml($this->product([
            '_polski_durability_guarantee_months' => -6,
        ]));

        $this->assertStringNotContainsString('-6', $html);
    }

    public function testACustomReminderReplacesTheDefault(): void
    {
        $this->enableModule();
        $GLOBALS['polski_test_options']['polski_consumer_info'] = [
            'legal_guarantee_text' => 'Two years, by law.',
        ];

        $html = (new ConsumerInformationService())->getHtml($this->product());

        $this->assertStringContainsString('Two years, by law.', $html);
        $this->assertStringNotContainsString('statutory guarantee of conformity', $html);
    }

    public function testTheRepairTextIsEscaped(): void
    {
        $this->enableModule();

        $html = (new ConsumerInformationService())->getHtml($this->product([
            '_polski_repair_info' => '<script>alert(1)</script>',
        ]));

        $this->assertStringNotContainsString('<script>', $html);
    }
}
