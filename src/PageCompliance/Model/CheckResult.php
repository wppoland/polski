<?php

declare(strict_types=1);

namespace Polski\PageCompliance\Model;

use Polski\PageCompliance\Enum\Severity;

defined('ABSPATH') || exit;

/**
 * Outcome of applying a CheckRule to page content.
 */
final class CheckResult
{
    public function __construct(
        public readonly string $ruleId,
        public readonly string $label,
        public readonly Severity $severity,
        public readonly bool $passed,
        public readonly string $hint,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->ruleId,
            'label' => $this->label,
            'severity' => $this->severity->value,
            'passed' => $this->passed,
            'hint' => $this->passed ? '' : $this->hint,
        ];
    }
}
