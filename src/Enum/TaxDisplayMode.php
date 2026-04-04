<?php

declare(strict_types=1);
namespace Polski\Enum;

defined('ABSPATH') || exit;
enum TaxDisplayMode: string
{
    case Brutto = 'brutto';
    case Netto = 'netto';

    public function label(): string
    {
        return match ($this) {
            self::Brutto => __('Prices include VAT (gross)', 'polski'),
            self::Netto => __('Prices exclude VAT (net)', 'polski'),
        };
    }
}
