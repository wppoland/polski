<?php
/**
 * Fails when a product taxonomy registers without its module toggle.
 *
 * PostTypes::registerTaxonomies() used to call every register* method on init,
 * so polski_unit, polski_allergen, polski_nutrient and polski_brand appeared
 * under Products even with food_module and brands defaulting to false. On a
 * stone worktop shop that meant Units, Allergens, Nutrients and a second
 * "Brands" next to WooCommerce core Brands, all of them empty.
 *
 * Two things have to hold and both are cheap to read off the source:
 *   1. every private register*Taxonomy method is listed in TAXONOMY_MODULES,
 *      so nothing registers unconditionally again;
 *   2. every module id used as a key has a default state on the Modules screen,
 *      otherwise isModuleEnabled() answers false forever and the taxonomy is
 *      dead code.
 *
 * Usage: php tests/taxonomies-follow-module-toggles-check.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$postTypes = $root . '/src/Admin/PostTypes.php';
$screen = $root . '/src/Admin/ModulesPage.php';

$src = (string) file_get_contents($postTypes);

$mapStart = strpos($src, 'private const TAXONOMY_MODULES');
$mapEnd = $mapStart === false ? false : strpos($src, '];', $mapStart);

if ($mapStart === false || $mapEnd === false) {
    fwrite(STDERR, "FAIL: TAXONOMY_MODULES not found in {$postTypes}.\n");
    exit(1);
}

$map = substr($src, $mapStart, $mapEnd - $mapStart);

preg_match_all("/'([a-z0-9_]+)'\s*=>\s*\[/", $map, $mModules);
preg_match_all("/'(register[A-Za-z]+Taxonomy)'/", $map, $mMapped);
preg_match_all('/private function (register[A-Za-z]+Taxonomy)\(/', $src, $mDefined);

$modules = array_values(array_unique($mModules[1]));
$mapped = array_values(array_unique($mMapped[1]));
$defined = array_values(array_unique($mDefined[1]));

if ($modules === [] || $defined === []) {
    fwrite(STDERR, "FAIL: parsed 0 modules or 0 register methods, the check is not reading what it thinks it is.\n");
    exit(1);
}

$ungated = array_values(array_diff($defined, $mapped));

if ($ungated !== []) {
    fwrite(STDERR, "FAIL: taxonomy registrations that no module toggle controls:\n");
    foreach ($ungated as $method) {
        fwrite(STDERR, "  - {$method}()\n");
    }
    fwrite(STDERR, "\nAdd it to PostTypes::TAXONOMY_MODULES under the module that owns the feature.\n");
    fwrite(STDERR, "A taxonomy nobody can switch off is an admin screen for a feature that is not on.\n");
    exit(1);
}

$orphans = array_values(array_diff($mapped, $defined));

if ($orphans !== []) {
    fwrite(STDERR, "FAIL: TAXONOMY_MODULES names methods that do not exist:\n");
    foreach ($orphans as $method) {
        fwrite(STDERR, "  - {$method}()\n");
    }
    exit(1);
}

$screenSrc = (string) file_get_contents($screen);
$defaultsStart = strpos($screenSrc, 'private static function baseModuleDefaults');

if ($defaultsStart === false) {
    fwrite(STDERR, "FAIL: baseModuleDefaults() not found in {$screen}.\n");
    exit(1);
}

preg_match_all("/'([a-z0-9_]+)'\s*=>\s*(?:true|false)/", substr($screenSrc, $defaultsStart), $mDefaults);
$unknown = array_values(array_diff($modules, array_unique($mDefaults[1])));

if ($unknown !== []) {
    fwrite(STDERR, "FAIL: module ids used as taxonomy gates that have no default state:\n");
    foreach ($unknown as $id) {
        fwrite(STDERR, "  - {$id}\n");
    }
    fwrite(STDERR, "\nisModuleEnabled() falls back to false for an unknown id, so the taxonomy never registers.\n");
    exit(1);
}

printf(
    "OK: %d taxonomy registrations, all gated by %d known module ids.\n",
    count($defined),
    count($modules),
);
