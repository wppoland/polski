<?php

declare(strict_types=1);
namespace Polski;

defined('ABSPATH') || exit;
/**
 * Handles plugin activation: creates custom tables, sets default options, runs migrations.
 */
final class Activator
{
    public static function activate(): void
    {
        self::createTables();
        self::setDefaults();
        self::runMigrations();
        self::scheduleEvents();

        update_option('polski_version', VERSION);
        update_option('polski_activated_at', time());

        /**
         * Fires after Polski is activated for the first time.
         */
        do_action('polski/activated');

        flush_rewrite_rules();
    }

    private static function createTables(): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $sql = [
            // Omnibus directive price history.
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}polski_price_history (
                id BIGINT UNSIGNED AUTO_INCREMENT,
                product_id BIGINT UNSIGNED NOT NULL,
                price DECIMAL(19,4) NOT NULL,
                sale_price DECIMAL(19,4) DEFAULT NULL,
                price_type VARCHAR(20) NOT NULL DEFAULT 'regular',
                currency VARCHAR(3) NOT NULL DEFAULT 'PLN',
                recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_product_date (product_id, recorded_at),
                INDEX idx_product_type (product_id, price_type)
            ) {$charsetCollate};",

            // Consent / legal checkbox log.
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}polski_consent_log (
                id BIGINT UNSIGNED AUTO_INCREMENT,
                user_id BIGINT UNSIGNED DEFAULT NULL,
                session_id VARCHAR(64) DEFAULT NULL,
                checkbox_id VARCHAR(100) NOT NULL,
                context VARCHAR(50) NOT NULL,
                consented TINYINT(1) NOT NULL DEFAULT 1,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_user (user_id),
                INDEX idx_checkbox_context (checkbox_id, context)
            ) {$charsetCollate};",

            // Withdrawal requests.
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}polski_withdrawals (
                id BIGINT UNSIGNED AUTO_INCREMENT,
                order_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'requested',
                reason TEXT DEFAULT NULL,
                items_json JSON DEFAULT NULL,
                requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                confirmed_at DATETIME DEFAULT NULL,
                completed_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                INDEX idx_order (order_id),
                INDEX idx_status (status)
            ) {$charsetCollate};",

            // Migration tracking.
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}polski_migrations (
                id INT UNSIGNED AUTO_INCREMENT,
                version VARCHAR(20) NOT NULL,
                executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_version (version)
            ) {$charsetCollate};",
        ];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ($sql as $query) {
            dbDelta($query);
        }
    }

    private static function setDefaults(): void
    {
        $defaultsFile = PLUGIN_DIR . '/config/defaults.php';

        if (! file_exists($defaultsFile)) {
            return;
        }

        /** @var array<string, mixed> $defaults */
        $defaults = require $defaultsFile;

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                update_option($key, $value);
            }
        }
    }

    private static function runMigrations(): void
    {
        $migrator = new Migrator();
        $migrator->runPending();
    }

    private static function scheduleEvents(): void
    {
        if (! wp_next_scheduled('polski_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'polski_daily_maintenance');
        }
    }
}
