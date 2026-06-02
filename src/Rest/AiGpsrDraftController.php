<?php

declare(strict_types=1);

namespace Polski\Rest;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;
use Polski\Service\AiGpsrDraftService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST routes for the draft-only AI-assisted GPSR safety-text generator.
 *
 *   POST /polski/v1/ai/gpsr-draft  {product_id}  -> generate + store draft, return it
 *   GET  /polski/v1/ai/gpsr-draft  ?product_id   -> read the stored draft (if any)
 *
 * Both routes are gated behind manage_woocommerce, a REST nonce (on the write
 * route), and the 'ai_bridge' module toggle plus AI Client availability. No
 * provider key is ever handled here and the plugin makes no outbound HTTP request.
 *
 * The write route stores the result ONLY in the separate draft meta
 * (AiGpsrDraftService::DRAFT_META_KEY). It never writes the real, merchant-entered
 * GPSR fields.
 */
final class AiGpsrDraftController extends RestController implements HasHooks
{
    public function __construct(
        private readonly AiGpsrDraftService $service,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        $args = [
            'product_id' => [
                'description' => __('WooCommerce product ID to draft GPSR safety text for.', 'polski'),
                'type' => 'integer',
                'required' => true,
                'minimum' => 1,
                'sanitize_callback' => 'absint',
            ],
        ];

        register_rest_route($this->namespace, '/ai/gpsr-draft', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'generate'],
                'permission_callback' => [$this, 'writePermissionCheck'],
                'args' => $args,
            ],
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'read'],
                'permission_callback' => [$this, 'adminPermissionCheck'],
                'args' => $args,
            ],
        ]);
    }

    /**
     * Capability + nonce check for the write route.
     */
    public function writePermissionCheck(WP_REST_Request $request): bool|WP_Error
    {
        $capability = $this->adminPermissionCheck($request);
        if ($capability instanceof WP_Error) {
            return $capability;
        }

        $nonce = (string) $request->get_header('X-WP-Nonce');
        if ($nonce === '' || ! wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'polski_rest_invalid_nonce',
                __('Invalid or missing security token.', 'polski'),
                ['status' => 403],
            );
        }

        return true;
    }

    public function generate(WP_REST_Request $request): WP_REST_Response
    {
        if (! ModulesPage::isModuleEnabled(AiGpsrDraftService::MODULE) || ! $this->service->isAvailable()) {
            return new WP_REST_Response([
                'error' => 'ai_unavailable',
                'message' => __('AI GPSR draft generation is not available. Enable the AI Bridge module and configure an AI provider.', 'polski'),
            ], 422);
        }

        $product = $this->resolveProduct($request);
        if ($product instanceof WP_REST_Response) {
            return $product;
        }

        $draft = $this->service->generateAndStoreDraft($product);
        if ($draft === null) {
            return new WP_REST_Response([
                'error' => 'generation_failed',
                'message' => __('The AI provider could not generate a GPSR draft. No fields were changed.', 'polski'),
            ], 502);
        }

        return new WP_REST_Response([
            'product_id' => $product->get_id(),
            'draft' => $draft,
        ], 200);
    }

    public function read(WP_REST_Request $request): WP_REST_Response
    {
        $product = $this->resolveProduct($request);
        if ($product instanceof WP_REST_Response) {
            return $product;
        }

        $draft = $this->service->getStoredDraft($product);
        if ($draft === null) {
            return new WP_REST_Response([
                'product_id' => $product->get_id(),
                'draft' => null,
            ], 200);
        }

        return new WP_REST_Response([
            'product_id' => $product->get_id(),
            'draft' => $draft,
        ], 200);
    }

    /**
     * Resolve a valid WC_Product from the request, or an error response.
     */
    private function resolveProduct(WP_REST_Request $request): \WC_Product|WP_REST_Response
    {
        $productId = absint((string) $request->get_param('product_id'));
        if ($productId < 1 || ! function_exists('wc_get_product')) {
            return new WP_REST_Response([
                'error' => 'invalid_product',
                'message' => __('A valid product_id is required.', 'polski'),
            ], 400);
        }

        $product = wc_get_product($productId);
        if (! $product instanceof \WC_Product) {
            return new WP_REST_Response([
                'error' => 'product_not_found',
                'message' => __('Product not found.', 'polski'),
            ], 404);
        }

        return $product;
    }
}
