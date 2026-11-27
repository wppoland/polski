<?php

declare(strict_types=1);

namespace Polski\KSeF\Model;

enum InvoiceType: string
{
    case FakturaVAT = 'faktura_vat';
    case Proforma = 'proforma';
    case Korygujaca = 'korygujaca';
    case Paragon = 'paragon';
    case PackingSlip = 'packing_slip';

    public function label(): string
    {
        return match ($this) {
            self::FakturaVAT => __('VAT invoice', 'polski'),
            self::Proforma => __('Proforma invoice', 'polski'),
            self::Korygujaca => __('Corrective invoice', 'polski'),
            self::Paragon => __('Paragon', 'polski'),
            self::PackingSlip => __('Packing Slip', 'polski'),
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::FakturaVAT => 'FV',
            self::Proforma => 'PF',
            self::Korygujaca => 'FK',
            self::Paragon => 'PA',
            self::PackingSlip => 'WZ',
        };
    }

    /**
     * A proforma is not an accounting document: it must never be booked,
     * paid-marked or pushed to KSeF/JPK. Callers gate on this.
     */
    public function isAccountingDocument(): bool
    {
        return $this === self::FakturaVAT || $this === self::Korygujaca;
    }
}
