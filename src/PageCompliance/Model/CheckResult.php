<?php

declare(strict_types=1);

namespace Polski\PageCompliance\Model;

use Polski\PageCompliance\Enum\Severity;

defined('ABSPATH') || exit;

/**
 * Outcome of applying a CheckRule to page content.
 */
final readonly class CheckResult
{
    public function __construct(
        public string $ruleId,
        public string $label,
        public Severity $severity,
        public bool $passed,
        public string $hint,
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
