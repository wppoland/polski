<?php

declare(strict_types=1);
namespace Polski\Contract;

defined('ABSPATH') || exit;
/**
 * Extension point for accounting integrations.
 */
interface AccountingProvider
{
    public function id(): string;

    public function name(): string;

    public function isConfigured(): bool;

    /**
     * Sync an invoice to the external accounting system.
     *
     * @param array<string, mixed> $invoiceData
     * @return array{success: bool, error: string|null}
     */
    public function syncInvoice(array $invoiceData): array;
}
