<?php

declare(strict_types=1);

/**
 * Polski uninstall handler.
 *
 * Removes all plugin data: custom tables, options, post meta, taxonomies, scheduled events.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

// Drop custom tables.
$tables = [
    $wpdb->prefix . 'polski_price_history',
    $wpdb->prefix . 'polski_consent_log',
    $wpdb->prefix . 'polski_withdrawals',
    $wpdb->prefix . 'polski_migrations',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// Remove plugin options.
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'polski\_%'");

// Remove product meta.
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_polski\_%'");

// Remove order meta (HPOS).
if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wc_orders_meta'") !== null) {
    $wpdb->query("DELETE FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key LIKE '\_polski\_%'");
}

// Remove custom taxonomy terms.
$taxonomies = [
    'polski_delivery_time',
    'polski_manufacturer',
    'polski_unit',
    'polski_allergen',
    'polski_nutrient',
];

foreach ($taxonomies as $taxonomy) {
    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids']);

    if (is_array($terms)) {
        foreach ($terms as $termId) {
            wp_delete_term((int) $termId, $taxonomy);
        }
    }
}

// Clear scheduled events.
wp_clear_scheduled_hook('polski_daily_maintenance');

// Flush rewrite rules.
flush_rewrite_rules();
