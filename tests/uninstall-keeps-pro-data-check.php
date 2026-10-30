<?php

declare(strict_types=1);

/**
 * Fails when uninstalling FREE would delete data Polski PRO owns.
 *
 * FREE's uninstall deletes every polski_% option and _polski_% meta row. PRO
 * keeps its gift cards, affiliates, subscriptions and about ten more settings
 * under that same prefix, so a merchant who removed FREE with PRO still
 * installed lost them. Until 1.43.8 only polski_pro_* was spared.
 *
 * Runs uninstall.php against a fake $wpdb twice, with and without PRO on disk,
 * and reads which keys the DELETE statements exclude. A third run does it the
 * way a bulk Delete of both plugins does: core's delete_plugins() uninstalls
 * them one after the other in one request, so the plugin list cached by the
 * first uninstall still names PRO although its folder is gone. When a polski-pro
 * checkout sits next to this one (or POLSKI_PRO_DIR points at one), it also
 * checks that config/pro-owned-keys.php and PRO's after_uninstall cleanup
 * still list the same keys, and that every plain polski_* option PRO declares
 * is on the list.
 *
 *   php tests/uninstall-keeps-pro-data-check.php
 */

define('ABSPATH', sys_get_temp_dir() . '/');
define('WP_UNINSTALL_PLUGIN', 'polski/polski.php');

$GLOBALS['pro_installed'] = false;
$GLOBALS['plugins_cache'] = null;

function get_option($name, $default = false)
{
    return $name === 'polski_general' ? ['remove_data_on_uninstall' => true] : $default;
}
function apply_filters($hook, $value)
{
    return $value;
}
// Like core: the list is cached for the request until wp_cache_delete('plugins', 'plugins').
function get_plugins()
{
    if ($GLOBALS['plugins_cache'] !== null) {
        return $GLOBALS['plugins_cache'];
    }
    $plugins = ['polski/polski.php' => ['TextDomain' => 'polski']];
    if ($GLOBALS['pro_installed']) {
        $plugins['some-folder/polski-pro.php'] = ['TextDomain' => 'polski-pro'];
    }

    return $GLOBALS['plugins_cache'] = $plugins;
}
function wp_cache_delete($key, $group = '')
{
    if ($key === 'plugins' && $group === 'plugins') {
        $GLOBALS['plugins_cache'] = null;
    }

    return true;
}
function delete_metadata(...$args)
{
    return true;
}
function wp_clear_scheduled_hook($hook)
{
    return 0;
}
function is_multisite()
{
    return false;
}
function delete_option($name)
{
    return true;
}

final class FakeWpdb
{
    public string $prefix = 'wp_';
    public string $options = 'wp_options';
    public string $postmeta = 'wp_postmeta';
    public string $terms = 'wp_terms';
    public string $term_taxonomy = 'wp_term_taxonomy';
    public string $term_relationships = 'wp_term_relationships';
    public string $termmeta = 'wp_termmeta';
    /** @var list<string> */
    public array $queries = [];

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
        $this->queries[] = $sql;

        return 0;
    }

    public function get_var(string $sql): ?string
    {
        return 'wp_wc_orders_meta';
    }
}

/** @return list<string> the DELETE statements uninstall.php issued */
function run_uninstall(bool $proInstalled, bool $staleCache = false): array
{
    global $wpdb;
    $GLOBALS['plugins_cache'] = null;
    if ($staleCache) {
        // A bulk delete listed PRO, uninstalled and deleted it first.
        $GLOBALS['pro_installed'] = true;
        get_plugins();
    }
    $GLOBALS['pro_installed'] = $proInstalled;
    $wpdb = new FakeWpdb();
    include dirname(__DIR__) . '/uninstall.php';

    return $wpdb->queries;
}

/** Tables the run dropped, without prefix. */
function dropped(array $queries): array
{
    preg_match_all('/DROP TABLE IF EXISTS `wp_([a-z0-9_]+)`/', implode("\n", $queries), $m);

    return $m[1];
}

/** Keys the statement on $table keeps out of its NOT IN list. */
function excluded(array $queries, string $table): array
{
    foreach ($queries as $q) {
        if (str_starts_with($q, 'DELETE') && str_contains($q, "`{$table}`")) {
            if (! preg_match('/NOT IN \(([^)]*)\)/', $q, $m)) {
                return [];
            }
            preg_match_all("/'([^']+)'/", $m[1], $keys);

            return $keys[1];
        }
    }
    throw new RuntimeException("no DELETE on {$table}");
}

$fail = [];
$ownedFile = dirname(__DIR__) . '/config/pro-owned-keys.php';
if (! is_file($ownedFile)) {
    fwrite(STDERR, "FAIL: config/pro-owned-keys.php is missing, so FREE uninstall has no list of PRO data to spare\n");
    exit(1);
}
$owned = require $ownedFile;

$with = run_uninstall(true);
foreach (['wp_options' => 'options', 'wp_postmeta' => 'meta', 'wp_wc_orders_meta' => 'meta'] as $table => $kind) {
    $missing = array_diff($owned[$kind], excluded($with, $table));
    if ($missing !== []) {
        $fail[] = "with PRO installed, {$table} delete still removes: " . implode(', ', $missing);
    }
}
$kept = array_intersect($owned['tables'], dropped($with));
if ($kept !== []) {
    $fail[] = 'with PRO installed, FREE uninstall drops a table PRO writes to: ' . implode(', ', $kept);
}
foreach (['polski_gift_cards', 'polski_affiliates', 'polski_subscriptions'] as $known) {
    if (! in_array($known, $owned['options'], true)) {
        $fail[] = "{$known} is a PRO option and is not on the list";
    }
}

$without = run_uninstall(false);
foreach (['wp_options', 'wp_postmeta', 'wp_wc_orders_meta'] as $table) {
    if (excluded($without, $table) !== []) {
        $fail[] = "with no PRO installed, {$table} delete still spares PRO keys, leaving FREE-seeded rows behind";
    }
}
$left = array_diff($owned['tables'], dropped($without));
if ($left !== []) {
    $fail[] = 'with no PRO installed, FREE uninstall leaves the shared table behind: ' . implode(', ', $left);
}

$bulk = run_uninstall(false, true);
foreach (['wp_options', 'wp_postmeta', 'wp_wc_orders_meta'] as $table) {
    if (excluded($bulk, $table) !== []) {
        $fail[] = "after a bulk delete removed PRO first, {$table} delete still spares PRO keys: the cached plugin list still named PRO";
    }
}
$left = array_diff($owned['tables'], dropped($bulk));
if ($left !== []) {
    $fail[] = 'after a bulk delete removed PRO first, FREE uninstall leaves the shared table behind: ' . implode(', ', $left);
}

// Cross-check against a polski-pro checkout when one is available.
$proDir = getenv('POLSKI_PRO_DIR') ?: dirname(__DIR__, 2) . '/polski-pro';
if (is_file($proDir . '/freemius-init.php')) {
    $cleanup = (string) file_get_contents($proDir . '/freemius-init.php');
    foreach (array_merge($owned['options'], $owned['meta']) as $key) {
        if (! str_contains($cleanup, "'{$key}'")) {
            $fail[] = "PRO after_uninstall never removes {$key}";
        }
    }

    $declared = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($proDir . '/src')) as $file) {
        if ($file->getExtension() === 'php'
            && preg_match_all("/OPTION\\s*=\\s*'(polski_[a-z0-9_]+)'/", (string) file_get_contents((string) $file), $m)) {
            $declared = array_merge($declared, $m[1]);
        }
    }
    // Every table a PRO migration creates has to be dropped by PRO's cleanup;
    // six were not until 1.29.10. A table shared with FREE is dropped only
    // while FREE is gone, so its name must appear there all the same.
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($proDir . '/src')) as $file) {
        $code = $file->getExtension() === 'php' ? (string) file_get_contents((string) $file) : '';
        if (str_contains($code, 'CREATE TABLE') && preg_match_all("/prefix\\s*\\.\\s*'(polski_[a-z0-9_]+)'/", $code, $m)) {
            foreach (array_unique($m[1]) as $table) {
                if (! str_contains($cleanup, "'{$table}'")) {
                    $fail[] = "PRO creates the table {$table} and after_uninstall never drops it";
                }
            }
        }
    }

    $proDefaults = (string) file_get_contents($proDir . '/config/defaults.php');
    preg_match_all("/^    '(polski_[a-z0-9_]+)' =>/m", $proDefaults, $m);
    $declared = array_merge($declared, $m[1]);

    foreach (array_unique($declared) as $key) {
        if (! str_starts_with($key, 'polski_pro_') && ! in_array($key, $owned['options'], true)) {
            $fail[] = "PRO declares the option {$key}, missing from config/pro-owned-keys.php";
        }
    }
    echo "  ok  cross-checked against {$proDir}\n";
} else {
    echo "  skip  no polski-pro checkout at {$proDir}; set POLSKI_PRO_DIR to cross-check\n";
}

if ($fail !== []) {
    foreach ($fail as $f) {
        fwrite(STDERR, "FAIL: {$f}\n");
    }
    exit(1);
}

echo "ok: FREE uninstall spares " . count($owned['options']) . ' PRO options and ' . count($owned['meta']) . " PRO meta keys while PRO is installed\n";
