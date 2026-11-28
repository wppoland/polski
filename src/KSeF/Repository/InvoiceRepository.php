<?php

declare(strict_types=1);

namespace Polski\KSeF\Repository;

use Polski\KSeF\Model\Invoice;
use Polski\KSeF\Model\InvoiceItem;
use Polski\KSeF\Model\InvoiceStatus;
use Polski\KSeF\Model\InvoiceType;
use wpdb;

/**
 * Data access for the invoices and invoice_items tables.
 */
final class InvoiceRepository
{
    /** Offline rows KSeF has not numbered yet (one still processing counts: it can still be refused). */
    private const OFFLINE_UNSENT = "ksef_offline_mode IS NOT NULL AND ksef_offline_mode <> '' AND (ksef_status IS NULL OR ksef_status <> 'accepted')";

    public function __construct(
        private readonly wpdb $wpdb,
    ) {
    }

    public function invoiceTable(): string
    {
        return $this->wpdb->prefix . 'polski_invoices';
    }

    public function itemsTable(): string
    {
        return $this->wpdb->prefix . 'polski_invoice_items';
    }

    /**
     * Create an invoice with its line items.
     *
     * @param array<string, mixed> $data Invoice data.
     * @param list<array<string, mixed>> $items Line items.
     */
    public function create(array $data, array $items = []): int
    {
        $row = [
            'order_id' => (int) $data['order_id'],
            'original_invoice_id' => $data['original_invoice_id'] ?? null,
            'source_refund_id' => $data['source_refund_id'] ?? null,
            'type' => $data['type'],
            'number' => $data['number'],
            'status' => $data['status'] ?? InvoiceStatus::Draft->value,
            'nip_seller' => $data['nip_seller'] ?? null,
            'nip_buyer' => $data['nip_buyer'] ?? null,
            'buyer_name' => $data['buyer_name'] ?? null,
            'buyer_address' => $data['buyer_address'] ?? null,
            'buyer_city' => $data['buyer_city'] ?? null,
            'buyer_postcode' => $data['buyer_postcode'] ?? null,
            'correction_reason' => $data['correction_reason'] ?? null,
            'correction_kind' => $data['correction_kind'] ?? null,
            'correction_type' => $data['correction_type'] ?? null,
            'net_total' => (float) $data['net_total'],
            'vat_total' => (float) $data['vat_total'],
            'gross_total' => (float) $data['gross_total'],
            'currency' => $data['currency'] ?? 'PLN',
            'pdf_path' => $data['pdf_path'] ?? null,
            'issued_at' => $data['issued_at'] ?? current_time('mysql', true),
            'sold_at' => $data['sold_at'] ?? null,
            'created_at' => current_time('mysql', true),
        ];

        // The free plugin creates this same table with a UNIQUE key on
        // (series, sequence, year) for its own numbering. PRO leaves those
        // columns at their defaults, so every PRO row after the first collides
        // on it. The number itself stays unique under uniq_number, which is
        // what the free plugin's race guard actually rests on, so the key is
        // dropped the first time it gets in the way. Not in a migration: the
        // free plugin can be activated after PRO and add the key back.
        if ($this->wpdb->insert($this->invoiceTable(), $row) === false
            && str_contains((string) $this->wpdb->last_error, 'uniq_series_seq_year')
        ) {
            $wpdb = $this->wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- one-off: drops the free numbering key that blocks a PRO-numbered row, see above.
            $wpdb->query($wpdb->prepare('ALTER TABLE %i DROP INDEX uniq_series_seq_year', $this->invoiceTable()));
            $this->wpdb->insert($this->invoiceTable(), $row);
        }

        $invoiceId = (int) $this->wpdb->insert_id;

        foreach ($items as $i => $item) {
            $this->wpdb->insert($this->itemsTable(), [
                'invoice_id' => $invoiceId,
                'product_id' => $item['product_id'] ?? null,
                'name' => $item['name'],
                'quantity' => (float) $item['quantity'],
                'unit' => $item['unit'] ?? 'szt.',
                'net_price' => (float) $item['net_price'],
                'vat_rate' => (float) $item['vat_rate'],
                'vat_amount' => (float) $item['vat_amount'],
                'gross_price' => (float) $item['gross_price'],
                'sort_order' => $i,
            ]);
        }

        return $invoiceId;
    }

    public function findBySourceRefundId(int $refundId): ?Invoice
    {
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Custom plugin table; statement prepared with %i identifier and value placeholders. Sniff does not recognize the $this->wpdb property as wpdb.
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                'SELECT * FROM %i WHERE source_refund_id = %d LIMIT 1',
                $this->invoiceTable(),
                $refundId,
            ),
        );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

        if ($row === null) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * Whether a correction cites this invoice.
     *
     * @param bool $withoutRefund Count only corrections that did not come from a refund.
     */
    public function hasCorrection(int $originalInvoiceId, bool $withoutRefund = false): bool
    {
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Custom plugin table; statement prepared with %i identifier and value placeholders. Sniff does not recognize the $this->wpdb property as wpdb.
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE original_invoice_id = %d AND (%d = 0 OR COALESCE(correction_kind, %s) <> %s)',
                $this->invoiceTable(),
                $originalInvoiceId,
                (int) $withoutRefund,
                'refund',
                'refund',
            ),
        ) > 0;
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * A MySQL named lock, so a check and the insert it guards cannot
     * interleave with another request doing the same. False after $timeout seconds.
     */
    public function lock(string $name, int $timeout = 10): bool
    {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared with a value placeholder. Sniff does not recognize the $this->wpdb property as wpdb.
        return (int) $this->wpdb->get_var($this->wpdb->prepare('SELECT GET_LOCK(%s, %d)', $this->lockName($name), $timeout)) === 1;
    }

    public function unlock(string $name): void
    {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared with a value placeholder. Sniff does not recognize the $this->wpdb property as wpdb.
        $this->wpdb->query($this->wpdb->prepare('SELECT RELEASE_LOCK(%s)', $this->lockName($name)));
    }

    /** Lock names are server-wide: scope them to this site's tables, within MySQL's 64 characters. */
    private function lockName(string $name): string
    {
        return substr(md5($this->invoiceTable()), 0, 12) . '_' . substr($name, 0, 50);
    }

    public function findById(int $id): ?Invoice
    {
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Custom plugin table; statement prepared with %i identifier and value placeholders. Sniff does not recognize the $this->wpdb property as wpdb.
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                'SELECT * FROM %i WHERE id = %d',
                $this->invoiceTable(),
                $id,
            ),
        );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

        if ($row === null) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @return list<Invoice>
     */
    public function findByOrder(int $orderId): array
    {
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Custom plugin table; statement prepared with %i identifier and value placeholders. Sniff does not recognize the $this->wpdb property as wpdb.
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                'SELECT * FROM %i WHERE order_id = %d ORDER BY created_at DESC',
                $this->invoiceTable(),
                $orderId,
            ),
        );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

        return array_values(array_map(
            fn (\stdClass $row) => $this->hydrate($row),
            $rows ?? [],
        ));
    }

    /**
     * Invoice with its lines; a by-state correction also carries the invoice
     * it corrects, whose lines are its state before.
     */
    private function hydrate(\stdClass $row): Invoice
    {
        $invoice = Invoice::fromRow($row, $this->findItems((int) $row->id));

        if (! $invoice->correctsByState() || $invoice->originalInvoiceId === null) {
            return $invoice;
        }

        return Invoice::fromRow($row, $invoice->items, $this->findById($invoice->originalInvoiceId));
    }

    /**
     * @param array<string, mixed> $filters Optional: order_id, type, status.
     * @return list<Invoice>
     */
    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = $this->buildWhereClause($filters);

        $params[] = $limit;
        $params[] = $offset;

        array_unshift($params, $this->invoiceTable());

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Custom plugin table; table via %i, values via placeholders bound from the variadic $params (count matches the dynamic $where built from whitelisted columns in buildWhereClause()). Sniff cannot statically count spread args; replacements always match at runtime.
        $rows = $this->wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where whitelisted (buildWhereClause); table via %i, values via prepare().
            $this->wpdb->prepare(
                "SELECT * FROM %i WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                ...$params,
            ),
        );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

        return array_values(array_map(
            fn (\stdClass $row) => Invoice::fromRow($row),
            $rows ?? [],
        ));
    }

    /**
     * @param array<string, mixed> $filters Optional: order_id, type, status.
     */
    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->buildWhereClause($filters);

        if (empty($params)) {
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom plugin table via %i; $where is '1=1' (no filters) here, built from whitelisted column names in buildWhereClause(). Sniff does not recognize the $this->wpdb property as wpdb.
            return (int) $this->wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where whitelisted (buildWhereClause); table via %i, values via prepare().
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM %i WHERE {$where}",
                    $this->invoiceTable(),
                ),
            );
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        array_unshift($params, $this->invoiceTable());

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Custom plugin table via %i; values via placeholders bound from the variadic $params (count matches the dynamic $where built from whitelisted columns in buildWhereClause()). Sniff cannot statically count spread args; replacements always match at runtime.
        return (int) $this->wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where whitelisted (buildWhereClause); table via %i, values via prepare().
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE {$where}",
                ...$params,
            ),
        );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
    }

    /**
     * Invoice statistics for the dashboard.
     *
     * @return array<string, mixed>
     */
    public function getStats(int $days = 30): array
    {
        $table = $this->invoiceTable();
        $since = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Custom plugin table; statements prepared with %i identifier and value placeholders. Sniff does not recognize the $this->wpdb property as wpdb.
        $total = (int) $this->wpdb->get_var(
            $this->wpdb->prepare('SELECT COUNT(*) FROM %i WHERE created_at >= %s', $table, $since),
        );

        $byType = $this->wpdb->get_results(
            $this->wpdb->prepare(
                'SELECT type, COUNT(*) AS count, SUM(gross_total) AS revenue
                 FROM %i WHERE created_at >= %s GROUP BY type',
                $table,
                $since,
            ),
        );

        $byStatus = $this->wpdb->get_results(
            $this->wpdb->prepare(
                'SELECT status, COUNT(*) AS count FROM %i WHERE created_at >= %s GROUP BY status',
                $table,
                $since,
            ),
        );

        $totalRevenue = (float) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COALESCE(SUM(gross_total), 0) FROM %i
                 WHERE created_at >= %s AND status != 'cancelled'",
                $table,
                $since,
            ),
        );

        // Accepted only: ksef_reference also holds a pending session reference,
        // and an empty string after a refusal. The pattern is the NIP form of
        // TNumerKSeF, for rows a status other than 'accepted' ever wrote a number into.
        $ksefSent = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE created_at >= %s
                 AND (ksef_status = 'accepted' OR ksef_reference REGEXP %s)",
                $table,
                $since,
                '^[0-9]{10}-[0-9]{8}-[0-9A-F]{6}-?[0-9A-F]{6}-[0-9A-F]{2}$',
            ),
        );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

        return [
            'period_days' => $days,
            'total' => $total,
            'total_revenue' => $totalRevenue,
            'ksef_sent' => $ksefSent,
            'by_type' => array_map(static fn (\stdClass $r) => [
                'type' => $r->type,
                'count' => (int) $r->count,
                'revenue' => (float) $r->revenue,
            ], $byType ?? []),
            'by_status' => array_map(static fn (\stdClass $r) => [
                'status' => $r->status,
                'count' => (int) $r->count,
            ], $byStatus ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildWhereClause(array $filters): array
    {
        $where = '1=1';
        $params = [];

        if (! empty($filters['order_id'])) {
            $where .= ' AND order_id = %d';
            $params[] = (int) $filters['order_id'];
        }

        if (! empty($filters['type'])) {
            $where .= ' AND type = %s';
            $params[] = (string) $filters['type'];
        }

        if (! empty($filters['status'])) {
            $where .= ' AND status = %s';
            $params[] = (string) $filters['status'];
        }

        if (($filters['ksef'] ?? '') === 'offline_unsent') {
            $where .= ' AND ' . self::OFFLINE_UNSENT;
        }

        return [$where, $params];
    }

    public function updateStatus(int $id, InvoiceStatus $status): bool
    {
        return $this->wpdb->update(
            $this->invoiceTable(),
            ['status' => $status->value, 'updated_at' => current_time('mysql', true)],
            ['id' => $id],
        ) !== false;
    }

    public function updatePdfPath(int $id, string $path): bool
    {
        return $this->wpdb->update(
            $this->invoiceTable(),
            ['pdf_path' => $path],
            ['id' => $id],
        ) !== false;
    }

    /**
     * @param array{ksef_session?: string|null, ksef_upo?: string|null, ksef_error?: string|null, ksef_environment?: string|null, ksef_qr_url?: string|null, ksef_xml?: string|null, ksef_offline_hash?: string|null, ksef_cert_url?: string|null, ksef_offline_mode?: string|null, ksef_offline_deadline?: string|null} $details
     *        Only the keys given are written; null clears one.
     */
    public function updateKSeF(int $id, string $reference, string $status, array $details = []): bool
    {
        $updated = $this->wpdb->update(
            $this->invoiceTable(),
            ['ksef_reference' => $reference, 'ksef_status' => $status] + array_intersect_key(
                $details,
                ['ksef_session' => true, 'ksef_upo' => true, 'ksef_error' => true, 'ksef_environment' => true, 'ksef_qr_url' => true, 'ksef_xml' => true, 'ksef_offline_hash' => true, 'ksef_cert_url' => true, 'ksef_offline_mode' => true, 'ksef_offline_deadline' => true],
            ),
            ['id' => $id],
        ) !== false;

        if ($updated) {
            $this->mirrorKSeFStatusToOrder($id, $status);
        }

        return $updated;
    }

    /**
     * The XML frozen when an offline invoice was issued, and the plain Base64
     * SHA-256 of the first one; null for an online invoice. Kept off the
     * Invoice model like the UPO.
     *
     * @return array{xml: string, hash: string}|null
     */
    public function frozenXml(int $id): ?array
    {
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Custom plugin table; statement prepared with %i identifier and value placeholders. Sniff does not recognize the $this->wpdb property as wpdb.
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare('SELECT ksef_xml, ksef_offline_hash FROM %i WHERE id = %d', $this->invoiceTable(), $id),
        );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

        return is_object($row) && is_string($row->ksef_xml ?? null) && $row->ksef_xml !== ''
            ? ['xml' => $row->ksef_xml, 'hash' => (string) ($row->ksef_offline_hash ?? '')]
            : null;
    }

    /**
     * Offline invoices KSeF has not received yet, the most urgent first. A
     * rejected or failed one is still owed, so this goes by the absence of a
     * receipt, not by one status.
     *
     * @return list<Invoice>
     */
    public function findOfflineUnsent(int $limit = 50): array
    {
        return $this->findAll(['ksef' => 'offline_unsent'], $limit);
    }

    /**
     * The UPO KSeF issued for the invoice, or null before there is one. Kept
     * off the Invoice model so listing invoices does not drag receipts along.
     */
    public function ksefUpo(int $id): ?string
    {
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Custom plugin table; statement prepared with %i identifier and value placeholders. Sniff does not recognize the $this->wpdb property as wpdb.
        $upo = $this->wpdb->get_var(
            $this->wpdb->prepare('SELECT ksef_upo FROM %i WHERE id = %d', $this->invoiceTable(), $id),
        );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

        return is_string($upo) && $upo !== '' ? $upo : null;
    }

    /**
     * Copy the KSeF status onto the order the invoice belongs to.
     *
     * The FREE plugin's order-list badge and order panel read
     * `_polski_ksef_status`, and nothing ever wrote it, so every order that had
     * been sent to KSeF still showed as pending. The status lives in the
     * invoice table, which the FREE plugin cannot see. Mirrored here rather
     * than at each of the ten updateKSeF() call sites, so a new one cannot
     * forget it.
     */
    private function mirrorKSeFStatusToOrder(int $invoiceId, string $status): void
    {
        $invoice = $this->findById($invoiceId);

        if (! $invoice instanceof Invoice || $invoice->orderId <= 0) {
            return;
        }

        $order = wc_get_order($invoice->orderId);

        if (! $order instanceof \WC_Order) {
            return;
        }

        $order->update_meta_data('_polski_ksef_status', $status);
        $order->save();
    }

    /**
     * @return list<InvoiceItem>
     */
    private function findItems(int $invoiceId): array
    {
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Custom plugin table; statement prepared with %i identifier and value placeholders. Sniff does not recognize the $this->wpdb property as wpdb.
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                'SELECT * FROM %i WHERE invoice_id = %d ORDER BY sort_order ASC',
                $this->itemsTable(),
                $invoiceId,
            ),
        );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

        return array_values(array_map(
            static fn (\stdClass $row) => InvoiceItem::fromRow($row),
            $rows ?? [],
        ));
    }
}
