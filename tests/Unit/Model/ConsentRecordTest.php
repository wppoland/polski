<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Polski\Enum\CheckboxContext;
use Polski\Model\ConsentRecord;

final class ConsentRecordTest extends TestCase
{
    public function testFromRowCreatesCorrectInstance(): void
    {
        $row = (object) [
            'id' => '42',
            'user_id' => '7',
            'session_id' => 'order_123',
            'checkbox_id' => 'terms',
            'context' => 'checkout',
            'consented' => '1',
            'ip_address' => '192.168.1.0',
            'user_agent' => 'Mozilla/5.0',
            'created_at' => '2026-04-03 12:00:00',
        ];

        $record = ConsentRecord::fromRow($row);

        $this->assertSame(42, $record->id);
        $this->assertSame(7, $record->userId);
        $this->assertSame('order_123', $record->sessionId);
        $this->assertSame('terms', $record->checkboxId);
        $this->assertSame(CheckboxContext::Checkout, $record->context);
        $this->assertTrue($record->consented);
        $this->assertSame('192.168.1.0', $record->ipAddress);
        $this->assertSame('2026-04-03 12:00:00', $record->createdAt->format('Y-m-d H:i:s'));
    }

    public function testFromRowWithNullUser(): void
    {
        $row = (object) [
            'id' => '1',
            'user_id' => null,
            'session_id' => 'guest_abc',
            'checkbox_id' => 'privacy',
            'context' => 'registration',
            'consented' => '0',
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => '2026-01-01 00:00:00',
        ];

        $record = ConsentRecord::fromRow($row);

        $this->assertNull($record->userId);
        $this->assertSame('guest_abc', $record->sessionId);
        $this->assertFalse($record->consented);
        $this->assertNull($record->ipAddress);
    }
}
