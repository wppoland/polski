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
            self::Brutto => __('Ceny zawierają VAT (brutto)', 'polski'),
            self::Netto => __('Ceny nie zawierają VAT (netto)', 'polski'),
        };
    }
}
