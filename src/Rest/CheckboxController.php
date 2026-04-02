<?php

declare(strict_types=1);

namespace Spolszczony\Rest;

use Spolszczony\Contract\HasHooks;
use Spolszczony\Model\LegalCheckbox;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API controller for legal checkbox management.
 *
 * GET    /spolszczony/v1/checkboxes           — List all checkboxes
 * POST   /spolszczony/v1/checkboxes           — Create custom checkbox
 * PUT    /spolszczony/v1/checkboxes/{id}       — Update checkbox
 * DELETE /spolszczony/v1/checkboxes/{id}       — Delete custom checkbox
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

        register_rest_route($this->namespace, '/checkboxes/(?P<id>[a-z0-9_]+)', [
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
        $service = \Spolszczony\Plugin::instance()->container()->get(\Spolszczony\Service\CheckboxService::class);
        $checkboxes = $service->all();

        $data = array_map(
            static fn (LegalCheckbox $cb) => $cb->toArray(),
            array_values($checkboxes),
        );

        return new WP_REST_Response($data, 200);
    }

    public function createCheckbox(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        $id = sanitize_key($params['id'] ?? '');

        if ($id === '') {
            return new WP_REST_Response(
                ['message' => __('Checkbox ID is required.', 'spolszczony')],
                400,
            );
        }

        $params['id'] = $id;
        $checkbox = LegalCheckbox::fromArray($params);

        // Save to custom checkboxes option.
        $custom = get_option('spolszczony_custom_checkboxes', []);

        if (! is_array($custom)) {
            $custom = [];
        }

        $custom[$id] = $checkbox->toArray();
        update_option('spolszczony_custom_checkboxes', $custom);

        return new WP_REST_Response($checkbox->toArray(), 201);
    }

    public function updateCheckbox(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $params = $request->get_json_params();
        $params['id'] = $id;

        $checkbox = LegalCheckbox::fromArray($params);

        // Check if it's a built-in checkbox (update settings) or custom (update option).
        $builtIn = ['terms', 'privacy', 'withdrawal', 'digital_waiver', 'parcel_delivery', 'review_reminder', 'marketing'];

        if (in_array($id, $builtIn, true)) {
            // Built-in: update enabled state via checkout settings.
            $settings = get_option('spolszczony_checkout', []);

            if (! is_array($settings)) {
                $settings = [];
            }

            $settings[$id . '_checkbox_enabled'] = $checkbox->enabled;
            update_option('spolszczony_checkout', $settings);
        } else {
            // Custom: update in custom checkboxes option.
            $custom = get_option('spolszczony_custom_checkboxes', []);

            if (! is_array($custom)) {
                $custom = [];
            }

            $custom[$id] = $checkbox->toArray();
            update_option('spolszczony_custom_checkboxes', $custom);
        }

        return new WP_REST_Response($checkbox->toArray(), 200);
    }

    public function deleteCheckbox(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');

        $builtIn = ['terms', 'privacy', 'withdrawal', 'digital_waiver', 'parcel_delivery', 'review_reminder', 'marketing'];

        if (in_array($id, $builtIn, true)) {
            return new WP_REST_Response(
                ['message' => __('Built-in checkboxes cannot be deleted. Disable them instead.', 'spolszczony')],
                400,
            );
        }

        $custom = get_option('spolszczony_custom_checkboxes', []);

        if (is_array($custom) && isset($custom[$id])) {
            unset($custom[$id]);
            update_option('spolszczony_custom_checkboxes', $custom);
        }

        return new WP_REST_Response(null, 204);
    }
}
