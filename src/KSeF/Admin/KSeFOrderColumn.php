<?php

declare(strict_types=1);

namespace Polski\KSeF\Admin;

use Polski\Contract\HasHooks;
use Polski\KSeF\Repository\InvoiceRepository;

/**
 * "KSeF" column on the orders list (HPOS and the legacy post table): a badge
 * for the accounting invoice of each order that most needs attention.
 *
 * One query per page, not per row: the order ids of the page are collected
 * (HPOS: from the wc_get_orders result the list table runs; legacy: from the
 * main WP_Query) and every invoice row for them is read at once. A row that
 * was not prefetched falls back to a query for that order alone.
 *
 * Adapted from FakturFlow KSeF (GPLv2+)
 */
final class KSeFOrderColumn implements HasHooks
{
    private const COLUMN = 'polski_ksef';

    /** @var list<int> Order ids of the page, collected before rendering. */
    private array $pageIds = [];

    /** @var array<int, array{ksef_status: ?string, ksef_environment: ?string, ksef_error: ?string}|null> */
    private array $shown = [];

    /**
     * @param object|null $free The free plugin's KSeFReadyService. Its KSeF
     *        module paints a "KSeF required" dot into this same column key, so
     *        with both active one cell held two badges. PRO's invoice status
     *        supersedes the dot, so the free renderer is unhooked; without PRO
     *        the free column is untouched.
     */
    public function __construct(
        private readonly InvoiceRepository $repository,
        private readonly ?object $free = null,
    ) {
    }

    public function registerHooks(): void
    {
        // Until sending is on, the KSeF readiness module's "KSeF required" dot is the useful signal.
        $settings = get_option('polski_pro_ksef', []);
        if (! is_array($settings) || empty($settings['enabled'])) {
            return;
        }

        add_filter('manage_edit-shop_order_columns', [$this, 'addColumn']);
        add_action('manage_shop_order_posts_custom_column', [$this, 'renderLegacy'], 10, 2);

        add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'addColumn']);
        add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'renderHpos'], 10, 2);

        if ($this->free !== null) {
            // On current_screen, so it holds whichever plugin registered first.
            add_action('current_screen', function (): void {
                remove_action('manage_shop_order_posts_custom_column', [$this->free, 'renderOrderColumn'], 10);
                remove_action('woocommerce_shop_order_list_table_custom_column', [$this->free, 'renderHPOSOrderColumn'], 10);
            });
        }

        // Only on the HPOS orders screen, so no other wc_get_orders call is observed.
        add_action('load-woocommerce_page_wc-orders', function (): void {
            add_filter('woocommerce_order_query', [$this, 'collectHpos']);
        });
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function addColumn(array $columns): array
    {
        $out = [];

        foreach ($columns as $key => $label) {
            $out[$key] = $label;
            if ($key === 'order_status') {
                $out[self::COLUMN] = __('KSeF', 'polski');
            }
        }

        $out[self::COLUMN] ??= __('KSeF', 'polski');

        return $out;
    }

    /**
     * Remember the order ids the HPOS list table is about to render.
     */
    public function collectHpos(mixed $results): mixed
    {
        $orders = is_object($results) && isset($results->orders) ? $results->orders : $results;

        if (is_array($orders)) {
            foreach ($orders as $order) {
                $id = $order instanceof \WC_Order ? $order->get_id() : (is_numeric($order) ? (int) $order : 0);
                if ($id > 0) {
                    $this->pageIds[] = $id;
                }
            }
        }

        return $results;
    }

    public function renderLegacy(string $column, int $postId): void
    {
        if ($column !== self::COLUMN) {
            return;
        }

        if ($this->pageIds === [] && isset($GLOBALS['wp_query']->posts) && is_array($GLOBALS['wp_query']->posts)) {
            foreach ($GLOBALS['wp_query']->posts as $post) {
                $this->pageIds[] = $post instanceof \WP_Post ? $post->ID : (int) $post;
            }
        }

        $this->render($postId);
    }

    public function renderHpos(string $column, mixed $order): void
    {
        if ($column !== self::COLUMN) {
            return;
        }

        $this->render($order instanceof \WC_Order ? $order->get_id() : (int) $order);
    }

    private function render(int $orderId): void
    {
        if (! array_key_exists($orderId, $this->shown)) {
            $this->prefetch(in_array($orderId, $this->pageIds, true) ? $this->pageIds : [$orderId]);
        }

        $row = $this->shown[$orderId] ?? null;

        if ($row === null) {
            echo '<span aria-hidden="true">-</span><span class="screen-reader-text">' . esc_html__('No invoice', 'polski') . '</span>';
            return;
        }

        echo self::badgeHtml((string) $row['ksef_status'], (string) $row['ksef_environment'], (string) $row['ksef_error']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside badgeHtml().
    }

    /**
     * Read, in one query, the accounting invoice of each order that most needs
     * attention: an accepted correction must not hide a rejected original.
     * Among equals the newest wins.
     *
     * @param list<int> $orderIds
     */
    private function prefetch(array $orderIds): void
    {
        global $wpdb;

        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));

        foreach ($orderIds as $id) {
            $this->shown[$id] = null;
        }

        if ($orderIds === [] || ! $wpdb instanceof \wpdb) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '%d'));

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholders built from the id count (spread below); custom plugin table.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT order_id, ksef_status, ksef_environment, ksef_error FROM %i WHERE order_id IN ({$placeholders}) AND type IN ('faktura_vat', 'korygujaca') ORDER BY id DESC",
                $this->repository->invoiceTable(),
                ...$orderIds,
            ),
        );
        // phpcs:enable

        foreach (is_array($rows) ? $rows : [] as $row) {
            $id = (int) $row->order_id;
            $current = $this->shown[$id] ?? null;
            if ($current === null || self::urgency((string) $row->ksef_status) > self::urgency((string) $current['ksef_status'])) {
                $this->shown[$id] = [
                    'ksef_status' => $row->ksef_status,
                    'ksef_environment' => $row->ksef_environment,
                    'ksef_error' => $row->ksef_error,
                ];
            }
        }
    }

    /** Higher means the merchant has to act sooner. */
    private static function urgency(string $status): int
    {
        return ['success' => 0, 'info' => 1, 'neutral' => 2, 'warning' => 3, 'error' => 4][self::badge($status)['tone']];
    }

    /**
     * What a KSeF status looks like to the merchant.
     *
     * @return array{label: string, tone: string, tip: string} tone: neutral, info, success, warning or error.
     */
    public static function badge(string $status): array
    {
        return match ($status) {
            '' => ['label' => __('Not sent', 'polski'), 'tone' => 'neutral', 'tip' => __('This invoice has not been sent to KSeF yet.', 'polski')],
            'queued' => ['label' => __('Queued', 'polski'), 'tone' => 'info', 'tip' => __('Waiting to be sent. It goes out with the next background run, usually within a minute.', 'polski')],
            'submitted' => ['label' => __('Processing', 'polski'), 'tone' => 'info', 'tip' => __('KSeF received the invoice and is still checking it. The status updates by itself.', 'polski')],
            'accepted' => ['label' => __('Accepted', 'polski'), 'tone' => 'success', 'tip' => __('KSeF accepted the invoice and gave it a KSeF number.', 'polski')],
            'offline' => ['label' => __('Offline', 'polski'), 'tone' => 'info', 'tip' => __('Issued in KSeF offline mode with both QR codes. It is sent in the background before its deadline.', 'polski')],
            'offline_overdue' => ['label' => __('Offline, overdue', 'polski'), 'tone' => 'error', 'tip' => __('An offline invoice past its deadline to reach KSeF. The shop keeps sending it; open the order to see why it has not gone yet.', 'polski')],
            'rejected' => ['label' => __('Rejected', 'polski'), 'tone' => 'error', 'tip' => __('KSeF refused the invoice. Open the order to see why.', 'polski')],
            'failed' => ['label' => __('Failed', 'polski'), 'tone' => 'error', 'tip' => __('The invoice could not be sent. Open the order to see why and send it again.', 'polski')],
            'no_nip' => ['label' => __('No seller NIP', 'polski'), 'tone' => 'warning', 'tip' => __('Set a ten-digit seller NIP in the invoice settings, then send again.', 'polski')],
            default => ['label' => ucfirst(str_replace('_', ' ', $status)), 'tone' => 'neutral', 'tip' => __('Status reported by KSeF.', 'polski')],
        };
    }

    /**
     * The badge markup, escaped. The last error, when there is one, replaces
     * the generic tooltip: it is what the merchant needs to act on.
     */
    public static function badgeHtml(string $status, string $environment = '', string $error = ''): string
    {
        $badge = self::badge($status);
        $tip = $error !== '' ? $error : $badge['tip'];
        // tipTip inserts data-tip as HTML, and the error can echo KSeF text.
        $tip = function_exists('wc_sanitize_tooltip') ? wc_sanitize_tooltip($tip) : $tip;

        $html = sprintf(
            '<mark class="polski-ksef-badge is-%1$s tips" data-tip="%2$s"><span>%3$s</span></mark>',
            esc_attr($badge['tone']),
            esc_attr($tip),
            esc_html($badge['label']),
        );

        if ($environment === 'test') {
            $html .= sprintf(
                ' <span class="polski-ksef-env tips" data-tip="%1$s">%2$s</span>',
                esc_attr__('Sent to the KSeF TEST environment. It has no legal effect.', 'polski'),
                esc_html__('TEST', 'polski'),
            );
        }

        return $html;
    }
}
