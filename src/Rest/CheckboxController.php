<?php

declare(strict_types=1);

namespace Polski\Rest;

use Polski\Contract\HasHooks;
use Polski\Model\LegalCheckbox;
use Polski\Repository\ConsentLogRepository;
use Polski\Service\CheckboxService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API controller for legal checkbox management.
 *
 * GET    /polski/v1/checkboxes              - List all checkboxes
 * POST   /polski/v1/checkboxes              - Create custom checkbox
 * PUT    /polski/v1/checkboxes/{id}          - Update checkbox (all fields)
 * DELETE /polski/v1/checkboxes/{id}          - Delete custom checkbox
 * GET    /polski/v1/checkboxes/stats          - Compliance statistics
 */
final class CheckboxController extends RestController implements HasHooks
{
    public function registerHooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/checkboxes', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'listCheckboxes'],
                'permission_callback' => [$this, 'adminPermissionCheck'],
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createCheckbox'],
                'permission_callback' => [$this, 'adminPermissionCheck'],
            ],
        ]);

        register_rest_route($this->namespace, '/checkboxes/stats', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'getStats'],
                'permission_callback' => [$this, 'adminPermissionCheck'],
            ],
        ]);

        register_rest_route($this->namespace, '/checkboxes/(?P<id>[a-z0-9_]+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'getCheckbox'],
                'permission_callback' => [$this, 'adminPermissionCheck'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateCheckbox'],
                'permission_callback' => [$this, 'adminPermissionCheck'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteCheckbox'],
                'permission_callback' => [$this, 'adminPermissionCheck'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ],
        ]);
    }

    public function listCheckboxes(WP_REST_Request $request): WP_REST_Response
    {
        $service = $this->getService();
        $checkboxes = $service->all();

        $data = array_map(
            static fn (LegalCheckbox $cb) => $cb->toArray(),
            array_values($checkboxes),
        );

        return new WP_REST_Response($data, 200);
    }

    public function getCheckbox(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $service = $this->getService();
        $checkbox = $service->get($id);

        if ($checkbox === null) {
            return new WP_REST_Response(
                ['message' => __('Checkbox nie znaleziony.', 'polski')],
                404,
            );
        }

        return new WP_REST_Response($checkbox->toArray(), 200);
    }

    public function createCheckbox(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        $id = sanitize_key($params['id'] ?? '');

        if ($id === '') {
            return new WP_REST_Response(
                ['message' => __('ID checkboxa jest wymagane.', 'polski')],
                400,
            );
        }

        $service = $this->getService();

        if ($service->isCore($id)) {
            return new WP_REST_Response(
                ['message' => __('Nie mozna utworzyc checkboxa o zarezerwowanym ID.', 'polski')],
                400,
            );
        }

        $params['id'] = $id;
        $checkbox = LegalCheckbox::fromArray($params);

        // Save to custom checkboxes option.
        $custom = get_option('polski_custom_checkboxes', []);

        if (! is_array($custom)) {
            $custom = [];
        }

        $custom[$id] = $checkbox->toArray();
        update_option('polski_custom_checkboxes', $custom);

        // Register immediately.
        $service->register($checkbox);

        return new WP_REST_Response($checkbox->toArray(), 201);
    }

    public function updateCheckbox(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $params = $request->get_json_params();
        $service = $this->getService();

        $existing = $service->get($id);
        if ($existing === null) {
            return new WP_REST_Response(
                ['message' => __('Checkbox nie znaleziony.', 'polski')],
                404,
            );
        }

        if ($service->isCore($id)) {
            // Built-in: save all editable fields as overrides.
            $overrides = $this->extractOverrides($params);
            $service->saveOverrides($id, $overrides);

            // Re-read the checkbox after overrides applied.
            $updated = $service->get($id);
            return new WP_REST_Response($updated !== null ? $updated->toArray() : [], 200);
        }

        // Custom: rebuild and save.
        $params['id'] = $id;
        $checkbox = LegalCheckbox::fromArray($params);

        $custom = get_option('polski_custom_checkboxes', []);

        if (! is_array($custom)) {
            $custom = [];
        }

        $custom[$id] = $checkbox->toArray();
        update_option('polski_custom_checkboxes', $custom);

        // Update in memory.
        $service->register($checkbox);

        return new WP_REST_Response($checkbox->toArray(), 200);
    }

    public function deleteCheckbox(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $service = $this->getService();

        if ($service->isCore($id)) {
            return new WP_REST_Response(
                ['message' => __('Wbudowane checkboxy nie moga byc usuniete. Mozesz je wylaczac.', 'polski')],
                400,
            );
        }

        $custom = get_option('polski_custom_checkboxes', []);

        if (is_array($custom) && isset($custom[$id])) {
            unset($custom[$id]);
            update_option('polski_custom_checkboxes', $custom);
        }

        $service->unregister($id);

        return new WP_REST_Response(null, 204);
    }

    /**
     * Get compliance statistics, checkbox summary, and consent log data for the dashboard.
     */
    public function getStats(WP_REST_Request $request): WP_REST_Response
    {
        $service = $this->getService();
        $consentLog = $this->getConsentLog();

        $days = (int) ($request->get_param('days') ?? 30);
        $days = max(1, min($days, 365));

        $plugin = \Polski\Plugin::instance();

        $stats = $service->getComplianceStats();
        $stats['consent_log'] = $consentLog->getStats($days);
        $stats['pro_active'] = $plugin->isProActive();
        $stats['pro_version'] = $plugin->proVersion();

        return new WP_REST_Response($stats, 200);
    }

    /**
     * Extract editable override fields from request params.
     *
     * All fields are available in the FREE version. PRO adds additional
     * features (consent versioning, export, etc.) as a separate plugin.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function extractOverrides(array $params): array
    {
        $overrides = [];
        $allowedKeys = [
            'label', 'error_message', 'type', 'priority', 'enabled',
            'contexts', 'html_classes', 'html_style', 'hide_input',
            'description', 'categories', 'countries', 'payment_methods',
            'product_types',
        ];

        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $params)) {
                $overrides[$key] = $params[$key];
            }
        }

        return $overrides;
    }

    private function getService(): CheckboxService
    {
        return \Polski\Plugin::instance()->container()->get(CheckboxService::class);
    }

    private function getConsentLog(): ConsentLogRepository
    {
        return \Polski\Plugin::instance()->container()->get(ConsentLogRepository::class);
    }
}
