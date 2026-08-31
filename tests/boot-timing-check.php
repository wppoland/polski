<?php

declare(strict_types=1);

/**
 * Two failures reported from a clean WooCommerce 11 install, both invisible to
 * every existing test, both a hook that can never run:
 *
 *  1. Polski boots on `init` priority 0. WooCommerce registers its own `init`
 *     callback while its plugin file is still being included, so
 *     `woocommerce_init` has ALWAYS fired before we get here. Three services
 *     registered their block checkout fields on it, which meant the NIP field,
 *     the B2B fields and the PRO invoice field simply never appeared.
 *
 *  2. `admin_post_polski_save_module_settings` had two handlers. Both redirect
 *     and exit, so the first one registered silently won, and the winner only
 *     wrote back the keys present in $_POST. An unticked checkbox is absent
 *     from $_POST, so no legal checkbox could ever be switched off.
 *
 * Neither shape produces an error, a warning or a failing assertion, which is
 * why this is a grep and not a unit test. Run: php tests/boot-timing-check.php
 */

$root = dirname(__DIR__);
$failures = [];

/** @return list<string> */
$phpFiles = static function (string $dir): array {
    if (! is_dir($dir)) {
        return [];
    }

    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $out[] = $file->getPathname();
        }
    }

    return $out;
};

$files = $phpFiles($root . '/src');

// PRO sits beside FREE in a dev checkout and boots through the same handshake,
// so the same dead hook is possible there (it was, in InvoiceCheckoutFields).
$files = array_merge($files, $phpFiles(dirname($root) . '/polski-pro/src'));

// 1. woocommerce_init is spent by the time we boot.
foreach ($files as $file) {
    $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];

    foreach ($lines as $i => $line) {
        if (! preg_match('/add_action\(\s*[\'"]woocommerce_init[\'"]/', $line)) {
            continue;
        }

        // An add_action inside an `else` branch of a did_action() guard is the
        // fix, not the bug.
        $context = implode("\n", array_slice($lines, max(0, $i - 6), 6));

        if (str_contains($context, "did_action('woocommerce_init')")) {
            continue;
        }

        {
            $failures[] = sprintf(
                '%s:%d hooks woocommerce_init, which has already fired when Polski boots on init:0. '
                    . 'Guard with did_action(\'woocommerce_init\') and call the method directly.',
                str_replace($root . '/', '', $file),
                $i + 1,
            );
        }
    }
}

// 2. One handler per admin_post action.
$handlers = [];

foreach ($files as $file) {
    $source = (string) file_get_contents($file);

    if (preg_match_all('/add_action\(\s*[\'"](admin_post_[a-z0-9_]+)[\'"]/i', $source, $m)) {
        foreach ($m[1] as $action) {
            $handlers[$action][] = str_replace($root . '/', '', $file);
        }
    }
}

foreach ($handlers as $action => $where) {
    if (count($where) > 1) {
        $failures[] = sprintf(
            '%s has %d handlers (%s). Both redirect and exit, so the first one registered wins '
                . 'silently and the other is dead code.',
            $action,
            count($where),
            implode(', ', $where),
        );
    }
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n" . implode("\n", array_map(static fn (string $f): string => '  - ' . $f, $failures)) . "\n");
    exit(1);
}

echo "OK: no dead woocommerce_init hooks, no duplicated admin_post handlers.\n";
