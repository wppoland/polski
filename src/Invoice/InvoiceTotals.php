<?php

declare(strict_types=1);

namespace Polski\Invoice;

defined('ABSPATH') || exit;

/**
 * The money on an invoice, as stored.
 */
final class InvoiceTotals
{
    public function __construct(
        public readonly string $currency,
        public readonly float $net,
        public readonly float $tax,
        public readonly float $gross,
    ) {
    }
}
