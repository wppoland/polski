<?php

declare(strict_types=1);

namespace Spolszczony\Rest;

use WP_REST_Controller;

/**
 * Abstract base for all Spolszczony REST API controllers.
 */
abstract class RestController extends WP_REST_Controller
{
    /** @var string */
    protected $namespace = 'spolszczony/v1';

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
                'spolszczony_rest_forbidden',
                __('You do not have permission to access this resource.', 'spolszczony'),
                ['status' => 403],
            );
        }

        return true;
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
