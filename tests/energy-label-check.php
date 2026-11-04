<?php

declare(strict_types=1);

/**
 * The energy label arrow: right colour for the class, nothing without a class
 * and a label (art. 6(d) forbids mimic labels), variations read the parent.
 *
 * Framework-free: php tests/energy-label-check.php
 */

namespace {
    define('ABSPATH', __DIR__);
    $GLOBALS['opts'] = ['polski_modules' => ['energy_label' => true]];
    function get_option($k, $d = false) { return $GLOBALS['opts'][$k] ?? $d; }
    function __($s, $d = '') { return $s; }
    function esc_html__($s, $d = '') { return $s; }
    function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
    function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
    function esc_url($s) { return (string) $s; }
    function apply_filters($h, $v, ...$a) { return $v; }
    function wc_get_product($id) { return $GLOBALS['products'][$id] ?? false; }

    class WC_Product
    {
        public function __construct(private array $meta = [], private string $type = 'simple', private int $parent = 0) {}
        public function get_meta($k, $single = true) { return $this->meta[$k] ?? ''; }
        public function is_type($t) { return $t === $this->type; }
        public function get_parent_id() { return $this->parent; }
    }

    require __DIR__ . '/../src/Contract/HasHooks.php';
    require __DIR__ . '/../src/Admin/ModulesPage.php';
    require __DIR__ . '/../src/Service/EnergyLabelService.php';

    $s = new Polski\Service\EnergyLabelService();
    $fail = 0;
    $ok = static function (string $label, bool $cond) use (&$fail): void {
        echo ($cond ? '  ok   ' : '  FAIL ') . $label . "\n";
        $cond || $fail++;
    };

    $ok('A to G has seven classes', ['A', 'B', 'C', 'D', 'E', 'F', 'G'] === $s->range('A-G'));
    $ok('A+++ to D has seven classes', 7 === count($s->range('A+++-D')) && 'D' === $s->range('A+++-D')[6]);
    $ok('A is 100,0,100,0 green', '#00ff00' === $s->colour(0, 7));
    $ok('D is 0,0,100,0 yellow', '#ffff00' === $s->colour(3, 7));
    $ok('G is 0,100,100,0 red', '#ff0000' === $s->colour(6, 7));

    $label = ['_polski_energy_class' => 'B', '_polski_energy_label_url' => 'https://eprel.example/label.png'];
    $html = $s->getArrowHtml(new WC_Product($label));
    $ok('a class and a label render the arrow in the B colour', str_contains($html, '#4dff00') && str_contains($html, '>B<'));
    $ok('no sheet URL, no sheet link', ! str_contains($html, 'Product information sheet'));

    $html = $s->getArrowHtml(new WC_Product($label + ['_polski_energy_sheet_url' => 'https://eprel.example/sheet']));
    $ok('the sheet link carries the Annex VIII wording', str_contains($html, '>Product information sheet</a>'));

    $ok('no label, no arrow', '' === $s->getArrowHtml(new WC_Product(['_polski_energy_class' => 'B'])));
    $ok('no class, no arrow', '' === $s->getArrowHtml(new WC_Product(['_polski_energy_label_url' => 'https://x'])));
    $ok('a class outside the scale renders nothing', '' === $s->getArrowHtml(new WC_Product(array_merge($label, ['_polski_energy_class' => 'A+++']))));

    $GLOBALS['products'][7] = new WC_Product($label);
    $ok('a variation shows its parent label', str_contains($s->getArrowHtml(new WC_Product([], 'variation', 7)), '>B<'));

    $GLOBALS['opts']['polski_modules']['energy_label'] = false;
    $ok('module off renders nothing', '' === $s->getArrowHtml(new WC_Product($label)));

    echo $fail === 0 ? "energy-label-check: OK\n" : "energy-label-check: {$fail} FAILED\n";
    exit($fail === 0 ? 0 : 1);
}
