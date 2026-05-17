<?php

declare(strict_types=1);
namespace Polski\Admin;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Enum\WithdrawalStatus;
use Polski\Repository\WithdrawalRepository;
use Polski\Service\WithdrawalOrderStatusService;

/**
 * Admin screens for the withdrawal module:
 *  - List of all withdrawal requests (filterable by status / channel).
 *  - Manual registration form for off-line declarations (phone / e-mail / letter)
 *    so the store can log them with the same data model as online requests.
 */
final class WithdrawalsAdminPage implements HasHooks
{
    private const CAPABILITY = 'manage_woocommerce';
    private const PAGE_SLUG = 'polski-withdrawals';
    private const NONCE_ACTION = 'polski_manual_withdrawal';

    public function __construct(
        private readonly WithdrawalRepository $repository,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'registerMenu'], 65);
        add_action('admin_post_polski_register_manual_withdrawal', [$this, 'handleManualSubmission']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'polski',
            __('Withdrawals', 'polski'),
            __('Withdrawals', 'polski'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [$this, 'renderListPage'],
        );

        add_submenu_page(
            'polski',
            __('Register withdrawal', 'polski'),
            __('Register withdrawal', 'polski'),
            self::CAPABILITY,
            self::PAGE_SLUG . '-new',
            [$this, 'renderManualPage'],
        );
    }

    public function renderListPage(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            return;
        }

        $statusFilter = $this->readStatusFilter();
        $rows = $this->repository->findAll(50, 0, $statusFilter);

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Withdrawals', 'polski'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '-new')); ?>" class="page-title-action">
                <?php esc_html_e('Register manually', 'polski'); ?>
            </a>
            <hr class="wp-header-end" />

            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>">
                <label for="polski-filter-status"><?php esc_html_e('Status:', 'polski'); ?></label>
                <select id="polski-filter-status" name="status">
                    <option value=""><?php esc_html_e('All', 'polski'); ?></option>
                    <?php foreach (WithdrawalStatus::cases() as $case) : ?>
                        <option value="<?php echo esc_attr($case->value); ?>" <?php selected($statusFilter?->value, $case->value); ?>>
                            <?php echo esc_html($case->label()); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button(__('Filter', 'polski'), 'secondary', '', false); ?>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'polski'); ?></th>
                        <th><?php esc_html_e('Order', 'polski'); ?></th>
                        <th><?php esc_html_e('Channel', 'polski'); ?></th>
                        <th><?php esc_html_e('Status', 'polski'); ?></th>
                        <th><?php esc_html_e('Reason', 'polski'); ?></th>
                        <th><?php esc_html_e('Filed at', 'polski'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($rows === []) : ?>
                    <tr><td colspan="6"><?php esc_html_e('No withdrawal requests yet.', 'polski'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <td>#<?php echo esc_html((string) $row->id); ?></td>
                            <td>
                                <?php
                                $orderUrl = $this->orderEditUrl($row->orderId);
                                if ($orderUrl !== '') {
                                    printf(
                                        '<a href="%s">#%d</a>',
                                        esc_url($orderUrl),
                                        (int) $row->orderId,
                                    );
                                } else {
                                    echo '#' . (int) $row->orderId;
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html($row->channel ?? 'online'); ?></td>
                            <td><?php echo esc_html($row->status->label()); ?></td>
                            <td><?php echo esc_html(wp_trim_words((string) ($row->reason ?? ''), 12)); ?></td>
                            <td><?php echo esc_html($row->requestedAt->date_i18n(get_option('date_format') . ' H:i')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function renderManualPage(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            return;
        }

        $notice = $this->popNotice();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Register withdrawal manually', 'polski'); ?></h1>
            <p class="description">
                <?php esc_html_e('Use this form to record a withdrawal received outside the shop (phone, e-mail, letter, in-store). The order will be moved to the withdrawal status just like an online request.', 'polski'); ?>
            </p>

            <?php if ($notice !== null) : ?>
                <div class="notice notice-<?php echo esc_attr($notice['type']); ?>">
                    <p><?php echo esc_html($notice['message']); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="polski_register_manual_withdrawal">
                <?php wp_nonce_field(self::NONCE_ACTION); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="polski_order_id"><?php esc_html_e('Order number or ID', 'polski'); ?></label></th>
                        <td><input name="polski_order_id" id="polski_order_id" type="text" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="polski_channel"><?php esc_html_e('Channel', 'polski'); ?></label></th>
                        <td>
                            <select name="polski_channel" id="polski_channel">
                                <option value="phone"><?php esc_html_e('Phone', 'polski'); ?></option>
                                <option value="email"><?php esc_html_e('E-mail', 'polski'); ?></option>
                                <option value="letter"><?php esc_html_e('Letter / post', 'polski'); ?></option>
                                <option value="in_store"><?php esc_html_e('In-store / in-person', 'polski'); ?></option>
                                <option value="other"><?php esc_html_e('Other', 'polski'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="polski_reason"><?php esc_html_e('Reason / notes', 'polski'); ?></label></th>
                        <td><textarea name="polski_reason" id="polski_reason" rows="4" class="large-text"></textarea></td>
                    </tr>
                </table>

                <?php submit_button(__('Register withdrawal', 'polski')); ?>
            </form>
        </div>
        <?php
    }

    public function handleManualSubmission(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You are not allowed to perform this action.', 'polski'));
        }

        check_admin_referer(self::NONCE_ACTION);

        $rawOrder = isset($_POST['polski_order_id'])
            ? sanitize_text_field(wp_unslash((string) $_POST['polski_order_id']))
            : '';
        $rawOrder = ltrim($rawOrder, '#');
        $channel = isset($_POST['polski_channel'])
            ? sanitize_key((string) wp_unslash($_POST['polski_channel']))
            : 'other';
        $reason = isset($_POST['polski_reason'])
            ? sanitize_textarea_field(wp_unslash((string) $_POST['polski_reason']))
            : '';

        $order = null;
        if ($rawOrder !== '') {
            $order = wc_get_order((int) $rawOrder);
        }

        if (! $order instanceof \WC_Order) {
            $this->setNotice('error', __('Order not found.', 'polski'));
            $this->redirectBack();
        }

        $customerId = $order->get_customer_id() > 0 ? $order->get_customer_id() : null;

        $id = $this->repository->createManual(
            $order->get_id(),
            $customerId,
            $channel,
            get_current_user_id(),
            $reason !== '' ? $reason : null,
            null,
        );

        if ($id <= 0) {
            $this->setNotice('error', __('Could not save the withdrawal request.', 'polski'));
            $this->redirectBack();
        }

        $actor = wp_get_current_user();
        $actorLabel = ($actor instanceof \WP_User && $actor->ID > 0)
            ? sprintf('%s (#%d)', $actor->user_login, $actor->ID)
            : __('system', 'polski');

        $order->add_order_note(
            sprintf(
                /* translators: 1: declaration id, 2: channel, 3: actor */
                __('[Polski] Withdrawal request #%1$d registered manually (channel: %2$s) by %3$s.', 'polski'),
                $id,
                $channel,
                $actorLabel,
            ),
            1,
            true,
        );

        $newStatus = WithdrawalOrderStatusService::statusKey(WithdrawalOrderStatusService::STATUS_REQUESTED);
        if ($order->get_status() !== $newStatus) {
            $order->update_status($newStatus, '', true);
        }

        do_action('polski/withdrawal/manual_registered', $id, $order, $channel);

        $this->setNotice('success', __('Withdrawal request registered.', 'polski'));
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
        exit;
    }

    private function readStatusFilter(): ?WithdrawalStatus
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin filter.
        if (empty($_GET['status'])) {
            return null;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin filter.
        $value = sanitize_key((string) wp_unslash($_GET['status']));

        return WithdrawalStatus::tryFrom($value);
    }

    private function orderEditUrl(int $orderId): string
    {
        $order = wc_get_order($orderId);
        if (! $order instanceof \WC_Order) {
            return '';
        }

        return (string) $order->get_edit_order_url();
    }

    private function setNotice(string $type, string $message): void
    {
        set_transient('polski_withdrawal_admin_notice_' . get_current_user_id(), [
            'type' => $type,
            'message' => $message,
        ], 60);
    }

    /**
     * @return array{type: string, message: string}|null
     */
    private function popNotice(): ?array
    {
        $key = 'polski_withdrawal_admin_notice_' . get_current_user_id();
        $value = get_transient($key);

        if (! is_array($value) || ! isset($value['type'], $value['message'])) {
            return null;
        }

        delete_transient($key);

        return [
            'type' => (string) $value['type'],
            'message' => (string) $value['message'],
        ];
    }

    private function redirectBack(): never
    {
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG . '-new'));
        exit;
    }
}
