<?php

declare(strict_types=1);

namespace Spolszczony\Model;

use Spolszczony\Enum\CheckboxContext;
use Spolszczony\Enum\ConsentType;

/**
 * Definition of a legal checkbox that can appear at checkout, registration, etc.
 */
final class LegalCheckbox
{
    /**
     * @param string              $id          Unique identifier (e.g., 'terms', 'privacy', 'withdrawal').
     * @param string              $label       HTML label shown to the user.
     * @param ConsentType         $type        Required or optional.
     * @param list<CheckboxContext> $contexts   Where this checkbox appears.
     * @param int                 $priority    Display order (lower = first).
     * @param bool                $enabled     Whether this checkbox is active.
     * @param string              $errorMessage Validation error when required but unchecked.
     * @param string              $description Admin-facing description.
     */
    public function __construct(
        public readonly string $id,
        public string $label,
        public ConsentType $type,
        public array $contexts,
        public int $priority = 10,
        public bool $enabled = true,
        public string $errorMessage = '',
        public string $description = '',
    ) {
    }

    /**
     * Check if this checkbox should appear in a given context.
     */
    public function isVisibleIn(CheckboxContext $context): bool
    {
        return $this->enabled && in_array($context, $this->contexts, true);
    }

    /**
     * Check if this checkbox is required (must be checked).
     */
    public function isRequired(): bool
    {
        return $this->type === ConsentType::Required;
    }

    /**
     * Serialize to array for REST API / storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'type' => $this->type->value,
            'contexts' => array_map(static fn (CheckboxContext $c) => $c->value, $this->contexts),
            'priority' => $this->priority,
            'enabled' => $this->enabled,
            'error_message' => $this->errorMessage,
            'description' => $this->description,
        ];
    }

    /**
     * Create from stored array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $contexts = array_filter(
            array_map(
                static fn (string $v) => CheckboxContext::tryFrom($v),
                (array) ($data['contexts'] ?? []),
            ),
        );

        return new self(
            id: (string) ($data['id'] ?? ''),
            label: (string) ($data['label'] ?? ''),
            type: ConsentType::tryFrom((string) ($data['type'] ?? 'required')) ?? ConsentType::Required,
            contexts: array_values($contexts),
            priority: (int) ($data['priority'] ?? 10),
            enabled: (bool) ($data['enabled'] ?? true),
            errorMessage: (string) ($data['error_message'] ?? ''),
            description: (string) ($data['description'] ?? ''),
        );
    }
}
