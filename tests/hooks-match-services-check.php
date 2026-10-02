<?php

declare(strict_types=1);

/**
 * A service that implements HasHooks only runs if config/hooks.php lists it.
 *
 * Plugin::registerHookSubscribers() walks hooks.php and nothing else. A class
 * that is defined, registered in config/services.php and never listed in
 * hooks.php is built by nobody and hooks nothing, with no error anywhere. That
 * is how the Elementor widgets, the CartFlows checkbox guard and the Google
 * feed attributes went dark in April 2026: a rewrite of hooks.php dropped them
 * while services.php kept them, and the docs went on describing the widgets.
 *
 * The reverse is checked too: a hooks.php entry without a services.php
 * definition is skipped by `$container->has()`, just as silently.
 *
 * Run: php tests/hooks-match-services-check.php
 */

$root = dirname(__DIR__);

/** Resolve short class names in a config file through its `use` imports. */
$classesIn = static function (string $file, string $pattern): array {
    $code = (string) file_get_contents($file);
    $imports = [];
    if (preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?;/m', $code, $m, PREG_SET_ORDER)) {
        foreach ($m as $use) {
            $alias = $use[2] ?? '';
            $imports[$alias !== '' ? $alias : substr(strrchr('\\' . $use[1], '\\'), 1)] = $use[1];
        }
    }
    preg_match_all($pattern, $code, $m);
    $out = [];
    foreach ($m[1] as $name) {
        $out[] = $name[0] === '\\' ? ltrim($name, '\\') : ($imports[$name] ?? 'Polski\\' . $name);
    }
    return array_unique($out);
};

$services = $classesIn($root . '/config/services.php', '/->singleton\(\s*([\w\\\\]+)::class/');
$hooks    = $classesIn($root . '/config/hooks.php', '/^\s*([\w\\\\]+)::class,/m');

// Every class under src/ that implements HasHooks.
$subscribers = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $code = (string) file_get_contents($file->getPathname());
    if (preg_match('/^namespace\s+([\w\\\\]+);/m', $code, $ns)
        && preg_match('/^(?:final\s+|abstract\s+)*class\s+(\w+)[^{]*\bimplements\b[^{]*\bHasHooks\b/m', $code, $cls)
    ) {
        $subscribers[] = $ns[1] . '\\' . $cls[1];
    }
}

// Deliberately absent from hooks.php, each with the reason.
$notListed = [
    'Polski\\Admin\\AdminPage' => 'AdminHooks::registerHooks() delegates to it',
    'Polski\\PageCompliance\\PageComplianceService' => 'registerHooks() is empty; REST and the admin page call it directly',
];

$failures = [];
foreach (array_intersect($subscribers, $services) as $class) {
    if (! in_array($class, $hooks, true) && ! isset($notListed[$class])) {
        $failures[] = "{$class} implements HasHooks and is in services.php, but hooks.php never lists it";
    }
}
foreach ($hooks as $class) {
    if (! in_array($class, $services, true)) {
        $failures[] = "{$class} is in hooks.php, but services.php never defines it, so it is skipped";
    }
}

if ($failures !== []) {
    echo "FAILED\n\n";
    foreach ($failures as $line) {
        echo '  ' . $line . "\n";
    }
    exit(1);
}

printf("ok  %d hook subscribers in hooks.php, all defined in services.php, none left out\n", count($hooks));
exit(0);
