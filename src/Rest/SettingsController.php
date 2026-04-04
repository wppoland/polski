<?php

declare(strict_types=1);
namespace Polski\Rest;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;
use Polski\Util\Sanitizer;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API controller for plugin settings.
 *
 * GET  /polski/v1/settings          - All settings
 * GET  /polski/v1/settings/{group}  - Settings by group
 * PUT  /polski/v1/settings/{group}  - Update settings group
 */
final class SettingsController extends RestController implements HasHooks
{
    /** @var array<string, array<string, mixed>> */
    private array $defaults = [];

    public function __construct()
    {
        $defaultsFile = \Polski\PLUGIN_DIR . '/config/defaults.php';

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

        register_rest_route($this->namespace, '/wizard/complete', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'completeWizard'],
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
            $group = str_replace('polski_', '', $key);
            $allSettings[$group] = $this->getSettings($key, $defaults);
        }

        return new WP_REST_Response($allSettings, 200);
    }

    public function getGroupSettings(WP_REST_Request $request): WP_REST_Response
    {
        $group = $request->get_param('group');
        $optionKey = 'polski_' . $group;

        if (! isset($this->defaults[$optionKey])) {
            return new WP_REST_Response(
                ['message' => __('Unknown settings group.', 'polski')],
                404,
            );
        }

        return new WP_REST_Response(
            $this->getSettings($optionKey, $this->defaults[$optionKey]),
            200,
        );
    }

    /**
     * Complete the setup wizard: save all initial settings in one request.
     */
    public function completeWizard(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();

        // Save company/general settings.
        $general = get_option('polski_general', []);
        if (! is_array($general)) {
            $general = [];
        }

        $general['company_name'] = sanitize_text_field((string) ($params['company_name'] ?? ''));
        $general['company_address'] = sanitize_text_field((string) ($params['company_address'] ?? ''));
        $general['company_email'] = sanitize_email((string) ($params['company_email'] ?? ''));
        $general['company_phone'] = sanitize_text_field((string) ($params['company_phone'] ?? ''));
        $general['company_nip'] = sanitize_text_field((string) ($params['company_nip'] ?? ''));

        update_option('polski_general', $general);

        // Save checkout settings.
        $checkout = get_option('polski_checkout', []);
        if (! is_array($checkout)) {
            $checkout = [];
        }

        $checkout['order_button_text'] = sanitize_text_field($params['order_button_text'] ?? __('I order with an obligation to pay', 'polski'));
        $checkout['terms_checkbox_enabled'] = (bool) ($params['terms_enabled'] ?? true);
        $checkout['privacy_checkbox_enabled'] = (bool) ($params['privacy_enabled'] ?? true);
        $checkout['withdrawal_checkbox_enabled'] = (bool) ($params['withdrawal_enabled'] ?? true);
        $checkout['digital_waiver_checkbox_enabled'] = (bool) ($params['digital_waiver_enabled'] ?? false);
        $checkout['marketing_checkbox_enabled'] = (bool) ($params['marketing_enabled'] ?? false);

        update_option('polski_checkout', $checkout);

        // Save Omnibus setting.
        $omnibus = get_option('polski_omnibus', []);
        if (! is_array($omnibus)) {
            $omnibus = [];
        }

        $omnibus['enabled'] = (bool) ($params['omnibus_enabled'] ?? true);
        update_option('polski_omnibus', $omnibus);

        $modules = ModulesPage::getDefaultModuleStates();
        $modules['omnibus'] = $omnibus['enabled'];
        update_option('polski_modules', $modules);

        // Generate legal pages if requested.
        if (! empty($params['generate_legal_pages'])) {
            $legalService = \Polski\Plugin::instance()->container()->get(
                \Polski\Service\LegalPageService::class,
            );

            $variables = [
                'company_name' => sanitize_text_field($params['company_name'] ?? ''),
                'company_address' => sanitize_text_field($params['company_address'] ?? ''),
                'company_email' => sanitize_email($params['company_email'] ?? ''),
                'company_phone' => sanitize_text_field($params['company_phone'] ?? ''),
            ];

            $legalService->generateDefaultPages($variables);
        }

        // Mark wizard as complete.
        update_option('polski_wizard_complete', true);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Wizard completed.', 'polski'),
        ], 200);
    }

    public function updateGroupSettings(WP_REST_Request $request): WP_REST_Response
    {
        $group = $request->get_param('group');
        $optionKey = 'polski_' . $group;

        if (! isset($this->defaults[$optionKey])) {
            return new WP_REST_Response(
                ['message' => __('Unknown settings group.', 'polski')],
                404,
            );
        }

        $input = $request->get_json_params();
        $sanitized = Sanitizer::settingsArray($input, $this->defaults[$optionKey]);

        update_option($optionKey, $sanitized);

        return new WP_REST_Response($sanitized, 200);
    }
}
