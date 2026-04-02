<?php

declare(strict_types=1);

namespace Spolszczony\Util;

/**
 * Polish-locale formatting utilities.
 */
final class Formatter
{
    /**
     * Format a price with Polish conventions (comma as decimal separator).
     */
    public static function price(float $amount, string $currency = 'PLN'): string
    {
        return wc_price($amount, ['currency' => $currency]);
    }

    /**
     * Format a VAT rate for display (e.g., "23%").
     */
    public static function vatRate(float $rate): string
    {
        $formatted = rtrim(rtrim(number_format($rate, 2, ',', ''), '0'), ',');
        return $formatted . '%';
    }

    /**
     * Format a delivery time range (e.g., "2-4 dni robocze").
     */
    public static function deliveryTimeRange(int $min, int $max, string $unit): string
    {
        if ($min === $max) {
            return sprintf('%d %s', $min, $unit);
        }

        return sprintf('%d-%d %s', $min, $max, $unit);
    }

    /**
     * Interpolate placeholders in a string.
     *
     * @param string               $template The template string with {placeholder} syntax.
     * @param array<string, string> $values   Replacement values.
     */
    public static function interpolate(string $template, array $values): string
    {
        $replacements = [];

        foreach ($values as $key => $value) {
            $replacements['{' . $key . '}'] = $value;
        }

        return strtr($template, $replacements);
    }
}
