<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Enum\ReturnRequestStatus;
use Polski\Repository\ReturnRepository;

defined('ABSPATH') || exit;

/**
 * Returns and complaints (RMA).
 *
 * Lets a customer open a complaint (reklamacja) or return (zwrot) request for an
 * eligible order from My Account, stores it, confirms by email, and gives the shop
 * an admin queue with status changes. Mirrors the withdrawal request flow. Optional
 * module, OFF by default. This provides tools and templates; it is not legal advice.
 */
final class ReturnRequestService implements Bootable, HasHooks
{
    private const OPTION = 'polski_returns';
    private const PAGE_SLUG = 'polski-returns';
    private const TYPES = ['complaint', 'return'];

    public function __construct(
        private readonly ReturnRepository $returns,
    ) {
    }

    public function boot(): void
    {
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('returns_rma');
    }

    public function registerHooks(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        add_filter('woocommerce_my_account_my_orders_actions', [$this, 'addOrderAction'], 10, 2);
        add_action('woocommerce_order_details_after_order_table', [$this, 'renderOnOrder']);
        add_action('admin_post_polski_return_submit', [$this, 'handleSubmit']);
        add_action('admin_menu', [$this, 'registerAdminPage'], 95);
        add_action('admin_post_polski_return_status', [$this, 'handleStatusChange']);
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $settings = get_option(self::OPTION, []);

        return is_array($settings) ? $settings : [];
    }

    private function windowDays(): int
    {
        return max(0, (int) ($this->settings()['window_days'] ?? 365));
    }

    public function isEligible(\WC_Order $order): bool
    {
        $userId = get_current_user_id();

        if ($userId === 0 || (int) $order->get_customer_id() !== $userId) {
            return false;
        }

        $created = $order->get_date_created();

        if ($created === null) {
            return false;
        }

        $deadline = $created->getTimestamp() + $this->windowDays() * DAY_IN_SECONDS;

        return time() <= $deadline;
    }

    /**
     * @param array<string, array{url: string, name: string}> $actions
     * @return array<string, array{url: string, name: string}>
     */
    public function addOrderAction(array $actions, \WC_Order $order): array
    {
        if (! $this->isEligible($order)) {
            return $actions;
        }

        $actions['polski_return'] = [
            'url' => add_query_arg('polski_return', $order->get_id(), $order->get_view_order_url()),
            'name' => __('Complaint / return', 'polski'),
        ];

        return $actions;
    }

    public function renderOnOrder(\WC_Order $order): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $existing = $this->returns->findByOrder($order->get_id());

        if ($existing !== []) {
            echo '<h2>' . esc_html__('Your complaints and returns', 'polski') . '</h2><ul>';

            foreach ($existing as $request) {
                $typeLabel = $request->type === 'return' ? __('Return', 'polski') : __('Complaint', 'polski');
                echo '<li>' . esc_html($typeLabel) . ' - ' . esc_html($request->status->label())
                    . ' (' . esc_html($request->createdAt->format('Y-m-d')) . ')</li>';
            }

            echo '</ul>';
        }

        $requested = isset($_GET['polski_return']) ? absint(wp_unslash($_GET['polski_return'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display toggle, no state change.

        if ($requested !== $order->get_id() || ! $this->isEligible($order)) {
            return;
        }

        echo '<h2>' . esc_html__('Submit a complaint or return', 'polski') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="polski-return-form">';
        echo '<input type="hidden" name="action" value="polski_return_submit">';
        echo '<input type="hidden" name="order_id" value="' . esc_attr((string) $order->get_id()) . '">';
        wp_nonce_field('polski_return_submit_' . $order->get_id(), '_polski_return_nonce');

        echo '<p><label>' . esc_html__('Type', 'polski') . '<br><select name="type">'
            . '<option value="complaint">' . esc_html__('Complaint (reklamacja)', 'polski') . '</option>'
            . '<option value="return">' . esc_html__('Return (zwrot)', 'polski') . '</option>'
            . '</select></label></p>';
        echo '<p><label>' . esc_html__('Reason', 'polski') . '<br><textarea name="reason" rows="4" cols="50"></textarea></label></p>';
        echo '<p><button type="submit" class="button">' . esc_html__('Submit request', 'polski') . '</button></p>';
        echo '</form>';
    }

    public function handleSubmit(): void
    {
        $orderId = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;

        if (! isset($_POST['_polski_return_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_polski_return_nonce'])), 'polski_return_submit_' . $orderId)) {
            wp_die(esc_html__('Security check failed.', 'polski'));
        }

        $order = $orderId > 0 ? wc_get_order($orderId) : false;

        if (! $order instanceof \WC_Order || ! $this->isEligible($order)) {
            wp_die(esc_html__('This order is not eligible.', 'polski'));
        }

        $type = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : 'complaint';
        $type = in_array($type, self::TYPES, true) ? $type : 'complaint';
        $reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';

        $this->returns->create($orderId, get_current_user_id() ?: null, $type, $reason !== '' ? $reason : null);

        $this->notify($order, $type, $reason);

        wp_safe_redirect(add_query_arg('polski_return_done', '1', $order->get_view_order_url()));
        exit;
    }

    private function notify(\WC_Order $order, string $type, string $reason): void
    {
        $typeLabel = $type === 'return' ? __('Return', 'polski') : __('Complaint', 'polski');
        $subject = sprintf(
            /* translators: 1: type, 2: order number */
            __('%1$s received for order #%2$s', 'polski'),
            $typeLabel,
            $order->get_order_number(),
        );
        $body = $subject . "\n\n" . ($reason !== '' ? $reason : '');

        $customerEmail = $order->get_billing_email();

        if (is_email($customerEmail)) {
            wp_mail($customerEmail, $subject, $body);
        }

        $adminEmail = (string) ($this->settings()['notify_email'] ?? get_option('admin_email'));

        if (is_email($adminEmail)) {
            wp_mail($adminEmail, $subject, $body);
        }
    }

    public function registerAdminPage(): void
    {
        add_submenu_page(
            'polski',
            __('Returns & complaints', 'polski'),
            __('Returns & complaints', 'polski'),
            'manage_woocommerce',
            self::PAGE_SLUG,
            [$this, 'renderAdminPage'],
        );
    }

    public function renderAdminPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        echo '<div class="wrap"><h1>' . esc_html__('Returns & complaints', 'polski') . '</h1>';

        $rows = $this->returns->findAll(100, 0);

        if ($rows === []) {
            echo '<p>' . esc_html__('No requests yet.', 'polski') . '</p></div>';

            return;
        }

        echo '<table class="widefat striped"><thead><tr>'
            . '<th>' . esc_html__('Order', 'polski') . '</th>'
            . '<th>' . esc_html__('Type', 'polski') . '</th>'
            . '<th>' . esc_html__('Reason', 'polski') . '</th>'
            . '<th>' . esc_html__('Date', 'polski') . '</th>'
            . '<th>' . esc_html__('Status', 'polski') . '</th></tr></thead><tbody>';

        foreach ($rows as $request) {
            $typeLabel = $request->type === 'return' ? __('Return', 'polski') : __('Complaint', 'polski');
            echo '<tr>';
            echo '<td><a href="' . esc_url(admin_url('post.php?post=' . $request->orderId . '&action=edit')) . '">#' . esc_html((string) $request->orderId) . '</a></td>';
            echo '<td>' . esc_html($typeLabel) . '</td>';
            echo '<td>' . esc_html((string) $request->reason) . '</td>';
            echo '<td>' . esc_html($request->createdAt->format('Y-m-d')) . '</td>';
            echo '<td><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="polski_return_status">';
            echo '<input type="hidden" name="id" value="' . esc_attr((string) $request->id) . '">';
            wp_nonce_field('polski_return_status_' . $request->id, '_polski_status_nonce');
            echo '<select name="status">';

            foreach (ReturnRequestStatus::cases() as $case) {
                echo '<option value="' . esc_attr($case->value) . '"' . selected($case, $request->status, false) . '>' . esc_html($case->label()) . '</option>';
            }

            echo '</select> <button type="submit" class="button button-small">' . esc_html__('Update', 'polski') . '</button>';
            echo '</form></td></tr>';
        }

        echo '</tbody></table></div>';
    }

    public function handleStatusChange(): void
    {
        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;

        if (! isset($_POST['_polski_status_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_polski_status_nonce'])), 'polski_return_status_' . $id)) {
            wp_die(esc_html__('Security check failed.', 'polski'));
        }

        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission.', 'polski'));
        }

        $statusValue = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';
        $status = ReturnRequestStatus::tryFrom($statusValue);

        if ($id > 0 && $status !== null) {
            $this->returns->updateStatus($id, $status);
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
        exit;
    }
}
