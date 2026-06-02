<?php

declare(strict_types=1);

namespace Polski\Migration;

use Polski\Contract\Migration;

defined('ABSPATH') || exit;

/**
 * Adds a wording/version hash column to the consent log so the Consent Manager
 * banner can record which banner wording a visitor agreed to.
 */
final class Migration_2_5_0 implements Migration
{
    public const VERSION = '2.5.0';

    public function run(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'polski_consent_log';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-off schema migration on a custom plugin table.
        $hasColumn = $wpdb->get_results(
            $wpdb->prepare(
                'SHOW COLUMNS FROM %i LIKE %s',
                $table,
                'consent_version',
            ),
        );

        if (! empty($hasColumn)) {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-off schema migration on a custom plugin table; table name is interpolated via %i.
        $wpdb->query(
            $wpdb->prepare(
                'ALTER TABLE %i ADD COLUMN consent_version VARCHAR(64) DEFAULT NULL AFTER user_agent',
                $table,
            ),
        );
    }
}
