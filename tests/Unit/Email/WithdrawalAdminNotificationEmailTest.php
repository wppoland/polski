<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Email;

use PHPUnit\Framework\TestCase;
use Polski\Email\WithdrawalAdminNotificationEmail;
use Polski\Enum\WithdrawalStatus;
use Polski\Model\WithdrawalRequest;

final class WithdrawalAdminNotificationEmailTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['polski_test_options'] = [
            'date_format' => 'Y-m-d',
            'admin_email' => 'admin@example.test',
        ];

        // The templates list the declared lines from the per-item rows.
        $GLOBALS['wpdb'] = new \wpdb();
        $items = (new \ReflectionClass(\Polski\Repository\WithdrawalItemsRepository::class))->newInstanceWithoutConstructor();
        $wpdb = (new \ReflectionClass($items))->getProperty('wpdb');
        $wpdb->setAccessible(true);
        $wpdb->setValue($items, $GLOBALS['wpdb']);
        \Polski\Plugin::instance()->container()->instance(\Polski\Repository\WithdrawalItemsRepository::class, $items);
    }

    public function testGetContentHtmlRendersWithoutErrors(): void
    {
        $email = new WithdrawalAdminNotificationEmail();
        $order = new \WC_Order();
        $request = new WithdrawalRequest(
            id: 123,
            orderId: 456,
            customerId: 789,
            status: WithdrawalStatus::Requested,
            reason: 'Za mały rozmiar',
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
        self::assertStringContainsString('Za mały rozmiar', $html);
    }

    public function testGetContentPlainRendersWithoutErrors(): void
    {
        $email = new WithdrawalAdminNotificationEmail();
        $order = new \WC_Order();
        $request = new WithdrawalRequest(
            id: 123,
            orderId: 456,
            customerId: 789,
            status: WithdrawalStatus::Requested,
            reason: 'Zły kolor',
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
        self::assertStringContainsString('Zły kolor', $plain);
    }

    public function testDefaultsAndConfiguration(): void
    {
        $email = new WithdrawalAdminNotificationEmail();

        self::assertFalse($email->customer_email, 'Admin notification must not be customer_email');
        self::assertSame('polski_withdrawal_admin_notification', $email->id);
        self::assertNotEmpty($email->get_default_subject());
        self::assertNotEmpty($email->get_default_heading());
        self::assertNotEmpty($email->get_default_additional_content());
    }
}
