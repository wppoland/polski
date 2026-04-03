<?php

declare(strict_types=1);

namespace Polski\Contract;

/**
 * Extension point for accounting integrations.
 */
interface AccountingProvider
{
    public function id(): string;

    public function name(): string;

    public function isConfigured(): bool;

    /**
     * @param array<string, mixed> $invoiceData
     */
    public function syncInvoice(array $invoiceData): bool;
}
