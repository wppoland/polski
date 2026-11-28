<?php

declare(strict_types=1);

namespace Polski\Invoice;

defined('ABSPATH') || exit;

/**
 * Reads and writes issued invoices.
 *
 * Numbering is the whole difficulty here. Polish law wants an unbroken
 * sequence, and "read the highest number, add one, insert" is a race two admins
 * can lose in the same second. The sequence is therefore derived inside the
 * INSERT and the table carries a UNIQUE key on it, so a collision fails loudly
 * and is retried rather than silently producing two invoices with one number.
 */
final class InvoiceRepository
{
    /** How many times to retry a number collision before giving up. */
    private const MAX_ATTEMPTS = 5;

    private function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'polski_invoices';
    }

    /**
     * Issue and store an invoice, allocating the next number in its series.
     *
     * @param array<string, mixed> $snapshot The frozen document.
     *
     * @throws \RuntimeException If a number cannot be allocated.
     */
    public function issue(int $orderId, string $series, array $snapshot, InvoiceTotals $totals, ?string $soldAt, ?string $dueAt): Invoice
    {
        global $wpdb;

        $year = (int) gmdate('Y');

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $next = $this->nextSequence($series, $year);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; $wpdb->insert prepares.
            $ok = $wpdb->insert(
                $this->table(),
                [
                    'order_id'    => $orderId,
                    'number'      => InvoiceNumber::format($series, $next, $year),
                    'series'      => $series,
                    'sequence'    => $next,
                    'year'        => $year,
                    'issued_at'   => current_time('mysql', true),
                    'sold_at'     => $soldAt,
                    'due_at'      => $dueAt,
                    'currency'    => $totals->currency,
                    'total_net'   => $totals->net,
                    'total_tax'   => $totals->tax,
                    'total_gross' => $totals->gross,
                    'snapshot'    => (string) wp_json_encode($snapshot),
                ],
                ['%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s'],
            );

            if (false !== $ok) {
                $invoice = $this->find((int) $wpdb->insert_id);

                if (null !== $invoice) {
                    return $invoice;
                }
            }
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Escaped by the caller before display.
        throw new \RuntimeException(__('Could not allocate an invoice number. Try again.', 'polski'));
    }

    /**
     * Writes the columns and lines the KSeF engine reads (the same ones Polski
     * PRO writes for its own invoices), so an invoice issued here can be sent
     * to KSeF. The snapshot stays the document; these are a copy of it.
     *
     * @param array<string, mixed> $snapshot
     * @param array{original_invoice_id?: int, source_refund_id?: int, correction_reason?: string} $correction Set for a corrective invoice.
     */
    public function mirrorForKSeF(int $id, \WC_Order $order, array $snapshot, InvoiceTotals $totals, array $correction = []): void
    {
        global $wpdb;

        $seller = is_array($snapshot['seller'] ?? null) ? $snapshot['seller'] : [];
        $buyer = is_array($snapshot['buyer'] ?? null) ? $snapshot['buyer'] : [];
        $address = trim($order->get_billing_address_1() . ' ' . $order->get_billing_address_2());

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; $wpdb->update prepares.
        $wpdb->update(
            $this->table(),
            [
                'type'           => [] === $correction ? 'faktura_vat' : 'korygujaca',
                'status'         => 'issued',
                'nip_seller'     => substr((string) preg_replace('/\D/', '', (string) ($seller['nip'] ?? '')), 0, 20) ?: null,
                'nip_buyer'      => substr(trim((string) ($buyer['nip'] ?? '')), 0, 20) ?: null,
                'net_total'      => $totals->net,
                'vat_total'      => $totals->tax,
                'gross_total'    => $totals->gross,
                'buyer_name'     => substr((string) ($buyer['name'] ?? ''), 0, 255),
                'buyer_address'  => substr($address, 0, 255),
                'buyer_city'     => substr($order->get_billing_city(), 0, 120),
                'buyer_postcode' => substr($order->get_billing_postcode(), 0, 20),
            ] + ([] === $correction ? [] : [
                'original_invoice_id' => (int) ($correction['original_invoice_id'] ?? 0) ?: null,
                'source_refund_id'    => (int) ($correction['source_refund_id'] ?? 0) ?: null,
                'correction_reason'   => (string) ($correction['correction_reason'] ?? ''),
                'correction_kind'     => 'refund',
            ]),
            ['id' => $id],
        );

        foreach (array_values(is_array($snapshot['lines'] ?? null) ? $snapshot['lines'] : []) as $i => $line) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; $wpdb->insert prepares.
            $wpdb->insert($wpdb->prefix . 'polski_invoice_items', [
                'invoice_id'  => $id,
                'product_id'  => (int) ($line['product_id'] ?? 0) ?: null,
                'name'        => substr((string) ($line['name'] ?? ''), 0, 255),
                'quantity'    => (float) ($line['quantity'] ?? 1),
                'net_price'   => (float) ($line['net'] ?? 0),
                'vat_rate'    => (float) ($line['rate'] ?? 0),
                'vat_amount'  => (float) ($line['tax'] ?? 0),
                'gross_price' => (float) ($line['gross'] ?? 0),
                'sort_order'  => $i,
            ]);
        }

        /**
         * Fires once an invoice can be sent to KSeF; the KSeF engine queues it when automatic sending is on.
         *
         * @param int $id Invoice id in wp_polski_invoices.
         */
        // With Polski PRO active, PRO issues the invoices that go to KSeF; two binding invoices for one sale must not be filed.
        if (! class_exists('Polski\\Pro\\ProPlugin')) {
            do_action('polski/ksef/invoice_ready_to_send', $id);
        }
    }

    /**
     * The next free sequence number in a series for a year.
     *
     * MAX()+1 rather than a stored counter: a counter drifts the moment a row is
     * removed by hand or an insert fails after incrementing it, and the number
     * that matters is the one actually in the table.
     */
    private function nextSequence(string $series, int $year): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $max = $wpdb->get_var($wpdb->prepare(
            'SELECT MAX(sequence) FROM %i WHERE series = %s AND year = %d',
            $this->table(),
            $series,
            $year,
        ));

        return (int) $max + 1;
    }

    public function find(int $id): ?Invoice
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM %i WHERE id = %d',
            $this->table(),
            $id,
        ), ARRAY_A);

        return is_array($row) ? Invoice::fromRow($row) : null;
    }

    /**
     * Corrective invoices of an order, oldest first.
     *
     * @return list<Invoice>
     */
    public function correctionsByOrder(int $orderId): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM %i WHERE order_id = %d AND source_refund_id > 0 ORDER BY id ASC',
            $this->table(),
            $orderId,
        ), ARRAY_A);

        return array_values(array_map(static fn (array $row): Invoice => Invoice::fromRow($row), is_array($rows) ? $rows : []));
    }

    /** The corrective invoice already issued for a refund, or null. */
    public function findByRefund(int $refundId): ?Invoice
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM %i WHERE source_refund_id = %d LIMIT 1',
            $this->table(),
            $refundId,
        ), ARRAY_A);

        return is_array($row) ? Invoice::fromRow($row) : null;
    }

    /**
     * The invoice issued for an order, or null if there is none.
     */
    public function findByOrder(int $orderId): ?Invoice
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $row = $wpdb->get_row($wpdb->prepare(
            // The VAT invoice, never one of its corrections (issued later, on the same order).
            "SELECT * FROM %i WHERE order_id = %d AND (source_refund_id IS NULL OR source_refund_id = 0) ORDER BY id ASC LIMIT 1",
            $this->table(),
            $orderId,
        ), ARRAY_A);

        return is_array($row) ? Invoice::fromRow($row) : null;
    }

    /**
     * @return list<Invoice>
     */
    public function recent(int $limit = 50): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM %i ORDER BY id DESC LIMIT %d',
            $this->table(),
            max(1, $limit),
        ), ARRAY_A);

        return array_values(array_map(
            static fn (array $row): Invoice => Invoice::fromRow($row),
            is_array($rows) ? $rows : [],
        ));
    }
}
