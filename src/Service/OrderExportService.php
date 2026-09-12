<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * WooCommerce order export to CSV.
 *
 * Configurable fields, date range filter, status filter,
 * admin page under WooCommerce. Inspired by woo-order-export-lite.
 */
final class OrderExportService implements HasHooks
{
    /**
     * How many orders the CSV export hydrates per query.
     */
    private const EXPORT_BATCH_SIZE = 200;

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('order_export')) {
            return;
        }

        add_action('admin_menu', [$this, 'addAdminPage']);
        add_action('admin_init', [$this, 'handleExport']);
    }

    public function addAdminPage(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Order Export', 'polski'),
            __('Order Export', 'polski'),
            'view_woocommerce_reports',
            'polski-order-export',
            [$this, 'renderPage'],
        );
    }

    public function renderPage(): void
    {
        $statuses = wc_get_order_statuses();
        $defaultDateFrom = wp_date('Y-m-01');
        $defaultDateTo = wp_date('Y-m-d');

        $availableFields = [
            'order_id' => __('Order ID', 'polski'),
            'order_number' => __('Order number', 'polski'),
            'order_date' => __('Order date', 'polski'),
            'order_status' => __('Status', 'polski'),
            'order_total' => __('Total', 'polski'),
            'order_subtotal' => __('Subtotal', 'polski'),
            'order_tax' => __('Tax', 'polski'),
            'order_shipping' => __('Shipping', 'polski'),
            'order_discount' => __('Discount', 'polski'),
            'payment_method' => __('Payment method', 'polski'),
            'shipping_method' => __('Shipping method', 'polski'),
            'billing_first_name' => __('Billing first name', 'polski'),
            'billing_last_name' => __('Billing last name', 'polski'),
            'billing_email' => __('Billing email', 'polski'),
            'billing_phone' => __('Billing phone', 'polski'),
            'billing_company' => __('Billing company', 'polski'),
            'billing_address' => __('Billing address', 'polski'),
            'billing_city' => __('Billing city', 'polski'),
            'billing_postcode' => __('Billing postcode', 'polski'),
            'billing_country' => __('Billing country', 'polski'),
            'shipping_first_name' => __('Shipping first name', 'polski'),
            'shipping_last_name' => __('Shipping last name', 'polski'),
            'shipping_address' => __('Shipping address', 'polski'),
            'shipping_city' => __('Shipping city', 'polski'),
            'shipping_postcode' => __('Shipping postcode', 'polski'),
            'shipping_country' => __('Shipping country', 'polski'),
            'customer_note' => __('Customer note', 'polski'),
            'product_names' => __('Product names', 'polski'),
            'product_skus' => __('Product SKUs', 'polski'),
            'product_quantities' => __('Product quantities', 'polski'),
            'coupon_codes' => __('Coupon codes', 'polski'),
        ];

        $savedFields = get_option('polski_order_export_fields', [
            'order_id', 'order_date', 'order_status', 'order_total',
            'billing_first_name', 'billing_last_name', 'billing_email',
            'product_names', 'product_quantities',
        ]);

        echo '<div class="wrap"><h1>' . esc_html__('Order Export', 'polski') . '</h1>';
        echo '<form method="post">';
        wp_nonce_field('polski_order_export', '_polski_oe_nonce');
        echo '<input type="hidden" name="action" value="polski_export_orders">';

        echo '<table class="form-table">';

        // Date range.
        echo '<tr><th>' . esc_html__('Date range', 'polski') . '</th><td>';
        echo '<input type="date" name="date_from" value="' . esc_attr($defaultDateFrom) . '"> ';
        echo esc_html__('to', 'polski') . ' ';
        echo '<input type="date" name="date_to" value="' . esc_attr($defaultDateTo) . '">';
        echo '</td></tr>';

        // Status filter.
        echo '<tr><th>' . esc_html__('Order statuses', 'polski') . '</th><td>';

        foreach ($statuses as $slug => $label) {
            printf(
                '<label style="display:inline-block;margin-right:12px"><input type="checkbox" name="statuses[]" value="%s" %s> %s</label>',
                esc_attr($slug),
                checked(in_array($slug, ['wc-processing', 'wc-completed'], true), true, false),
                esc_html($label),
            );
        }

        echo '</td></tr>';

        // Fields.
        echo '<tr><th>' . esc_html__('Export fields', 'polski') . '</th><td>';

        foreach ($availableFields as $key => $label) {
            printf(
                '<label style="display:block;margin-bottom:3px"><input type="checkbox" name="fields[]" value="%s" %s> %s</label>',
                esc_attr($key),
                checked(in_array($key, $savedFields, true), true, false),
                esc_html($label),
            );
        }

        echo '</td></tr>';

        echo '</table>';

        submit_button(__('Export CSV', 'polski'));
        echo '</form></div>';
    }

    public function handleExport(): void
    {
        if (empty($_POST['action']) || $_POST['action'] !== 'polski_export_orders') {
            return;
        }

        if (! current_user_can('view_woocommerce_reports')) {
            return;
        }

        check_admin_referer('polski_order_export', '_polski_oe_nonce');

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Admin referer verified above.
        $dateFrom = isset($_POST['date_from'])
            ? sanitize_text_field((string) wp_unslash($_POST['date_from']))
            : (string) wp_date('Y-m-01');
        $dateTo = isset($_POST['date_to'])
            ? sanitize_text_field((string) wp_unslash($_POST['date_to']))
            : (string) wp_date('Y-m-d');

        $statuses = ['wc-processing', 'wc-completed'];
        if (isset($_POST['statuses']) && is_array($_POST['statuses'])) {
            $statuses = [];
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each item is sanitized in the loop body.
            foreach (wp_unslash($_POST['statuses']) as $value) {
                $statuses[] = sanitize_text_field((string) $value);
            }
        }

        $fields = ['order_id', 'order_date', 'order_total'];
        if (isset($_POST['fields']) && is_array($_POST['fields'])) {
            $fields = [];
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each item is sanitized in the loop body.
            foreach (wp_unslash($_POST['fields']) as $value) {
                $fields[] = sanitize_key((string) $value);
            }
            $fields = array_values($fields);
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        update_option('polski_order_export_fields', $fields);

        // The whole file is built into a temporary file before a single byte of
        // it goes out. While the export streamed straight to php://output, a
        // fatal, an exhausted memory limit or a hit time limit arrived as HTTP
        // 200 with a short CSV attached, which looks like a complete export.
        // Nothing below the headers can produce a visible error page, so the
        // work happens above them.
        $output = tmpfile();

        if ($output === false) {
            wp_die(esc_html__('The export could not create a temporary file.', 'polski'));
        }

        $this->writeCsv($output, $fields, $this->collectOrderIds($statuses, $dateFrom, $dateTo));

        $filename = 'orders_' . $dateFrom . '_' . $dateTo . '.csv';

        // No Content-Length. The size of the file is not the size of the
        // response. If anything already wrote into an output buffer that is
        // still open - a notice, another plugin - those bytes go out in front
        // of the CSV, and the browser stops reading at the length this header
        // promised, which cuts the same number of bytes off the end of the
        // file. Measured on PHP 8.4: a 383-byte CSV behind a 67-byte notice
        // arrives as 383 bytes ending mid-row. Without the header the whole
        // response arrives. WooCommerce's own CSV exporter sends no length
        // either, and neither did 1.37.3. It bought a progress bar.
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        echo "\xEF\xBB\xBF"; // BOM.

        rewind($output);
        fpassthru($output);
        fclose($output);

        exit;
    }

    /**
     * The IDs of every order in the range, read in one query as a snapshot.
     *
     * Only IDs are held here, not hydrated orders, and the list is what the CSV
     * is written from. That is what makes the export immune to writes that land
     * while it runs: an order placed, trashed or moved out of the range after
     * this query cannot shift a page boundary, because there are no page
     * boundaries left to shift.
     *
     * @param  array<string> $statuses
     * @return list<int>
     */
    public function collectOrderIds(array $statuses, string $dateFrom, string $dateTo): array
    {
        $ids = wc_get_orders([
            'limit' => -1,
            'return' => 'ids',
            'status' => $statuses,
            'date_created' => $dateFrom . '...' . $dateTo . ' 23:59:59',
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $snapshot = [];

        foreach (is_array($ids) ? $ids : [] as $id) {
            // 'return' => 'ids' gives integers, but a filter on the query can
            // hand back the orders themselves.
            $snapshot[] = $id instanceof \WC_Order ? $id->get_id() : (int) $id;
        }

        return $snapshot;
    }

    /**
     * Write the export to an open stream, hydrating the snapshot in batches.
     *
     * @param resource      $output
     * @param array<string> $fields
     * @param list<int>     $orderIds
     */
    public function writeCsv($output, array $fields, array $orderIds): void
    {
        // Header row.
        $headers = array_map(fn ($f) => $this->getFieldLabel($f), $fields);
        fputcsv($output, $headers, ';');

        /**
         * Number of orders hydrated per query while building the CSV.
         *
         * @param int $batchSize
         */
        $batchSize = (int) apply_filters('polski/order_export/batch_size', self::EXPORT_BATCH_SIZE);

        if ($batchSize < 1) {
            $batchSize = self::EXPORT_BATCH_SIZE;
        }

        foreach (array_chunk($orderIds, $batchSize) as $chunk) {
            $orders = wc_get_orders([
                'limit' => count($chunk),
                'post__in' => $chunk,
            ]);

            $byId = [];

            foreach (is_array($orders) ? $orders : [] as $order) {
                $byId[(int) $order->get_id()] = $order;
            }

            foreach ($chunk as $orderId) {
                if (! isset($byId[$orderId])) {
                    // Deleted or trashed between the snapshot and this batch.
                    // There is nothing left to read, so the row is dropped; no
                    // other row moves, because the snapshot fixed their places.
                    continue;
                }

                $row = array_map(fn ($f) => $this->getFieldValue($byId[$orderId], $f), $fields);
                fputcsv($output, $row, ';');
            }
        }
    }

    private function getFieldLabel(string $field): string
    {
        $labels = [
            'order_id' => 'ID', 'order_number' => 'Number', 'order_date' => 'Date',
            'order_status' => 'Status', 'order_total' => 'Total', 'order_subtotal' => 'Subtotal',
            'order_tax' => 'Tax', 'order_shipping' => 'Shipping', 'order_discount' => 'Discount',
            'payment_method' => 'Payment', 'shipping_method' => 'Shipping Method',
            'billing_first_name' => 'First Name', 'billing_last_name' => 'Last Name',
            'billing_email' => 'Email', 'billing_phone' => 'Phone',
            'billing_company' => 'Company', 'billing_address' => 'Address',
            'billing_city' => 'City', 'billing_postcode' => 'Postcode',
            'billing_country' => 'Country',
            'shipping_first_name' => 'Ship First Name', 'shipping_last_name' => 'Ship Last Name',
            'shipping_address' => 'Ship Address', 'shipping_city' => 'Ship City',
            'shipping_postcode' => 'Ship Postcode', 'shipping_country' => 'Ship Country',
            'customer_note' => 'Note', 'product_names' => 'Products',
            'product_skus' => 'SKUs', 'product_quantities' => 'Quantities',
            'coupon_codes' => 'Coupons',
        ];

        return $labels[$field] ?? $field;
    }

    private function getFieldValue(\WC_Order $order, string $field): string
    {
        return match ($field) {
            'order_id' => (string) $order->get_id(),
            'order_number' => $order->get_order_number(),
            'order_date' => $order->get_date_created()?->date('Y-m-d H:i:s') ?? '',
            'order_status' => wc_get_order_status_name($order->get_status()),
            'order_total' => wc_format_decimal($order->get_total(), 2),
            'order_subtotal' => wc_format_decimal($order->get_subtotal(), 2),
            'order_tax' => wc_format_decimal($order->get_total_tax(), 2),
            'order_shipping' => wc_format_decimal($order->get_shipping_total(), 2),
            'order_discount' => wc_format_decimal($order->get_total_discount(), 2),
            'payment_method' => $order->get_payment_method_title(),
            'shipping_method' => implode(', ', array_map(fn ($item) => $item->get_name(), $order->get_shipping_methods())),
            'billing_first_name' => $order->get_billing_first_name(),
            'billing_last_name' => $order->get_billing_last_name(),
            'billing_email' => $order->get_billing_email(),
            'billing_phone' => $order->get_billing_phone(),
            'billing_company' => $order->get_billing_company(),
            'billing_address' => $order->get_billing_address_1() . ($order->get_billing_address_2() ? ', ' . $order->get_billing_address_2() : ''),
            'billing_city' => $order->get_billing_city(),
            'billing_postcode' => $order->get_billing_postcode(),
            'billing_country' => $order->get_billing_country(),
            'shipping_first_name' => $order->get_shipping_first_name(),
            'shipping_last_name' => $order->get_shipping_last_name(),
            'shipping_address' => $order->get_shipping_address_1() . ($order->get_shipping_address_2() ? ', ' . $order->get_shipping_address_2() : ''),
            'shipping_city' => $order->get_shipping_city(),
            'shipping_postcode' => $order->get_shipping_postcode(),
            'shipping_country' => $order->get_shipping_country(),
            'customer_note' => $order->get_customer_note(),
            'product_names' => implode(' | ', array_map(fn ($item) => $item->get_name() . ' x' . $item->get_quantity(), $order->get_items())),
            'product_skus' => implode(' | ', array_filter(array_map(static function (\WC_Order_Item $item): string {
                if (! $item instanceof \WC_Order_Item_Product) {
                    return '';
                }

                $product = $item->get_product();

                if (! $product instanceof \WC_Product) {
                    return '';
                }

                return (string) $product->get_sku();
            }, $order->get_items()))),
            'product_quantities' => implode(' | ', array_map(fn ($item) => (string) $item->get_quantity(), $order->get_items())),
            'coupon_codes' => implode(', ', $order->get_coupon_codes()),
            default => '',
        };
    }
}
