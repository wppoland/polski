<?php

declare(strict_types=1);

namespace Polski\KSeF\Model;

/**
 * Invoice entity representing a Faktura VAT, Korygujaca, or Paragon.
 */
final class Invoice
{
    /**
     * @param list<InvoiceItem> $items
     */
    public function __construct(
        public readonly int $id,
        public readonly int $orderId,
        public readonly ?int $originalInvoiceId,
        public readonly ?int $sourceRefundId,
        public readonly InvoiceType $type,
        public readonly string $number,
        public InvoiceStatus $status,
        public readonly ?string $nipSeller,
        public readonly ?string $nipBuyer,
        public readonly ?string $correctionReason,
        public readonly float $netTotal,
        public readonly float $vatTotal,
        public readonly float $grossTotal,
        public readonly string $currency,
        public readonly ?string $pdfPath,
        public readonly ?string $ksefReference,
        public readonly ?string $ksefStatus,
        public readonly \DateTimeImmutable $issuedAt,
        public readonly \DateTimeImmutable $createdAt,
        public readonly array $items = [],
        public readonly ?string $buyerName = null,
        public readonly ?string $buyerAddress = null,
        public readonly ?string $buyerCity = null,
        public readonly ?string $buyerPostcode = null,
        public readonly ?string $ksefSession = null,
        public readonly ?string $ksefError = null,
        public readonly ?string $ksefEnvironment = null,
        public readonly ?string $ksefQrUrl = null,
        // Date of supply (art. 106e ust. 1 pkt 6 of the VAT Act); null when not recorded.
        public readonly ?\DateTimeImmutable $soldAt = null,
        // One of CORRECTION_KINDS on a correction, null on anything else.
        public readonly ?string $correctionKind = null,
        // TypKorekty (1, 2 or 3) chosen by hand; null keeps FaMapper's default.
        public readonly ?int $correctionType = null,
        // The invoice a by-state correction corrects; its lines are the state before.
        public readonly ?Invoice $corrected = null,
        // KSeF offline: offline24, offline or awaryjny; null for an online invoice.
        public readonly ?string $ksefOfflineMode = null,
        // Last moment (UTC) to send an offline invoice; null while the outage end is unknown.
        public readonly ?\DateTimeImmutable $ksefOfflineDeadline = null,
        // KOD II link of an offline invoice.
        public readonly ?string $ksefCertUrl = null,
    ) {
    }

    /**
     * What a correction changes. "refund" and "amounts" are written by
     * difference; the rest list the lines before (StanPrzed) and after.
     */
    public const CORRECTION_KINDS = ['refund', 'amounts', 'rate', 'buyer', 'description', 'date'];

    /** Whether this correction lists the lines before and after instead of their difference. */
    public function correctsByState(): bool
    {
        return in_array($this->correctionKind, ['rate', 'buyer', 'description', 'date'], true);
    }

    /**
     * The lines as amounts: the stored lines, or on a by-state correction the
     * lines before negated plus the lines after, which sum to the totals.
     *
     * @return list<InvoiceItem>
     */
    public function differenceItems(): array
    {
        if (! $this->correctsByState() || $this->corrected === null) {
            return $this->items;
        }

        $before = array_map(
            static fn (InvoiceItem $i): InvoiceItem => new InvoiceItem($i->id, $i->invoiceId, $i->productId, $i->name, $i->quantity, $i->unit, -$i->netPrice, $i->vatRate, -$i->vatAmount, -$i->grossPrice, $i->sortOrder),
            $this->corrected->items,
        );

        return array_merge($before, $this->items);
    }

    /**
     * @param list<InvoiceItem> $items
     */
    public static function fromRow(\stdClass $row, array $items = [], ?self $corrected = null): self
    {
        return new self(
            id: (int) $row->id,
            orderId: (int) $row->order_id,
            originalInvoiceId: isset($row->original_invoice_id) ? (int) $row->original_invoice_id : null,
            sourceRefundId: isset($row->source_refund_id) ? (int) $row->source_refund_id : null,
            type: InvoiceType::from($row->type),
            number: $row->number,
            status: InvoiceStatus::from($row->status),
            nipSeller: $row->nip_seller,
            nipBuyer: $row->nip_buyer,
            correctionReason: $row->correction_reason ?? null,
            netTotal: (float) $row->net_total,
            vatTotal: (float) $row->vat_total,
            grossTotal: (float) $row->gross_total,
            currency: $row->currency,
            pdfPath: $row->pdf_path,
            ksefReference: $row->ksef_reference,
            ksefStatus: $row->ksef_status,
            issuedAt: new \DateTimeImmutable($row->issued_at),
            createdAt: new \DateTimeImmutable($row->created_at),
            items: $items,
            buyerName: $row->buyer_name ?? null,
            buyerAddress: $row->buyer_address ?? null,
            buyerCity: $row->buyer_city ?? null,
            buyerPostcode: $row->buyer_postcode ?? null,
            ksefSession: $row->ksef_session ?? null,
            ksefError: $row->ksef_error ?? null,
            ksefEnvironment: $row->ksef_environment ?? null,
            ksefQrUrl: $row->ksef_qr_url ?? null,
            soldAt: ! empty($row->sold_at) ? new \DateTimeImmutable((string) $row->sold_at) : null,
            correctionKind: isset($row->correction_kind) && $row->correction_kind !== '' ? (string) $row->correction_kind : null,
            correctionType: isset($row->correction_type) ? (int) $row->correction_type : null,
            corrected: $corrected,
            ksefOfflineMode: isset($row->ksef_offline_mode) && $row->ksef_offline_mode !== '' ? (string) $row->ksef_offline_mode : null,
            ksefOfflineDeadline: ! empty($row->ksef_offline_deadline) ? new \DateTimeImmutable((string) $row->ksef_offline_deadline, new \DateTimeZone('UTC')) : null,
            ksefCertUrl: $row->ksef_cert_url ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->orderId,
            'original_invoice_id' => $this->originalInvoiceId,
            'source_refund_id' => $this->sourceRefundId,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'nip_seller' => $this->nipSeller,
            'nip_buyer' => $this->nipBuyer,
            'buyer_name' => $this->buyerName,
            'buyer_address' => $this->buyerAddress,
            'buyer_city' => $this->buyerCity,
            'buyer_postcode' => $this->buyerPostcode,
            'correction_reason' => $this->correctionReason,
            'correction_kind' => $this->correctionKind,
            'correction_type' => $this->correctionType,
            'net_total' => $this->netTotal,
            'vat_total' => $this->vatTotal,
            'gross_total' => $this->grossTotal,
            'currency' => $this->currency,
            'pdf_path' => $this->pdfPath,
            'ksef_reference' => $this->ksefReference,
            'ksef_status' => $this->ksefStatus,
            'ksef_error' => $this->ksefError,
            'ksef_environment' => $this->ksefEnvironment,
            'ksef_qr_url' => $this->ksefQrUrl,
            'ksef_offline_mode' => $this->ksefOfflineMode,
            'ksef_offline_deadline' => $this->ksefOfflineDeadline?->format('c'),
            'ksef_cert_url' => $this->ksefCertUrl,
            'issued_at' => $this->issuedAt->format('c'),
            'sold_at' => $this->soldAt?->format('Y-m-d'),
            'created_at' => $this->createdAt->format('c'),
            'items' => array_map(fn (InvoiceItem $i) => $i->toArray(), $this->items),
        ];
    }

    /**
     * KSeF number a buyer may see: only a real number from the production
     * register. Test and demo numbers would send a payer to a document
     * that does not exist for the tax office.
     */
    public function customerKsefNumber(): ?string
    {
        $number = (string) $this->ksefReference;

        return $this->ksefEnvironment === 'production' && \Polski\KSeF\V2\VerificationLink::isKsefNumber($number)
            ? $number
            : null;
    }
}
