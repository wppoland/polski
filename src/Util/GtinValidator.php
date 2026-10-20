<?php

declare(strict_types=1);
namespace Polski\Util;

defined('ABSPATH') || exit;

/**
 * Validates GTIN check digits without looking up product data.
 */
final class GtinValidator
{
    /**
     * Validate a GTIN-8, GTIN-12, GTIN-13 or GTIN-14 value.
     */
    public static function isValid(string $value): bool
    {
        if (! preg_match('/^(?:[0-9]{8}|[0-9]{12}|[0-9]{13}|[0-9]{14})$/D', $value)) {
            return false;
        }

        $checkDigit = (int) substr($value, -1);
        $payload = substr($value, 0, -1);
        $sum = 0;
        $weight = 3;

        for ($index = strlen($payload) - 1; $index >= 0; $index--) {
            $sum += (int) $payload[$index] * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return (10 - ($sum % 10)) % 10 === $checkDigit;
    }
}
