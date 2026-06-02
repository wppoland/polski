<?php

declare(strict_types=1);
namespace Polski\Rest;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;
use Polski\Enum\ConsentCategory;
use Polski\Model\ConsentRecord;
use Polski\Repository\ConsentLogRepository;
use Polski\Service\ConsentManagerService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API for the Consent Manager.
 *
 * POST /polski/v1/consent           - Record a banner decision (public, nonce-verified).
 * GET  /polski/v1/consent/records   - List stored banner decisions (manage_woocommerce).
 */
final class ConsentController extends RestController implements HasHooks
{
    public function __construct(
        private readonly ConsentLogRepository $consentLog,
        private readonly ConsentManagerService $service,
    ) {
    }

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('consent_manager')) {
            return;
        }

        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/consent', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'recordConsent'],
                'permission_callback' => [$this, 'recordPermissionCheck'],
                'args' => [
                    'categories' => [
                        'required' => true,
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'version' => [
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/consent/records', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'listRecords'],
                'permission_callback' => [$this, 'adminPermissionCheck'],
                'args' => [
                    'per_page' => [
                        'required' => false,
                        'type' => 'integer',
                        'default' => 100,
                        'sanitize_callback' => 'absint',
                    ],
                    'page' => [
                        'required' => false,
                        'type' => 'integer',
                        'default' => 1,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Public endpoint, but the WordPress REST nonce must be valid. The banner
     * sends it in the X-WP-Nonce header.
     */
    public function recordPermissionCheck(WP_REST_Request $request): bool|\WP_Error
    {
        $nonce = $request->get_header('X-WP-Nonce');

        if (! is_string($nonce) || ! wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error(
                'polski_consent_bad_nonce',
                __('Could not verify the request. Please reload the page and try again.', 'polski'),
                ['status' => 403],
            );
        }

        return true;
    }

    public function recordConsent(WP_REST_Request $request): WP_REST_Response
    {
        $requested = $request->get_param('categories');
        $requested = is_array($requested) ? $requested : [];

        // Build the canonical category map: necessary always granted, every
        // other known optional category granted only if present in the payload.
        $states = [ConsentCategory::Necessary->value => true];
        foreach (ConsentCategory::optional() as $category) {
            $states[$category->value] = in_array($category->value, $requested, true);
        }

        $version = $request->get_param('version');
        $version = is_string($version) && $version !== ''
            ? sanitize_text_field($version)
            : $this->service->consentVersion();

        $userId = get_current_user_id();

        $this->consentLog->logCookieConsent(
            $states,
            $version,
            $userId > 0 ? $userId : null,
            null,
        );

        return new WP_REST_Response(['recorded' => true], 201);
    }

    public function listRecords(WP_REST_Request $request): WP_REST_Response
    {
        $perPage = max(1, min((int) $request->get_param('per_page'), 500));
        $page = max(1, (int) $request->get_param('page'));
        $offset = ($page - 1) * $perPage;

        $records = $this->consentLog->findCookieConsents($perPage, $offset);

        $data = array_map(
            static fn (ConsentRecord $record): array => [
                'id' => $record->id,
                'category' => preg_replace('/^cookie_/', '', $record->checkboxId),
                'granted' => $record->consented,
                'user_id' => $record->userId,
                'ip_address' => $record->ipAddress,
                'user_agent' => $record->userAgent,
                'consent_version' => $record->consentVersion,
                'created_at' => $record->createdAt->format('c'),
            ],
            $records,
        );

        $response = new WP_REST_Response($data, 200);
        $response->header('X-WP-Total', (string) $this->consentLog->countCookieConsents());

        return $response;
    }
}
