<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Email;

use PHPUnit\Framework\TestCase;
use Polski\Email\WithdrawalConfirmationEmail;
use Polski\Enum\WithdrawalStatus;
use Polski\Model\WithdrawalRequest;

final class WithdrawalConfirmationEmailTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['polski_test_options'] = [
            'date_format' => 'Y-m-d',
        ];
    }

    public function testGetContentHtmlRendersWithoutErrors(): void
    {
        $email = new WithdrawalConfirmationEmail();
        $order = new \WC_Order();
        $request = new WithdrawalRequest(
            id: 123,
            orderId: 456,
            customerId: 789,
            status: WithdrawalStatus::Requested,
            reason: 'Za duży rozmiar',
            items: [['product_id' => 10, 'quantity' => 1]],
            requestedAt: new \DateTimeImmutable('2026-08-25 10:00:00'),
            confirmedAt: null,
            completedAt: null,
        );

        $email->object = $order;
        $email->request = $request;

        $html = $email->get_content_html();

        self::assertNotEmpty($html);
        self::assertStringContainsString('POL-WD-000123', $html);
        self::assertStringContainsString('2026-08-25 10:00', $html);
        self::assertStringContainsString('Za duży rozmiar', $html);
    }

    public function testGetContentPlainRendersWithoutErrors(): void
    {
        $email = new WithdrawalConfirmationEmail();
        $order = new \WC_Order();
        $request = new WithdrawalRequest(
            id: 123,
            orderId: 456,
            customerId: 789,
            status: WithdrawalStatus::Requested,
            reason: 'Nie podoba się kolor',
            items: [['product_id' => 10, 'quantity' => 1]],
            requestedAt: new \DateTimeImmutable('2026-08-25 10:00:00'),
            confirmedAt: null,
            completedAt: null,
        );

        $email->object = $order;
        $email->request = $request;

        $plain = $email->get_content_plain();

        self::assertNotEmpty($plain);
        self::assertStringContainsString('POL-WD-000123', $plain);
        self::assertStringContainsString('2026-08-25 10:00', $plain);
        self::assertStringContainsString('Nie podoba się kolor', $plain);
    }
}
