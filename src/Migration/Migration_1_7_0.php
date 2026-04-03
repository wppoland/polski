<?php

declare(strict_types=1);

namespace Polski\Migration;

/**
 * Adds storage for affiliate accounts and referrals.
 */
final class Migration_1_7_0
{
    public const VERSION = '1.7.0';

    public function run(): void
    {
        global $wpdb;

        $affiliatesTable = $wpdb->prefix . 'polski_affiliates';
        $referralsTable = $wpdb->prefix . 'polski_affiliate_referrals';
        $charsetCollate = $wpdb->get_charset_collate();

        $sqlAffiliates = "CREATE TABLE {$affiliatesTable} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            token VARCHAR(64) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_user (user_id),
            UNIQUE KEY uk_token (token)
        ) {$charsetCollate};";

        $sqlReferrals = "CREATE TABLE {$referralsTable} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            affiliate_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            customer_email VARCHAR(191) NOT NULL,
            order_total DECIMAL(19,4) NOT NULL DEFAULT 0,
            commission_amount DECIMAL(19,4) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_affiliate_order (affiliate_id, order_id),
            INDEX idx_affiliate (affiliate_id),
            INDEX idx_status (status)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sqlAffiliates);
        dbDelta($sqlReferrals);
    }
}
