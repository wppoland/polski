<?php
/**
 * Build a compendium .po for one locale from a translation map produced by the
 * i18n fan-out, so it can be merged over the locale catalog with:
 *   msgcat --use-first /tmp/trans-<loc>.po languages/polski-<loc>.po -o merged.po
 *
 * The map file /tmp/map-<loc>.json may be either {pairs:[{src,tr}]} or {src:tr}.
 * Entries where tr is empty or equals src are skipped (kept untranslated).
 * The header is copied from the existing locale .po so msgcat keeps it.
 *
 * Usage: php scripts/i18n/build-po.php <langDir> <locale>
 */
$dir = $argv[1]; $loc = $argv[2];
$poFile = "$dir/polski-$loc.po";
$raw = json_decode((string) file_get_contents("/tmp/map-$loc.json"), true);
if (!is_array($raw)) { fwrite(STDERR, "bad map for $loc\n"); exit(1); }

$map = [];
if (isset($raw['pairs']) && is_array($raw['pairs'])) {
    foreach ($raw['pairs'] as $p) {
        if (isset($p['src'], $p['tr'])) { $map[(string) $p['src']] = (string) $p['tr']; }
    }
} else {
    $map = $raw;
}

$src = (string) file_get_contents($poFile);
$header = substr($src, 0, strpos($src, "\n\n") + 1);

function po_quote(string $s): string {
    $s = str_replace(['\\', "\"", "\n", "\t"], ['\\\\', '\\"', '\\n', '\\t'], $s);
    return '"' . $s . '"';
}

$out = $header . "\n";
$n = 0;
foreach ($map as $srcStr => $tr) {
    $srcStr = (string) $srcStr; $tr = (string) $tr;
    if ($tr === '' || $tr === $srcStr) { continue; }
    $out .= 'msgid ' . po_quote($srcStr) . "\n";
    $out .= 'msgstr ' . po_quote($tr) . "\n\n";
    $n++;
}
file_put_contents("/tmp/trans-$loc.po", $out);
echo "$loc: wrote $n translated entries\n";
