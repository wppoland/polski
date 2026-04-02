<?php

declare(strict_types=1);

namespace Spolszczony\Service;

use Spolszczony\Contract\Bootable;
use Spolszczony\Contract\HasHooks;
use Spolszczony\Enum\WithdrawalStatus;
use Spolszczony\Model\WithdrawalRequest;
use Spolszczony\Repository\WithdrawalRepository;

/**
 * 14-day consumer withdrawal right (prawo odstapienia od umowy).
 *
 * Handles the full flow: eligibility check, request creation, confirmation,
 * completion, and email notifications.
 */
final class WithdrawalService implements Bootable, HasHooks
{
    private const WITHDRAWAL_PERIOD_DAYS = 14;

    public function __construct(
        private readonly WithdrawalRepository $repository,
        private readonly EmailService $emailService,
    ) {
    }

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        // Add withdrawal button to customer order actions.
        add_filter('woocommerce_my_account_my_orders_actions', [$this, 'addWithdrawalAction'], 10, 2);

        // Handle withdrawal form submission.
        add_action('template_redirect', [$this, 'handleWithdrawalFormSubmission']);

        // Add withdrawal info to order details page.
        add_action('woocommerce_order_details_after_order_table', [$this, 'showWithdrawalStatus']);
    }

    /**
     * Check if an order is eligible for withdrawal.
     *
     * @param \WC_Order $order
     */
    public function isEligible(\WC_Order $order): bool
    {
        // Check if order is within the withdrawal period.
        $orderDate = $order->get_date_completed() ?? $order->get_date_created();

        if ($orderDate === null) {
            return false;
        }

        $deadline = clone $orderDate;
        $deadline->modify('+' . $this->getWithdrawalDays() . ' days');
        $now = new \WC_DateTime('now', $orderDate->getTimezone());

        if ($now > $deadline) {
            return false;
        }

        // Check if a withdrawal already exists.
        $existing = $this->repository->findByOrder($order->get_id());
        if ($existing !== null && $existing->status !== WithdrawalStatus::Rejected) {
            return false;
        }

        // Check if all items are exempt.
        $allExempt = true;
        foreach ($order->get_items() as $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }
            $product = $item->get_product();
            if ($product instanceof \WC_Product) {
                $exempt = $product->get_meta('_spolszczony_withdrawal_exempt', true);
                if ($exempt !== 'yes') {
                    $allExempt = false;
                    break;
                }
            }
        }

        if ($allExempt) {
            return false;
        }

        /**
         * Filter withdrawal eligibility.
         *
         * @param bool      $eligible Whether the order is eligible.
         * @param \WC_Order $order    The order.
         */
        return (bool) apply_filters('spolszczony/withdrawal/eligible', true, $order);
    }

    /**
     * Create a withdrawal request.
     *
     * @param int         $orderId
     * @param string|null $reason
     * @param list<array{product_id: int, quantity: int}>|null $items
     */
    public function createRequest(int $orderId, ?string $reason = null, ?array $items = null): ?WithdrawalRequest
    {
        $order = wc_get_order($orderId);

        if (! $order instanceof \WC_Order) {
            return null;
        }

        if (! $this->isEligible($order)) {
            return null;
        }

        $customerId = $order->get_customer_id() > 0 ? $order->get_customer_id() : null;

        $id = $this->repository->create($orderId, $customerId, $reason, $items);

        if ($id <= 0) {
            return null;
        }

        $request = $this->repository->findById($id);

        if ($request !== null) {
            // Add order note.
            $order->add_order_note(
                __('Customer submitted a withdrawal request.', 'spolszczony'),
                false,
                true,
            );

            do_action('spolszczony/withdrawal/requested', $request);
        }

        return $request;
    }

    /**
     * Confirm a withdrawal request (admin action).
     */
    public function confirm(int $requestId): bool
    {
        $request = $this->repository->findById($requestId);

        if ($request === null || ! $request->canConfirm()) {
            return false;
        }

        $result = $this->repository->updateStatus($requestId, WithdrawalStatus::Confirmed);

        if ($result) {
            $request->status = WithdrawalStatus::Confirmed;
            do_action('spolszczony/withdrawal/confirmed', $request);

            $order = wc_get_order($request->orderId);
            if ($order instanceof \WC_Order) {
                $order->add_order_note(
                    __('Withdrawal request confirmed.', 'spolszczony'),
                    true,
                    true,
                );
            }
        }

        return $result;
    }

    /**
     * Complete a withdrawal request (after refund processed).
     */
    public function complete(int $requestId): bool
    {
        $request = $this->repository->findById($requestId);

        if ($request === null || ! $request->canComplete()) {
            return false;
        }

        $result = $this->repository->updateStatus($requestId, WithdrawalStatus::Completed);

        if ($result) {
            $request->status = WithdrawalStatus::Completed;
            do_action('spolszczony/withdrawal/completed', $request);
        }

        return $result;
    }

    /**
     * Reject a withdrawal request.
     */
    public function reject(int $requestId): bool
    {
        return $this->repository->updateStatus($requestId, WithdrawalStatus::Rejected);
    }

    /**
     * Add "Withdraw" button to order actions in My Account.
     *
     * @param array<string, array<string, string>> $actions
     * @param \WC_Order $order
     * @return array<string, array<string, string>>
     */
    public function addWithdrawalAction(array $actions, \WC_Order $order): array
    {
        if ($this->isEligible($order)) {
            $actions['spolszczony_withdraw'] = [
                'url' => wp_nonce_url(
                    add_query_arg([
                        'spolszczony_withdrawal' => $order->get_id(),
                    ], wc_get_account_endpoint_url('orders')),
                    'spolszczony_withdrawal_' . $order->get_id(),
                ),
                'name' => __('Withdraw', 'spolszczony'),
            ];
        }

        return $actions;
    }

    /**
     * Handle withdrawal form submission from My Account.
     */
    public function handleWithdrawalFormSubmission(): void
    {
        if (! isset($_GET['spolszczony_withdrawal'])) {
            return;
        }

        $orderId = (int) $_GET['spolszczony_withdrawal'];
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));

        if (! wp_verify_nonce($nonce, 'spolszczony_withdrawal_' . $orderId)) {
            wc_add_notice(__('Invalid request. Please try again.', 'spolszczony'), 'error');
            return;
        }

        $order = wc_get_order($orderId);

        if (! $order instanceof \WC_Order) {
            wc_add_notice(__('Order not found.', 'spolszczony'), 'error');
            return;
        }

        // Verify ownership.
        if ($order->get_customer_id() !== get_current_user_id()) {
            wc_add_notice(__('You do not have permission to withdraw this order.', 'spolszczony'), 'error');
            return;
        }

        $reason = isset($_POST['spolszczony_withdrawal_reason'])
            ? sanitize_textarea_field(wp_unslash($_POST['spolszczony_withdrawal_reason']))
            : null;

        $request = $this->createRequest($orderId, $reason);

        if ($request !== null) {
            wc_add_notice(
                __('Your withdrawal request has been submitted. You will receive a confirmation email.', 'spolszczony'),
                'success',
            );
        } else {
            wc_add_notice(
                __('This order is not eligible for withdrawal.', 'spolszczony'),
                'error',
            );
        }

        wp_safe_redirect(wc_get_account_endpoint_url('orders'));
        exit;
    }

    /**
     * Show withdrawal status on order details page.
     */
    public function showWithdrawalStatus(\WC_Order $order): void
    {
        $request = $this->repository->findByOrder($order->get_id());

        if ($request === null) {
            return;
        }

        printf(
            '<h2>%s</h2><p>%s: <strong>%s</strong></p><p>%s: %s</p>',
            esc_html__('Withdrawal Request', 'spolszczony'),
            esc_html__('Status', 'spolszczony'),
            esc_html($request->status->label()),
            esc_html__('Submitted', 'spolszczony'),
            esc_html($request->requestedAt->format('Y-m-d H:i')),
        );
    }

    /**
     * Get withdrawal form fields.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFormFields(): array
    {
        $fields = [
            'reason' => [
                'type' => 'textarea',
                'label' => __('Reason for withdrawal (optional)', 'spolszczony'),
                'required' => false,
            ],
        ];

        /**
         * Filter withdrawal form fields.
         *
         * @param array<string, array<string, mixed>> $fields
         */
        return (array) apply_filters('spolszczony/withdrawal/form_fields', $fields);
    }

    private function getWithdrawalDays(): int
    {
        /**
         * Filter the withdrawal period in days.
         *
         * @param int $days Default 14.
         */
        return (int) apply_filters('spolszczony/withdrawal/period_days', self::WITHDRAWAL_PERIOD_DAYS);
    }
}
