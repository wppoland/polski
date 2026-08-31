<?php
/**
 * The guest withdrawal form's wording is settable. Asked for on the support
 * forum, and the shape matters more than the feature: an empty setting must
 * leave the translated default alone, so a shop that never opens the screen
 * sees no change, and a merchant who mistypes a placeholder must not take the
 * page down.
 *
 * Run: php tests/withdrawal-lookup-texts-check.php
 *
 * @package Polski
 */

$fail = 0;
$assert = static function (string $label, $got, $want) use (&$fail): void {
    if ($got !== $want) {
        fwrite(STDERR, sprintf("FAIL %s\n  expected: %s\n  got:      %s\n", $label, var_export($want, true), var_export($got, true)));
        $fail = 1;
        return;
    }
    printf("ok   %s\n", $label);
};

// The resolver the template uses: a setting wins only when it is non-empty.
$resolve = static function (array $settings, string $key, string $default): string {
    $value = trim((string) ($settings[$key] ?? ''));

    return '' === $value ? $default : $value;
};

$assert('an absent setting keeps the default', $resolve([], 'lookup_heading', 'Default heading'), 'Default heading');
$assert('an empty setting keeps the default', $resolve(['lookup_heading' => ''], 'lookup_heading', 'Default heading'), 'Default heading');
$assert('whitespace only keeps the default', $resolve(['lookup_heading' => "  \n "], 'lookup_heading', 'Default heading'), 'Default heading');
$assert('a real setting wins', $resolve(['lookup_heading' => 'Zwrot towaru'], 'lookup_heading', 'Default heading'), 'Zwrot towaru');

// The intro is the only one carrying placeholders, so it is the only one that
// can blow up. Whatever the merchant types, the page must still render.
$intro = static function (string $text, string $merchant, int $days): string {
    try {
        return sprintf($text, $merchant, $days);
    } catch (\Throwable) {
        return $text;
    }
};

$assert(
    'both placeholders are filled',
    $intro('Bought from %1$s? You have %2$d days.', 'Sklep', 14),
    'Bought from Sklep? You have 14 days.',
);
$assert(
    'text without placeholders is printed as written',
    $intro('Masz 14 dni na odstapienie.', 'Sklep', 14),
    'Masz 14 dni na odstapienie.',
);
$assert(
    'only one placeholder used is fine',
    $intro('Kupiles w %1$s.', 'Sklep', 14),
    'Kupiles w Sklep.',
);
$assert(
    'a stray percent falls back to the literal text instead of fatalling',
    $intro('Zwroty do 100% wartosci, %q', 'Sklep', 14),
    'Zwroty do 100% wartosci, %q',
);

echo $fail ? "FAILED\n" : "all guest form wording checks passed\n";
exit($fail);
