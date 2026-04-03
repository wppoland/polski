<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Enum\CheckboxContext;
use Polski\Enum\ConsentType;
use Polski\Model\LegalCheckbox;

/**
 * Manages legal checkboxes: registration, validation, and rendering.
 *
 * Supports 7 built-in checkboxes (terms, privacy, withdrawal, digital_waiver,
 * parcel_delivery, review_reminder, marketing) and allows registration of
 * custom checkboxes via filters or REST API.
 *
 * All built-in checkbox properties are customizable via stored overrides.
 * Some conditional features (categories, countries, payment methods) are
 * reserved for the PRO version.
 */
final class CheckboxService implements Bootable, HasHooks
{
    private const OPTION_OVERRIDES = 'polski_checkbox_overrides';
    private const OPTION_CUSTOM = 'polski_custom_checkboxes';

    /** @var array<string, LegalCheckbox> */
    private array $checkboxes = [];

    private bool $initialized = false;

    /** @var array<string, array<string, mixed>>|null Cached overrides from DB. */
    private ?array $cachedOverrides = null;

    public function boot(): void
    {
        // Defer to init so __() calls don't trigger early textdomain loading.
    }

    public function registerHooks(): void
    {
        add_action('init', [$this, 'initCheckboxes'], 20);
    }

    /**
     * Called on init - registers defaults, applies overrides, loads custom checkboxes.
     */
    public function initCheckboxes(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->registerDefaults();
        $this->applyStoredOverrides();
        $this->loadCustomCheckboxes();

        /**
         * Fires after all checkboxes are registered.
         *
         * Use this hook to register additional checkboxes or modify existing ones.
         *
         * @param CheckboxService $service The checkbox service instance.
         */
        do_action('polski/checkboxes/registered', $this);

        $this->initialized = true;
    }

    /**
     * Register a checkbox definition.
     */
    public function register(LegalCheckbox $checkbox): void
    {
        $this->checkboxes[$checkbox->id] = $checkbox;
    }

    /**
     * Remove a checkbox by ID.
     */
    public function unregister(string $id): void
    {
        unset($this->checkboxes[$id]);
    }

    /**
     * Get a checkbox by ID.
     */
    public function get(string $id): ?LegalCheckbox
    {
        return $this->checkboxes[$id] ?? null;
    }

    /**
     * Get all checkboxes visible in a given context, sorted by priority.
     *
     * @param array<string, mixed> $cartContext Optional cart/order context for conditional filtering.
     * @return list<LegalCheckbox>
     */
    public function getForContext(CheckboxContext $context, array $cartContext = []): array
    {
        $filtered = array_filter(
            $this->checkboxes,
            static fn (LegalCheckbox $cb) => $cb->isVisibleIn($context)
                && (empty($cartContext) || $cb->passesConditions($cartContext)),
        );

        usort($filtered, static fn (LegalCheckbox $a, LegalCheckbox $b) => $a->priority <=> $b->priority);

        /**
         * Filter the checkboxes for a given context.
         *
         * @param list<LegalCheckbox>  $checkboxes  The checkboxes.
         * @param CheckboxContext      $context     The display context.
         * @param array<string, mixed> $cartContext The cart context for conditionals.
         */
        $filtered = apply_filters('polski/checkboxes/for_context', array_values($filtered), $context, $cartContext);

        return $filtered;
    }

    /**
     * Validate checkbox submissions for a context.
     *
     * @param CheckboxContext      $context The context being validated.
     * @param array<string, mixed> $posted  The posted form data.
     * @return bool|\WP_Error True if valid, WP_Error with messages if not.
     */
    public function validate(CheckboxContext $context, array $posted): bool|\WP_Error
    {
        $errors = new \WP_Error();
        $checkboxes = $this->getForContext($context);

        foreach ($checkboxes as $checkbox) {
            if (! $checkbox->isRequired()) {
                continue;
            }

            if ($checkbox->hideInput) {
                continue;
            }

            $fieldName = $checkbox->getFieldName();
            $checked = ! empty($posted[$fieldName]);

            /**
             * Filter whether a specific checkbox should be validated.
             *
             * @param bool          $shouldValidate Whether to validate.
             * @param LegalCheckbox $checkbox       The checkbox.
             * @param bool          $checked        Whether it was checked.
             */
            $shouldValidate = apply_filters(
                "polski/checkboxes/validate/{$checkbox->id}",
                true,
                $checkbox,
                $checked,
            );

            if (! $shouldValidate) {
                continue;
            }

            if (! $checked) {
                $message = $checkbox->errorMessage !== ''
                    ? $checkbox->errorMessage
                    : sprintf(
                        /* translators: %s: checkbox label (stripped of HTML) */
                        __('Prosz&#281; zaakceptowa&#263;: %s', 'polski'),
                        wp_strip_all_tags($checkbox->label),
                    );

                $errors->add($fieldName, $message);
            }
        }

        if ($errors->has_errors()) {
            return $errors;
        }

        /**
         * Fires after all checkboxes for a context have been validated successfully.
         *
         * @param CheckboxContext $context The validated context.
         */
        do_action('polski/checkboxes/validated', $context);

        return true;
    }

    /**
     * Extract checkbox states from posted data.
     *
     * @param CheckboxContext      $context
     * @param array<string, mixed> $posted
     * @return array<string, bool> Map of checkbox_id => consented.
     */
    public function extractStates(CheckboxContext $context, array $posted): array
    {
        $states = [];
        $checkboxes = $this->getForContext($context);

        foreach ($checkboxes as $checkbox) {
            if (! $checkbox->logConsent) {
                continue;
            }

            $fieldName = $checkbox->getFieldName();
            $states[$checkbox->id] = ! empty($posted[$fieldName]);
        }

        return $states;
    }

    /**
     * Get all registered checkboxes.
     *
     * @return array<string, LegalCheckbox>
     */
    public function all(): array
    {
        return $this->checkboxes;
    }

    /**
     * Get IDs of all core (built-in) checkboxes.
     *
     * @return list<string>
     */
    public function getCoreIds(): array
    {
        return ['terms', 'privacy', 'withdrawal', 'digital_waiver', 'parcel_delivery', 'review_reminder', 'marketing'];
    }

    /**
     * Check if a checkbox ID is a core (built-in) one.
     */
    public function isCore(string $id): bool
    {
        return in_array($id, $this->getCoreIds(), true);
    }

    /**
     * Save overrides for a checkbox (persists label, priority, etc.).
     *
     * @param string               $id        The checkbox ID.
     * @param array<string, mixed> $overrides The fields to override.
     */
    public function saveOverrides(string $id, array $overrides): void
    {
        $allOverrides = $this->loadOverrides();
        $allOverrides[$id] = $overrides;

        update_option(self::OPTION_OVERRIDES, $allOverrides);
        $this->cachedOverrides = $allOverrides;

        // Apply immediately if already initialized.
        $checkbox = $this->get($id);
        if ($checkbox !== null) {
            $checkbox->applyOverrides($overrides);
        }
    }

    /**
     * Get stored overrides for a checkbox.
     *
     * @return array<string, mixed>
     */
    public function getOverrides(string $id): array
    {
        $allOverrides = $this->loadOverrides();
        return isset($allOverrides[$id]) ? (array) $allOverrides[$id] : [];
    }

    /**
     * Load all overrides from DB (cached after first call).
     *
     * @return array<string, array<string, mixed>>
     */
    private function loadOverrides(): array
    {
        if ($this->cachedOverrides !== null) {
            return $this->cachedOverrides;
        }

        $allOverrides = get_option(self::OPTION_OVERRIDES, []);
        $this->cachedOverrides = is_array($allOverrides) ? $allOverrides : [];

        return $this->cachedOverrides;
    }

    /**
     * Get compliance statistics for the dashboard.
     *
     * @return array<string, mixed>
     */
    public function getComplianceStats(): array
    {
        $all = $this->checkboxes;
        $core = array_filter($all, static fn (LegalCheckbox $cb) => $cb->isCore);
        $custom = array_filter($all, static fn (LegalCheckbox $cb) => ! $cb->isCore);

        $enabledCore = array_filter($core, static fn (LegalCheckbox $cb) => $cb->enabled);
        $enabledCustom = array_filter($custom, static fn (LegalCheckbox $cb) => $cb->enabled);

        $requiredEnabled = array_filter($all, static fn (LegalCheckbox $cb) => $cb->enabled && $cb->isRequired());
        $optionalEnabled = array_filter($all, static fn (LegalCheckbox $cb) => $cb->enabled && ! $cb->isRequired());

        // GDPR compliance scoring.
        $score = 0;
        $maxScore = 100;
        $suggestions = [];

        // Terms checkbox (25 points).
        $terms = $this->get('terms');
        if ($terms !== null && $terms->enabled) {
            $score += 25;
        } else {
            $suggestions[] = [
                'id' => 'enable_terms',
                'severity' => 'critical',
                'message' => __('Enable the Terms and Conditions checkbox - required by Polish consumer law.', 'polski'),
            ];
        }

        // Privacy checkbox (25 points).
        $privacy = $this->get('privacy');
        if ($privacy !== null && $privacy->enabled) {
            $score += 25;
        } else {
            $suggestions[] = [
                'id' => 'enable_privacy',
                'severity' => 'critical',
                'message' => __('Enable the Privacy Policy checkbox - required by GDPR Art. 6.1.a and Art. 7.', 'polski'),
            ];
        }

        // Withdrawal checkbox (20 points).
        $withdrawal = $this->get('withdrawal');
        if ($withdrawal !== null && $withdrawal->enabled) {
            $score += 20;
        } else {
            $suggestions[] = [
                'id' => 'enable_withdrawal',
                'severity' => 'high',
                'message' => __('Enable the Withdrawal Rights checkbox - required by Polish Consumer Rights Act (Art. 12).', 'polski'),
            ];
        }

        // Digital waiver (10 points - conditional).
        $digitalWaiver = $this->get('digital_waiver');
        if ($digitalWaiver !== null && $digitalWaiver->enabled) {
            $score += 10;
        } else {
            $suggestions[] = [
                'id' => 'enable_digital_waiver',
                'severity' => 'medium',
                'message' => __('Consider enabling Digital Content waiver if you sell digital products.', 'polski'),
            ];
        }

        // Marketing with proper consent type (10 points).
        $marketing = $this->get('marketing');
        if ($marketing !== null && $marketing->enabled && ! $marketing->isRequired()) {
            $score += 10;
        } elseif ($marketing !== null && $marketing->enabled && $marketing->isRequired()) {
            $suggestions[] = [
                'id' => 'marketing_not_required',
                'severity' => 'high',
                'message' => __('Marketing consent must be optional under GDPR - change it from required to optional.', 'polski'),
            ];
        } else {
            $suggestions[] = [
                'id' => 'enable_marketing',
                'severity' => 'low',
                'message' => __('Consider adding an optional marketing consent checkbox for newsletter compliance.', 'polski'),
            ];
        }

        // Review reminder with opt-in (5 points).
        $reviewReminder = $this->get('review_reminder');
        if ($reviewReminder !== null && $reviewReminder->enabled && ! $reviewReminder->isRequired()) {
            $score += 5;
        } elseif ($reviewReminder !== null && $reviewReminder->enabled && $reviewReminder->isRequired()) {
            $suggestions[] = [
                'id' => 'review_not_required',
                'severity' => 'medium',
                'message' => __('Review reminder consent should be optional - change it from required to optional.', 'polski'),
            ];
        }

        // Consent logging (5 points - always awarded since consent logging is built-in).
        $score += 5;

        // Build context breakdown.
        $byContext = [];
        foreach (CheckboxContext::cases() as $context) {
            $contextCheckboxes = $this->getForContext($context);
            $byContext[$context->value] = [
                'total' => count($contextCheckboxes),
                'required' => count(array_filter($contextCheckboxes, static fn (LegalCheckbox $cb) => $cb->isRequired())),
                'optional' => count(array_filter($contextCheckboxes, static fn (LegalCheckbox $cb) => ! $cb->isRequired())),
            ];
        }

        return [
            'total' => count($all),
            'core_total' => count($core),
            'custom_total' => count($custom),
            'enabled' => count($enabledCore) + count($enabledCustom),
            'disabled' => count($all) - count($enabledCore) - count($enabledCustom),
            'core_enabled' => count($enabledCore),
            'core_disabled' => count($core) - count($enabledCore),
            'custom_enabled' => count($enabledCustom),
            'required_enabled' => count($requiredEnabled),
            'optional_enabled' => count($optionalEnabled),
            'by_context' => $byContext,
            'compliance_score' => min($score, $maxScore),
            'compliance_max' => $maxScore,
            'compliance_grade' => $this->scoreToGrade($score),
            'suggestions' => $suggestions,
            'checkboxes' => array_map(
                static fn (LegalCheckbox $cb) => [
                    'id' => $cb->id,
                    'enabled' => $cb->enabled,
                    'is_core' => $cb->isCore,
                    'type' => $cb->type->value,
                    'contexts' => array_map(static fn (CheckboxContext $c) => $c->value, $cb->contexts),
                ],
                array_values($all),
            ),
        ];
    }

    /**
     * Register the built-in Polish compliance checkboxes.
     */
    private function registerDefaults(): void
    {
        $checkoutSettings = $this->getCheckoutSettings();
        $termsPageId = (int) get_option('polski_terms_page_id', 0);
        $termsUrl = $termsPageId > 0 ? get_permalink($termsPageId) : '#';

        $this->register(new LegalCheckbox(
            id: 'terms',
            label: sprintf(
                /* translators: %s: link to terms page */
                (string) ($checkoutSettings['terms_checkbox_label'] ?? __('I have read and accept the <a href="%s" target="_blank">Terms and Conditions</a>.', 'polski')),
                esc_url($termsUrl ?: '#'),
            ),
            type: ConsentType::Required,
            contexts: [CheckboxContext::Checkout, CheckboxContext::PayForOrder],
            priority: 1,
            errorMessage: (string) ($checkoutSettings['terms_checkbox_error'] ?? __('Musisz zaakceptować Regulamin, aby złożyć zamówienie.', 'polski')),
            description: (string) ($checkoutSettings['terms_checkbox_description'] ?? __('Akceptacja Regulaminu sklepu.', 'polski')),
            isCore: true,
        ));

        $privacyPageId = (int) get_option('polski_privacy_page_id', 0);
        $privacyUrl = $privacyPageId > 0 ? get_permalink($privacyPageId) : get_privacy_policy_url();

        $this->register(new LegalCheckbox(
            id: 'privacy',
            label: sprintf(
                /* translators: %s: link to privacy policy */
                (string) ($checkoutSettings['privacy_checkbox_label'] ?? __('I have read and accept the <a href="%s" target="_blank">Privacy Policy</a>.', 'polski')),
                esc_url($privacyUrl ?: '#'),
            ),
            type: ConsentType::Required,
            contexts: [CheckboxContext::Checkout, CheckboxContext::Registration, CheckboxContext::PayForOrder],
            priority: 2,
            errorMessage: (string) ($checkoutSettings['privacy_checkbox_error'] ?? __('Musisz zaakceptować Politykę prywatności.', 'polski')),
            description: (string) ($checkoutSettings['privacy_checkbox_description'] ?? __('Privacy Policy acceptance (Polityka prywatności).', 'polski')),
            isCore: true,
        ));

        $returnsPageId = (int) get_option('polski_returns_page_id', 0);
        $returnsUrl = $returnsPageId > 0 ? get_permalink($returnsPageId) : '#';

        $this->register(new LegalCheckbox(
            id: 'withdrawal',
            label: sprintf(
                /* translators: %s: link to return policy */
                (string) ($checkoutSettings['withdrawal_checkbox_label'] ?? __('I have been informed about my <a href="%s" target="_blank">right to withdraw from the contract</a> within 14 days.', 'polski')),
                esc_url($returnsUrl ?: '#'),
            ),
            type: ConsentType::Required,
            contexts: [CheckboxContext::Checkout],
            priority: 3,
            errorMessage: (string) ($checkoutSettings['withdrawal_checkbox_error'] ?? __('Musisz potwierdzić zapoznanie się z informacją o prawie odstąpienia.', 'polski')),
            description: (string) ($checkoutSettings['withdrawal_checkbox_description'] ?? __('14-day withdrawal right acknowledgment (Prawo odstąpienia).', 'polski')),
            isCore: true,
        ));

        $this->register(new LegalCheckbox(
            id: 'digital_waiver',
            label: (string) ($checkoutSettings['digital_waiver_checkbox_label'] ?? __('Wyrażam zgodę na rozpoczęcie dostarczania treści cyfrowych przed upływem terminu do odstąpienia od umowy i przyjmuję do wiadomości utratę prawa odstąpienia.', 'polski')),
            type: ConsentType::Required,
            contexts: [CheckboxContext::Checkout],
            priority: 4,
            enabled: false,
            errorMessage: (string) ($checkoutSettings['digital_waiver_checkbox_error'] ?? __('Musisz wyrazić zgodę na natychmiastowe dostarczenie treści cyfrowych.', 'polski')),
            description: (string) ($checkoutSettings['digital_waiver_checkbox_description'] ?? __('Zrzeczenie się prawa odstąpienia dla towarów cyfrowych.', 'polski')),
            isCore: true,
            productTypes: ['downloadable'],
        ));

        $this->register(new LegalCheckbox(
            id: 'parcel_delivery',
            label: (string) ($checkoutSettings['parcel_delivery_checkbox_label'] ?? __('Wyrażam zgodę na otrzymywanie powiadomień SMS/email o statusie dostawy przesyłki.', 'polski')),
            type: ConsentType::Optional,
            contexts: [CheckboxContext::Checkout],
            priority: 10,
            enabled: false,
            description: (string) ($checkoutSettings['parcel_delivery_checkbox_description'] ?? __('Opcjonalna zgoda na powiadomienia o dostawie.', 'polski')),
            isCore: true,
        ));

        $this->register(new LegalCheckbox(
            id: 'review_reminder',
            label: (string) ($checkoutSettings['review_reminder_checkbox_label'] ?? __('Wyrażam zgodę na otrzymanie przypomnienia o wystawieniu opinii drogą mailową po zakupie.', 'polski')),
            type: ConsentType::Optional,
            contexts: [CheckboxContext::Checkout],
            priority: 11,
            enabled: false,
            description: (string) ($checkoutSettings['review_reminder_checkbox_description'] ?? __('Opcjonalna zgoda na przypomnienia o opinii.', 'polski')),
            isCore: true,
        ));

        $this->register(new LegalCheckbox(
            id: 'marketing',
            label: (string) ($checkoutSettings['marketing_checkbox_label'] ?? __('Wyrażam zgodę na otrzymywanie komunikacji marketingowej i newslettera.', 'polski')),
            type: ConsentType::Optional,
            contexts: [CheckboxContext::Checkout, CheckboxContext::Registration],
            priority: 12,
            enabled: false,
            description: (string) ($checkoutSettings['marketing_checkbox_description'] ?? __('Opcjonalna zgoda marketingowa (RODO art. 6 ust. 1 lit. a).', 'polski')),
            isCore: true,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function getCheckoutSettings(): array
    {
        $settings = get_option('polski_checkout', []);

        return is_array($settings) ? $settings : [];
    }

    /**
     * Apply stored overrides to built-in checkboxes.
     *
     * This allows admin-customized labels, error messages, priorities, etc.
     * to persist across updates.
     */
    private function applyStoredOverrides(): void
    {
        $allOverrides = $this->loadOverrides();

        // Also merge legacy checkout settings for enabled/disabled state.
        $checkoutSettings = get_option('polski_checkout', []);
        if (is_array($checkoutSettings)) {
            foreach ($this->getCoreIds() as $coreId) {
                $key = $coreId . '_checkbox_enabled';
                if (isset($checkoutSettings[$key])) {
                    if (! isset($allOverrides[$coreId])) {
                        $allOverrides[$coreId] = [];
                    }
                    // Legacy setting only applies if no explicit override exists.
                    if (! isset($allOverrides[$coreId]['enabled'])) {
                        $allOverrides[$coreId]['enabled'] = (bool) $checkoutSettings[$key];
                    }
                }
            }
        }

        foreach ($allOverrides as $id => $overrides) {
            $checkbox = $this->get($id);
            if ($checkbox !== null && is_array($overrides)) {
                $checkbox->applyOverrides($overrides);
            }
        }
    }

    /**
     * Load custom checkboxes from stored options.
     */
    private function loadCustomCheckboxes(): void
    {
        $custom = get_option(self::OPTION_CUSTOM, []);

        if (! is_array($custom)) {
            return;
        }

        foreach ($custom as $data) {
            if (is_array($data) && ! empty($data['id'])) {
                $this->register(LegalCheckbox::fromArray($data));
            }
        }
    }

    /**
     * Convert a numeric compliance score to a letter grade.
     */
    private function scoreToGrade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 75 => 'B',
            $score >= 60 => 'C',
            $score >= 40 => 'D',
            default => 'F',
        };
    }
}
