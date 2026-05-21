<?php

declare(strict_types=1);
namespace Polski\Migration;

use Polski\Contract\Migration;

defined('ABSPATH') || exit;

/**
 * Migration 2.3.0: extend the withdrawals table with two optional columns that
 * hold the WordPress AI Client classification of the free-text reason a
 * customer (or operator) provided when filing the declaration.
 *
 *  - ai_category   : enum-like short string (e.g. "defective", "wrong_item").
 *  - ai_confidence : DECIMAL(4,3) - provider confidence in 0..1, or NULL when
 *                    the provider did not return a score.
 *
 * Both columns are NULLable; rows persisted before the migration or without
 * an AI provider configured simply remain NULL.
 */
final class Migration_2_3_0 implements Migration
{
    public const VERSION = '2.3.0';

    public function run(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $wpdb->prefix . 'polski_withdrawals';

        $columns = [
            'ai_category' => 'VARCHAR(40) DEFAULT NULL',
            'ai_confidence' => 'DECIMAL(4,3) DEFAULT NULL',
        ];

        foreach ($columns as $column => $definition) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema probe.
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
                    $table,
                    $column,
                ),
            );

            if ($exists !== null) {
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ALTER TABLE; identifiers from whitelisted map above.
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }
}
