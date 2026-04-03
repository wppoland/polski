<?php

declare(strict_types=1);

namespace Polski\Rest;

use Polski\Contract\HasHooks;
use Polski\Enum\LegalPageType;
use Polski\Service\LegalPageService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API controller for legal pages.
 *
 * GET  /polski/v1/legal-pages           - List all legal pages with status
 * POST /polski/v1/legal-pages/generate   - Generate default legal pages
 */
final class LegalPageController extends RestController implements HasHooks
{
    public function registerHooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/legal-pages', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'listPages'],
                'permission_callback' => [$this, 'adminPermissionCheck'],
            ],
        ]);

        register_rest_route($this->namespace, '/legal-pages/generate', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'generatePages'],
                'permission_callback' => [$this, 'adminPermissionCheck'],
            ],
        ]);
    }

    public function listPages(WP_REST_Request $request): WP_REST_Response
    {
        $service = \Polski\Plugin::instance()->container()->get(LegalPageService::class);
        $status = $service->getConfigurationStatus();

        $pages = [];

        foreach (LegalPageType::cases() as $type) {
            $pageId = $service->getPageId($type);
            $post = $pageId > 0 ? get_post($pageId) : null;

            $pages[] = [
                'type' => $type->value,
                'label' => $type->label(),
                'page_id' => $pageId,
                'page_title' => $post instanceof \WP_Post ? $post->post_title : null,
                'page_status' => $post instanceof \WP_Post ? $post->post_status : null,
                'url' => $service->getPageUrl($type),
                'configured' => $status[$type->value] ?? false,
                'edit_url' => $pageId > 0 ? get_edit_post_link($pageId, 'raw') : null,
            ];
        }

        return new WP_REST_Response($pages, 200);
    }

    public function generatePages(WP_REST_Request $request): WP_REST_Response
    {
        $service = \Polski\Plugin::instance()->container()->get(LegalPageService::class);
        $service->createDefaultPages();

        // Return updated status.
        return $this->listPages($request);
    }
}
