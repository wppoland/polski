<?php

declare(strict_types=1);
namespace Polski\Migration;

use Polski\Contract\Migration;

defined('ABSPATH') || exit;

/**
 * Migration 2.1.0: Create CRA incident reports table.
 */
final class Migration_2_1_0 implements Migration
{
    public const VERSION = '2.1.0';

    public function run(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $wpdb->prefix . 'polski_cra_incidents';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            kind VARCHAR(30) NOT NULL DEFAULT 'vulnerability',
            severity VARCHAR(20) NOT NULL DEFAULT 'medium',
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            title VARCHAR(255) NOT NULL DEFAULT '',
            summary TEXT NOT NULL,
            affected_component VARCHAR(255) NOT NULL DEFAULT '',
            affected_versions VARCHAR(255) NOT NULL DEFAULT '',
            discovered_at DATETIME NOT NULL,
            deadline_at DATETIME NOT NULL,
            notified_at DATETIME DEFAULT NULL,
            resolved_at DATETIME DEFAULT NULL,
            reporter VARCHAR(191) NOT NULL DEFAULT '',
            reference_id VARCHAR(100) NOT NULL DEFAULT '',
            payload_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_status (status),
            INDEX idx_deadline (deadline_at),
            INDEX idx_severity (severity)
        ) {$charset};";

        dbDelta($sql);
    }
}
