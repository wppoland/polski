<?php

declare(strict_types=1);

/**
 * PHPStan-only stubs for WP-CLI (not Composer-installed).
 * Loaded via .phpstan.neon bootstrapFiles — never autoloaded in WordPress.
 *
 * @see https://make.wordpress.org/cli/handbook/
 */

namespace {
    /**
     * Minimal API surface used by `src/CLI/PolskiCommand.php`.
     */
    final class WP_CLI
    {
        /**
         * @param callable-string|class-string|callable|object $callable
         * @param array<string, mixed>                        $args
         */
        public static function add_command(string $name, $callable, array $args = []): void
        {
        }

        public static function log(string $message): void
        {
        }

        public static function success(string $message): void
        {
        }

        /**
         * @phpstan-return never
         */
        public static function error(string $message, bool $exit = true): void
        {
            exit;
        }

        public static function warning(string $message): void
        {
        }

        public static function line(string $message = ''): void
        {
        }
    }
}

namespace WP_CLI\Utils {
    /**
     * @param array<int|string, array<string, mixed>> $items
     * @param list<string>                            $fields
     */
    function format_items(string $format, array $items, array $fields): void
    {
    }
}

