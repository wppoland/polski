<?php

declare(strict_types=1);

namespace Polski\Invoice;

use Polski\Contract\HasHooks;
use WC_Order;

defined('ABSPATH') || exit;

/**
 * Where a merchant issues an invoice and where anyone opens one.
 *
 * One route serves both the admin and the customer. Access is decided per
 * request: a shop manager may open any invoice, a customer only their own, and
 * an emailed link carries a token derived from the invoice so it works without
 * a login and cannot be walked by changing the id.
 */
final class InvoiceHooks implements HasHooks
{
    private const ISSUE_ACTION = 'polski_issue_invoice';

    private const VIEW_QUERY = 'polski_invoice';

    public function __construct(private InvoiceService $invoices)
    {
    }

    public function registerHooks(): void
    {
        if (! $this->invoices->isEnabled()) {
            return;
        }

        add_action('admin_post_' . self::ISSUE_ACTION, [$this, 'handleIssue']);
        add_action('template_redirect', [$this, 'maybeRenderInvoice']);

        // Order screen: HPOS and the classic post table use different hooks.
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'renderOrderPanel']);
        add_filter('woocommerce_my_account_my_orders_actions', [$this, 'addMyAccountAction'], 10, 2);
    }

    /**
     * The issue button, and the link to an invoice once there is one.
     */
    public function renderOrderPanel(WC_Order $order): void
    {
        if (! current_user_can('edit_shop_orders')) {
            return;
        }

        $invoice = $this->invoices->find($order->get_id());

        echo '<p class="form-field form-field-wide polski-invoice-panel">';

        if (null !== $invoice) {
            printf(
                '<strong>%s</strong><br><a href="%s" target="_blank" rel="noopener">%s</a>',
                esc_html__('Invoice', 'polski'),
                esc_url($this->viewUrl($invoice)),
                esc_html($invoice->number),
            );
        } else {
            printf(
                '<a href="%s" class="button">%s</a>',
                esc_url(wp_nonce_url(
                    add_query_arg(
                        ['action' => self::ISSUE_ACTION, 'order_id' => $order->get_id()],
                        admin_url('admin-post.php'),
                    ),
                    self::ISSUE_ACTION . '_' . $order->get_id(),
                )),
                esc_html__('Issue invoice', 'polski'),
            );
        }

        echo '</p>';
    }

    /**
     * @param array<string, array<string, string>> $actions
     *
     * @return array<string, array<string, string>>
     */
    public function addMyAccountAction(array $actions, WC_Order $order): array
    {
        $invoice = $this->invoices->find($order->get_id());

        if (null === $invoice) {
            return $actions;
        }

        $actions['polski_invoice'] = [
            'url'  => $this->viewUrl($invoice),
            'name' => __('Invoice', 'polski'),
        ];

        return $actions;
    }

    public function handleIssue(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $orderId = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;

        if (! current_user_can('edit_shop_orders')) {
            wp_die(esc_html__('You are not allowed to do this.', 'polski'));
        }

        check_admin_referer(self::ISSUE_ACTION . '_' . $orderId);

        $order = wc_get_order($orderId);

        if (! $order instanceof WC_Order) {
            wp_die(esc_html__('Order not found.', 'polski'));
        }

        try {
            $invoice = $this->invoices->issue($order);
        } catch (\RuntimeException $e) {
            wp_die(esc_html($e->getMessage()));
        }

        wp_safe_redirect($this->viewUrl($invoice));
        exit;
    }

    /**
     * Render the document when the request carries an invoice id it may see.
     */
    public function maybeRenderInvoice(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $id = isset($_GET[self::VIEW_QUERY]) ? absint($_GET[self::VIEW_QUERY]) : 0;
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ($id <= 0) {
            return;
        }

        $invoice = $this->invoices->findById($id);

        if (null === $invoice || ! $this->mayView($invoice, $token)) {
            wp_die(
                esc_html__('This invoice is not available.', 'polski'),
                '',
                ['response' => 404],
            );
        }

        // Never let an invoice sit in a page cache or a CDN: it is one person's
        // financial document, not a page.
        nocache_headers();

        // Registered here rather than on wp_enqueue_scripts: this runs on
        // template_redirect, which fires first, so a handle registered on the
        // later hook does not exist yet and the document printed unstyled.
        wp_register_style(
            'polski-invoice',
            plugins_url('assets/css/invoice.css', \Polski\PLUGIN_FILE),
            [],
            \Polski\VERSION,
        );

        require \Polski\PLUGIN_DIR . '/templates/invoice/document.php';
        exit;
    }

    /**
     * A shop manager sees everything; a signed-in customer sees their own; a
     * token that matches the invoice stands in for either.
     */
    private function mayView(Invoice $invoice, string $token): bool
    {
        if ('' !== $token && hash_equals($invoice->token(), $token)) {
            return true;
        }

        if (current_user_can('edit_shop_orders')) {
            return true;
        }

        $order = wc_get_order($invoice->orderId);

        return $order instanceof WC_Order
            && get_current_user_id() > 0
            && $order->get_customer_id() === get_current_user_id();
    }

    private function viewUrl(Invoice $invoice): string
    {
        return add_query_arg(
            [self::VIEW_QUERY => $invoice->id, 'token' => $invoice->token()],
            home_url('/'),
        );
    }
}
