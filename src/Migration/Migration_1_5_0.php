<?php

declare(strict_types=1);

namespace Polski\Migration;

/**
 * Adds storage for gift cards and their ledger.
 */
final class Migration_1_5_0
{
    public const VERSION = '1.5.0';

    public function run(): void
    {
        global $wpdb;

        $cardsTable = $wpdb->prefix . 'polski_gift_cards';
        $transactionsTable = $wpdb->prefix . 'polski_gift_card_transactions';
        $charsetCollate = $wpdb->get_charset_collate();

        $sqlCards = "CREATE TABLE {$cardsTable} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            code VARCHAR(64) NOT NULL,
            initial_balance DECIMAL(19,4) NOT NULL,
            balance DECIMAL(19,4) NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'PLN',
            purchaser_user_id BIGINT UNSIGNED DEFAULT NULL,
            purchaser_email VARCHAR(191) NOT NULL,
            recipient_name VARCHAR(191) NOT NULL,
            recipient_email VARCHAR(191) NOT NULL,
            sender_name VARCHAR(191) NOT NULL,
            message TEXT DEFAULT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            expires_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_code (code),
            INDEX idx_order (order_id),
            INDEX idx_recipient_email (recipient_email),
            INDEX idx_status (status)
        ) {$charsetCollate};";

        $sqlTransactions = "CREATE TABLE {$transactionsTable} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            gift_card_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED DEFAULT NULL,
            type VARCHAR(20) NOT NULL,
            amount DECIMAL(19,4) NOT NULL,
            note VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_gift_card (gift_card_id),
            INDEX idx_order (order_id)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sqlCards);
        dbDelta($sqlTransactions);
    }
}
