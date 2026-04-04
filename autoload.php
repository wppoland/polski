<?php

declare(strict_types=1);

defined('ABSPATH') || exit;
spl_autoload_register(static function (string $class): void {
    $prefix = 'Polski\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
