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
     * The invoice issued for an order, or null if there is none.
     */
    public function findByOrder(int $orderId): ?Invoice
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM %i WHERE order_id = %d ORDER BY id ASC LIMIT 1',
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
