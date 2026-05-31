<?php

declare(strict_types=1);
namespace Polski\Rest;

defined('ABSPATH') || exit;

use WP_REST_Controller;

/**
 * Abstract base for all Polski REST API controllers.
 */
abstract class RestController extends WP_REST_Controller
{
    /** @var non-falsy-string */
    protected $namespace = 'polski/v1';

    /**
     * Check if the current user can manage WooCommerce.
     */
    protected function hasAdminPermission(): bool
    {
        return current_user_can('manage_woocommerce');
    }

    /**
     * Standard admin permission check for REST callbacks.
     */
    public function adminPermissionCheck(\WP_REST_Request $request): bool|\WP_Error
    {
        if (! $this->hasAdminPermission()) {
            return new \WP_Error(
                'polski_rest_forbidden',
                __('Sorry, it seems you do not have access to this page.', 'polski'),
                ['status' => 403],
            );
        }

        return true;
    }

    /**
     * Allow access to authenticated customers and WooCommerce managers.
     */
    public function customerPermissionCheck(\WP_REST_Request $request): bool|\WP_Error
    {
        if ($this->hasAdminPermission() || get_current_user_id() > 0) {
            return true;
        }

        return new \WP_Error(
            'polski_rest_auth_required',
            __('Please log in to continue.', 'polski'),
            ['status' => 401],
        );
    }

    /**
     * Get a typed option value with fallback.
     *
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    protected function getSettings(string $optionKey, array $defaults): array
    {
        $saved = get_option($optionKey, []);

        if (! is_array($saved)) {
            return $defaults;
        }

        return array_merge($defaults, $saved);
    }
}
