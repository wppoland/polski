<?php

/**
 * Self-check for the Schema.org data ProductHooks adds to WooCommerce's
 * Product graph.
 *
 * Two things this guards, both of which shipped wrong and neither of which any
 * other gate can see, because both are correct-looking array keys:
 *
 *  1. Annex XV declares SALT, Schema.org only has sodiumContent, and salt is
 *     sodium x 2.5. Mapping the slugs straight across published every food
 *     product as 2.5 times as salty as its own label.
 *  2. The graph is public output, so it must not carry private keys or an
 *     untranslated Polish label.
 *
 * Run: php polski/tests/schema-structured-data-check.php
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);
define('HOUR_IN_SECONDS', 3600);

// WordPress surface used by enrichStructuredData().
function add_action(...$a): void {}
function add_filter(...$a): void {}
function get_option(string $k, $d = false) { return $d; }
function get_transient(string $k) { return false; }
function set_transient(string $k, $v, int $t = 0): bool { return true; }
function get_post_meta(int $id, string $k = '', bool $single = false) { return ''; }
function delete_transient(string $k): bool { return true; }
function __(string $text, string $domain = ''): string { return $text; }

// Collaborators, stubbed under their real names so ProductHooks' constructor
// type hints are satisfied without loading the plugin.
namespace_stubs();

function namespace_stubs(): void
{
    eval(<<<'STUB'
namespace Polski\Contract { interface Bootable { public function boot(): void; } interface HasHooks { public function registerHooks(): void; } }
namespace Polski\Service {
    class PriceDisplayService { public $unitPrice = null; public function getUnitPrice($p) { return $this->unitPrice; } }
    class DeliveryTimeService { public function getDeliveryTimeText($p): string { return ''; } }
    class ProductInfoService {
        public function getBrands($p): array { return []; }
        public function getManufacturer($p): string { return ''; }
        public function getGTIN($p): string { return ''; }
    }
    class FoodService { public array $nutrients = []; public function getNutrients($p): array { return $this->nutrients; } }
    class ConsumerInformationService {}
}
namespace Polski\Shopmark { class Location {} class Shopmark {} class ShopmarkManager {} }
namespace Polski\Util { class TemplateLoader {} }
namespace Polski\Admin { class ModulesPage { public static function isModuleEnabled(string $id): bool { return true; } } }
namespace { class WC_Product { public function get_id(): int { return 1; } } }
STUB);
}

require __DIR__ . '/../src/Hook/ProductHooks.php';

$prices = new \Polski\Service\PriceDisplayService();
$food = new \Polski\Service\FoodService();

$hooks = new \Polski\Hook\ProductHooks(
    $prices,
    new \Polski\Service\DeliveryTimeService(),
    new \Polski\Service\ProductInfoService(),
    $food,
    new \Polski\Service\ConsumerInformationService(),
    new \Polski\Shopmark\ShopmarkManager(),
    new \Polski\Util\TemplateLoader(),
);

$product = new \WC_Product();
$failures = [];

$check = static function (string $label, bool $ok) use (&$failures): void {
    echo ($ok ? '  ok    ' : '  FAILED') . '  ' . $label . "\n";
    if (! $ok) {
        $failures[] = $label;
    }
};

// 1. Salt is converted, not relabelled.
$food->nutrients = [
    'salt' => ['value' => '2.5', 'unit' => 'g'],
    'fat' => ['value' => '12.3', 'unit' => 'g'],
];
$out = $hooks->enrichStructuredData([], $product);
$nutrition = $out['nutrition'] ?? [];

$check('salt 2.5 g is published as 1 g of sodium', ($nutrition['sodiumContent'] ?? null) === '1 g');
$check('an unconverted nutrient still passes through verbatim', ($nutrition['fatContent'] ?? null) === '12.3 g');
$check('no saltContent key is invented', ! isset($nutrition['saltContent']));

// A non-numeric salt cell must be dropped, not divided into a PHP warning.
$food->nutrients = ['salt' => ['value' => 'trace', 'unit' => 'g']];
$out = $hooks->enrichStructuredData([], $product);
$check('a non-numeric salt value is dropped', ! isset($out['nutrition']['sodiumContent']));

// 2. The public graph carries no private keys and no hardcoded Polish.
$food->nutrients = [];
$prices->unitPrice = (object) ['pricePerUnit' => '8.90', 'baseAmount' => '100', 'unit' => 'g'];
$out = $hooks->enrichStructuredData([], $product);

$check('no private polski_* key reaches the graph', $out === array_filter(
    $out,
    static fn (string $k): bool => ! str_starts_with($k, 'polski_'),
    ARRAY_FILTER_USE_KEY,
));
$check(
    'the unit-price label is translatable, not hardcoded Polish',
    ($out['additionalProperty'][0]['name'] ?? '') === 'Unit price',
);

echo "\n" . ($failures === [] ? "RESULT: pass\n" : 'RESULT: ' . count($failures) . " failed\n");
exit($failures === [] ? 0 : 1);
