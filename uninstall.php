<?php

declare(strict_types=1);

/**
 * Polski uninstall handler.
 *
 * Removes all plugin data: custom tables, options, post meta, taxonomies, scheduled events.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

$polski_general_settings = get_option('polski_general', []);
$polski_general_settings = is_array($polski_general_settings) ? $polski_general_settings : [];

$polski_delete_data = (bool) apply_filters(
    'polski/uninstall/delete_data',
    (bool) ($polski_general_settings['remove_data_on_uninstall'] ?? false),
);

if (! $polski_delete_data) {
    return;
}

// Drop custom tables.
$polski_tables = [
    $wpdb->prefix . 'polski_price_history',
    $wpdb->prefix . 'polski_consent_log',
    $wpdb->prefix . 'polski_withdrawals',
    $wpdb->prefix . 'polski_wishlist_items',
    $wpdb->prefix . 'polski_compare_items',
    $wpdb->prefix . 'polski_waitlist',
    $wpdb->prefix . 'polski_dsa_reports',
    $wpdb->prefix . 'polski_migrations',
];

foreach ($polski_tables as $polski_table) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall cleanup of custom plugin tables, prepared statement.
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $polski_table));
}

// Remove plugin options.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup; bulk delete of plugin options, prepared statement.
$wpdb->query(
    $wpdb->prepare(
        'DELETE FROM %i WHERE option_name LIKE %s',
        $wpdb->options,
        'polski\_%',
    ),
);

// Remove product meta.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup; bulk delete of plugin post meta, prepared statement.
$wpdb->query(
    $wpdb->prepare(
        'DELETE FROM %i WHERE meta_key LIKE %s',
        $wpdb->postmeta,
        '\_polski\_%',
    ),
);

// Remove order meta (HPOS).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup; schema probe on HPOS table, prepared statement.
if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'wc_orders_meta')) !== null) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup; bulk delete of HPOS order meta, prepared statement.
    $wpdb->query(
        $wpdb->prepare(
            'DELETE FROM %i WHERE meta_key LIKE %s',
            $wpdb->prefix . 'wc_orders_meta',
            '\_polski\_%',
        ),
    );
}

// Remove custom taxonomy terms.
$polski_taxonomies = [
    'polski_delivery_time',
    'polski_manufacturer',
    'polski_unit',
    'polski_allergen',
    'polski_nutrient',
];

foreach ($polski_taxonomies as $taxonomy) {
    $polski_terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids']);

    if (is_array($polski_terms)) {
        foreach ($polski_terms as $polski_term_id) {
            wp_delete_term((int) $polski_term_id, $taxonomy);
        }
    }
}

// Clear scheduled events.
wp_clear_scheduled_hook('polski_daily_maintenance');

// Flush rewrite rules.
flush_rewrite_rules();
