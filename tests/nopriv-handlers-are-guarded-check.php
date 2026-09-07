<?php
/**
 * Fails when a hook reachable by a logged-out visitor still runs with its
 * module switched off.
 *
 * Services are booted unconditionally, so a module switch is only real if the
 * code behind it checks. The *_nopriv_* hooks are the sharp end of that: they
 * take input from anyone on the internet. The DSA report handler shipped with
 * no check at all, so with the module off an anonymous POST still tried to
 * insert into a table that may not exist and still sent mail, while the
 * reporter was redirected to a thank-you either way.
 *
 * A handler passes if registerHooks() refuses to register anything when the
 * module is off, or if the callback checks for itself. Both are used in this
 * codebase and both are correct; registering nothing is cheaper.
 *
 * Deliberately narrow. Plenty of classes serve several modules and guard per
 * callback, which is fine and not what this is looking for.
 *
 * Usage: php tests/nopriv-handlers-are-guarded-check.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$srcDir = $root . '/src';

$files = new RegexIterator(
    new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir)),
    '/\.php$/',
);

/** @var list<array{file: string, hook: string, method: string}> $handlers */
$handlers = [];

foreach ($files as $file) {
    $path = $file->getPathname();
    $src = (string) file_get_contents($path);

    // add_action('wp_ajax_nopriv_x', [$this, 'method']) and the admin_post twin.
    preg_match_all(
        "/add_action\(\s*'((?:wp_ajax|admin_post)_nopriv_[a-z0-9_]+)'\s*,\s*\[\s*\\\$this\s*,\s*'([A-Za-z0-9_]+)'/",
        $src,
        $m,
        PREG_SET_ORDER,
    );

    foreach ($m as $match) {
        $handlers[] = ['file' => $path, 'hook' => $match[1], 'method' => $match[2]];
    }
}

if ($handlers === []) {
    fwrite(STDERR, "FAIL: found 0 nopriv handlers, the check is not reading the tree it thinks it is.\n");
    exit(1);
}

/**
 * Body of a method, from its signature to the closing brace at method indent.
 */
$body = static function (string $src, string $method): string {
    $start = strpos($src, "function {$method}(");

    if ($start === false) {
        return '';
    }

    $end = strpos($src, "\n    }", $start);

    return $end === false ? substr($src, $start) : substr($src, $start, $end - $start);
};

$guards = '/isModuleEnabled\(|isEnabled\(\)/';
$unguarded = [];

foreach ($handlers as $handler) {
    $src = (string) file_get_contents($handler['file']);

    $registerGuarded = preg_match($guards, $body($src, 'registerHooks')) === 1;
    $callbackGuarded = preg_match($guards, $body($src, $handler['method'])) === 1;

    if (! $registerGuarded && ! $callbackGuarded) {
        $unguarded[] = sprintf(
            '%s -> %s() on %s',
            str_replace($root . '/', '', $handler['file']),
            $handler['method'],
            $handler['hook'],
        );
    }
}

if ($unguarded !== []) {
    fwrite(STDERR, "FAIL: public (nopriv) handlers that run with their module off:\n");
    foreach ($unguarded as $line) {
        fwrite(STDERR, "  - {$line}\n");
    }
    fwrite(STDERR, "\nEither return early from registerHooks() when the module is off, or check\n");
    fwrite(STDERR, "inside the callback. A switch nothing reads is not a switch.\n");
    exit(1);
}

printf("OK: %d nopriv handlers, all guarded by their module.\n", count($handlers));
