<?php

declare(strict_types=1);
namespace Polski;

defined('ABSPATH') || exit;
/**
 * Handles plugin deactivation: clears scheduled events.
 *
 * Does NOT drop tables or delete options - that happens in uninstall.php.
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('polski_daily_maintenance');
        wp_clear_scheduled_hook('polski_store_health_check');
        flush_rewrite_rules();
    }
}
