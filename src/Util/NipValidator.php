<?php

declare(strict_types=1);

namespace Polski\Util;

defined('ABSPATH') || exit;

/**
 * Polish NIP (Tax Identification Number) validator.
 *
 * Pure static utility implementing the public checksum algorithm. The
 * algorithm is documented by the Polish tax administration; there is no
 * licensing concern with shipping it in the free plugin.
 *
 * NIP format: 10 digits, optionally separated by hyphens or spaces
 *   ("123-456-78-90", "1234567890", "123 456 78 90").
 */
final class NipValidator
{
    /** @var array<int, int> NIP checksum weights for digits 1..9. */
    private const WEIGHTS = [6, 5, 7, 2, 3, 4, 5, 6, 7];

    /**
     * Strip whitespace, hyphens and a leading "PL" country prefix.
     */
    public static function normalize(string $nip): string
    {
        $stripped = preg_replace('/[\s\-]/', '', $nip) ?? '';
        if (str_starts_with(strtoupper($stripped), 'PL')) {
            $stripped = substr($stripped, 2);
        }

        return $stripped;
    }

    /**
     * Validate a Polish NIP using the official checksum algorithm.
     */
    public static function isValid(string $nip): bool
    {
        $normalized = self::normalize($nip);

        if (strlen($normalized) !== 10 || ! ctype_digit($normalized)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $normalized[$i] * self::WEIGHTS[$i];
        }

        $checkDigit = $sum % 11;
        if ($checkDigit === 10) {
            return false;
        }

        return $checkDigit === (int) $normalized[9];
    }

    /**
     * Format a normalized NIP as 123-456-78-90 (the canonical display format).
     * Returns the input unchanged if it is not a valid 10-digit string.
     */
    public static function format(string $nip): string
    {
        $normalized = self::normalize($nip);
        if (strlen($normalized) !== 10 || ! ctype_digit($normalized)) {
            return $nip;
        }

        return substr($normalized, 0, 3) . '-'
            . substr($normalized, 3, 3) . '-'
            . substr($normalized, 6, 2) . '-'
            . substr($normalized, 8, 2);
    }
}
