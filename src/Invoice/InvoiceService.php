<?php

declare(strict_types=1);

namespace Polski\Invoice;

use Polski\Admin\ModulesPage;
use WC_Order;

defined('ABSPATH') || exit;

/**
 * Issuing an invoice for an order, and finding one that already exists.
 */
final class InvoiceService
{
    public const MODULE = 'invoices';

    public const SETTINGS = 'polski_invoices';

    public function __construct(
        private InvoiceRepository $repository,
        private InvoiceDataBuilder $builder,
    ) {
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled(self::MODULE);
    }

    public function find(int $orderId): ?Invoice
    {
        return $this->repository->findByOrder($orderId);
    }

    public function findById(int $id): ?Invoice
    {
        return $this->repository->find($id);
    }

    /**
     * Issue an invoice for an order, or return the one already issued.
     *
     * Issuing twice for one order is not a retry, it is a second legal document
     * for the same sale, so an existing invoice is returned rather than a new
     * number burned.
     *
     * @throws \RuntimeException If the order cannot be invoiced.
     */
    public function issue(WC_Order $order): Invoice
    {
        $existing = $this->repository->findByOrder($order->get_id());

        if (null !== $existing) {
            return $existing;
        }

        $settings = get_option(self::SETTINGS, []);
        $settings = is_array($settings) ? $settings : [];

        $snapshot = $this->builder->build($order);
        $totals   = new InvoiceTotals(
            $order->get_currency(),
            (float) $order->get_total() - (float) $order->get_total_tax(),
            (float) $order->get_total_tax(),
            (float) $order->get_total(),
        );

        $paid   = $order->get_date_paid();
        $soldAt = null !== $paid ? $paid->date('Y-m-d') : gmdate('Y-m-d');

        $dueDays = isset($settings['due_days']) ? max(0, (int) $settings['due_days']) : 0;
        $dueAt   = $dueDays > 0 ? gmdate('Y-m-d', strtotime($soldAt . ' +' . $dueDays . ' days') ?: time()) : null;

        $invoice = $this->repository->issue(
            $order->get_id(),
            InvoiceNumber::sanitiseSeries((string) ($settings['series'] ?? InvoiceNumber::DEFAULT_SERIES)),
            $snapshot,
            $totals,
            $soldAt,
            $dueAt,
        );

        $order->add_order_note(sprintf(
            /* translators: %s: invoice number */
            __('Invoice %s issued.', 'polski'),
            $invoice->number,
        ));
        $order->save();

        /**
         * Fires once an invoice has been issued for an order.
         *
         * @param Invoice  $invoice The issued invoice.
         * @param WC_Order $order   The order it was issued for.
         */
        do_action('polski/invoice/issued', $invoice, $order);

        return $invoice;
    }
}
