<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

// Prefer Composer's autoloader (covers Polski\, the wppoland/storefront-kit
// package, and runtime deps like league/html-to-markdown). The hand-written
// PSR-4 fallback below keeps Polski\ resolvable even if vendor/ is absent.
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (is_readable($composerAutoload)) {
    require_once $composerAutoload;
}

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Polski\\' => __DIR__ . '/src/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }

        return;
    }
});
