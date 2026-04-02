<?php

declare(strict_types=1);

namespace Spolszczony\Rest;

use Spolszczony\Contract\HasHooks;
use Spolszczony\Util\Sanitizer;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API controller for plugin settings.
 *
 * GET  /spolszczony/v1/settings          — All settings
 * GET  /spolszczony/v1/settings/{group}  — Settings by group
 * PUT  /spolszczony/v1/settings/{group}  — Update settings group
 */
final class SettingsController extends RestController implements HasHooks
{
    /** @var array<string, array<string, mixed>> */
    private array $defaults = [];

    public function __construct()
    {
        $defaultsFile = \Spolszczony\PLUGIN_DIR . '/config/defaults.php';

        if (file_exists($defaultsFile)) {
            $this->defaults = require $defaultsFile;
        }
    }

    public function registerHooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/settings', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'getAllSettings'],
                'permission_callback' => [$this, 'adminPermissionCheck'],
            ],
        ]);

        register_rest_route($this->namespace, '/settings/(?P<group>[a-z_]+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'getGroupSettings'],
                'permission_callback' => [$this, 'adminPermissionCheck'],
                'args' => [
                    'group' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateGroupSettings'],
                'permission_callback' => [$this, 'adminPermissionCheck'],
                'args' => [
                    'group' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ],
        ]);
    }

    public function getAllSettings(WP_REST_Request $request): WP_REST_Response
    {
        $allSettings = [];

        foreach ($this->defaults as $key => $defaults) {
            $group = str_replace('spolszczony_', '', $key);
            $allSettings[$group] = $this->getSettings($key, $defaults);
        }

        return new WP_REST_Response($allSettings, 200);
    }

    public function getGroupSettings(WP_REST_Request $request): WP_REST_Response
    {
        $group = $request->get_param('group');
        $optionKey = 'spolszczony_' . $group;

        if (! isset($this->defaults[$optionKey])) {
            return new WP_REST_Response(
                ['message' => __('Unknown settings group.', 'spolszczony')],
                404,
            );
        }

        return new WP_REST_Response(
            $this->getSettings($optionKey, $this->defaults[$optionKey]),
            200,
        );
    }

    public function updateGroupSettings(WP_REST_Request $request): WP_REST_Response
    {
        $group = $request->get_param('group');
        $optionKey = 'spolszczony_' . $group;

        if (! isset($this->defaults[$optionKey])) {
            return new WP_REST_Response(
                ['message' => __('Unknown settings group.', 'spolszczony')],
                404,
            );
        }

        $input = $request->get_json_params();
        $sanitized = Sanitizer::settingsArray($input, $this->defaults[$optionKey]);

        update_option($optionKey, $sanitized);

        return new WP_REST_Response($sanitized, 200);
    }
}
