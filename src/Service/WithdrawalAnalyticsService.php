<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Model\WithdrawalRequest;

/**
 * Pushes withdrawal-flow milestones into a single analytics action so
 * GA4 / Matomo / Mixpanel subscribers can map the consumer funnel
 * without each one re-wiring the six underlying lifecycle hooks.
 *
 * The canonical event fires as `polski/withdrawal/event` with a
 * normalised payload:
 *
 *  array{
 *      event:           'filed'|'confirmed'|'completed'|'rejected',
 *      declaration_id:  string,   // POL-WD-NNNNNN
 *      numeric_id:      int,
 *      order_id:        int,
 *      order_total:     float,
 *      currency:        string,
 *      channel:         string,   // online | guest | phone | email | letter | in_store
 *      refund_amount:   float|null,
 *      timestamp:       string,   // ISO 8601 UTC
 *  }
 *
 * Subscribers (storefronts, analytics plugins) read those fields and map
 * them to the metric model they need. No third-party SDK ships with this
 * service so we stay vendor-neutral; sample subscribers for GA4 and
 * Matomo live in docs/withdrawal/analytics-subscribers.md.
 */
final class WithdrawalAnalyticsService implements HasHooks
{
    public function registerHooks(): void
    {
        add_action('polski/withdrawal/requested', [$this, 'onRequested'], 30, 1);
        add_action('polski/withdrawal/guest_requested', [$this, 'onGuestRequested'], 30, 3);
        add_action('polski/withdrawal/manual_registered', [$this, 'onManualRegistered'], 30, 3);
        add_action('polski/withdrawal/confirmed', [$this, 'onConfirmed'], 30, 1);
        add_action('polski/withdrawal/completed', [$this, 'onCompleted'], 30, 1);
        add_action('polski/withdrawal/rejected', [$this, 'onRejected'], 30, 1);
    }

    public function onRequested(WithdrawalRequest $request): void
    {
        $this->emit('filed', $request);
    }

    public function onGuestRequested(int $withdrawalId, \WC_Order $order, string $email): void
    {
        unset($email);
        $request = $this->loadRequest($withdrawalId);
        if ($request !== null) {
            $this->emit('filed', $request, $order);
        }
    }

    public function onManualRegistered(int $withdrawalId, \WC_Order $order, string $channel): void
    {
        unset($channel);
        $request = $this->loadRequest($withdrawalId);
        if ($request !== null) {
            $this->emit('filed', $request, $order);
        }
    }

    public function onConfirmed(WithdrawalRequest $request): void
    {
        $this->emit('confirmed', $request);
    }

    public function onCompleted(WithdrawalRequest $request): void
    {
        $this->emit('completed', $request);
    }

    public function onRejected(WithdrawalRequest $request): void
    {
        $this->emit('rejected', $request);
    }

    private function emit(string $event, WithdrawalRequest $request, ?\WC_Order $orderOverride = null): void
    {
        $order = $orderOverride ?? wc_get_order($request->orderId);
        $payload = [
            'event' => $event,
            'declaration_id' => sprintf('POL-WD-%06d', $request->id),
            'numeric_id' => $request->id,
            'order_id' => $request->orderId,
            'order_total' => $order instanceof \WC_Order ? (float) $order->get_total() : 0.0,
            'currency' => $order instanceof \WC_Order ? $order->get_currency() : '',
            'channel' => (string) ($request->channel ?? 'online'),
            'refund_amount' => $request->refundAmount,
            'ai_category' => $request->aiCategory,
            'ai_confidence' => $request->aiConfidence,
            'timestamp' => gmdate('c'),
        ];

        /**
         * Filter the payload before fan-out so subscribers see a single,
         * predictable shape and can attach store-specific dimensions.
         *
         * @param array<string, mixed> $payload
         * @param string               $event
         * @param WithdrawalRequest    $request
         */
        $payload = (array) apply_filters('polski/withdrawal/event_payload', $payload, $event, $request);

        /**
         * Single canonical analytics action. Subscribers map it to
         * gtag('event', …), Matomo trackEvent(), etc.
         *
         * @param array<string, mixed> $payload
         */
        do_action('polski/withdrawal/event', $payload);
    }

    private function loadRequest(int $id): ?WithdrawalRequest
    {
        return \Polski\Plugin::instance()->container()
            ->get(\Polski\Repository\WithdrawalRepository::class)
            ->findById($id);
    }
}
