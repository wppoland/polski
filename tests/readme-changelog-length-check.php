<?php
/**
 * WordPress.org truncates a readme.txt Changelog section over 5,000 words and
 * emails the authors a warning after the import. Nothing in the plugin notices,
 * so the page silently loses its oldest entries. Full history lives in
 * changelog.txt; this keeps the shipped readme under the limit with headroom.
 */

declare(strict_types=1);

const WPORG_CHANGELOG_WORD_LIMIT = 5000;
// ponytail: fixed headroom, ~15 releases at the current rate. Trim further when it trips.
const HEADROOM = 1000;

$readme = dirname(__DIR__) . '/readme.txt';
$text = (string) file_get_contents($readme);

$start = strpos($text, '== Changelog ==');
if ($start === false) {
    fwrite(STDERR, "FAIL: readme.txt has no == Changelog == section.\n");
    exit(1);
}

$section = substr($text, $start);
// The changelog runs to the next top-level section, if any.
if (preg_match('/\n== (?!Changelog ==)/', $section, $m, PREG_OFFSET_CAPTURE)) {
    $section = substr($section, 0, $m[0][1]);
}

// WordPress.org does not linkify a bare URL in readme.txt, only markdown, so
// the pointer at the full history has to be a link or it is not one.
if (! str_contains($section, '](https://plogins.com/')) {
    fwrite(STDERR, "FAIL: the Changelog section no longer links to the full history on plogins.com.\n");
    fwrite(STDERR, "      Only the most recent releases are shipped here; without the link the rest is unreachable.\n");
    fwrite(STDERR, "      Use markdown: [plogins.com/<slug>/changelog/](https://plogins.com/<slug>/changelog/)\n");
    exit(1);
}

$words = count(preg_split('/\s+/', trim($section), -1, PREG_SPLIT_NO_EMPTY) ?: []);
$budget = WPORG_CHANGELOG_WORD_LIMIT - HEADROOM;

if ($words > $budget) {
    fwrite(STDERR, sprintf(
        "FAIL: readme.txt Changelog is %d words; budget is %d (wp.org truncates at %d).\n"
        . "Move older entries out of readme.txt. changelog.txt keeps the full history.\n",
        $words,
        $budget,
        WPORG_CHANGELOG_WORD_LIMIT
    ));
    exit(1);
}

// changelog.txt is what the trimmed readme points at, so it has to exist and be ahead.
$full = dirname(__DIR__) . '/changelog.txt';
if (! is_file($full)) {
    fwrite(STDERR, "FAIL: readme.txt points at changelog.txt, which is missing.\n");
    exit(1);
}

preg_match('/^= ([0-9.]+) =$/m', $section, $readmeTop);
preg_match('/^= ([0-9.]+) =$/m', (string) file_get_contents($full), $fullTop);

if (($readmeTop[1] ?? '') !== ($fullTop[1] ?? '')) {
    fwrite(STDERR, sprintf(
        "FAIL: newest entry is %s in readme.txt but %s in changelog.txt.\n",
        $readmeTop[1] ?? '(none)',
        $fullTop[1] ?? '(none)'
    ));
    exit(1);
}

printf("OK: Changelog %d words (budget %d), changelog.txt current at %s.\n", $words, $budget, $fullTop[1]);
