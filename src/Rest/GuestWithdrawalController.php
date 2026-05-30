<?php

declare(strict_types=1);
namespace Polski\Rest;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Repository\WithdrawalRepository;
use Polski\Service\GuestWithdrawalService;

/**
 * REST endpoints mirroring the guest magic-link flow so headless and
 * mobile clients can drive it without rendering the shortcode page.
 *
 *  POST /polski/v1/withdrawals/guest/request  - start the flow (email + order#)
 *  POST /polski/v1/withdrawals/guest/redeem   - submit the declaration once the
 *                                                customer has clicked the link
 *
 * Both endpoints are intentionally `permission_callback => __return_true`
 * because the consumer is unauthenticated by design. Guarding is done
 * inside via rate-limiting + magic-link verification (identical to the
 * shortcode flow), and the request endpoint returns the same masked
 * response regardless of whether the order exists (anti-enumeration).
 */
final class GuestWithdrawalController implements HasHooks
{
    private const NAMESPACE = 'polski/v1';

    public function __construct(
        private readonly GuestWithdrawalService $guest,
        private readonly WithdrawalRepository $repository,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/withdrawals/guest/request', [
            'methods' => 'POST',
            'callback' => [$this, 'requestMagicLink'],
            'permission_callback' => '__return_true',
            'args' => [
                'order_number' => ['type' => 'string', 'required' => true],
                'email' => ['type' => 'string', 'required' => true, 'format' => 'email'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/withdrawals/guest/redeem', [
            'methods' => 'POST',
            'callback' => [$this, 'redeemToken'],
            'permission_callback' => '__return_true',
            'args' => [
                'token' => ['type' => 'string', 'required' => true],
                'reason' => ['type' => 'string', 'required' => false],
            ],
        ]);
    }

    /**
     * Start the flow. Returns 202 Accepted with a generic message regardless
     * of order existence - callers should display the message verbatim.
     */
    public function requestMagicLink(\WP_REST_Request $request): \WP_REST_Response
    {
        // Defence in depth: if the client provided a WordPress REST nonce, it
        // must be valid. CSRF probes from third-party origins typically fail
        // this check, so we reject them BEFORE the rate-limit slot is consumed.
        // Endpoints remain callable by non-browser clients (no header at all).
        $nonce = (string) $request->get_header('X-WP-Nonce');
        if ($nonce !== '' && ! wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_REST_Response(['accepted' => false, 'message' => 'invalid_nonce'], 403);
        }

        $orderNumber = sanitize_text_field((string) $request->get_param('order_number'));
        $email = sanitize_email((string) $request->get_param('email'));

        if ($orderNumber === '' || $email === '' || ! is_email($email)) {
            return new \WP_REST_Response(
                ['accepted' => false, 'message' => __('Brakuje numeru zamówienia lub adres e-mail jest nieprawidłowy.', 'polski')],
                400,
            );
        }

        $this->guest->dispatchMagicLinkForRest($orderNumber, $email);

        // Always 202 + masked notice - never reveal whether the order exists.
        return new \WP_REST_Response(
            [
                'accepted' => true,
                'message' => __(
                    'Jeśli to zamówienie istnieje, wysłaliśmy link do formularza na adres e-mail podany przy zakupie. Sprawdź skrzynkę odbiorczą oraz folder Spam.',
                    'polski',
                ),
            ],
            202,
        );
    }

    /**
     * Redeem the magic-link token and create the withdrawal request.
     */
    public function redeemToken(\WP_REST_Request $request): \WP_REST_Response
    {
        // Same defence-in-depth nonce check as requestMagicLink().
        $nonce = (string) $request->get_header('X-WP-Nonce');
        if ($nonce !== '' && ! wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_REST_Response(['error' => 'invalid_nonce'], 403);
        }

        $token = sanitize_text_field((string) $request->get_param('token'));
        $reason = $request->get_param('reason');
        $reason = is_string($reason) ? sanitize_textarea_field($reason) : null;

        if ($token === '') {
            return new \WP_REST_Response(['error' => 'invalid_token'], 400);
        }

        $payload = $this->guest->redeemTokenForRest($token);
        if ($payload === null) {
            return new \WP_REST_Response(['error' => 'invalid_or_expired_token'], 410);
        }

        $order = wc_get_order($payload['order_id']);
        if (! $order instanceof \WC_Order) {
            return new \WP_REST_Response(['error' => 'order_not_found'], 404);
        }

        $created = $this->repository->createForGuest(
            $order->get_id(),
            $payload['email'],
            $reason,
            null,
        );

        if ($created <= 0) {
            do_action(
                'polski/withdrawal/persist_failed',
                $order->get_id(),
                ['flow' => 'guest_rest', 'email' => $payload['email']],
            );
            return new \WP_REST_Response(['error' => 'persist_failed'], 500);
        }

        $this->guest->consumeTokenForRest($token);
        do_action('polski/withdrawal/guest_requested', $created, $order, $payload['email']);

        return new \WP_REST_Response(
            [
                'declaration_id' => sprintf('POL-WD-%06d', $created),
                'numeric_id' => $created,
                'status' => 'requested',
                'order_number' => (string) $order->get_order_number(),
            ],
            201,
        );
    }
}
