<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * Registers the dedicated order statuses that track withdrawal lifecycle.
 *
 * - `wc-withdrawal-req`:  customer (or admin) filed a withdrawal request.
 * - `wc-withdrawal-part`: at least one item refunded, items still remain.
 * - `wc-withdrawal-done`: all withdrawn items refunded, processing finished.
 *
 * The slugs are abbreviated because an order status is stored in a
 * `varchar(20)` column, `wp_posts.post_status` on legacy storage and
 * `wp_wc_orders.status` under HPOS. A longer key is silently truncated on
 * write, so the order ends up carrying a status nobody registered: it drops
 * out of the WooCommerce order list, which builds its "All" view from
 * `array_intersect(array_keys(wc_get_order_statuses()), get_post_stati(...))`,
 * while still opening fine by direct URL. Keep every slug at 20 characters or
 * fewer, including the `wc-` prefix. {@see self::MAX_STATUS_LENGTH}
 */
final class WithdrawalOrderStatusService implements HasHooks
{
    public const STATUS_REQUESTED = 'wc-withdrawal-req';
    public const STATUS_PARTIAL = 'wc-withdrawal-part';
    public const STATUS_COMPLETED = 'wc-withdrawal-done';

    /**
     * Storage limit of the order status column on both supported schemas.
     */
    public const MAX_STATUS_LENGTH = 20;

    public function registerHooks(): void
    {
        add_action('init', [$this, 'registerStatuses']);
        add_filter('wc_order_statuses', [$this, 'addToStatusList']);
        add_filter('woocommerce_order_is_paid_statuses', [$this, 'treatAsPaid']);
        add_filter('woocommerce_reports_order_statuses', [$this, 'includeInReports']);
        add_filter('bulk_actions-edit-shop_order', [$this, 'addBulkActions']);
        add_filter('bulk_actions-woocommerce_page_wc-orders', [$this, 'addBulkActions']);
        add_action('admin_head', [$this, 'injectStatusCss']);
    }

    public function registerStatuses(): void
    {
        foreach ($this->definitions() as $slug => $config) {
            register_post_status($slug, [
                'label' => $config['label'],
                'public' => false,
                'exclude_from_search' => true,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                // label_count intentionally omitted: it must use a literal noop
                // string for the i18n parser, but our labels are runtime values.
                // WordPress falls back to `label` when label_count is missing.
            ]);
        }
    }

    /**
     * @param array<string, string>|mixed $statuses
     * @return array<string, string>|mixed
     */
    public function addToStatusList(mixed $statuses): mixed
    {
        if (! is_array($statuses)) {
            return $statuses;
        }

        $injected = [];

        foreach ($statuses as $key => $value) {
            $injected[$key] = $value;

            // Insert withdrawal statuses right after wc-completed for natural ordering.
            if ($key === 'wc-completed') {
                foreach ($this->definitions() as $slug => $config) {
                    $injected[$slug] = $config['label'];
                }
            }
        }

        // Fallback: if wc-completed is missing (heavily customised store), append at end.
        foreach ($this->definitions() as $slug => $config) {
            if (! isset($injected[$slug])) {
                $injected[$slug] = $config['label'];
            }
        }

        return $injected;
    }

    /**
     * Treat partial/completed withdrawals as "paid" for reporting and refund permissions -
     * the original payment was successful; refund accounting is separate.
     *
     * @param list<string>|mixed $statuses
     * @return list<string>|mixed
     */
    public function treatAsPaid(mixed $statuses): mixed
    {
        if (! is_array($statuses)) {
            return $statuses;
        }

        if (! in_array(self::statusKey(self::STATUS_PARTIAL), $statuses, true)) {
            $statuses[] = self::statusKey(self::STATUS_PARTIAL);
        }

        return $statuses;
    }

    /**
     * @param list<string>|mixed $statuses
     * @return list<string>|mixed
     */
    public function includeInReports(mixed $statuses): mixed
    {
        if (! is_array($statuses)) {
            return $statuses;
        }

        foreach ($this->definitions() as $slug => $_config) {
            $key = self::statusKey($slug);
            if (! in_array($key, $statuses, true)) {
                $statuses[] = $key;
            }
        }

        return $statuses;
    }

    /**
     * @param array<string, string> $actions
     * @return array<string, string>
     */
    public function addBulkActions(array $actions): array
    {
        $actions['mark_' . self::statusKey(self::STATUS_REQUESTED)] = __('Mark as withdrawal requested', 'polski');
        $actions['mark_' . self::statusKey(self::STATUS_PARTIAL)] = __('Mark as partial withdrawal', 'polski');
        $actions['mark_' . self::statusKey(self::STATUS_COMPLETED)] = __('Mark as withdrawal completed', 'polski');

        return $actions;
    }

    public function injectStatusCss(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if ($screen === null) {
            return;
        }

        if (! in_array($screen->id, ['edit-shop_order', 'shop_order', 'woocommerce_page_wc-orders'], true)) {
            return;
        }

        $rules = [];
        foreach ($this->definitions() as $slug => $config) {
            $key = self::statusKey($slug);
            $rules[] = sprintf(
                '.order-status.status-%1$s, mark.order-status.status-%1$s { background: %2$s; color: %3$s; }',
                esc_attr($key),
                esc_attr($config['background']),
                esc_attr($config['color']),
            );
        }

        echo '<style id="polski-withdrawal-statuses">' . implode("\n", $rules) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Status slug without the `wc-` prefix (used in $order->set_status() and array keys
     * in many WC filters).
     */
    public static function statusKey(string $slug): string
    {
        return str_starts_with($slug, 'wc-') ? substr($slug, 3) : $slug;
    }

    /**
     * @return array<string, array{label: string, background: string, color: string}>
     */
    private function definitions(): array
    {
        return [
            self::STATUS_REQUESTED => [
                'label' => __('Withdrawal requested', 'polski'),
                'background' => '#fff8e1',
                'color' => '#7c5e10',
            ],
            self::STATUS_PARTIAL => [
                'label' => __('Withdrawal - partial', 'polski'),
                'background' => '#e7f3ff',
                'color' => '#0a4b78',
            ],
            self::STATUS_COMPLETED => [
                'label' => __('Withdrawal completed', 'polski'),
                'background' => '#e6f4ea',
                'color' => '#1e4620',
            ],
        ];
    }
}
