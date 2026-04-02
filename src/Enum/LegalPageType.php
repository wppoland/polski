<?php

declare(strict_types=1);

namespace Spolszczony\Enum;

enum LegalPageType: string
{
    case Terms = 'terms';
    case Privacy = 'privacy';
    case Returns = 'returns';
    case Complaints = 'complaints';

    public function label(): string
    {
        return match ($this) {
            self::Terms => __('Terms and Conditions (Regulamin)', 'spolszczony'),
            self::Privacy => __('Privacy Policy (Polityka prywatności)', 'spolszczony'),
            self::Returns => __('Return Policy (Prawo odstąpienia)', 'spolszczony'),
            self::Complaints => __('Complaints (Reklamacje)', 'spolszczony'),
        };
    }

    public function optionKey(): string
    {
        return 'spolszczony_' . $this->value . '_page_id';
    }
}
