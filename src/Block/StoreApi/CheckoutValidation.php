<?php

declare(strict_types=1);

namespace Spolszczony\Block\StoreApi;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use Spolszczony\Contract\HasHooks;
use Spolszczony\Enum\CheckboxContext;
use Spolszczony\Service\CheckboxService;
use Spolszczony\Repository\ConsentLogRepository;

/**
 * Block checkout integration: adds Spolszczony validation and data
 * to the WooCommerce checkout block via Store API.
 *
 * Handles:
 * - Legal checkbox validation before order placement
 * - Consent logging after order creation
 * - Order button text override
 * - Additional checkout data (NIP field for PRO)
 */
final class CheckoutValidation implements HasHooks
{
    public function __construct(
        private readonly CheckboxService $checkboxService,
        private readonly ConsentLogRepository $consentLog,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('woocommerce_blocks_loaded', [$this, 'registerExtensions']);
    }

    public function registerExtensions(): void
    {
        // Register additional checkout data namespace.
        if (function_exists('woocommerce_store_api_register_endpoint_data')) {
            woocommerce_store_api_register_endpoint_data([
                'endpoint' => CheckoutSchema::IDENTIFIER,
                'namespace' => 'spolszczony',
                'schema_callback' => [$this, 'getCheckoutSchema'],
                'data_callback' => [$this, 'getCheckoutData'],
                'schema_type' => ARRAY_A,
            ]);
        }

        // Validate on checkout.
        if (function_exists('woocommerce_store_api_register_update_callback')) {
            woocommerce_store_api_register_update_callback([
                'namespace' => 'spolszczony',
                'callback' => [$this, 'processCheckoutData'],
            ]);
        }

        // Validate before payment processing.
        add_action(
            'woocommerce_store_api_checkout_update_order_from_request',
            [$this, 'validateAndSaveCheckoutData'],
            10,
            2,
        );

        // Override order button text for block checkout.
        add_filter(
            'render_block_woocommerce/checkout-actions-block',
            [$this, 'filterOrderButtonBlock'],
        );
    }

    /**
     * Schema for additional checkout data.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getCheckoutSchema(): array
    {
        $schema = [
            'checkboxes' => [
                'description' => 'Zaakceptowane checkboxy prawne',
                'type' => 'object',
                'context' => ['view', 'edit'],
                'additionalProperties' => ['type' => 'boolean'],
            ],
        ];

        return $schema;
    }

    /**
     * Default checkout data.
     *
     * @return array<string, mixed>
     */
    public function getCheckoutData(): array
    {
        return [
            'checkboxes' => [],
        ];
    }

    /**
     * Process Store API checkout update callback.
     *
     * @param array<string, mixed> $data
     */
    public function processCheckoutData(array $data): void
    {
        // Store checkbox states in session for later validation.
        if (isset($data['checkboxes']) && is_array($data['checkboxes'])) {
            WC()->session?->set('spolszczony_checkboxes', $data['checkboxes']);
        }
    }

    /**
     * Validate checkbox data and save to order during block checkout.
     */
    public function validateAndSaveCheckoutData(\WC_Order $order, \WP_REST_Request $request): void
    {
        $extensionData = $request->get_param('extensions') ?? [];
        $spolszczonyData = $extensionData['spolszczony'] ?? [];
        $checkboxStates = $spolszczonyData['checkboxes'] ?? [];

        // Also check session fallback.
        if (empty($checkboxStates)) {
            $checkboxStates = WC()->session?->get('spolszczony_checkboxes', []) ?? [];
        }

        // Validate required checkboxes.
        $checkboxes = $this->checkboxService->getForContext(CheckboxContext::Checkout);

        foreach ($checkboxes as $checkbox) {
            if (! $checkbox->isRequired()) {
                continue;
            }

            $checked = ! empty($checkboxStates[$checkbox->id]);

            if (! $checked) {
                $message = $checkbox->errorMessage !== ''
                    ? $checkbox->errorMessage
                    : sprintf(__('Please accept: %s', 'spolszczony'), wp_strip_all_tags($checkbox->label));

                throw new \Automatic\WooCommerce\StoreApi\Exceptions\RouteException(
                    'spolszczony_checkbox_' . $checkbox->id,
                    $message,
                    400,
                );
            }
        }

        // Save to order meta.
        if (! empty($checkboxStates)) {
            $order->update_meta_data('_spolszczony_checkboxes_accepted', $checkboxStates);
        }

        // Log consents.
        $userId = $order->get_customer_id() > 0 ? $order->get_customer_id() : null;
        $sessionId = 'order_' . $order->get_id();

        $states = [];
        foreach ($checkboxes as $checkbox) {
            $states[$checkbox->id] = ! empty($checkboxStates[$checkbox->id]);
        }

        if (! empty($states)) {
            $this->consentLog->logBatch($states, CheckboxContext::Checkout, $userId, $sessionId);
        }

        // Clean up session.
        WC()->session?->set('spolszczony_checkboxes', null);
    }

    /**
     * Override order button text in block checkout.
     */
    public function filterOrderButtonBlock(string $blockContent): string
    {
        $settings = get_option('spolszczony_checkout', []);
        $buttonText = is_array($settings) ? ($settings['order_button_text'] ?? '') : '';

        if ($buttonText === '') {
            return $blockContent;
        }

        // Replace the button text in the rendered block HTML.
        return preg_replace(
            '/(<button[^>]*class="[^"]*wc-block-components-checkout-place-order-button[^"]*"[^>]*>)(.+?)(<\/button>)/s',
            '${1}' . esc_html($buttonText) . '${3}',
            $blockContent,
        ) ?? $blockContent;
    }
}
