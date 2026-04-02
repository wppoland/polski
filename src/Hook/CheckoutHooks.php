<?php

declare(strict_types=1);

namespace Spolszczony\Hook;

use Spolszczony\Contract\Bootable;
use Spolszczony\Contract\HasHooks;
use Spolszczony\Enum\CheckboxContext;
use Spolszczony\Model\LegalCheckbox;
use Spolszczony\Repository\ConsentLogRepository;
use Spolszczony\Service\CheckboxService;

/**
 * Checkout modifications: legal checkboxes, order button text, consent logging.
 */
final class CheckoutHooks implements Bootable, HasHooks
{
    public function __construct(
        private readonly CheckboxService $checkboxes,
        private readonly ConsentLogRepository $consentLog,
    ) {
    }

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        // Override order button text.
        add_filter('woocommerce_order_button_text', [$this, 'filterOrderButtonText']);

        // Render legal checkboxes before order submit.
        add_action('woocommerce_review_order_before_submit', [$this, 'renderCheckoutCheckboxes'], 10);

        // Validate checkboxes on checkout.
        add_action('woocommerce_checkout_process', [$this, 'validateCheckoutCheckboxes']);

        // Log consents after order is created.
        add_action('woocommerce_checkout_order_created', [$this, 'logCheckoutConsents']);

        // Registration form checkboxes.
        add_action('woocommerce_register_form', [$this, 'renderRegistrationCheckboxes']);
        add_filter('woocommerce_process_registration_errors', [$this, 'validateRegistrationCheckboxes'], 10, 4);

        // Pay-for-order page.
        add_action('woocommerce_pay_order_before_submit', [$this, 'renderPayForOrderCheckboxes']);

        // Remove default WC terms checkbox (we replace it).
        add_filter('woocommerce_checkout_show_terms', '__return_false');

        // Store checkbox states in order meta.
        add_action('woocommerce_checkout_create_order', [$this, 'saveCheckboxStatesToOrder'], 10, 2);
    }

    /**
     * Override the order button text to comply with Polish law.
     */
    public function filterOrderButtonText(string $text): string
    {
        $settings = get_option('spolszczony_checkout', []);
        $customText = is_array($settings) ? ($settings['order_button_text'] ?? '') : '';

        if ($customText !== '') {
            $text = $customText;
        }

        /**
         * Filter the order button text.
         *
         * @param string $text The button text.
         */
        return (string) apply_filters('spolszczony/checkout/order_button_text', $text);
    }

    /**
     * Render legal checkboxes in the checkout form.
     */
    public function renderCheckoutCheckboxes(): void
    {
        $this->renderCheckboxes(CheckboxContext::Checkout);
    }

    /**
     * Render checkboxes on the registration form.
     */
    public function renderRegistrationCheckboxes(): void
    {
        $this->renderCheckboxes(CheckboxContext::Registration);
    }

    /**
     * Render checkboxes on the pay-for-order page.
     */
    public function renderPayForOrderCheckboxes(): void
    {
        $this->renderCheckboxes(CheckboxContext::PayForOrder);
    }

    /**
     * Validate checkout checkboxes.
     */
    public function validateCheckoutCheckboxes(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce.
        $result = $this->checkboxes->validate(CheckboxContext::Checkout, $_POST);

        if ($result instanceof \WP_Error) {
            foreach ($result->get_error_messages() as $message) {
                wc_add_notice($message, 'error');
            }
        }
    }

    /**
     * Validate registration form checkboxes.
     *
     * @param \WP_Error $errors
     * @return \WP_Error
     */
    public function validateRegistrationCheckboxes(\WP_Error $errors): \WP_Error
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $result = $this->checkboxes->validate(CheckboxContext::Registration, $_POST);

        if ($result instanceof \WP_Error) {
            foreach ($result->get_error_codes() as $code) {
                $errors->add($code, $result->get_error_message($code));
            }
        }

        return $errors;
    }

    /**
     * Log checkbox consents after order creation.
     */
    public function logCheckoutConsents(\WC_Order $order): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $states = $this->checkboxes->extractStates(CheckboxContext::Checkout, $_POST);

        if (empty($states)) {
            return;
        }

        $userId = $order->get_customer_id() > 0 ? $order->get_customer_id() : null;
        $sessionId = 'order_' . $order->get_id();

        $this->consentLog->logBatch($states, CheckboxContext::Checkout, $userId, $sessionId);

        /**
         * Fires after checkout consent states are logged.
         *
         * @param array<string, bool> $states  The checkbox states.
         * @param \WC_Order           $order   The order.
         */
        do_action('spolszczony/checkout/consents_logged', $states, $order);
    }

    /**
     * Save checkbox states to order meta for reference.
     */
    public function saveCheckboxStatesToOrder(\WC_Order $order, array $data): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $states = $this->checkboxes->extractStates(CheckboxContext::Checkout, $_POST);

        if (! empty($states)) {
            $order->update_meta_data('_spolszczony_checkboxes_accepted', $states);
        }
    }

    /**
     * Render checkboxes for a given context.
     */
    private function renderCheckboxes(CheckboxContext $context): void
    {
        $checkboxes = $this->checkboxes->getForContext($context);

        if (empty($checkboxes)) {
            return;
        }

        echo '<div class="spolszczony-legal-checkboxes">';

        foreach ($checkboxes as $checkbox) {
            $this->renderSingleCheckbox($checkbox);
        }

        echo '</div>';
    }

    /**
     * Render a single checkbox field.
     */
    private function renderSingleCheckbox(LegalCheckbox $checkbox): void
    {
        $fieldName = 'spolszczony_checkbox_' . $checkbox->id;
        $required = $checkbox->isRequired();

        printf(
            '<p class="form-row spolszczony-checkbox spolszczony-checkbox--%s %s">',
            esc_attr($checkbox->id),
            $required ? 'validate-required' : '',
        );

        printf(
            '<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
                       name="%s" id="%s" value="1" %s />
                <span class="woocommerce-terms-and-conditions-checkbox-text">%s</span>
                %s
            </label>',
            esc_attr($fieldName),
            esc_attr($fieldName),
            $required ? 'required' : '',
            wp_kses(
                $checkbox->label,
                [
                    'a' => ['href' => [], 'target' => [], 'rel' => [], 'class' => []],
                    'strong' => [],
                    'em' => [],
                ],
            ),
            $required ? '<abbr class="required" title="' . esc_attr__('required', 'spolszczony') . '">*</abbr>' : '',
        );

        echo '</p>';
    }
}
