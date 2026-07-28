<?php

declare(strict_types=1);
namespace Polski\Migration;

use Polski\Contract\Migration;

defined('ABSPATH') || exit;

/**
 * Migration 2.3.1: compound index on `polski_withdrawals.(status, ai_category)`
 * so the new operator-dashboard combined filter (status x ai_category) hits
 * a covering index instead of falling back to a filtered table scan on stores
 * with thousands of declarations.
 */
final class Migration_2_3_1 implements Migration
{
    public const VERSION = '2.3.1';

    public function run(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $wpdb->prefix . 'polski_withdrawals';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema probe.
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s',
                $table,
                'idx_status_ai_category',
            ),
        );

        if ($exists !== null) {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- ALTER TABLE on custom table; identifiers from whitelisted constants and $wpdb->prefix.
        $wpdb->query("ALTER TABLE `{$table}` ADD INDEX `idx_status_ai_category` (`status`, `ai_category`)");
    }
}
