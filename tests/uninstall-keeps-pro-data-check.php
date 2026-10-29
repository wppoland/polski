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
 * and reads which keys the DELETE statements exclude. When a polski-pro
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

function get_option($name, $default = false)
{
    return $name === 'polski_general' ? ['remove_data_on_uninstall' => true] : $default;
}
function apply_filters($hook, $value)
{
    return $value;
}
function get_plugins()
{
    $plugins = ['polski/polski.php' => ['TextDomain' => 'polski']];
    if ($GLOBALS['pro_installed']) {
        $plugins['some-folder/polski-pro.php'] = ['TextDomain' => 'polski-pro'];
    }

    return $plugins;
}
function delete_metadata(...$args)
{
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
    return 0;
}
function flush_rewrite_rules()
{
}

final class FakeWpdb
{
    public string $prefix = 'wp_';
    public string $options = 'wp_options';
    public string $postmeta = 'wp_postmeta';
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
function run_uninstall(bool $proInstalled): array
{
    global $wpdb;
    $GLOBALS['pro_installed'] = $proInstalled;
    $wpdb = new FakeWpdb();
    include dirname(__DIR__) . '/uninstall.php';

    return array_values(array_filter($wpdb->queries, static fn (string $q): bool => str_starts_with($q, 'DELETE')));
}

/** Keys the statement on $table keeps out of its NOT IN list. */
function excluded(array $queries, string $table): array
{
    foreach ($queries as $q) {
        if (str_contains($q, "`{$table}`")) {
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
