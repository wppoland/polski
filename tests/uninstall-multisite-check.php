<?php

declare(strict_types=1);

/**
 * Deleting the plugin on a network must clean every site that asked for it,
 * not only the one the uninstall runs in.
 *
 * Tables, options, meta and scheduled events live per site, and each site's
 * tables carry that site's prefix. The remove-data choice is a per-site
 * setting too: here sites 1 and 3 opted in and site 2 did not, so site 2 must
 * keep everything. Whether PRO is on disk is a network-wide fact and is read
 * once.
 *
 *   php tests/uninstall-multisite-check.php
 */

define('ABSPATH', sys_get_temp_dir() . '/');
define('WP_UNINSTALL_PLUGIN', 'polski/polski.php');

$GLOBALS['blog']  = 1;
$GLOBALS['lists'] = 0;
$GLOBALS['log']   = [];

function px(int $id): string
{
    return $id === 1 ? 'wp_' : "wp_{$id}_";
}
function is_multisite()
{
    return true;
}
function get_sites($args)
{
    return [1, 2, 3];
}
function switch_to_blog($id)
{
    $GLOBALS['blog']           = (int) $id;
    $GLOBALS['wpdb']->prefix   = px((int) $id);
    $GLOBALS['wpdb']->options  = px((int) $id) . 'options';
    $GLOBALS['wpdb']->postmeta = px((int) $id) . 'postmeta';
}
function restore_current_blog()
{
    switch_to_blog(1);
}
function get_option($name, $default = false)
{
    return $name === 'polski_general' ? ['remove_data_on_uninstall' => $GLOBALS['blog'] !== 2] : $default;
}
function apply_filters($hook, $value)
{
    return $value;
}
function get_plugins()
{
    ++$GLOBALS['lists'];

    return ['polski/polski.php' => ['TextDomain' => 'polski']];
}
function wp_cache_delete($key, $group = '')
{
    return true;
}
function delete_metadata(...$args)
{
    $GLOBALS['log']['user'][] = $args[2];

    return true;
}
function delete_option($name)
{
    $GLOBALS['log'][$GLOBALS['blog']][] = 'option:' . $name;

    return true;
}
function get_terms($args)
{
    return [];
}
function wp_delete_term($id, $tax)
{
    return true;
}
function wp_clear_scheduled_hook($hook)
{
    $GLOBALS['log'][$GLOBALS['blog']][] = 'cron:' . $hook;

    return 0;
}
function flush_rewrite_rules()
{
    $GLOBALS['log'][$GLOBALS['blog']][] = 'flush_rewrite_rules';
}

final class FakeWpdb
{
    public string $prefix   = 'wp_';
    public string $options  = 'wp_options';
    public string $postmeta = 'wp_postmeta';

    public function prepare(string $sql, ...$args): string
    {
        $i = 0;

        return (string) preg_replace_callback('/%[is]/', static function (array $m) use (&$i, $args): string {
            $v = (string) $args[$i++];

            return $m[0] === '%i' ? "`{$v}`" : "'" . addslashes($v) . "'";
        }, $sql);
    }

    public function query(string $sql): int
    {
        $GLOBALS['log'][$GLOBALS['blog']][] = $sql;

        return 0;
    }

    public function get_var(string $sql): ?string
    {
        return 'found';
    }
}

$GLOBALS['wpdb'] = new FakeWpdb();
include dirname(__DIR__) . '/uninstall.php';

require_once dirname(__DIR__) . '/src/Deactivator.php';

$fail = [];
foreach ([1, 3] as $site) {
    $p    = px($site);
    $done = $GLOBALS['log'][$site] ?? [];
    $sql  = implode("\n", $done);

    preg_match_all('/DROP TABLE IF EXISTS `([a-z0-9_]+)`/', $sql, $m);
    $foreign = array_filter($m[1], static fn (string $t): bool => ! str_starts_with($t, $p . 'polski_'));
    if (count($m[1]) !== 13 || $foreign !== []) {
        $fail[] = "site {$site}: dropped " . count($m[1]) . " tables, expected 13 named {$p}polski_*: " . implode(', ', $m[1]);
    }
    foreach (['options', 'postmeta', 'wc_orders_meta'] as $table) {
        if (! str_contains($sql, "DELETE FROM `{$p}{$table}`")) {
            $fail[] = "site {$site}: no DELETE on {$p}{$table}";
        }
    }
    foreach (\Polski\Deactivator::CRON_HOOKS as $hook) {
        if (! in_array('cron:' . $hook, $done, true)) {
            $fail[] = "site {$site}: {$hook} still scheduled";
        }
    }
    if (! in_array('option:rewrite_rules', $done, true)) {
        $fail[] = "site {$site}: rewrite rules not reset";
    }
    if (in_array('flush_rewrite_rules', $done, true)) {
        $fail[] = "site {$site}: flush_rewrite_rules() after switch_to_blog() writes the main site's rules into this site";
    }
}
if (($GLOBALS['log'][2] ?? []) !== []) {
    $fail[] = 'site 2 did not opt in and still lost: ' . implode(' | ', $GLOBALS['log'][2]);
}
if ($GLOBALS['lists'] !== 1) {
    $fail[] = "the plugin list was read {$GLOBALS['lists']} times, expected once for the whole network";
}
if (($GLOBALS['log']['user'] ?? []) !== ['polski_pro_banner_dismissed']) {
    $fail[] = 'the global user meta was not deleted exactly once';
}

if ($fail !== []) {
    foreach ($fail as $f) {
        fwrite(STDERR, "FAIL: {$f}\n");
    }
    exit(1);
}

echo "ok: uninstall cleans every site that opted in, with that site's table prefix\n";
