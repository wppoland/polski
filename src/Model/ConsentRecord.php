<?php

declare(strict_types=1);

namespace Spolszczony\Model;

use Spolszczony\Enum\CheckboxContext;

/**
 * Value object for a consent log entry (GDPR audit trail).
 */
final class ConsentRecord
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $userId,
        public readonly ?string $sessionId,
        public readonly string $checkboxId,
        public readonly CheckboxContext $context,
        public readonly bool $consented,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param object $row Database row.
     */
    public static function fromRow(object $row): self
    {
        return new self(
            id: (int) $row->id,
            userId: $row->user_id !== null ? (int) $row->user_id : null,
            sessionId: $row->session_id,
            checkboxId: (string) $row->checkbox_id,
            context: CheckboxContext::from($row->context),
            consented: (bool) $row->consented,
            ipAddress: $row->ip_address,
            userAgent: $row->user_agent,
            createdAt: new \DateTimeImmutable($row->created_at),
        );
    }
}
