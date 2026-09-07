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

$haystack = '';
foreach (['src', 'templates', 'config'] as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir));
    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }
        if ($file->getFilename() === 'ModulesPage.php') {
            continue;
        }
        $haystack .= (string) file_get_contents($file->getPathname());
    }
}

$dead = [];
foreach ($declared as $full => $subKey) {
    if (isset($dynamic[$subKey])) {
        continue;
    }
    if (str_contains($haystack, "'" . $subKey . "'") || str_contains($haystack, '"' . $subKey . '"')) {
        continue;
    }
    $dead[] = $full;
}

// Settings known to be dead and not yet fixed. Shrink this list, never grow it.
$known = [
    'polski_prices|unit_price_show_loop',
    'polski_omnibus|include_tax',
    'polski_omnibus|show_on_related',
    'polski_omnibus|show_regular_price',
    'polski_omnibus|no_history_text',
    'polski_omnibus|no_history_custom_text',
    'polski_omnibus|price_count_from',
    'polski_omnibus|variable_tracking',
    'polski_gpsr|display_mode',
    'polski_dsa|contact_name',
    'polski_dsa|contact_phone',
    'polski_ksef|auto_detect_nip',
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
