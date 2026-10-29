<?php

declare(strict_types=1);

namespace Polski\Util;

defined('ABSPATH') || exit;

/**
 * CSV cells a spreadsheet must not run as a formula.
 *
 * Exports carry text shoppers typed (names, addresses, notes, withdrawal
 * reasons, a browser user agent). A cell starting with = + - @ or a tab/CR is
 * evaluated by Excel and LibreOffice when the merchant opens the file. Same
 * rule as WooCommerce core's WC_CSV_Exporter::escape_data(); numbers pass
 * untouched so amounts stay numeric.
 */
final class Csv
{
    /**
     * @param array<mixed> $row
     * @return list<string>
     */
    public static function row(array $row): array
    {
        return array_values(array_map([self::class, 'cell'], $row));
    }

    public static function cell(mixed $value): string
    {
        $value = is_scalar($value) ? (string) $value : '';

        if ($value === '' || is_numeric($value)) {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }
}
