<?php

declare(strict_types=1);

namespace Polski\Migration;

use Polski\Contract\Migration;

defined('ABSPATH') || exit;

/**
 * Storage for issued VAT invoices.
 *
 * An invoice is not a view over an order. Once issued it is a fixed document:
 * the seller's details, the buyer's details, the lines and the VAT breakdown are
 * whatever they were on the day it was issued, and a later edit to the order,
 * the product name or the shop's own address must not change it. That is why
 * the whole document is snapshotted here rather than rebuilt on demand.
 *
 * The number carries a UNIQUE index. Sequential numbering with no gaps is a
 * legal requirement, and a unique key is the only thing that actually holds
 * under two admins pressing the button at the same moment.
 */
final class Migration_2_8_0 implements Migration
{
    public const VERSION = '2.8.0';

    public function run(): void
    {
        global $wpdb;

        $table          = $wpdb->prefix . 'polski_invoices';
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
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
            PRIMARY KEY (id),
            UNIQUE KEY uniq_number (number),
            UNIQUE KEY uniq_series_seq_year (series, sequence, year),
            INDEX idx_order (order_id)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);
    }
}
