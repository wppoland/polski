<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Enum\WithdrawalStatus;
use Polski\Model\WithdrawalRequest;
use Polski\Repository\WithdrawalRepository;
use Polski\Util\TemplateLoader;

/**
 * 14-day consumer withdrawal right.
 *
 * Handles the full flow: eligibility check, request creation, confirmation,
 * completion, and email notifications.
 */
final class WithdrawalService implements Bootable, HasHooks
{
    private const WITHDRAWAL_PERIOD_DAYS = 14;

    public function __construct(
        private readonly WithdrawalRepository $repository,
        private readonly TemplateLoader $templateLoader,
    ) {
    }

    public function boot(): void
    {
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        $settings = get_option('polski_withdrawal', []);

        return is_array($settings) ? $settings : [];
    }

    public function registerHooks(): void
    {
        // Add withdrawal button to customer order actions.
        add_filter('woocommerce_my_account_my_orders_actions', [$this, 'addWithdrawalAction'], 10, 2);

        // Handle withdrawal form submission.
        add_action('template_redirect', [$this, 'handleWithdrawalFormSubmission']);

        // Handle one-click withdrawal flow.
        add_action('template_redirect', [$this, 'handleOneClickWithdrawal'], 5);

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
                $exempt = $product->get_meta('_polski_withdrawal_exempt', true);
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
        return (bool) apply_filters('polski/withdrawal/eligible', true, $order);
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
                (string) ($this->getSettings()['requested_order_note'] ?? __('Customer has submitted a withdrawal request.', 'polski')),
                0,
                true,
            );

            do_action('polski/withdrawal/requested', $request);
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
            do_action('polski/withdrawal/confirmed', $request);

            $order = wc_get_order($request->orderId);
            if ($order instanceof \WC_Order) {
                $order->add_order_note(
                    (string) ($this->getSettings()['confirmed_order_note'] ?? __('Withdrawal request confirmed.', 'polski')),
                    1,
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
            do_action('polski/withdrawal/completed', $request);
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
            $actions['polski_withdraw'] = [
                'url' => wp_nonce_url(
                    add_query_arg([
                        'polski_withdrawal' => $order->get_id(),
                    ], wc_get_account_endpoint_url('orders')),
                    'polski_withdrawal_' . $order->get_id(),
                ),
                'name' => (string) ($this->getSettings()['button_text'] ?? __('Withdraw from contract', 'polski')),
            ];

            $settings = $this->getSettings();
            if (! empty($settings['oneclick_enabled'])) {
                $actions['polski_oneclick_withdrawal'] = [
                    'url' => wp_nonce_url(
                        add_query_arg('polski_oneclick_withdrawal', $order->get_id(), wc_get_account_endpoint_url('orders')),
                        'polski_oneclick_' . $order->get_id(),
                    ),
                    'name' => $settings['oneclick_button_text'] ?? __('Withdraw from contract', 'polski'),
                ];
            }
        }

        return $actions;
    }

    /**
     * Handle withdrawal form submission from My Account.
     */
    public function handleWithdrawalFormSubmission(): void
    {
        if (! isset($_GET['polski_withdrawal'])) {
            return;
        }

        $orderId = (int) $_GET['polski_withdrawal'];
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));

        if (! wp_verify_nonce($nonce, 'polski_withdrawal_' . $orderId)) {
            wc_add_notice((string) ($this->getSettings()['invalid_nonce_text'] ?? __('Oops, something went wrong on our side. Please try again!', 'polski')), 'error');
            return;
        }

        $order = wc_get_order($orderId);

        if (! $order instanceof \WC_Order) {
            wc_add_notice((string) ($this->getSettings()['order_not_found_text'] ?? __('Unfortunately, we could not find such an order.', 'polski')), 'error');
            return;
        }

        // Verify ownership.
        if ($order->get_customer_id() !== get_current_user_id()) {
            wc_add_notice((string) ($this->getSettings()['permission_error_text'] ?? __('You do not have permission to withdraw from this order.', 'polski')), 'error');
            return;
        }

        $reason = isset($_POST['polski_withdrawal_reason'])
            ? sanitize_textarea_field(wp_unslash($_POST['polski_withdrawal_reason']))
            : null;

        $request = $this->createRequest($orderId, $reason);

        if ($request !== null) {
            wc_add_notice(
                (string) ($this->getSettings()['success_text'] ?? __('Your return request has been accepted. We will send you a confirmation email shortly!', 'polski')),
                'success',
            );
        } else {
            wc_add_notice(
                (string) ($this->getSettings()['not_eligible_text'] ?? __('This order is not eligible for withdrawal.', 'polski')),
                'error',
            );
        }

        wp_safe_redirect(wc_get_account_endpoint_url('orders'));
        exit;
    }

    /**
     * Handle one-click withdrawal: show confirmation page (GET) or process withdrawal (POST).
     */
    public function handleOneClickWithdrawal(): void
    {
        if (! isset($_GET['polski_oneclick_withdrawal'])) {
            return;
        }

        $orderId = (int) $_GET['polski_oneclick_withdrawal'];
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));

        if (! wp_verify_nonce($nonce, 'polski_oneclick_' . $orderId)) {
            wc_add_notice(__('Invalid security token. Please try again.', 'polski'), 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('orders'));
            exit;
        }

        $order = wc_get_order($orderId);

        if (! $order instanceof \WC_Order) {
            wc_add_notice(__('Order not found.', 'polski'), 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('orders'));
            exit;
        }

        if ($order->get_customer_id() !== get_current_user_id()) {
            wc_add_notice(__('You do not have permission to withdraw from this order.', 'polski'), 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('orders'));
            exit;
        }

        if (! $this->isEligible($order)) {
            wc_add_notice(__('This order is not eligible for withdrawal.', 'polski'), 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('orders'));
            exit;
        }

        // POST: process the confirmed withdrawal.
        $requestMethod = isset($_SERVER['REQUEST_METHOD'])
            ? sanitize_key((string) wp_unslash($_SERVER['REQUEST_METHOD']))
            : '';
        if (
            $requestMethod === 'POST'
            && isset($_POST['_polski_confirm_withdrawal'])
            && wp_verify_nonce(
                sanitize_text_field((string) wp_unslash($_POST['_polski_confirm_withdrawal'])),
                'polski_confirm_withdrawal_' . $orderId,
            )
        ) {
            $request = $this->createRequest($orderId);

            if ($request !== null) {
                wc_add_notice(
                    (string) ($this->getSettings()['success_text'] ?? __('Your withdrawal request has been accepted.', 'polski')),
                    'success',
                );
            } else {
                wc_add_notice(__('Failed to submit the withdrawal request.', 'polski'), 'error');
            }

            wp_safe_redirect(wc_get_account_endpoint_url('orders'));
            exit;
        }

        // GET: render the confirmation page.
        $settings = $this->getSettings();

        // Output WooCommerce account page wrapper with confirmation template.
        get_header('shop');

        echo '<div class="woocommerce">';
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Template handles its own escaping.
        echo $this->templateLoader->render('account/withdrawal-confirm', [
            'order' => $order,
            'settings' => $settings,
        ]);
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';

        get_footer('shop');
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
            esc_html((string) ($this->getSettings()['status_heading'] ?? __('Withdrawal request', 'polski'))),
            esc_html((string) ($this->getSettings()['status_label'] ?? __('Status', 'polski'))),
            esc_html($request->status->label()),
            esc_html((string) ($this->getSettings()['submitted_label'] ?? __('Submitted', 'polski'))),
            esc_html($request->requestedAt->format((string) ($this->getSettings()['status_date_format'] ?? 'Y-m-d H:i'))),
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
                'label' => (string) ($this->getSettings()['reason_label'] ?? __('Reason for withdrawal (optional)', 'polski')),
                'required' => false,
            ],
        ];

        /**
         * Filter withdrawal form fields.
         *
         * @param array<string, array<string, mixed>> $fields
         */
        return (array) apply_filters('polski/withdrawal/form_fields', $fields);
    }

    private function getWithdrawalDays(): int
    {
        /**
         * Filter the withdrawal period in days.
         *
         * @param int $days Default 14.
         */
        return (int) apply_filters('polski/withdrawal/period_days', self::WITHDRAWAL_PERIOD_DAYS);
    }
}
