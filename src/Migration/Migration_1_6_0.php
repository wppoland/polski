<?php

declare(strict_types=1);

namespace Polski\Migration;

/**
 * Adds storage for subscriptions.
 */
final class Migration_1_6_0
{
    public const VERSION = '1.6.0';

    public function run(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'polski_subscriptions';
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            email VARCHAR(191) NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            product_name VARCHAR(191) NOT NULL,
            source_order_id BIGINT UNSIGNED NOT NULL,
            quantity INT UNSIGNED NOT NULL DEFAULT 1,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            interval_count INT UNSIGNED NOT NULL DEFAULT 1,
            interval_period VARCHAR(20) NOT NULL DEFAULT 'month',
            cycles_total INT UNSIGNED NOT NULL DEFAULT 0,
            cycles_completed INT UNSIGNED NOT NULL DEFAULT 0,
            recurring_amount DECIMAL(19,4) NOT NULL DEFAULT 0,
            signup_fee DECIMAL(19,4) NOT NULL DEFAULT 0,
            trial_days INT UNSIGNED NOT NULL DEFAULT 0,
            next_payment_at DATETIME DEFAULT NULL,
            last_payment_at DATETIME DEFAULT NULL,
            last_reminder_at DATETIME DEFAULT NULL,
            cancelled_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_user (user_id),
            INDEX idx_email (email),
            INDEX idx_status_next (status, next_payment_at),
            INDEX idx_source_order (source_order_id)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);
    }
}
