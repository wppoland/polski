<?php

declare(strict_types=1);

namespace Polski\Util;

/**
 * Input sanitization helpers that wrap WordPress functions for type safety.
 */
final class Sanitizer
{
    /**
     * Sanitize a Polish NIP (tax identification number).
     * Removes all non-digit characters.
     */
    public static function nip(string $input): string
    {
        return preg_replace('/\D/', '', $input) ?? '';
    }

    /**
     * Sanitize a price value.
     */
    public static function price(string $input): float
    {
        // Accept both comma and dot as decimal separator.
        $normalized = str_replace(',', '.', $input);
        $cleaned = preg_replace('/[^\d.]/', '', $normalized) ?? '0';

        return (float) $cleaned;
    }

    /**
     * Sanitize a checkbox ID.
     */
    public static function checkboxId(string $input): string
    {
        return sanitize_key($input);
    }

    /**
     * Sanitize HTML content for legal pages (allows safe tags).
     */
    public static function legalHtml(string $input): string
    {
        return wp_kses_post($input);
    }

    /**
     * Sanitize a settings array against an allowed schema.
     *
     * @param array<string, mixed> $input   Raw input values.
     * @param array<string, mixed> $defaults Default/allowed keys with their default values.
     * @return array<string, mixed>
     */
    public static function settingsArray(array $input, array $defaults): array
    {
        $sanitized = [];

        foreach ($defaults as $key => $default) {
            if (! array_key_exists($key, $input)) {
                $sanitized[$key] = $default;
                continue;
            }

            $sanitized[$key] = match (true) {
                is_bool($default) => (bool) $input[$key],
                is_int($default) => (int) $input[$key],
                is_float($default) => (float) $input[$key],
                is_string($default) => sanitize_text_field((string) $input[$key]),
                is_array($default) => is_array($input[$key]) ? $input[$key] : $default,
                default => $default,
            };
        }

        return $sanitized;
    }
}
