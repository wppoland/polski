<?php

declare(strict_types=1);

namespace Polski\PageCompliance\Model;

use Polski\PageCompliance\Enum\Severity;

defined('ABSPATH') || exit;

/**
 * Single compliance rule: a set of keyword patterns evaluated against page content.
 *
 * A rule matches when any pattern in `$patterns` is found (case-insensitive,
 * diacritic-insensitive). The list is OR-combined so Polish-language sites
 * can provide multiple phrasings that count as the same requirement.
 *
 * With `$forbidden` the test inverts: the rule passes only when NONE of the
 * patterns appears. That is how a document is checked for things that must not
 * be there any more, such as a repealed act or an unfilled template placeholder.
 */
final class CheckRule
{
    /**
     * @param list<string> $patterns Lowercased, diacritic-stripped substrings to search for.
     * @param bool         $forbidden Pass when none of the patterns is present, instead of when one is.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly Severity $severity,
        public readonly array $patterns,
        public readonly string $hint,
        public readonly int $minLength = 0,
        public readonly bool $forbidden = false,
    ) {
    }
}
