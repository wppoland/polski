<?php

declare(strict_types=1);

namespace Spolszczony;

/**
 * Handles plugin deactivation: clears scheduled events.
 *
 * Does NOT drop tables or delete options — that happens in uninstall.php.
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('spolszczony_daily_maintenance');
        flush_rewrite_rules();
    }
}
