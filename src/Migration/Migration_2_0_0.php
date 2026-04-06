<?php

declare(strict_types=1);
namespace Polski\Migration;

use Polski\Contract\Migration;

defined('ABSPATH') || exit;
/**
 * Migration 2.0.0: Create DSA reports table.
 */
final class Migration_2_0_0 implements Migration
{
    public const VERSION = '2.0.0';

    public function run(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = $wpdb->prefix . 'polski_dsa_reports';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            reporter_name VARCHAR(255) NOT NULL DEFAULT '',
            reporter_email VARCHAR(191) NOT NULL DEFAULT '',
            content_url TEXT NOT NULL,
            reason VARCHAR(100) NOT NULL DEFAULT '',
            description TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            admin_notes TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX idx_status (status)
        ) {$charset};";

        dbDelta($sql);
    }
}
