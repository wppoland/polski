<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Enum\WithdrawalStatus;
use Polski\Model\WithdrawalRequest;
use Polski\Repository\WithdrawalItemsRepository;
use Polski\Repository\WithdrawalRepository;
use Polski\Service\WithdrawalOrderStatusService;
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
    private const CLOCK_START_META = '_polski_withdrawal_clock_start';

    public function __construct(
        private readonly WithdrawalRepository $repository,
        private readonly TemplateLoader $templateLoader,
        private readonly WithdrawalItemsRepository $itemsRepository,
    ) {
    }

    public function boot(): void
    {
    }

    /**
     * Order statuses (without the wc- prefix) that start the withdrawal countdown
     * when an order transitions into them. Configurable via settings + filter.
     *
     * @return list<string>
     */
    public function getTriggerStatuses(): array
    {
        $settings = $this->getSettings();
        $raw = $settings['trigger_statuses'] ?? ['completed'];

        if (! is_array($raw)) {
            $raw = ['completed'];
        }

        $clean = [];
        foreach ($raw as $status) {
            $key = sanitize_key((string) $status);
            if ($key === '') {
                continue;
            }
            $clean[] = str_starts_with($key, 'wc-') ? substr($key, 3) : $key;
        }

        if ($clean === []) {
            $clean = ['completed'];
        }

        /**
         * Filter the list of order statuses (without wc- prefix) that start the withdrawal countdown.
         *
         * @param list<string> $statuses
         */
        return (array) apply_filters('polski/withdrawal/trigger_statuses', array_values(array_unique($clean)));
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

        // Record the moment the 14-day clock starts when an order enters a trigger status.
        add_action('woocommerce_order_status_changed', [$this, 'maybeStampClockStart'], 10, 4);
    }

    /**
     * Stamp `_polski_withdrawal_clock_start` the first time an order enters a configured
     * trigger status. The stamp is HPOS-safe (uses WC_Order meta API).
     */
    public function maybeStampClockStart(int $orderId, string $oldStatus, string $newStatus, \WC_Order $order): void
    {
        $newStatus = str_starts_with($newStatus, 'wc-') ? substr($newStatus, 3) : $newStatus;

        if (! in_array($newStatus, $this->getTriggerStatuses(), true)) {
            return;
        }

        if ($order->get_meta(self::CLOCK_START_META, true) !== '') {
            return;
        }

        $order->update_meta_data(self::CLOCK_START_META, current_time('mysql', true));
        $order->save();
    }

    /**
     * Check if an order is eligible for withdrawal.
     *
     * @param \WC_Order $order
     */
    public function isEligible(\WC_Order $order): bool
    {
        // Check if order is within the withdrawal period.
        $orderDate = $this->getClockStart($order);

        if ($orderDate === null) {
            return false;
        }

        $deadline = clone $orderDate;
        $deadline->modify('+' . $this->getWithdrawalDays() . ' days');
        $now = new \WC_DateTime('now', $orderDate->getTimezone());

        if ($now > $deadline) {
            return false;
        }

        // Allow new requests only if at least one item still has remaining quantity.
        $remaining = $this->getRemainingItems($order);
        if ($remaining === []) {
            return false;
        }

        /**
         * Filter withdrawal eligibility. Listeners (notably
         * {@see WithdrawalExemptionService}) refine the answer based on product/
         * category exemptions, digital-content consent, etc.
         *
         * @param bool      $eligible Whether the order is eligible.
         * @param \WC_Order $order    The order.
         */
        return (bool) apply_filters('polski/withdrawal/eligible', true, $order);
    }

    /**
     * Return the items on this order that are still withdrawable, with the qty
     * remaining for each. Excludes fully-withdrawn items and exempt categories
     * (the exemption check is delegated to the eligibility filter at the order
     * level; here we strictly report quantities).
     *
     * @return list<array{
     *     order_item_id: int,
     *     product_id: int,
     *     variation_id: int|null,
     *     name: string,
     *     attributes: string,
     *     sku: string,
     *     quantity_total: float,
     *     quantity_remaining: float,
     *     line_total: float,
     *     line_tax: float,
     *     currency: string,
     *     is_exempt: bool,
     *     exempt_reason: string,
     * }>
     */
    public function getRemainingItems(\WC_Order $order): array
    {
        $withdrawn = $this->itemsRepository->withdrawnQuantitiesForOrder($order->get_id());
        $rows = [];

        foreach ($order->get_items() as $itemId => $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $product = $item->get_product();
            $totalQty = round((float) $item->get_quantity(), 10);
            $remainingQty = round($totalQty - ($withdrawn[(int) $itemId] ?? 0), 10);

            if ($remainingQty <= 0) {
                continue;
            }

            $attrs = '';
            $variationId = $item->get_variation_id();
            if ($variationId > 0 && $product instanceof \WC_Product_Variation) {
                $attrs = wc_get_formatted_variation($product, true, true, false);
            }

            $rows[] = [
                'order_item_id' => (int) $itemId,
                'product_id' => (int) $item->get_product_id(),
                'variation_id' => $variationId > 0 ? $variationId : null,
                'name' => (string) $item->get_name(),
                'attributes' => $attrs,
                'sku' => $product instanceof \WC_Product ? (string) $product->get_sku() : '',
                'quantity_total' => $totalQty,
                'quantity_remaining' => $remainingQty,
                'line_total' => (float) $item->get_total(),
                'line_tax' => (float) $item->get_total_tax(),
                'currency' => $order->get_currency(),
                'is_exempt' => false,
                'exempt_reason' => '',
            ];
        }

        /**
         * Allow exemption-aware services (eg. WithdrawalExemptionService) to
         * decorate each row with `is_exempt` and `exempt_reason` so the form
         * can render them as info-only and the server-side selection parser
         * can drop them. Subscribers MUST preserve the rest of the row shape.
         *
         * @param array<int, array<string, mixed>> $rows
         * @param \WC_Order                        $order
         */
        $decorated = apply_filters('polski/withdrawal/items', $rows, $order);

        if (! is_array($decorated)) {
            return $rows;
        }

        // Re-key the trusted, server-built rows so we can overlay only the two
        // fields subscribers are allowed to influence. This enforces the
        // documented contract ("preserve the rest of the row shape") and keeps
        // malformed third-party filter output from corrupting order data.
        $baseById = [];
        foreach ($rows as $row) {
            $baseById[$row['order_item_id']] = $row;
        }

        $result = [];
        foreach ($decorated as $entry) {
            if (! is_array($entry) || ! isset($entry['order_item_id'])) {
                continue;
            }

            $itemId = (int) $entry['order_item_id'];
            if (! isset($baseById[$itemId])) {
                continue;
            }

            $row = $baseById[$itemId];
            $row['is_exempt'] = ! empty($entry['is_exempt']);
            $row['exempt_reason'] = isset($entry['exempt_reason']) ? (string) $entry['exempt_reason'] : '';
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Create a withdrawal request.
     *
     * @param array<int, float|int>|null $selection Map of order_item_id => quantity to
     *   withdraw. When null the whole remaining order is taken; when empty the call fails.
     */
    public function createRequest(int $orderId, ?string $reason = null, ?array $selection = null): ?WithdrawalRequest
    {
        $order = wc_get_order($orderId);

        if (! $order instanceof \WC_Order) {
            return null;
        }

        if (! $this->isEligible($order)) {
            return null;
        }

        $resolved = $this->resolveSelection($order, $selection);
        if ($resolved === []) {
            return null;
        }

        $customerId = $order->get_customer_id() > 0 ? $order->get_customer_id() : null;

        // Keep the legacy JSON column populated for backwards-compat readers.
        $legacyItems = [];
        foreach ($resolved as $entry) {
            $legacyItems[] = [
                'product_id' => $entry['product_id'],
                'quantity' => (int) ceil($entry['quantity']),
            ];
        }

        $id = $this->repository->create($orderId, $customerId, $reason, $legacyItems);

        if ($id <= 0) {
            do_action(
                'polski/withdrawal/persist_failed',
                $orderId,
                ['flow' => 'logged_in', 'customer_id' => $customerId],
            );
            return null;
        }

        $this->itemsRepository->insertMany($id, $resolved);

        $request = $this->repository->findById($id);

        if ($request !== null) {
            // Add order note with declaration id, items summary, and reason.
            $order->add_order_note(
                $this->formatRequestedNote($request, $reason, $resolved),
                0,
                true,
            );

            // Move the order to the dedicated withdrawal status (unless the store
            // has opted out via filter - e.g. integrations with custom workflow).
            $newStatus = (string) apply_filters(
                'polski/withdrawal/order_status_on_request',
                WithdrawalOrderStatusService::statusKey(WithdrawalOrderStatusService::STATUS_REQUESTED),
                $order,
                $request,
            );

            if ($newStatus !== '' && $order->get_status() !== $newStatus) {
                $order->update_status(
                    $newStatus,
                    sprintf(
                        /* translators: %d = withdrawal request id */
                        __('[Polski] Withdrawal request #%d filed.', 'polski'),
                        $request->id,
                    ),
                );
            }

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
                $actor = wp_get_current_user();
                $actorLabel = ($actor instanceof \WP_User && $actor->ID > 0)
                    ? sprintf('%s (#%d)', $actor->user_login, $actor->ID)
                    : __('system', 'polski');

                $order->add_order_note(
                    sprintf(
                        /* translators: 1: declaration id, 2: actor label */
                        __('[Polski] Withdrawal request #%1$d confirmed by %2$s.', 'polski'),
                        $request->id,
                        $actorLabel,
                    ),
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

            $order = wc_get_order($request->orderId);
            if ($order instanceof \WC_Order) {
                // If anything still remains to be withdrawn on this order, prefer the
                // "partial" status so the operator knows the lifecycle is not closed.
                $stillRemaining = $this->getRemainingItems($order) !== [];
                $defaultStatus = $stillRemaining
                    ? WithdrawalOrderStatusService::STATUS_PARTIAL
                    : WithdrawalOrderStatusService::STATUS_COMPLETED;

                $newStatus = (string) apply_filters(
                    'polski/withdrawal/order_status_on_complete',
                    WithdrawalOrderStatusService::statusKey($defaultStatus),
                    $order,
                    $request,
                );

                if ($newStatus !== '' && $order->get_status() !== $newStatus) {
                    $order->update_status(
                        $newStatus,
                        sprintf(
                            /* translators: %d = withdrawal request id */
                            __('[Polski] Withdrawal request #%d completed.', 'polski'),
                            $request->id,
                        ),
                    );
                }
            }

            do_action('polski/withdrawal/completed', $request);
        }

        return $result;
    }

    /**
     * Reject a withdrawal request. Captures the operator and a free-text reason in
     * the order notes for audit purposes.
     */
    public function reject(int $requestId, ?string $reason = null): bool
    {
        $request = $this->repository->findById($requestId);
        $result = $this->repository->updateStatus($requestId, WithdrawalStatus::Rejected);

        if ($result && $request !== null) {
            $order = wc_get_order($request->orderId);
            if ($order instanceof \WC_Order) {
                $actor = wp_get_current_user();
                $actorLabel = ($actor instanceof \WP_User && $actor->ID > 0)
                    ? sprintf('%s (#%d)', $actor->user_login, $actor->ID)
                    : __('system', 'polski');

                $note = sprintf(
                    /* translators: 1: declaration id, 2: actor label */
                    __('[Polski] Withdrawal request #%1$d rejected by %2$s.', 'polski'),
                    $request->id,
                    $actorLabel,
                );

                if ($reason !== null && trim($reason) !== '') {
                    $note .= "\n" . sprintf(
                        /* translators: %s: free-text reason (customer-supplied for filing, operator-supplied for rejection) */
                        __('Reason: %s', 'polski'),
                        wp_strip_all_tags($reason),
                    );
                }

                $order->add_order_note($note, 1, true);
            }

            // Refresh model snapshot so listeners (e.g. Pro audit log) see the new status.
            $latest = $this->repository->findById($requestId);
            if ($latest !== null) {
                do_action('polski/withdrawal/rejected', $latest);
            }
        }

        return $result;
    }

    /**
     * Format the customer-visible note added at request time. Includes declaration id,
     * itemised summary (with variation attributes for variants), and the trimmed reason.
     *
     * @param list<array{
     *     order_item_id: int,
     *     product_id: int,
     *     variation_id: int|null,
     *     name: string,
     *     attributes: string,
     *     quantity: float,
     * }> $resolved
     */
    private function formatRequestedNote(WithdrawalRequest $request, ?string $reason, array $resolved): string
    {
        $note = sprintf(
            /* translators: %d: declaration id */
            __('[Polski] Withdrawal request #%d filed by customer.', 'polski'),
            $request->id,
        );

        if ($resolved !== []) {
            $lines = [];
            foreach ($resolved as $entry) {
                $line = sprintf('- %s', $entry['name']);
                if ($entry['attributes'] !== '') {
                    $line .= ' (' . $entry['attributes'] . ')';
                }
                $line .= ' x ' . rtrim(rtrim((string) $entry['quantity'], '0'), '.');
                $lines[] = $line;
            }
            $note .= "\n" . __('Items:', 'polski') . "\n" . implode("\n", $lines);
        }

        if ($reason !== null && trim($reason) !== '') {
            $note .= "\n" . sprintf(
                /* translators: %s: free-text reason (customer-supplied for filing, operator-supplied for rejection) */
                __('Reason: %s', 'polski'),
                wp_strip_all_tags($reason),
            );
        }

        return $note;
    }

    /**
     * Translate the user selection (map of order_item_id => qty) into a fully-
     * populated list of items ready for the items repository. Caps each requested
     * qty at the remaining qty; drops zeros; falls back to the full remaining set
     * when no selection was supplied.
     *
     * @param array<int, float|int>|null $selection
     *
     * @return list<array{
     *     order_item_id: int,
     *     product_id: int,
     *     variation_id: int|null,
     *     name: string,
     *     attributes: string,
     *     sku: string,
     *     quantity: float,
     *     line_total: float,
     *     line_tax: float,
     * }>
     */
    private function resolveSelection(\WC_Order $order, ?array $selection): array
    {
        $remaining = $this->getRemainingItems($order);

        if ($remaining === []) {
            return [];
        }

        // Build a quick lookup of exempt item ids so we can strip them from
        // both the full-order path and the explicit selection path. Items are
        // marked exempt by subscribers of the `polski/withdrawal/items` filter
        // applied at the end of getRemainingItems().
        $exemptIds = [];
        foreach ($remaining as $entry) {
            if (! empty($entry['is_exempt'])) {
                $exemptIds[(int) $entry['order_item_id']] = true;
            }
        }

        if ($selection === null) {
            // Full order: take all remaining non-exempt items at their remaining qty.
            $resolved = [];
            foreach ($remaining as $entry) {
                if (isset($exemptIds[(int) $entry['order_item_id']])) {
                    do_action('polski/withdrawal/exempt_item_skipped', (int) $entry['order_item_id'], $order, (string) ($entry['exempt_reason'] ?? ''));
                    continue;
                }
                $resolved[] = [
                    'order_item_id' => $entry['order_item_id'],
                    'product_id' => $entry['product_id'],
                    'variation_id' => $entry['variation_id'],
                    'name' => $entry['name'],
                    'attributes' => $entry['attributes'],
                    'sku' => $entry['sku'],
                    'quantity' => $entry['quantity_remaining'],
                    'line_subtotal' => $entry['line_total'],
                    'line_total' => $entry['line_total'],
                    'line_tax' => $entry['line_tax'],
                ];
            }
            return $resolved;
        }

        $resolved = [];
        $remainingById = [];
        foreach ($remaining as $entry) {
            $remainingById[$entry['order_item_id']] = $entry;
        }

        foreach ($selection as $orderItemId => $qty) {
            $orderItemId = (int) $orderItemId;
            $qty = round(max(0.0, (float) $qty), 10);

            if ($qty <= 0) {
                continue;
            }

            // Strip any posted exempt item silently. The form should already
            // render them as info-only, but a tampered POST or a client that
            // ignores the disabled attribute could still try to include them.
            if (isset($exemptIds[$orderItemId])) {
                $reasonForTelemetry = (string) ($remainingById[$orderItemId]['exempt_reason'] ?? '');
                do_action('polski/withdrawal/exempt_item_skipped', $orderItemId, $order, $reasonForTelemetry);
                continue;
            }

            // Defence in depth: reject any posted order_item_id that does not
            // belong to this order. The nonce binds the submission to the
            // order id, but a tampered POST could still try to slip a foreign
            // item id through; we silently drop and fire a telemetry hook so
            // operators can detect probing.
            if (! isset($remainingById[$orderItemId])) {
                /**
                 * Fired when a withdrawal submission references an item id that
                 * is not part of the order.
                 *
                 * @param int       $orderItemId The posted item id.
                 * @param \WC_Order $order       The order the submission targeted.
                 */
                do_action('polski/withdrawal/foreign_item_rejected', $orderItemId, $order);
                continue;
            }

            $entry = $remainingById[$orderItemId];
            $qty = round(min($qty, $entry['quantity_remaining']), 10);

            if ($qty <= 0) {
                continue;
            }

            // Pro-rata totals so per-item refunds reflect the partial qty.
            $ratio = $entry['quantity_total'] > 0 ? round($qty / $entry['quantity_total'], 10) : 1;

            $resolved[] = [
                'order_item_id' => $orderItemId,
                'product_id' => $entry['product_id'],
                'variation_id' => $entry['variation_id'],
                'name' => $entry['name'],
                'attributes' => $entry['attributes'],
                'sku' => $entry['sku'],
                'quantity' => $qty,
                'line_subtotal' => round($entry['line_total'] * $ratio, 2),
                'line_total' => round($entry['line_total'] * $ratio, 2),
                'line_tax' => round($entry['line_tax'] * $ratio, 2),
            ];
        }

        return $resolved;
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
     * Handle the My Account withdrawal flow. On GET we render the item-selection
     * form (step 1); on POST we process the submission (step 2). The same URL serves
     * both stages so the customer never leaves it.
     */
    public function handleWithdrawalFormSubmission(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified before any state change below.
        if (! isset($_GET['polski_withdrawal'])) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $orderId = (int) $_GET['polski_withdrawal'];
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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

        $requestMethod = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper(sanitize_key((string) wp_unslash($_SERVER['REQUEST_METHOD'])))
            : 'GET';

        // Step 1: GET → render the form (items + reason).
        if ($requestMethod !== 'POST') {
            $this->renderItemSelectionForm($order);
            exit;
        }

        // Step 2: POST → verify submission nonce and create the request.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified immediately below.
        $submitNonce = isset($_POST['polski_submit_nonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['polski_submit_nonce']))
            : '';

        if (! wp_verify_nonce($submitNonce, 'polski_submit_withdrawal_' . $orderId)) {
            wc_add_notice((string) ($this->getSettings()['invalid_nonce_text'] ?? __('Oops, something went wrong on our side. Please try again!', 'polski')), 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('orders'));
            exit;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
        $reason = isset($_POST['polski_withdrawal_reason'])
            ? sanitize_textarea_field(wp_unslash((string) $_POST['polski_withdrawal_reason']))
            : null;

        $selection = $this->parseItemSelectionFromPost();

        $request = $this->createRequest($orderId, $reason, $selection);

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
     * Render the My Account item-selection template (step 1 of the two-step flow).
     */
    private function renderItemSelectionForm(\WC_Order $order): void
    {
        $remaining = $this->getRemainingItems($order);

        get_header('shop');
        echo '<div class="woocommerce">';
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Template handles its own escaping.
        echo $this->templateLoader->render('forms/withdrawal-form', [
            'polski_order' => $order,
            'polski_remaining_items' => $remaining,
            'polski_submit_nonce' => wp_create_nonce('polski_submit_withdrawal_' . $order->get_id()),
            'polski_form_action' => wp_nonce_url(
                add_query_arg('polski_withdrawal', $order->get_id(), wc_get_account_endpoint_url('orders')),
                'polski_withdrawal_' . $order->get_id(),
            ),
        ]);
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
        get_footer('shop');
    }

    /**
     * Extract a sanitised {order_item_id: qty} selection from the submitted form.
     * Expected POST shape: polski_items[order_item_id] = quantity.
     *
     * @return array<int, float>|null Null when no selection was sent (caller should
     *   fall back to "entire order").
     */
    private function parseItemSelectionFromPost(): ?array
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by caller.
        if (! isset($_POST['polski_items']) || ! is_array($_POST['polski_items'])) {
            return null;
        }

        $selection = [];
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by caller.
        foreach ((array) wp_unslash($_POST['polski_items']) as $itemId => $qty) {
            $itemId = (int) $itemId;
            $qty = is_numeric($qty) ? (float) $qty : 0.0;

            if ($itemId <= 0 || $qty <= 0) {
                continue;
            }

            $selection[$itemId] = $qty;
        }

        return $selection !== [] ? $selection : null;
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
        $settings = $this->getSettings();
        $days = isset($settings['period_days']) ? (int) $settings['period_days'] : self::WITHDRAWAL_PERIOD_DAYS;

        if ($days < 1) {
            $days = self::WITHDRAWAL_PERIOD_DAYS;
        }

        /**
         * Filter the withdrawal period in days.
         *
         * @param int $days Default 14.
         */
        return (int) apply_filters('polski/withdrawal/period_days', $days);
    }

    /**
     * Return the timestamp that starts the withdrawal countdown for the given order.
     * Priority: explicit clock-start meta (set when entering a trigger status) ->
     * date_completed -> date_created.
     */
    public function getClockStart(\WC_Order $order): ?\WC_DateTime
    {
        $stamp = (string) $order->get_meta(self::CLOCK_START_META, true);

        if ($stamp !== '') {
            try {
                $dt = new \WC_DateTime($stamp, new \DateTimeZone('UTC'));
                $dt->setTimezone(wp_timezone());
                return $dt;
            } catch (\Throwable) {
                // Fall through to fallback.
            }
        }

        $completed = $order->get_date_completed();
        if ($completed !== null) {
            return $completed;
        }

        return $order->get_date_created();
    }

    /**
     * Deadline (last second of the withdrawal window) for the given order, or null if
     * the clock has not started yet.
     */
    public function getDeadline(\WC_Order $order): ?\WC_DateTime
    {
        $start = $this->getClockStart($order);
        if ($start === null) {
            return null;
        }

        $deadline = clone $start;
        $deadline->modify('+' . $this->getWithdrawalDays() . ' days');

        return $deadline;
    }
}
