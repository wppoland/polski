<?php

declare(strict_types=1);
namespace Polski\Admin;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Repository\WithdrawalRepository;
use Polski\Service\WithdrawalService;

/**
 * Sidebar metabox on the WooCommerce order edit screen that surfaces the
 * current withdrawal request (if any) and offers one-click Confirm /
 * Reject buttons. Pro adds a "Process refund" button via the existing
 * polski-pro-withdrawal-refund metabox.
 *
 * Lives on both the classic post.php?post=…&action=edit screen and the
 * HPOS woocommerce_page_wc-orders screen.
 */
final class WithdrawalOrderMetaBox implements HasHooks
{
    private const NONCE_ACTION = 'polski_withdrawal_order_action';

    public function __construct(
        private readonly WithdrawalRepository $repository,
        private readonly WithdrawalService $withdrawal,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('add_meta_boxes', [$this, 'register']);
        add_action('admin_post_polski_withdrawal_order_action', [$this, 'handleAction']);
    }

    public function register(): void
    {
        $screens = ['shop_order', 'woocommerce_page_wc-orders'];
        foreach ($screens as $screen) {
            add_meta_box(
                'polski-withdrawal-status',
                __('Polski - odstąpienie', 'polski'),
                [$this, 'render'],
                $screen,
                'side',
                'high',
            );
        }
    }

    public function render(\WP_Post|\WC_Order $postOrOrder): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $orderId = $postOrOrder instanceof \WC_Order ? $postOrOrder->get_id() : (int) $postOrOrder->ID;
        $order = wc_get_order($orderId);
        if (! $order instanceof \WC_Order) {
            return;
        }

        $request = $this->repository->findByOrder($orderId);

        if ($request === null) {
            ?>
            <p style="color:#475569;">
                <?php esc_html_e('Brak oświadczenia o odstąpieniu dla tego zamówienia.', 'polski'); ?>
            </p>
            <p>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=polski-withdrawals-new')); ?>">
                    <?php esc_html_e('Zarejestruj ręcznie', 'polski'); ?>
                </a>
            </p>
            <?php
            return;
        }

        $declarationId = sprintf('POL-WD-%06d', $request->id);
        ?>
        <p>
            <strong><?php echo esc_html($declarationId); ?></strong><br>
            <span style="color:#475569;">
                <?php echo esc_html($request->status->label()); ?> &middot;
                <?php echo esc_html($request->channel); ?>
            </span>
        </p>

        <?php if ($request->reason !== null && $request->reason !== '') : ?>
            <p style="color:#475569; font-size: 0.9rem;">
                <em><?php echo esc_html(wp_trim_words($request->reason, 28)); ?></em>
            </p>
        <?php endif; ?>

        <?php
        $canConfirm = $request->canConfirm();
        $canComplete = $request->canComplete();
        $canReject = $request->status !== \Polski\Enum\WithdrawalStatus::Completed
            && $request->status !== \Polski\Enum\WithdrawalStatus::Rejected;
        ?>

        <p style="display: flex; flex-direction: column; gap: 0.5rem;">
            <?php if ($canConfirm) : ?>
                <a class="button button-primary"
                   href="<?php echo esc_url($this->actionUrl($request->id, 'confirm')); ?>">
                    <?php esc_html_e('Potwierdź', 'polski'); ?>
                </a>
            <?php endif; ?>

            <?php if ($canComplete) : ?>
                <a class="button button-primary"
                   href="<?php echo esc_url($this->actionUrl($request->id, 'complete')); ?>">
                    <?php esc_html_e('Oznacz jako rozliczone', 'polski'); ?>
                </a>
            <?php endif; ?>

            <?php if ($canReject) : ?>
                <a class="button"
                   href="<?php echo esc_url($this->actionUrl($request->id, 'reject')); ?>"
                   onclick="return confirm('<?php echo esc_js(__('Odrzucić to oświadczenie?', 'polski')); ?>');">
                    <?php esc_html_e('Odrzuć', 'polski'); ?>
                </a>
            <?php endif; ?>

            <a class="button button-link"
               href="<?php echo esc_url(admin_url('admin.php?page=polski-withdrawals')); ?>">
                <?php esc_html_e('Otwórz listę', 'polski'); ?>
            </a>
        </p>
        <?php
    }

    public function handleAction(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Brak uprawnień.', 'polski'));
        }

        $withdrawalId = isset($_GET['withdrawal_id']) ? (int) $_GET['withdrawal_id'] : 0;
        $action = isset($_GET['polski_action']) ? sanitize_key((string) wp_unslash($_GET['polski_action'])) : '';

        check_admin_referer(self::NONCE_ACTION . '_' . $withdrawalId . '_' . $action);

        match ($action) {
            'confirm' => $this->withdrawal->confirm($withdrawalId),
            'complete' => $this->withdrawal->complete($withdrawalId),
            'reject' => $this->withdrawal->reject($withdrawalId, __('Odrzucone z ekranu zamówienia.', 'polski')),
            default => null,
        };

        // Return to the order edit screen.
        $request = $this->repository->findById($withdrawalId);
        $redirect = admin_url('admin.php?page=polski-withdrawals');
        if ($request !== null) {
            $order = wc_get_order($request->orderId);
            if ($order instanceof \WC_Order) {
                $redirect = $order->get_edit_order_url();
            }
        }
        wp_safe_redirect($redirect);
        exit;
    }

    private function actionUrl(int $withdrawalId, string $action): string
    {
        return wp_nonce_url(
            add_query_arg(
                [
                    'action' => 'polski_withdrawal_order_action',
                    'withdrawal_id' => $withdrawalId,
                    'polski_action' => $action,
                ],
                admin_url('admin-post.php'),
            ),
            self::NONCE_ACTION . '_' . $withdrawalId . '_' . $action,
        );
    }
}
