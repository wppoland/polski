<?php

declare(strict_types=1);

/**
 * Polski uninstall handler.
 *
 * Removes all plugin data: custom tables, options, post meta, user meta,
 * taxonomies, scheduled events.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

// The dismissed state of the PRO banner is per-user admin chrome, not shop
// data, so it is removed before either guard below. Those guards exist to
// protect a merchant's own content when they want to reinstall; a notice one
// admin clicked away is not that, and leaving it behind means an uninstall
// never really removes the plugin. User meta is global, hence delete_metadata
// with $delete_all rather than delete_user_meta for the current user.
delete_metadata('user', 0, 'polski_pro_banner_dismissed', '', true);

// The plugin_data module governs whether uninstall may delete anything. There
// is no autoloader in this context, so read the option directly.
// array_key_exists, not isset or empty: a stored false is a real answer and
// must not be read as "not configured", or the guard inverts and the delete
// setting silently stops being honoured on every store.
$polski_modules = get_option('polski_modules', []);

if (is_array($polski_modules)
    && array_key_exists('plugin_data', $polski_modules)
    && ! $polski_modules['plugin_data']) {
    return;
}

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

// PRO keeps some data under the plain polski_ prefix (config/pro-owned-keys.php).
// While PRO is installed, active or not, that data is PRO's to remove through
// its own uninstall cleanup; with no PRO on disk it is ours, since six of those
// options are seeded by config/defaults.php. Matched on the text domain, so a
// renamed plugin folder still counts. A bulk Delete uninstalls both plugins in
// one request and core clears its cached plugin list only after the loop, so
// read the folders fresh.
if (! function_exists('get_plugins')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
wp_cache_delete('plugins', 'plugins');

$polski_pro_installed = in_array('polski-pro', array_column(get_plugins(), 'TextDomain'), true);
$polski_pro_keys      = $polski_pro_installed
    ? require __DIR__ . '/config/pro-owned-keys.php'
    : ['options' => [], 'meta' => [], 'tables' => []];

// Drop custom tables.
$polski_tables = [
    $wpdb->prefix . 'polski_price_history',
    $wpdb->prefix . 'polski_consent_log',
    $wpdb->prefix . 'polski_withdrawals',
    $wpdb->prefix . 'polski_wishlist_items',
    $wpdb->prefix . 'polski_compare_items',
    $wpdb->prefix . 'polski_waitlist',
    $wpdb->prefix . 'polski_dsa_reports',
    $wpdb->prefix . 'polski_returns',
    $wpdb->prefix . 'polski_withdrawal_items',
    $wpdb->prefix . 'polski_cra_incidents',
    $wpdb->prefix . 'polski_invoices',
    $wpdb->prefix . 'polski_invoice_items',
    $wpdb->prefix . 'polski_migrations',
];

// A table PRO writes to as well stays while PRO is installed; whichever
// plugin is uninstalled last drops it.
foreach (array_diff($polski_tables, array_map(static fn (string $t): string => $wpdb->prefix . $t, $polski_pro_keys['tables'])) as $polski_table) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall cleanup of custom plugin tables, prepared statement.
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $polski_table));
}

// Appended to a meta_key / option_name filter; an empty list adds nothing.
$polski_not_in = static function (string $column, array $keys) use ($wpdb): string {
    if ($keys === []) {
        return '';
    }

    $polski_placeholders = implode(', ', array_fill(0, count($keys), '%s'));

    // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built above, one per key.
    return $wpdb->prepare(" AND {$column} NOT IN ({$polski_placeholders})", ...$keys);
};

// Remove plugin options. polski_pro_* belongs to the PRO add-on, which keeps
// its settings when only FREE is uninstalled.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Uninstall cleanup; bulk delete of plugin options, prepared statement plus a prepared NOT IN.
$wpdb->query(
    $wpdb->prepare(
        'DELETE FROM %i WHERE option_name LIKE %s AND option_name NOT LIKE %s',
        $wpdb->options,
        'polski\_%',
        'polski\_pro\_%',
    ) . $polski_not_in('option_name', $polski_pro_keys['options']),
);

// Remove product meta.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Uninstall cleanup; bulk delete of plugin post meta, prepared statement plus a prepared NOT IN.
$wpdb->query(
    $wpdb->prepare(
        'DELETE FROM %i WHERE meta_key LIKE %s',
        $wpdb->postmeta,
        '\_polski\_%',
    ) . $polski_not_in('meta_key', $polski_pro_keys['meta']),
);

// Remove order meta (HPOS).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup; schema probe on HPOS table, prepared statement.
if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'wc_orders_meta')) !== null) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Uninstall cleanup; bulk delete of HPOS order meta, prepared statement plus a prepared NOT IN.
    $wpdb->query(
        $wpdb->prepare(
            'DELETE FROM %i WHERE meta_key LIKE %s',
            $wpdb->prefix . 'wc_orders_meta',
            '\_polski\_%',
        ) . $polski_not_in('meta_key', $polski_pro_keys['meta']),
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

// Clear scheduled events. The list lives on Deactivator so the two paths cannot
// disagree; uninstall.php runs without the plugin booted, so the file is required
// directly rather than relying on the autoloader.
require_once __DIR__ . '/src/Deactivator.php';
foreach (\Polski\Deactivator::CRON_HOOKS as $polski_cron_hook) {
    wp_clear_scheduled_hook($polski_cron_hook);
}

// Flush rewrite rules.
flush_rewrite_rules();
