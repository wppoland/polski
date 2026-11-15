<?php

// NOTE: no `declare(strict_types=1)` here on purpose - `wp eval-file` wraps this
// file in eval(), where a declare() can't be the first statement.

/**
 * wpml-config.xml lets Polylang (and WPML) translate polski's option texts:
 * the order button text saved in Polish comes back in English when the current
 * language is English. Needs Polylang active; leaves the site as it found it.
 *
 * Run: wp eval-file wp-content/plugins/<dir>/tests/wpml-config-polylang-check.php
 */

if (! function_exists('PLL') || ! class_exists('PLL_MO')) {
    echo "SKIP  wpml-config-polylang (Polylang not active)\n";
    return;
}

$polski_failures = [];
$polski_check = static function (string $label, bool $ok) use (&$polski_failures): void {
    if (! $ok) {
        $polski_failures[] = $label;
    }
};

$polski_model = PLL()->model;
$polski_added = [];
foreach ([['pl', 'pl_PL', 'Polski'], ['en', 'en_US', 'English']] as [$slug, $locale, $name]) {
    if (! $polski_model->get_language($slug)) {
        $polski_model->add_language(['name' => $name, 'slug' => $slug, 'locale' => $locale, 'rtl' => 0, 'term_group' => 0]);
        $polski_added[] = $slug;
    }
}
$polski_model->clean_languages_cache();

$polski_saved = get_option('polski_checkout', []);
update_option('polski_checkout', array_merge(is_array($polski_saved) ? $polski_saved : [], ['order_button_text' => 'Zamawiam i płacę']));

$polski_en = $polski_model->get_language('en');
$polski_mo = new PLL_MO();
$polski_mo->import_from_db($polski_en);
$polski_mo->add_entry($polski_mo->make_entry('Zamawiam i płacę', 'I order and pay'));
$polski_mo->export_to_db($polski_en);

$polski_check('wpml-config.xml makes Polylang filter polski_checkout', (bool) has_filter('option_polski_checkout'));

PLL()->curlang = $polski_en;
PLL()->load_strings_translations('en_US');
wp_cache_delete('alloptions', 'options');
$polski_value = get_option('polski_checkout')['order_button_text'] ?? '';
$polski_check('order button text comes back in English, got "' . $polski_value . '"', 'I order and pay' === $polski_value);

update_option('polski_checkout', $polski_saved);
$polski_mo = new PLL_MO();
$polski_mo->import_from_db($polski_en);
$polski_mo->export_to_db($polski_en);
foreach ($polski_added as $slug) {
    $polski_model->delete_language($polski_model->get_language($slug)->term_id);
}

if ($polski_failures !== []) {
    echo "FAIL  wpml-config-polylang\n  - " . implode("\n  - ", $polski_failures) . "\n";
    exit(1);
}

echo "ok    wpml-config-polylang\n";
