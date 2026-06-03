<?php
/**
 * Detect "effectively untranslated" .po entries: msgstr empty OR msgstr === msgid.
 * Used by the i18n maintenance fan-out (see scripts/i18n/README.md).
 *
 * Usage:
 *   php scripts/i18n/po-analyze.php <langDir>            # report counts per locale
 *   php scripts/i18n/po-analyze.php <langDir> dump       # also dump empty+copy ids to /tmp/untr-<loc>.json
 *   php scripts/i18n/po-analyze.php <langDir> emptyonly  # dump only empty-msgstr ids to /tmp/untr-<loc>.json
 */
function parse_po(string $file): array {
    $entries = [];
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    $msgid = null; $msgstr = null; $cur = null;
    $flush = function () use (&$entries, &$msgid, &$msgstr) {
        if ($msgid !== null && $msgid !== '') {
            $entries[] = ['id' => $msgid, 'str' => (string) $msgstr];
        }
        $msgid = null; $msgstr = null;
    };
    $unq = function (string $s): string {
        $s = trim($s);
        if (strlen($s) >= 2 && $s[0] === '"') { $s = substr($s, 1, -1); }
        return stripcslashes($s);
    };
    foreach ($lines as $ln) {
        if (preg_match('/^msgid\s+(.*)$/', $ln, $m)) { $flush(); $cur = 'id'; $msgid = $unq($m[1]); continue; }
        if (preg_match('/^msgid_plural/', $ln)) { $cur = 'skip'; continue; }
        if (preg_match('/^msgstr(\[0\])?\s+(.*)$/', $ln, $m)) { $cur = 'str'; $msgstr = $unq($m[2]); continue; }
        if (preg_match('/^msgstr\[/', $ln)) { $cur = 'skip'; continue; }
        if (preg_match('/^"(.*)"$/', $ln, $m)) {
            $piece = stripcslashes($m[1]);
            if ($cur === 'id') { $msgid .= $piece; }
            elseif ($cur === 'str') { $msgstr .= $piece; }
            continue;
        }
    }
    $flush();
    return $entries;
}

$dir = $argv[1] ?? '.';
$mode = $argv[2] ?? '';
$locales = ['pl_PL','de_DE','cs_CZ','sk_SK','uk','lt_LT','be_BY','zh_CN'];
foreach ($locales as $loc) {
    $f = "$dir/polski-$loc.po";
    if (!is_file($f)) { echo "$loc: MISSING\n"; continue; }
    $e = parse_po($f);
    $empty = 0; $copy = 0; $untr = []; $emptyIds = [];
    foreach ($e as $row) {
        if ($row['str'] === '') { $empty++; $untr[] = $row['id']; $emptyIds[] = $row['id']; }
        elseif ($row['str'] === $row['id']) { $copy++; $untr[] = $row['id']; }
    }
    printf("%-7s total=%d empty=%d copy(EN)=%d effectively_untranslated=%d\n", $loc, count($e), $empty, $copy, $empty + $copy);
    if ($mode === 'dump') {
        file_put_contents("/tmp/untr-$loc.json", json_encode($untr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    } elseif ($mode === 'emptyonly') {
        file_put_contents("/tmp/untr-$loc.json", json_encode($emptyIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
