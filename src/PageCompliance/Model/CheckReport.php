<?php

declare(strict_types=1);

namespace Polski\PageCompliance\Model;

use Polski\PageCompliance\Enum\Severity;

defined('ABSPATH') || exit;

/**
 * Aggregated compliance check report for a single legal page.
 */
final class CheckReport
{
    /**
     * @param list<CheckResult> $results
     */
    public function __construct(
        public readonly string $pageType,
        public readonly ?int $pageId,
        public readonly int $contentLength,
        public readonly array $results,
    ) {
    }

    public function score(): int
    {
        if ($this->results === []) {
            return 0;
        }

        $weighted = 0;
        $total = 0;

        foreach ($this->results as $result) {
            $weight = match ($result->severity) {
                Severity::Required => 3,
                Severity::Recommended => 2,
                Severity::Optional => 1,
            };

            $total += $weight;

            if ($result->passed) {
                $weighted += $weight;
            }
        }

        return $total === 0 ? 0 : (int) round($weighted / $total * 100);
    }

    public function hasMissingRequired(): bool
    {
        foreach ($this->results as $result) {
            if ($result->severity === Severity::Required && ! $result->passed) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'page_type' => $this->pageType,
            'page_id' => $this->pageId,
            'content_length' => $this->contentLength,
            'score' => $this->score(),
            'has_missing_required' => $this->hasMissingRequired(),
            'results' => array_map(
                static fn (CheckResult $r): array => $r->toArray(),
                $this->results,
            ),
        ];
    }
}
