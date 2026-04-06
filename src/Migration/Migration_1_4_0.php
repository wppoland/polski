<?php

declare(strict_types=1);
namespace Polski\Migration;

use Polski\Contract\Migration;

defined('ABSPATH') || exit;
/**
 * Adds storage for waitlist subscriptions.
 */
final class Migration_1_4_0 implements Migration
{
    public const VERSION = '1.4.0';

    public function run(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'polski_waitlist';
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(191) NOT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            notified TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            notified_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_product_email (product_id, email),
            INDEX idx_product_notified (product_id, notified)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);
    }
}
