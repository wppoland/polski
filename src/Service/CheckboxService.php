<?php

declare(strict_types=1);

namespace Spolszczony\Service;

use Spolszczony\Contract\Bootable;
use Spolszczony\Contract\HasHooks;
use Spolszczony\Enum\CheckboxContext;
use Spolszczony\Enum\ConsentType;
use Spolszczony\Model\LegalCheckbox;

/**
 * Manages legal checkboxes: registration, validation, and rendering.
 *
 * Supports built-in checkboxes (terms, privacy, withdrawal, etc.) and
 * allows registration of custom checkboxes via filters.
 */
final class CheckboxService implements Bootable, HasHooks
{
    /** @var array<string, LegalCheckbox> */
    private array $checkboxes = [];

    public function boot(): void
    {
        $this->registerDefaults();
        $this->loadCustomCheckboxes();
    }

    public function registerHooks(): void
    {
        // CheckboxService doesn't register hooks itself — CheckoutHooks handles rendering/validation.
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
     * @return list<LegalCheckbox>
     */
    public function getForContext(CheckboxContext $context): array
    {
        $filtered = array_filter(
            $this->checkboxes,
            static fn (LegalCheckbox $cb) => $cb->isVisibleIn($context),
        );

        usort($filtered, static fn (LegalCheckbox $a, LegalCheckbox $b) => $a->priority <=> $b->priority);

        /**
         * Filter the checkboxes for a given context.
         *
         * @param list<LegalCheckbox>  $checkboxes The checkboxes.
         * @param CheckboxContext      $context    The display context.
         */
        $filtered = apply_filters('spolszczony/checkout/checkboxes', array_values($filtered), $context);

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

            $fieldName = 'spolszczony_checkbox_' . $checkbox->id;
            $checked = ! empty($posted[$fieldName]);

            if (! $checked) {
                $message = $checkbox->errorMessage !== ''
                    ? $checkbox->errorMessage
                    : sprintf(
                        /* translators: %s: checkbox label (stripped of HTML) */
                        __('Please accept: %s', 'spolszczony'),
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
         */
        do_action('spolszczony/checkout/all_checkboxes_validated', $context);

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
            $fieldName = 'spolszczony_checkbox_' . $checkbox->id;
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
     * Register the built-in Polish compliance checkboxes.
     */
    private function registerDefaults(): void
    {
        $settings = get_option('spolszczony_checkout', []);

        if (! is_array($settings)) {
            $settings = [];
        }

        // Terms & Conditions.
        if ($settings['terms_checkbox_enabled'] ?? true) {
            $termsPageId = (int) get_option('spolszczony_terms_page_id', 0);
            $termsUrl = $termsPageId > 0 ? get_permalink($termsPageId) : '#';

            $this->register(new LegalCheckbox(
                id: 'terms',
                label: sprintf(
                    /* translators: %s: link to terms page */
                    __('I have read and accept the <a href="%s" target="_blank">Terms and Conditions</a>.', 'spolszczony'),
                    esc_url($termsUrl ?: '#'),
                ),
                type: ConsentType::Required,
                contexts: [CheckboxContext::Checkout, CheckboxContext::PayForOrder],
                priority: 1,
                errorMessage: __('You must accept the Terms and Conditions to place an order.', 'spolszczony'),
                description: __('Terms and Conditions acceptance (Regulamin sklepu).', 'spolszczony'),
            ));
        }

        // Privacy Policy.
        if ($settings['privacy_checkbox_enabled'] ?? true) {
            $privacyPageId = (int) get_option('spolszczony_privacy_page_id', 0);
            $privacyUrl = $privacyPageId > 0 ? get_permalink($privacyPageId) : get_privacy_policy_url();

            $this->register(new LegalCheckbox(
                id: 'privacy',
                label: sprintf(
                    /* translators: %s: link to privacy policy */
                    __('I have read and accept the <a href="%s" target="_blank">Privacy Policy</a>.', 'spolszczony'),
                    esc_url($privacyUrl ?: '#'),
                ),
                type: ConsentType::Required,
                contexts: [CheckboxContext::Checkout, CheckboxContext::Registration, CheckboxContext::PayForOrder],
                priority: 2,
                errorMessage: __('You must accept the Privacy Policy.', 'spolszczony'),
                description: __('Privacy Policy acceptance (Polityka prywatnosci).', 'spolszczony'),
            ));
        }

        // Withdrawal rights acknowledgment.
        if ($settings['withdrawal_checkbox_enabled'] ?? true) {
            $returnsPageId = (int) get_option('spolszczony_returns_page_id', 0);
            $returnsUrl = $returnsPageId > 0 ? get_permalink($returnsPageId) : '#';

            $this->register(new LegalCheckbox(
                id: 'withdrawal',
                label: sprintf(
                    /* translators: %s: link to return policy */
                    __('I have been informed about my <a href="%s" target="_blank">right to withdraw from the contract</a> within 14 days.', 'spolszczony'),
                    esc_url($returnsUrl ?: '#'),
                ),
                type: ConsentType::Required,
                contexts: [CheckboxContext::Checkout],
                priority: 3,
                errorMessage: __('You must acknowledge the withdrawal rights information.', 'spolszczony'),
                description: __('14-day withdrawal right acknowledgment (Prawo odstapienia).', 'spolszczony'),
            ));
        }

        // Digital content / service — waiver of withdrawal right.
        $this->register(new LegalCheckbox(
            id: 'digital_waiver',
            label: __('I agree that the digital content delivery begins immediately and I acknowledge that I lose my right to withdraw from the contract.', 'spolszczony'),
            type: ConsentType::Required,
            contexts: [CheckboxContext::Checkout],
            priority: 4,
            enabled: false, // Enabled per-product via _spolszczony_withdrawal_exempt meta.
            errorMessage: __('You must consent to immediate digital content delivery.', 'spolszczony'),
            description: __('Waiver of withdrawal right for digital goods.', 'spolszczony'),
        ));

        // Parcel delivery notification.
        $this->register(new LegalCheckbox(
            id: 'parcel_delivery',
            label: __('I agree to receive SMS/email notifications about parcel delivery status.', 'spolszczony'),
            type: ConsentType::Optional,
            contexts: [CheckboxContext::Checkout],
            priority: 10,
            enabled: false,
            description: __('Optional consent for delivery notifications.', 'spolszczony'),
        ));

        // Review reminder.
        $this->register(new LegalCheckbox(
            id: 'review_reminder',
            label: __('I agree to receive a product review reminder by email after my purchase.', 'spolszczony'),
            type: ConsentType::Optional,
            contexts: [CheckboxContext::Checkout],
            priority: 11,
            enabled: false,
            description: __('Optional consent for review reminder emails.', 'spolszczony'),
        ));

        // Marketing consent (newsletter).
        $this->register(new LegalCheckbox(
            id: 'marketing',
            label: __('I agree to receive marketing communications and newsletters.', 'spolszczony'),
            type: ConsentType::Optional,
            contexts: [CheckboxContext::Checkout, CheckboxContext::Registration],
            priority: 12,
            enabled: false,
            description: __('Optional marketing consent (GDPR Art. 6.1.a).', 'spolszczony'),
        ));
    }

    /**
     * Load custom checkboxes from stored options.
     */
    private function loadCustomCheckboxes(): void
    {
        $custom = get_option('spolszczony_custom_checkboxes', []);

        if (! is_array($custom)) {
            return;
        }

        foreach ($custom as $data) {
            if (is_array($data) && ! empty($data['id'])) {
                $this->register(LegalCheckbox::fromArray($data));
            }
        }
    }
}
