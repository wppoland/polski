<?php

declare(strict_types=1);
namespace Polski\Block\StoreApi;

defined('ABSPATH') || exit;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use Polski\Contract\HasHooks;
use Polski\Enum\CheckboxContext;
use Polski\Service\CheckboxService;
use Polski\Repository\ConsentLogRepository;

/**
 * Block checkout integration: adds Polski validation and data
 * to the WooCommerce checkout block via Store API.
 *
 * Handles:
 * - Legal checkbox validation before order placement
 * - Consent logging after order creation
 * - Order button text override
 * - Additional checkout data via extension points
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
                'namespace' => 'polski',
                'schema_callback' => [$this, 'getCheckoutSchema'],
                'data_callback' => [$this, 'getCheckoutData'],
                'schema_type' => ARRAY_A,
            ]);
        }

        // Validate on checkout.
        if (function_exists('woocommerce_store_api_register_update_callback')) {
            woocommerce_store_api_register_update_callback([
                'namespace' => 'polski',
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

        // Register the checkout block integration script.
        add_action('woocommerce_blocks_enqueue_checkout_block_scripts_after', [$this, 'enqueueBlockScript']);
    }

    /**
     * Enqueue the legal checkboxes block script for WC checkout block.
     */
    public function enqueueBlockScript(): void
    {
        $assetFile = \Polski\PLUGIN_DIR . '/build/blocks/checkout-legal-checkboxes/index.asset.php';

        if (! file_exists($assetFile)) {
            return;
        }

        $asset = require $assetFile;

        $handle = 'polski-checkout-legal-checkboxes-block';

        wp_enqueue_script(
            $handle,
            plugins_url('build/blocks/checkout-legal-checkboxes/index.js', \Polski\PLUGIN_FILE),
            $asset['dependencies'] ?? [],
            $asset['version'] ?? \Polski\VERSION,
            true,
        );

        wp_set_script_translations($handle, 'polski', \Polski\PLUGIN_DIR . '/languages');
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
            WC()->session?->set('polski_checkboxes', $data['checkboxes']);
        }
    }

    /**
     * Validate checkbox data and save to order during block checkout.
     */
    public function validateAndSaveCheckoutData(\WC_Order $order, \WP_REST_Request $request): void
    {
        $extensionData = $request->get_param('extensions') ?? [];
        $polskiData = $extensionData['polski'] ?? [];
        $checkboxStates = $polskiData['checkboxes'] ?? [];

        // Also check session fallback.
        if (empty($checkboxStates)) {
            $checkboxStates = WC()->session?->get('polski_checkboxes', []) ?? [];
        }

        // Validate required checkboxes.
        $checkboxes = $this->checkboxService->getForContext(CheckboxContext::Checkout);

        foreach ($checkboxes as $checkbox) {
            if (! $checkbox->isRequired() || $checkbox->hideInput) {
                continue;
            }

            $checked = ! empty($checkboxStates[$checkbox->id]);

            /** @see \Polski\Service\CheckboxService::validate() */
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
                    : sprintf(__('Prosz&#281; zaakceptowa&#263;: %s', 'polski'), wp_strip_all_tags($checkbox->label));

                throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
                    $checkbox->getFieldName(),
                    $message,
                    400,
                );
            }
        }

        // Save to order meta.
        if (! empty($checkboxStates)) {
            $order->update_meta_data('_polski_checkboxes_accepted', $checkboxStates);
        }

        // Log consents.
        $userId = $order->get_customer_id() > 0 ? $order->get_customer_id() : null;
        $sessionId = 'order_' . $order->get_id();

        $states = [];
        foreach ($checkboxes as $checkbox) {
            if (! $checkbox->logConsent) {
                continue;
            }
            $states[$checkbox->id] = ! empty($checkboxStates[$checkbox->id]);
        }

        if (! empty($states)) {
            $this->consentLog->logBatch($states, CheckboxContext::Checkout, $userId, $sessionId);
        }

        // Clean up session.
        WC()->session?->set('polski_checkboxes', null);
    }

    /**
     * Override order button text in block checkout.
     */
    public function filterOrderButtonBlock(string $blockContent): string
    {
        $settings = get_option('polski_checkout', []);
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
