<?php

declare(strict_types=1);

namespace Polski\Migration;

use Polski\Contract\Migration;

defined('ABSPATH') || exit;

/**
 * Adds storage for return / complaint (RMA) requests.
 */
final class Migration_2_4_0 implements Migration
{
    public const VERSION = '2.4.0';

    public function run(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'polski_returns';
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED DEFAULT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'complaint',
            status VARCHAR(20) NOT NULL DEFAULT 'submitted',
            reason TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX idx_order (order_id),
            INDEX idx_status (status)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);
    }
}
