<?php
/**
 * Fails when a module id has a default state but no card anywhere.
 *
 * getDefaultModuleStates() decides what isModuleEnabled() answers for an id
 * nobody has saved yet. A card in the registry is the only way a merchant can
 * change that answer. An id with a default of false and no card is therefore a
 * feature that is off, cannot be switched on, and looks like a working product
 * on the pricing page. Fifteen paid modules shipped that way.
 *
 * FREE cards live in src/Admin/ModulesPage.php. PRO cards live in the PRO
 * plugin's own config/pro-modules.php and reach the screen through the
 * `polski/modules` filter, so the ids are read from PRO's source rather than
 * copied here: one list, no drift.
 *
 * The two plugins are developed side by side (../polski-pro, the same layout
 * .wp-env.json already assumes). A missing PRO checkout fails the check rather
 * than passing quietly, because without it nothing can vouch for the PRO ids.
 *
 * Usage: php tests/module-ids-are-reachable-check.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$screen = $root . '/src/Admin/ModulesPage.php';
$proRegistry = dirname($root) . '/polski-pro/config/pro-modules.php';

$src = (string) file_get_contents($screen);

// Default states: the array literal returned by baseModuleDefaults().
$start = strpos($src, 'private static function baseModuleDefaults');

if ($start === false) {
    fwrite(STDERR, "FAIL: baseModuleDefaults() not found in {$screen}.\n");
    exit(1);
}

preg_match_all("/'([a-z0-9_]+)'\s*=>\s*(?:true|false)/", substr($src, $start), $m);
$defaults = array_values(array_unique($m[1]));

// FREE cards: the registry literal inside getModules().
$registryStart = strpos($src, 'public function getModules');
$registryEnd = strpos($src, "apply_filters('polski/modules'", (int) $registryStart);

if ($registryStart === false || $registryEnd === false) {
    fwrite(STDERR, "FAIL: the module registry or its filter is missing from {$screen}.\n");
    exit(1);
}

preg_match_all(
    "/'id'\s*=>\s*'([a-z0-9_]+)'/",
    substr($src, $registryStart, $registryEnd - $registryStart),
    $m2,
);
$freeCards = array_values(array_unique($m2[1]));

if (! is_readable($proRegistry)) {
    fwrite(STDERR, "FAIL: PRO module registry not readable at {$proRegistry}.\n");
    fwrite(STDERR, "      Check out polski-pro next to this plugin, or the PRO ids cannot be verified.\n");
    exit(1);
}

preg_match_all("/'id'\s*=>\s*'([a-z0-9_]+)'/", (string) file_get_contents($proRegistry), $m3);
$proCards = array_values(array_unique($m3[1]));

if ($defaults === [] || $freeCards === []) {
    fwrite(STDERR, "FAIL: parsed 0 defaults or 0 FREE cards, the check is not reading the file it thinks it is.\n");
    exit(1);
}

$unreachable = array_values(array_diff($defaults, $freeCards, $proCards));

if ($unreachable !== []) {
    fwrite(STDERR, "FAIL: module ids with a default state but no card, so nothing can switch them on:\n");
    foreach ($unreachable as $id) {
        fwrite(STDERR, "  - {$id}\n");
    }
    fwrite(STDERR, "\nAdd a card to the FREE registry, or, when only the paid edition implements it,\n");
    fwrite(STDERR, "to polski-pro/config/pro-modules.php. Never ship a FREE card for something FREE\n");
    fwrite(STDERR, "cannot do: that is the locked-feature pattern wp.org pends under Guideline 5.\n");
    exit(1);
}

printf(
    "OK: %d module ids, all reachable (%d FREE cards, %d PRO cards).\n",
    count($defaults),
    count($freeCards),
    count($proCards),
);
