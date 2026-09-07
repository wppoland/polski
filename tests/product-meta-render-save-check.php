<?php
/**
 * A product meta field is declared twice: once as a rendered input in the
 * product data panel, once in the save map that reads $_POST back. Add the
 * input and forget the map entry and the field looks real, accepts typing, and
 * silently discards it on every save. Nothing at runtime complains.
 *
 * This is the same failure mode as a settings key nothing reads, so it gets the
 * same treatment: fail the release, not the shop.
 */

declare(strict_types=1);

$file = dirname(__DIR__) . '/src/Admin/ProductMetaBox.php';
$src = (string) file_get_contents($file);

preg_match_all("/'id'\s*=>\s*'(_polski_[a-z0-9_]+)'/", $src, $m);
$rendered = array_values(array_unique($m[1]));

// Only the $fields map inside saveProductMeta() actually writes; the
// MODULE_GATED_FIELDS constant above it looks identical but maps to module ids.
$start = strpos($src, 'public function saveProductMeta');
$body = $start === false ? '' : substr($src, $start);
preg_match("/\\\$fields = \\[(.*?)\\n        \\];/s", $body, $block);

preg_match_all("/'(_polski_[a-z0-9_]+)'\s*=>/", $block[1] ?? '', $m2);
$saved = array_values(array_unique($m2[1]));

// Variations save through their own routine, not the $fields map.
preg_match_all("/update_post_meta\(\\\$variationId, '(_polski_[a-z0-9_]+)'/", $src, $m3);
$saved = array_values(array_unique(array_merge($saved, $m3[1])));

$unsaved = array_diff($rendered, $saved);
$unrendered = array_diff($saved, $rendered);

$fail = false;

if ($unsaved !== []) {
    $fail = true;
    fwrite(STDERR, "FAIL: rendered in the product panel but never saved:\n");
    foreach ($unsaved as $key) {
        fwrite(STDERR, "  - {$key}\n");
    }
}

if ($unrendered !== []) {
    $fail = true;
    fwrite(STDERR, "FAIL: in the save map but no input renders it:\n");
    foreach ($unrendered as $key) {
        fwrite(STDERR, "  - {$key}\n");
    }
}

if ($fail) {
    exit(1);
}

printf("OK: %d product meta fields, every one rendered and saved.\n", count($rendered));
