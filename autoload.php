<?php

declare(strict_types=1);

defined('ABSPATH') || exit;
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Polski\\' => __DIR__ . '/src/',
        'WPPoland\\StorefrontKit\\' => __DIR__ . '/packages/storefront-kit/src/',
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
