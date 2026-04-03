<?php

declare(strict_types=1);

namespace Polski\Migration;

/**
 * Adds storage for B2B quote requests.
 */
final class Migration_1_1_0
{
    public const VERSION = '1.1.0';

    public function run(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'polski_quote_requests';
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            variation_id BIGINT UNSIGNED DEFAULT NULL,
            customer_id BIGINT UNSIGNED DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            customer_name VARCHAR(191) NOT NULL,
            customer_email VARCHAR(191) NOT NULL,
            customer_phone VARCHAR(50) DEFAULT NULL,
            company_name VARCHAR(191) DEFAULT NULL,
            nip VARCHAR(20) DEFAULT NULL,
            quantity DECIMAL(19,3) NOT NULL DEFAULT 1.000,
            postcode VARCHAR(20) DEFAULT NULL,
            message TEXT DEFAULT NULL,
            source VARCHAR(50) NOT NULL DEFAULT 'product',
            source_url TEXT DEFAULT NULL,
            consented TINYINT(1) NOT NULL DEFAULT 0,
            meta_json LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_status (status),
            INDEX idx_product (product_id),
            INDEX idx_customer_email (customer_email)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);
    }
}
