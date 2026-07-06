<?php

declare(strict_types=1);
namespace Polski\Migration;

use Polski\Contract\Migration;

defined('ABSPATH') || exit;

/**
 * Migration 2.2.0: Extend withdrawal schema for guest flow, manual registration,
 * multi-language declarations, refund tracking, and normalised item lines.
 */
final class Migration_2_2_0 implements Migration
{
    public const VERSION = '2.2.0';

    public function run(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $withdrawals = $wpdb->prefix . 'polski_withdrawals';
        $items = $wpdb->prefix . 'polski_withdrawal_items';

        $columns = [
            'channel' => "VARCHAR(20) NOT NULL DEFAULT 'online'",
            'created_by_user_id' => 'BIGINT UNSIGNED DEFAULT NULL',
            'registered_by_user_id' => 'BIGINT UNSIGNED DEFAULT NULL',
            'guest_email' => 'VARCHAR(191) DEFAULT NULL',
            'guest_token_hash' => 'CHAR(64) DEFAULT NULL',
            'guest_token_expires_at' => 'DATETIME DEFAULT NULL',
            'language_code' => "VARCHAR(5) NOT NULL DEFAULT 'pl'",
            'clock_started_at' => 'DATETIME DEFAULT NULL',
            'rejected_at' => 'DATETIME DEFAULT NULL',
            'rejected_reason' => 'TEXT DEFAULT NULL',
            'refund_id' => 'BIGINT UNSIGNED DEFAULT NULL',
            'refund_amount' => 'DECIMAL(19,4) DEFAULT NULL',
            'pdf_attachment_id' => 'BIGINT UNSIGNED DEFAULT NULL',
        ];

        foreach ($columns as $column => $definition) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema probe, identifiers from internal whitelist.
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
                    $withdrawals,
                    $column,
                ),
            );

            if ($exists !== null) {
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- ALTER TABLE on custom table, identifiers from whitelisted map and $wpdb->prefix.
            $wpdb->query("ALTER TABLE `{$withdrawals}` ADD COLUMN `{$column}` {$definition}");
        }

        $indexes = [
            'idx_channel' => '(channel)',
            'idx_guest_email' => '(guest_email)',
            'idx_guest_token' => '(guest_token_hash)',
            'idx_customer' => '(customer_id)',
        ];

        foreach ($indexes as $name => $definition) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema probe.
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s',
                    $withdrawals,
                    $name,
                ),
            );

            if ($exists !== null) {
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- ALTER TABLE on custom table, identifiers whitelisted and $wpdb->prefix.
            $wpdb->query("ALTER TABLE `{$withdrawals}` ADD INDEX `{$name}` {$definition}");
        }

        $sql = "CREATE TABLE IF NOT EXISTS {$items} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            withdrawal_id BIGINT UNSIGNED NOT NULL,
            order_item_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            variation_id BIGINT UNSIGNED DEFAULT NULL,
            quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
            line_subtotal DECIMAL(19,4) NOT NULL DEFAULT 0,
            line_total DECIMAL(19,4) NOT NULL DEFAULT 0,
            line_tax DECIMAL(19,4) NOT NULL DEFAULT 0,
            sku VARCHAR(100) DEFAULT NULL,
            name VARCHAR(255) NOT NULL DEFAULT '',
            attributes_json LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_withdrawal (withdrawal_id),
            INDEX idx_order_item (order_item_id),
            INDEX idx_product (product_id)
        ) {$charset};";

        dbDelta($sql);
    }
}
