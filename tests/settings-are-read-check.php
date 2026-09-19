<?php

declare(strict_types=1);

/**
 * Fails when the modules screen offers a setting that no code ever reads.
 *
 * A switch nobody reads is worse than a missing feature: the merchant ticks it,
 * sees nothing happen, and reports a bug. It cost us two support reports in a
 * week. `polski_omnibus|show_on_cart` was offered from the day the module
 * shipped and CartHooks was an empty stub saying "Phase 2".
 *
 * The check is deliberately crude: it looks for the sub-key as a quoted string
 * anywhere outside the screen that declares it. That is enough to catch a
 * setting with no reader at all, which is the failure that actually happens.
 * Keys assembled at runtime cannot be seen this way and are listed below with
 * the line that builds them.
 *
 * Usage: php tests/settings-are-read-check.php
 */

$root = dirname(__DIR__);

/**
 * Sub-keys built at runtime rather than written out, with where they are built.
 * Anything added here must name the file that assembles the key.
 *
 * @var array<string, string>
 */
$dynamic = [
    'amount_pet' => 'DepositService::amountForType() builds "amount_" . $type',
    'amount_can' => 'DepositService::amountForType() builds "amount_" . $type',
    'amount_glass' => 'DepositService::amountForType() builds "amount_" . $type',
];

$screen = $root . '/src/Admin/ModulesPage.php';
$declared = [];
preg_match_all(
    "/'key'\s*=>\s*'(polski_[a-z0-9_]+)\|([a-z0-9_]+)'/",
    (string) file_get_contents($screen),
    $matches,
    PREG_SET_ORDER,
);
foreach ($matches as $m) {
    $declared[$m[1] . '|' . $m[2]] = $m[2];
}

/**
 * Sources indexed per file, not concatenated.
 *
 * A single blob was not enough: a sub-key read for one module vouched for every
 * other module declaring the same name. `show_on_loop` is read from
 * `polski_brand`, and that alone was covering `polski_omnibus|show_on_loop`,
 * which nothing reads. A key counts as read only when one file mentions both
 * its option group and its sub-key.
 *
 * @var array<string, string> $sources
 */
$sources = [];
// The vendored storefront kit runs on every request this plugin serves, so a
// setting it reads is read. Leaving it out parked ten live waitlist settings on
// the accepted-debt list below as if nothing consumed them.
//
// It is indexed separately, because a kit file names no option group: taken as
// an ordinary source it would vouch for every group declaring the same sub-key,
// and its 'success_text' immediately cleared polski_dsa|success_text, which
// nothing reads. A kit read counts only for the groups whose own service hands
// its settings to the kit.
$kitDir = $root . '/vendor/wppoland/storefront-kit/src';
$kitSources = [];

if (is_dir($kitDir)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($kitDir));
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $kitSources[] = (string) file_get_contents($file->getPathname());
        }
    }
}

foreach (['src', 'templates', 'config'] as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir));
    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }
        // defaults.php declares rather than acts. Counting it as a reader is
        // how polski_omnibus|show_on_loop and |show_on_single stayed hidden: it
        // names the group and the sub-key while nothing ever branches on either.
        if ($file->getFilename() === 'defaults.php') {
            continue;
        }

        $code = (string) file_get_contents($file->getPathname());

        // ModulesPage declares every setting AND renders the omnibus status
        // panel. Skipping the whole file parked four live settings as dead, so
        // only its declaration entries are cut, not the file.
        if ($file->getFilename() === 'ModulesPage.php') {
            $code = (string) preg_replace("/'key'\s*=>\s*'polski_[a-z0-9_]+\|[a-z0-9_]+'/", ' ', $code);
            $code = (string) preg_replace("/'default'\s*=>\s*[^,]+,/", ' ', $code);
        }

        $sources[$file->getPathname()] = $code;
    }
}

// Groups whose service delegates to the kit: it mentions StorefrontKit and its
// own OPTION constant in the same file.
$kitGroups = [];
foreach ($sources as $code) {
    if (! str_contains($code, 'StorefrontKit')) {
        continue;
    }
    foreach ($declared as $full => $_sub) {
        [$group] = explode('|', $full, 2);
        if (str_contains($code, "'" . $group . "'")) {
            $kitGroups[$group] = true;
        }
    }
}

$dead = [];
foreach ($declared as $full => $subKey) {
    if (isset($dynamic[$subKey])) {
        continue;
    }

    [$group] = explode('|', $full, 2);
    $read = false;

    foreach ($sources as $code) {
        $mentionsKey = str_contains($code, "'" . $subKey . "'") || str_contains($code, '"' . $subKey . '"');

        if (! $mentionsKey) {
            continue;
        }

        // The group can be named directly, or reached through a helper such as
        // getSettings() on the service that owns it. A file that reads the
        // sub-key and never names another group is taken as the owner.
        $otherGroups = 0;
        foreach ($declared as $otherFull => $_sub) {
            [$otherGroup] = explode('|', $otherFull, 2);
            if ($otherGroup !== $group && str_contains($code, "'" . $otherGroup . "'")) {
                $otherGroups++;
            }
        }

        if (str_contains($code, "'" . $group . "'") || $otherGroups === 0) {
            $read = true;
            break;
        }
    }

    if (! $read && isset($kitGroups[$group])) {
        foreach ($kitSources as $code) {
            if (str_contains($code, "'" . $subKey . "'") || str_contains($code, '"' . $subKey . '"')) {
                $read = true;
                break;
            }
        }
    }

    if (! $read) {
        $dead[] = $full;
    }
}

// Settings known to be dead and not yet fixed. Shrink this list, never grow it.
$known = [
    // Empty since 2026-09-19, down from 34. Most of that list was
    // never dead: ten waitlist settings are read in the vendored storefront
    // kit and four omnibus ones in the render half of ModulesPage, neither of
    // which this check indexed. The rest were real and are wired now, except
    // polski_withdrawal|column_price, which named a column the form does not
    // have and was withdrawn from the screen. Shrink this list, never grow it.
];

$new = array_values(array_diff($dead, $known));
$fixed = array_values(array_diff($known, $dead));

printf("settings declared: %d, with no reader: %d (%d known)\n", count($declared), count($dead), count($known));

if ($fixed !== []) {
    echo "These are read now. Remove them from \$known in " . basename(__FILE__) . ":\n";
    foreach ($fixed as $key) {
        echo "  - {$key}\n";
    }
    exit(1);
}

if ($new !== []) {
    echo "A setting is offered on the modules screen that nothing reads:\n";
    foreach ($new as $key) {
        echo "  - {$key}\n";
    }
    echo "Implement it, remove it from the screen, or read the key where it is used.\n";
    exit(1);
}

echo "OK: every new setting has a reader.\n";
