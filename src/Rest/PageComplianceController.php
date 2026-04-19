<?php

declare(strict_types=1);

namespace Polski\Rest;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Enum\LegalPageType;
use Polski\PageCompliance\PageComplianceService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET /polski/v1/compliance/page/{type}
 *
 * Returns a checklist report for the named legal page (privacy or terms).
 */
final class PageComplianceController extends RestController implements HasHooks
{
    public function __construct(
        private readonly PageComplianceService $service,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/compliance/page/(?P<type>[a-z]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'checkPage'],
                'permission_callback' => [$this, 'adminPermissionCheck'],
                'args' => [
                    'type' => [
                        'description' => __('Legal page type (privacy, terms).', 'polski'),
                        'type' => 'string',
                        'required' => true,
                        'enum' => ['privacy', 'terms'],
                    ],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/compliance/cookie-banner', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'checkCookieBanner'],
                'permission_callback' => [$this, 'adminPermissionCheck'],
                'args' => [
                    'url' => [
                        'description' => __('URL to scan; defaults to the site home.', 'polski'),
                        'type' => 'string',
                        'required' => false,
                        'format' => 'uri',
                    ],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/compliance/accessibility', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'checkAccessibility'],
                'permission_callback' => [$this, 'adminPermissionCheck'],
                'args' => [
                    'url' => [
                        'description' => __('URL to scan; defaults to the site home.', 'polski'),
                        'type' => 'string',
                        'required' => false,
                        'format' => 'uri',
                    ],
                ],
            ],
        ]);
    }

    public function checkPage(WP_REST_Request $request): WP_REST_Response
    {
        $rawType = (string) $request->get_param('type');
        $type = LegalPageType::tryFrom($rawType);

        if ($type === null || ! in_array($type, [LegalPageType::Privacy, LegalPageType::Terms], true)) {
            return new WP_REST_Response([
                'error' => 'unsupported_page_type',
                'message' => __('Supported page types: privacy, terms.', 'polski'),
            ], 400);
        }

        $report = $this->service->check($type);

        return new WP_REST_Response($report->toArray(), 200);
    }

    public function checkCookieBanner(WP_REST_Request $request): WP_REST_Response
    {
        $url = $request->get_param('url');
        $report = $this->service->checkCookieBanner(is_string($url) ? $url : null);

        return new WP_REST_Response($report->toArray(), 200);
    }

    public function checkAccessibility(WP_REST_Request $request): WP_REST_Response
    {
        $url = $request->get_param('url');
        $report = $this->service->checkAccessibility(is_string($url) ? $url : null);

        return new WP_REST_Response($report->toArray(), 200);
    }
}
