<?php

declare(strict_types=1);
namespace Polski\Rest;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Enum\WithdrawalStatus;
use Polski\Repository\WithdrawalRepository;
use Polski\Service\WithdrawalService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API controller for withdrawal requests.
 *
 * GET    /polski/v1/withdrawals           - List all withdrawals (admin)
 * POST   /polski/v1/withdrawals           - Submit withdrawal (customer)
 * PUT    /polski/v1/withdrawals/{id}       - Update status (admin)
 */
final class WithdrawalController extends RestController implements HasHooks
{
    public function registerHooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/withdrawals', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'listWithdrawals'],
                'permission_callback' => [$this, 'adminPermissionCheck'],
                'args' => [
                    'status' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                    'per_page' => [
                        'type' => 'integer',
                        'default' => 20,
                        'minimum' => 1,
                        'maximum' => 100,
                    ],
                    'page' => [
                        'type' => 'integer',
                        'default' => 1,
                        'minimum' => 1,
                    ],
                ],
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createWithdrawal'],
                'permission_callback' => [$this, 'customerPermissionCheck'],
                'args' => [
                    'order_id' => [
                        'required' => true,
                        'type' => 'integer',
                    ],
                    'reason' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field',
                    ],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/withdrawals/(?P<id>\d+)', [
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateWithdrawal'],
                'permission_callback' => [$this, 'adminPermissionCheck'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'type' => 'integer',
                    ],
                    'status' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ],
        ]);
    }

    public function listWithdrawals(WP_REST_Request $request): WP_REST_Response
    {
        $container = \Polski\Plugin::instance()->container();
        $repository = $container->get(WithdrawalRepository::class);

        $statusParam = $request->get_param('status');
        $status = $statusParam !== null ? WithdrawalStatus::tryFrom($statusParam) : null;

        $perPage = (int) $request->get_param('per_page');
        $page = (int) $request->get_param('page');
        $offset = ($page - 1) * $perPage;

        $items = $repository->findAll($perPage, $offset, $status);

        $data = array_map(
            static fn ($item) => $item->toArray(),
            $items,
        );

        return new WP_REST_Response($data, 200);
    }

    public function createWithdrawal(WP_REST_Request $request): WP_REST_Response
    {
        $orderId = (int) $request->get_param('order_id');
        $reason = $request->get_param('reason');
        $currentUser = get_current_user_id();
        $isAdmin = $this->hasAdminPermission();

        $order = wc_get_order($orderId);

        if (! $isAdmin) {
            if (! $order instanceof \WC_Order || $currentUser <= 0 || $order->get_customer_id() !== $currentUser) {
                return new WP_REST_Response(
                    ['message' => __('We could not prepare a withdrawal request for this order.', 'polski')],
                    404,
                );
            }
        }

        if (! $order instanceof \WC_Order) {
            return new WP_REST_Response(
                ['message' => __('We could not find that order.', 'polski')],
                404,
            );
        }

        $container = \Polski\Plugin::instance()->container();
        $service = $container->get(WithdrawalService::class);

        $withdrawal = $service->createRequest($orderId, $reason);

        if ($withdrawal === null) {
            return new WP_REST_Response(
                ['message' => __('This order is not eligible for withdrawal.', 'polski')],
                400,
            );
        }

        return new WP_REST_Response($withdrawal->toArray(), 201);
    }

    public function updateWithdrawal(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $statusParam = $request->get_param('status');
        $newStatus = WithdrawalStatus::tryFrom($statusParam);

        if ($newStatus === null) {
            return new WP_REST_Response(
                ['message' => __('Nieprawidłowy status.', 'polski')],
                400,
            );
        }

        $container = \Polski\Plugin::instance()->container();
        $service = $container->get(WithdrawalService::class);

        $result = match ($newStatus) {
            WithdrawalStatus::Confirmed => $service->confirm($id),
            WithdrawalStatus::Completed => $service->complete($id),
            WithdrawalStatus::Rejected => $service->reject($id),
            default => false,
        };

        if (! $result) {
            return new WP_REST_Response(
                ['message' => __('Zmiana statusu niedozwolona.', 'polski')],
                400,
            );
        }

        $repository = $container->get(WithdrawalRepository::class);
        $updated = $repository->findById($id);

        return new WP_REST_Response($updated?->toArray(), 200);
    }
}
