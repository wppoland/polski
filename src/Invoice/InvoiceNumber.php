<?php

declare(strict_types=1);

namespace Polski\Invoice;

defined('ABSPATH') || exit;

/**
 * How an invoice number is written.
 *
 * `FV/1/2026`, restarting each year. Deliberately one fixed format: a merchant
 * who needs a different scheme needs it applied consistently across corrections,
 * proformas and the tax filings that reference them, which is more than this
 * plugin issues.
 */
final class InvoiceNumber
{
    public const DEFAULT_SERIES = 'FV';

    public static function format(string $series, int $sequence, int $year): string
    {
        return sprintf('%s/%d/%d', self::sanitiseSeries($series), max(1, $sequence), $year);
    }

    /**
     * A series is part of a legal document number, so keep it to characters that
     * survive a filename, a URL and an accountant's spreadsheet unchanged.
     */
    public static function sanitiseSeries(string $series): string
    {
        $clean = strtoupper(trim($series));
        $clean = (string) preg_replace('/[^A-Z0-9-]/', '', $clean);

        return '' !== $clean ? substr($clean, 0, 16) : self::DEFAULT_SERIES;
    }
}
