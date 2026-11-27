<?php

declare(strict_types=1);

namespace Polski\Migration;

use Polski\Contract\Migration;

defined('ABSPATH') || exit;

/**
 * Columns and the line table KSeF needs, now that KSeF lives in this plugin.
 *
 * Until now Polski PRO added them (its migrations 1.0.0 to 3.5.0) to the same
 * wp_polski_invoices table this plugin creates. A shop without PRO never had
 * them. dbDelta only adds what is missing, so on a shop that has PRO this
 * changes nothing. The (series, sequence, year) unique key is deliberately not
 * listed: PRO drops it, and dbDelta would put it back.
 *
 * existing-checked: Migration/Migration_2_8_0.php - it creates the base table for the free invoices and has already run on every shop; a new version is how this plugin adds schema.
 */
final class Migration_3_0_0 implements Migration
{
    public const VERSION = '3.0.0';

    public function run(): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $invoices = $wpdb->prefix . 'polski_invoices';
        $items = $wpdb->prefix . 'polski_invoice_items';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE {$invoices} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            original_invoice_id BIGINT UNSIGNED DEFAULT NULL,
            source_refund_id BIGINT UNSIGNED DEFAULT NULL,
            number VARCHAR(64) NOT NULL,
            series VARCHAR(16) NOT NULL DEFAULT 'FV',
            sequence INT UNSIGNED NOT NULL DEFAULT 0,
            year SMALLINT UNSIGNED NOT NULL,
            issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sold_at DATE DEFAULT NULL,
            due_at DATE DEFAULT NULL,
            currency VARCHAR(8) NOT NULL DEFAULT 'PLN',
            total_net DECIMAL(18,4) NOT NULL DEFAULT 0,
            total_tax DECIMAL(18,4) NOT NULL DEFAULT 0,
            total_gross DECIMAL(18,4) NOT NULL DEFAULT 0,
            snapshot LONGTEXT NOT NULL,
            type VARCHAR(30) NOT NULL DEFAULT 'faktura_vat',
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            nip_seller VARCHAR(20) DEFAULT NULL,
            nip_buyer VARCHAR(20) DEFAULT NULL,
            correction_reason TEXT DEFAULT NULL,
            net_total DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
            vat_total DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
            gross_total DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
            pdf_path TEXT DEFAULT NULL,
            ksef_reference VARCHAR(100) DEFAULT NULL,
            ksef_status VARCHAR(30) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            buyer_name VARCHAR(255) DEFAULT NULL,
            buyer_address VARCHAR(255) DEFAULT NULL,
            buyer_city VARCHAR(120) DEFAULT NULL,
            buyer_postcode VARCHAR(20) DEFAULT NULL,
            ksef_session VARCHAR(100) DEFAULT NULL,
            ksef_upo MEDIUMTEXT DEFAULT NULL,
            ksef_error TEXT DEFAULT NULL,
            ksef_environment VARCHAR(20) DEFAULT NULL,
            ksef_qr_url VARCHAR(255) DEFAULT NULL,
            correction_kind VARCHAR(20) DEFAULT NULL,
            correction_type TINYINT UNSIGNED DEFAULT NULL,
            ksef_xml MEDIUMTEXT DEFAULT NULL,
            ksef_offline_mode VARCHAR(20) DEFAULT NULL,
            ksef_offline_deadline DATETIME DEFAULT NULL,
            ksef_cert_url VARCHAR(1024) DEFAULT NULL,
            ksef_offline_hash VARCHAR(64) DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_number (number),
            KEY idx_order (order_id),
            KEY idx_type (type),
            KEY idx_status (status),
            KEY idx_ksef (ksef_reference),
            KEY idx_original_invoice (original_invoice_id),
            KEY idx_source_refund (source_refund_id)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$items} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            invoice_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            quantity DECIMAL(19,3) NOT NULL DEFAULT 1.000,
            unit VARCHAR(20) NOT NULL DEFAULT 'szt.',
            net_price DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
            vat_rate DECIMAL(5,2) NOT NULL DEFAULT 23.00,
            vat_amount DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
            gross_price DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY idx_invoice (invoice_id)
        ) {$charsetCollate};");
    }
}
