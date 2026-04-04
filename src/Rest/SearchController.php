<?php

declare(strict_types=1);
namespace Polski\Rest;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Service\SearchService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API for enhanced product search.
 *
 * GET /polski/v1/search?q=term - search products including Polski meta
 */
final class SearchController extends RestController implements HasHooks
{
    public function __construct(
        private readonly SearchService $searchService,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/search', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'search'],
                'permission_callback' => '__return_true',
                'args' => [
                    'q' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'per_page' => [
                        'type' => 'integer',
                        'default' => 10,
                        'minimum' => 1,
                        'maximum' => 50,
                    ],
                ],
            ],
        ]);
    }

    public function search(WP_REST_Request $request): WP_REST_Response
    {
        $query = (string) $request->get_param('q');
        $perPage = (int) $request->get_param('per_page');

        return new WP_REST_Response($this->searchService->searchAjax($query, $perPage), 200);
    }
}
