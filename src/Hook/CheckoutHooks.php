<?php

declare(strict_types=1);
namespace Polski\Hook;

defined('ABSPATH') || exit;

use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Enum\CheckboxContext;
use Polski\Repository\ConsentLogRepository;
use Polski\Service\CheckboxService;
use Polski\Util\TemplateLoader;

use const Polski\PLUGIN_FILE;
use const Polski\VERSION;

/**
 * Checkout modifications: legal checkboxes, order button text, consent logging.
 */
final class CheckoutHooks implements Bootable, HasHooks
{
    public function __construct(
        private readonly CheckboxService $checkboxes,
        private readonly ConsentLogRepository $consentLog,
        private readonly TemplateLoader $templates,
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

        // AJAX fragment refresh for conditional checkboxes.
        add_filter('woocommerce_update_order_review_fragments', [$this, 'refreshCheckboxFragments']);

        // Enqueue frontend checkout JS.
        add_action('wp_enqueue_scripts', [$this, 'enqueueCheckoutAssets']);
    }

    /**
     * Enqueue checkout assets on checkout, pay-for-order, and registration pages.
     */
    public function enqueueCheckoutAssets(): void
    {
        if (! function_exists('is_checkout') || (! is_checkout() && ! is_account_page())) {
            return;
        }

        wp_enqueue_style(
            'polski-frontend',
            plugins_url('assets/css/frontend.css', PLUGIN_FILE),
            [],
            VERSION,
        );

        wp_enqueue_script(
            'polski-checkout',
            plugins_url('build/frontend-checkout.js', PLUGIN_FILE),
            ['jquery'],
            VERSION,
            true,
        );
    }

    /**
     * Override the order button text to comply with Polish law.
     */
    public function filterOrderButtonText(string $text): string
    {
        $settings = get_option('polski_checkout', []);
        $customText = is_array($settings) ? ($settings['order_button_text'] ?? '') : '';

        if ($customText !== '') {
            $text = $customText;
        }

        /**
         * Filter the order button text.
         *
         * @param string $text The button text.
         */
        return (string) apply_filters('polski/checkout/order_button_text', $text);
    }

    /**
     * Render legal checkboxes in the checkout form.
     */
    public function renderCheckoutCheckboxes(): void
    {
        $cartContext = $this->buildCartContext();
        $checkboxes = $this->checkboxes->getForContext(CheckboxContext::Checkout, $cartContext);

        $this->templates->include('checkout/legal-checkboxes', [
            'checkboxes' => $checkboxes,
            'context' => CheckboxContext::Checkout,
        ]);
    }

    /**
     * Render checkboxes on the registration form.
     */
    public function renderRegistrationCheckboxes(): void
    {
        $checkboxes = $this->checkboxes->getForContext(CheckboxContext::Registration);

        $this->templates->include('checkout/legal-checkboxes', [
            'checkboxes' => $checkboxes,
            'context' => CheckboxContext::Registration,
        ]);
    }

    /**
     * Render checkboxes on the pay-for-order page.
     */
    public function renderPayForOrderCheckboxes(): void
    {
        $checkboxes = $this->checkboxes->getForContext(CheckboxContext::PayForOrder);

        $this->templates->include('checkout/legal-checkboxes', [
            'checkboxes' => $checkboxes,
            'context' => CheckboxContext::PayForOrder,
        ]);
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
     */
    public function validateRegistrationCheckboxes(
        \WP_Error $errors,
        string $username = '',
        string $password = '',
        string $email = '',
    ): \WP_Error
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
        do_action('polski/checkout/consents_logged', $states, $order);
    }

    /**
     * Save checkbox states to order meta for reference.
     *
     * @param array<string, mixed> $data
     */
    public function saveCheckboxStatesToOrder(\WC_Order $order, array $data): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $states = $this->checkboxes->extractStates(CheckboxContext::Checkout, $_POST);

        if (! empty($states)) {
            $order->update_meta_data('_polski_checkboxes_accepted', $states);
        }
    }

    /**
     * Refresh checkbox fragments on order review AJAX update.
     *
     * This allows conditional checkboxes (e.g., digital_waiver for downloadable
     * products) to appear/disappear when cart contents or payment method changes.
     *
     * @param array<string, string> $fragments
     * @return array<string, string>
     */
    public function refreshCheckboxFragments(array $fragments): array
    {
        $cartContext = $this->buildCartContext();
        $checkboxes = $this->checkboxes->getForContext(CheckboxContext::Checkout, $cartContext);

        $html = $this->templates->render('checkout/legal-checkboxes', [
            'checkboxes' => $checkboxes,
            'context' => CheckboxContext::Checkout,
        ]);

        $fragments['.polski-legal-checkboxes'] = $html;

        return $fragments;
    }

    /**
     * Build cart context array for conditional checkbox display.
     *
     * @return array<string, mixed>
     */
    private function buildCartContext(): array
    {
        $context = [
            'category_ids' => [],
            'country' => '',
            'payment_method' => '',
            'product_types' => [],
        ];

        if (! function_exists('WC') || WC()->cart === null) {
            return $context;
        }

        $cart = WC()->cart;

        foreach ($cart->get_cart() as $item) {
            $product = $item['data'] ?? null;
            if (! $product instanceof \WC_Product) {
                continue;
            }

            // Collect category IDs.
            $categoryIds = $product->get_category_ids();
            $context['category_ids'] = array_unique(array_merge($context['category_ids'], $categoryIds));

            // Collect product types.
            if ($product->is_downloadable()) {
                $context['product_types'][] = 'downloadable';
            }
            if ($product->is_virtual()) {
                $context['product_types'][] = 'virtual';
            }
            $context['product_types'][] = $product->get_type();
        }

        $context['product_types'] = array_unique($context['product_types']);

        // Billing country.
        $customer = WC()->customer;
        if ($customer !== null) {
            $context['country'] = $customer->get_billing_country();
        }

        // Selected payment method (read-only context lookup; WooCommerce handles checkout nonce).
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $paymentMethod = isset($_POST['payment_method'])
            ? wp_unslash($_POST['payment_method'])
            : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $context['payment_method'] = sanitize_key((string) $paymentMethod);

        return $context;
    }
}
