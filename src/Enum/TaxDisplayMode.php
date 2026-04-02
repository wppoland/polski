<?php

declare(strict_types=1);

namespace Spolszczony\Enum;

enum TaxDisplayMode: string
{
    case Brutto = 'brutto';
    case Netto = 'netto';

    public function label(): string
    {
        return match ($this) {
            self::Brutto => __('Prices include VAT (brutto)', 'spolszczony'),
            self::Netto => __('Prices exclude VAT (netto)', 'spolszczony'),
        };
    }
}
