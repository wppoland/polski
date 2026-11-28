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

    /** @return list<Invoice> */
    public function corrections(int $orderId): array
    {
        return $this->repository->correctionsByOrder($orderId);
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

        $this->repository->mirrorForKSeF($invoice->id, $order, $snapshot, $totals);

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

    /**
     * Issue the corrective invoice for a refund (art. 106j), or return the one
     * already issued for it. Null when the order has no invoice from this
     * module, or the refund lists no items (an amount-only refund cannot be
     * split into lines), which is noted on the order.
     */
    public function issueCorrection(WC_Order $order, \WC_Order_Refund $refund): ?Invoice
    {
        $existing = $this->repository->findByRefund($refund->get_id());
        if (null !== $existing) {
            return $existing;
        }

        $original = $this->repository->findByOrder($order->get_id());
        if (null === $original) {
            return null;
        }

        $snapshot = $this->builder->buildCorrection($order, $refund, $original->snapshot, $original->number, $original->issuedAt);
        $lines    = is_array($snapshot['lines'] ?? null) ? $snapshot['lines'] : [];
        if ([] === $lines) {
            $order->add_order_note(__('No corrective invoice was issued: the refund is an amount only, with no items. Refund by item quantities, or correct the invoice by hand.', 'polski'));
            $order->save();

            return null;
        }

        $settings = get_option(self::SETTINGS, []);
        $settings = is_array($settings) ? $settings : [];
        $net      = array_sum(array_map(static fn (array $l): float => (float) $l['net'], $lines));
        $tax      = array_sum(array_map(static fn (array $l): float => (float) $l['tax'], $lines));
        $totals   = new InvoiceTotals($order->get_currency(), $net, $tax, $net + $tax);

        $invoice = $this->repository->issue(
            $order->get_id(),
            InvoiceNumber::sanitiseSeries((string) ($settings['correction_series'] ?? 'KOR')),
            $snapshot,
            $totals,
            gmdate('Y-m-d'),
            null,
        );

        $this->repository->mirrorForKSeF($invoice->id, $order, $snapshot, $totals, [
            'original_invoice_id' => $original->id,
            'source_refund_id'    => $refund->get_id(),
            'correction_reason'   => (string) ($snapshot['correction']['reason'] ?? ''),
        ]);

        $order->add_order_note(sprintf(
            /* translators: 1: corrective invoice number, 2: corrected invoice number */
            __('Corrective invoice %1$s issued for invoice %2$s.', 'polski'),
            $invoice->number,
            $original->number,
        ));
        $order->save();

        /**
         * Fires once a corrective invoice has been issued for a refund.
         *
         * @param Invoice          $invoice  The corrective invoice.
         * @param Invoice          $original The invoice it corrects.
         * @param \WC_Order_Refund $refund   The refund it documents.
         */
        do_action('polski/invoice/corrected', $invoice, $original, $refund);

        return $invoice;
    }
}
