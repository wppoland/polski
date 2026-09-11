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
    /**
     * Every event this plugin schedules, in one place.
     *
     * They used to be listed by hand here and again in uninstall.php, and the
     * two lists drifted: the CRA incident check was in neither, so an hourly
     * event outlived the plugin that created it and fired against a missing
     * callback forever. uninstall.php reads this constant rather than keeping
     * its own copy.
     */
    public const CRON_HOOKS = [
        'polski_daily_maintenance',
        'polski_store_health_check',
        'polski_cra_incident_deadline_check',
    ];

    public static function deactivate(): void
    {
        foreach (self::CRON_HOOKS as $hook) {
            wp_clear_scheduled_hook($hook);
        }
        flush_rewrite_rules();
    }
}
