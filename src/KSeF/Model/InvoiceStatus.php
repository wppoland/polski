<?php

declare(strict_types=1);

namespace Polski\KSeF\Model;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Sent = 'sent';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft', 'polski'),
            self::Issued => __('Issued', 'polski'),
            self::Sent => __('Sent', 'polski'),
            self::Paid => __('Paid', 'polski'),
            self::Cancelled => __('Cancelled', 'polski'),
        };
    }
}
