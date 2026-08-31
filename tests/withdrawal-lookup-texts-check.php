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

// The intro is merchant-editable, so it is substituted with {tokens} and never
// through sprintf. sprintf reads "100% c" as a conversion and emits a NUL byte
// into a public page WITHOUT throwing, so a try/catch around it is no guard at
// all. That shipped in 1.30.4; these cases are why it will not ship again.
$intro = static function (string $text, string $merchant, int $days): string {
    return strtr($text, ['{company}' => $merchant, '{days}' => (string) $days]);
};

$assert(
    'both tokens are filled',
    $intro('Kupiles w {company}? Masz {days} dni.', 'Sklep', 14),
    'Kupiles w Sklep? Masz 14 dni.',
);
$assert(
    'text without tokens is printed as written',
    $intro('Masz 14 dni na odstapienie.', 'Sklep', 14),
    'Masz 14 dni na odstapienie.',
);
$assert(
    'only one token used is fine',
    $intro('Kupiles w {company}.', 'Sklep', 14),
    'Kupiles w Sklep.',
);
$assert(
    'a literal percent survives untouched, which sprintf could not manage',
    $intro('Zwracamy 100% ceny zamowienia.', 'Sklep', 14),
    'Zwracamy 100% ceny zamowienia.',
);
$assert(
    'a percent next to a token still survives',
    $intro('{company} zwraca 100% ceny w {days} dni.', 'Sklep', 14),
    'Sklep zwraca 100% ceny w 14 dni.',
);
$assert(
    'nothing sprintf-shaped is interpreted any more',
    $intro('Rabat %1$s i %2$d, dokladnie tak', 'Sklep', 14),
    'Rabat %1$s i %2$d, dokladnie tak',
);
$assert(
    'no NUL byte can reach the page',
    str_contains($intro('Zwracamy 100% ceny', 'Sklep', 14), "\0"),
    false,
);

echo $fail ? "FAILED\n" : "all guest form wording checks passed\n";
exit($fail);
