<?php

declare(strict_types=1);

namespace Polski\Rest;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;
use Polski\Service\AiProductSummaryService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * POST /polski/v1/ai/product-summary
 *
 * Admin-triggered, draft-only generation of a factual product summary. The route
 * is gated behind manage_woocommerce, a REST nonce, and the 'ai_bridge' module
 * toggle plus AI Client availability. No provider key is ever handled here and no
 * outbound HTTP request is made by the plugin.
 */
final class AiProductSummaryController extends RestController implements HasHooks
{
    public function __construct(
        private readonly AiProductSummaryService $service,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/ai/product-summary', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'generate'],
                'permission_callback' => [$this, 'writePermissionCheck'],
                'args' => [
                    'product_id' => [
                        'description' => __('WooCommerce product ID to summarise.', 'polski'),
                        'type' => 'integer',
                        'required' => true,
                        'minimum' => 1,
                        'sanitize_callback' => 'absint',
                    ],
                ],
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
        if (! ModulesPage::isModuleEnabled(AiProductSummaryService::MODULE) || ! $this->service->isAvailable()) {
            return new WP_REST_Response([
                'error' => 'ai_unavailable',
                'message' => __('AI summary generation is not available. Enable the AI Bridge module and configure an AI provider.', 'polski'),
            ], 422);
        }

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

        $summary = $this->service->generateAndStore($product);
        if ($summary === null) {
            return new WP_REST_Response([
                'error' => 'generation_failed',
                'message' => __('The AI provider could not generate a summary. The product is unchanged.', 'polski'),
            ], 502);
        }

        return new WP_REST_Response([
            'product_id' => $productId,
            'summary' => $summary,
        ], 200);
    }
}
