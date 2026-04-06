<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

final class KSeFReadyService implements HasHooks
{
    public function registerHooks(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        add_action('woocommerce_checkout_order_processed', [$this, 'detectKSeFRequired'], 10, 1);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'renderKSeFStatus']);
        add_filter('manage_edit-shop_order_columns', [$this, 'addOrderColumn'], 20);
        add_action('manage_shop_order_posts_custom_column', [$this, 'renderOrderColumn'], 10, 2);
        // HPOS support.
        add_filter('woocommerce_shop_order_list_table_columns', [$this, 'addOrderColumn'], 20);
        add_action('woocommerce_shop_order_list_table_custom_column', [$this, 'renderHPOSOrderColumn'], 10, 2);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('ksef_ready');
    }

    /**
     * Detect whether the order requires a KSeF invoice based on NIP presence.
     */
    public function detectKSeFRequired(int $orderId): void
    {
        $order = wc_get_order($orderId);

        if (! $order instanceof \WC_Order) {
            return;
        }

        // Check for NIP in billing data from supported checkout fields.
        $nip = $order->get_meta('_billing_nip', true);

        if (empty($nip)) {
            $nip = $order->get_meta('_polski_billing_nip', true);
        }

        $required = ! empty($nip);

        /** @var bool $required Filterable KSeF requirement. */
        $required = (bool) apply_filters('polski/ksef/is_required', $required, $order);

        $order->update_meta_data('_polski_ksef_required', $required ? 'yes' : 'no');
        $order->save();

        if ($required) {
            /** Fires when an order is ready for KSeF invoicing. */
            do_action('polski/ksef/invoice_ready', $order);
        }
    }

    /**
     * Render KSeF status in the admin order billing section.
     */
    public function renderKSeFStatus(\WC_Order $order): void
    {
        $required = $order->get_meta('_polski_ksef_required', true);

        if ($required !== 'yes') {
            return;
        }

        $status = $order->get_meta('_polski_ksef_status', true) ?: __('pending', 'polski');

        printf(
            '<p><strong>%s:</strong> %s</p>',
            esc_html__('KSeF', 'polski'),
            esc_html($status),
        );
    }

    /**
     * Add KSeF column to WooCommerce orders list.
     *
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function addOrderColumn(array $columns): array
    {
        $columns['polski_ksef'] = __('KSeF', 'polski');

        return $columns;
    }

    /**
     * Render KSeF column for legacy (CPT) orders.
     */
    public function renderOrderColumn(string $column, int $postId): void
    {
        if ($column !== 'polski_ksef') {
            return;
        }

        $order = wc_get_order($postId);

        if (! $order instanceof \WC_Order) {
            return;
        }

        $this->echoKSeFBadge($order);
    }

    /**
     * Render KSeF column for HPOS orders.
     */
    public function renderHPOSOrderColumn(string $column, \WC_Order $order): void
    {
        if ($column !== 'polski_ksef') {
            return;
        }

        $this->echoKSeFBadge($order);
    }

    /**
     * Output KSeF status badge HTML.
     */
    private function echoKSeFBadge(\WC_Order $order): void
    {
        $required = $order->get_meta('_polski_ksef_required', true);

        if ($required !== 'yes') {
            echo '<span style="color:#ccc;">—</span>';
            return;
        }

        $status = $order->get_meta('_polski_ksef_status', true) ?: 'pending';
        $color = $status === 'sent' ? '#46b450' : '#f0ad4e';

        echo '<span style="color:' . esc_attr($color) . ';" title="' . esc_attr($status) . '">&#9679; KSeF</span>';
    }
}
