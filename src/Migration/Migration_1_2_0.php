<?php

declare(strict_types=1);
namespace Polski\Migration;

use Polski\Contract\Migration;

defined('ABSPATH') || exit;
/**
 * Adds storage for wishlist items.
 */
final class Migration_1_2_0 implements Migration
{
    public const VERSION = '1.2.0';

    public function run(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'polski_wishlist_items';
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            session_id VARCHAR(64) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_product_user (product_id, user_id),
            UNIQUE KEY uk_product_session (product_id, session_id),
            INDEX idx_user (user_id),
            INDEX idx_session (session_id)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);
    }
}
