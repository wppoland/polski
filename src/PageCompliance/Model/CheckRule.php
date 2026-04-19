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
 */
final readonly class CheckRule
{
    /**
     * @param list<string> $patterns Lowercased, diacritic-stripped substrings to search for.
     */
    public function __construct(
        public string $id,
        public string $label,
        public Severity $severity,
        public array $patterns,
        public string $hint,
        public int $minLength = 0,
    ) {
    }
}
