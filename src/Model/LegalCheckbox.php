<?php

declare(strict_types=1);
namespace Polski\Model;

defined('ABSPATH') || exit;

use Polski\Enum\CheckboxContext;
use Polski\Enum\ConsentType;

/**
 * Definition of a legal checkbox that can appear at checkout, registration, etc.
 *
 * Every field is customizable via admin UI or REST API.
 * Every field is fully customizable in the FREE version.
 */
final class LegalCheckbox
{
    /**
     * @param string                $id               Unique identifier (e.g., 'terms', 'privacy').
     * @param string                $label            HTML label shown to the user.
     * @param ConsentType           $type             Required or optional.
     * @param list<CheckboxContext> $contexts         Where this checkbox appears.
     * @param int                   $priority         Display order (lower = first).
     * @param bool                  $enabled          Whether this checkbox is active.
     * @param string                $errorMessage     Validation error when required but unchecked.
     * @param string                $description      Admin-facing description.
     * @param bool                  $isCore           True for built-in checkboxes (cannot be deleted).
     * @param string                $htmlId           Custom HTML id attribute. Defaults to field name.
     * @param string                $htmlClasses      Extra CSS classes (space-separated).
     * @param string                $htmlStyle        Inline style attribute.
     * @param bool                  $hideInput        Hide the input, show label only (info text).
     * @param string                $templateName     Custom template name override.
     * @param bool                  $refreshFragments AJAX-refresh this checkbox on order review update.
     * @param bool                  $logConsent       Whether to log consent in the audit trail.
     * @param list<int>             $categories       Show only when cart contains products from these category IDs.
     * @param list<string>          $countries         Show only for these billing country codes.
     * @param list<string>          $paymentMethods   Show only for these payment method IDs.
     * @param list<string>          $productTypes     Show only for these product types (e.g., 'virtual', 'downloadable').
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
        public bool $isCore = false,
        public string $htmlId = '',
        public string $htmlClasses = '',
        public string $htmlStyle = '',
        public bool $hideInput = false,
        public string $templateName = '',
        public bool $refreshFragments = false,
        public bool $logConsent = true,
        public array $categories = [],
        public array $countries = [],
        public array $paymentMethods = [],
        public array $productTypes = [],
    ) {
    }

    /**
     * Get the HTML field name for form submissions.
     */
    public function getFieldName(): string
    {
        return 'polski_checkbox_' . $this->id;
    }

    /**
     * Get the effective HTML id attribute.
     */
    public function getHtmlId(): string
    {
        return $this->htmlId !== '' ? $this->htmlId : $this->getFieldName();
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
     * Check if this checkbox passes conditional display rules.
     *
     * @param array<string, mixed> $cartContext {
     *     @type list<int>    $category_ids    Product category IDs in cart.
     *     @type string       $country         Billing country code.
     *     @type string       $payment_method  Selected payment method.
     *     @type list<string> $product_types   Product types in cart.
     * }
     */
    public function passesConditions(array $cartContext): bool
    {
        if (! empty($this->categories)) {
            $cartCategories = (array) ($cartContext['category_ids'] ?? []);
            if (empty(array_intersect($this->categories, $cartCategories))) {
                return false;
            }
        }

        if (! empty($this->countries)) {
            $country = (string) ($cartContext['country'] ?? '');
            if ($country !== '' && ! in_array($country, $this->countries, true)) {
                return false;
            }
        }

        if (! empty($this->paymentMethods)) {
            $method = (string) ($cartContext['payment_method'] ?? '');
            if ($method !== '' && ! in_array($method, $this->paymentMethods, true)) {
                return false;
            }
        }

        if (! empty($this->productTypes)) {
            $cartTypes = (array) ($cartContext['product_types'] ?? []);
            if (empty(array_intersect($this->productTypes, $cartTypes))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Merge saved overrides into this checkbox.
     *
     * @param array<string, mixed> $overrides Key-value pairs to apply.
     */
    public function applyOverrides(array $overrides): void
    {
        if (isset($overrides['label']) && is_string($overrides['label']) && $overrides['label'] !== '') {
            $this->label = $overrides['label'];
        }

        if (isset($overrides['error_message']) && is_string($overrides['error_message'])) {
            $this->errorMessage = $overrides['error_message'];
        }

        if (isset($overrides['type'])) {
            $parsed = ConsentType::tryFrom((string) $overrides['type']);
            if ($parsed !== null) {
                $this->type = $parsed;
            }
        }

        if (isset($overrides['priority'])) {
            $this->priority = (int) $overrides['priority'];
        }

        if (isset($overrides['enabled'])) {
            $this->enabled = (bool) $overrides['enabled'];
        }

        if (isset($overrides['contexts']) && is_array($overrides['contexts'])) {
            $parsed = array_filter(array_map(
                static fn (string $v) => CheckboxContext::tryFrom($v),
                $overrides['contexts'],
            ));
            if (! empty($parsed)) {
                $this->contexts = array_values($parsed);
            }
        }

        if (isset($overrides['html_classes']) && is_string($overrides['html_classes'])) {
            $this->htmlClasses = $overrides['html_classes'];
        }

        if (isset($overrides['html_style']) && is_string($overrides['html_style'])) {
            $this->htmlStyle = $overrides['html_style'];
        }

        if (isset($overrides['hide_input'])) {
            $this->hideInput = (bool) $overrides['hide_input'];
        }

        if (isset($overrides['description']) && is_string($overrides['description'])) {
            $this->description = $overrides['description'];
        }

        if (isset($overrides['categories']) && is_array($overrides['categories'])) {
            $this->categories = array_map('intval', $overrides['categories']);
        }

        if (isset($overrides['countries']) && is_array($overrides['countries'])) {
            $this->countries = array_map('strval', $overrides['countries']);
        }

        if (isset($overrides['payment_methods']) && is_array($overrides['payment_methods'])) {
            $this->paymentMethods = array_map('strval', $overrides['payment_methods']);
        }

        if (isset($overrides['product_types']) && is_array($overrides['product_types'])) {
            $this->productTypes = array_map('strval', $overrides['product_types']);
        }
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
            'is_core' => $this->isCore,
            'html_id' => $this->getHtmlId(),
            'html_classes' => $this->htmlClasses,
            'html_style' => $this->htmlStyle,
            'hide_input' => $this->hideInput,
            'template_name' => $this->templateName,
            'refresh_fragments' => $this->refreshFragments,
            'log_consent' => $this->logConsent,
            'categories' => $this->categories,
            'countries' => $this->countries,
            'payment_methods' => $this->paymentMethods,
            'product_types' => $this->productTypes,
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
            isCore: (bool) ($data['is_core'] ?? false),
            htmlId: (string) ($data['html_id'] ?? ''),
            htmlClasses: (string) ($data['html_classes'] ?? ''),
            htmlStyle: (string) ($data['html_style'] ?? ''),
            hideInput: (bool) ($data['hide_input'] ?? false),
            templateName: (string) ($data['template_name'] ?? ''),
            refreshFragments: (bool) ($data['refresh_fragments'] ?? false),
            logConsent: (bool) ($data['log_consent'] ?? true),
            categories: array_map('intval', (array) ($data['categories'] ?? [])),
            countries: array_map('strval', (array) ($data['countries'] ?? [])),
            paymentMethods: array_map('strval', (array) ($data['payment_methods'] ?? [])),
            productTypes: array_map('strval', (array) ($data['product_types'] ?? [])),
        );
    }
}
