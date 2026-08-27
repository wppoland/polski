<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Service\WithdrawalOrderStatusService;

final class WithdrawalOrderStatusServiceTest extends TestCase
{
    private WithdrawalOrderStatusService $service;

    protected function setUp(): void
    {
        $this->service = new WithdrawalOrderStatusService();
    }

    public function testIncludeInReportsReturnsFalseUntouchedWhenWooCommercePassesFalse(): void
    {
        // WooCommerce WC_Report_Sales_By_Date passes `false` for refund datasets
        // to signify "no status filter". The callback must not throw TypeError.
        $result = $this->service->includeInReports(false);

        self::assertFalse($result);
    }

    public function testIncludeInReportsAddsWithdrawalStatusesToArray(): void
    {
        $input = ['completed', 'processing'];
        $result = $this->service->includeInReports($input);

        self::assertIsArray($result);
        self::assertContains('completed', $result);
        self::assertContains('processing', $result);
        self::assertContains('withdrawal-requested', $result);
        self::assertContains('withdrawal-partial', $result);
        self::assertContains('withdrawal-completed', $result);
    }

    public function testTreatAsPaidReturnsNonArrayUntouched(): void
    {
        self::assertFalse($this->service->treatAsPaid(false));
        self::assertNull($this->service->treatAsPaid(null));
    }

    public function testTreatAsPaidAddsPartialStatus(): void
    {
        $result = $this->service->treatAsPaid(['completed']);

        self::assertIsArray($result);
        self::assertContains('completed', $result);
        self::assertContains('withdrawal-partial', $result);
    }

    public function testAddToStatusListReturnsNonArrayUntouched(): void
    {
        self::assertFalse($this->service->addToStatusList(false));
    }

    public function testAddToStatusListInjectsWithdrawalStatusesAfterCompleted(): void
    {
        $statuses = [
            'wc-pending' => 'Pending',
            'wc-completed' => 'Completed',
            'wc-cancelled' => 'Cancelled',
        ];

        $result = $this->service->addToStatusList($statuses);

        self::assertIsArray($result);
        self::assertArrayHasKey(WithdrawalOrderStatusService::STATUS_REQUESTED, $result);
        self::assertArrayHasKey(WithdrawalOrderStatusService::STATUS_PARTIAL, $result);
        self::assertArrayHasKey(WithdrawalOrderStatusService::STATUS_COMPLETED, $result);
    }
}
