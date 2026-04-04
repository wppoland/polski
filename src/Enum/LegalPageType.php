<?php

declare(strict_types=1);
namespace Polski\Enum;

defined('ABSPATH') || exit;
enum LegalPageType: string
{
    case Terms = 'terms';
    case Privacy = 'privacy';
    case Returns = 'returns';
    case Complaints = 'complaints';

    public function label(): string
    {
        return match ($this) {
            self::Terms => __('Regulamin sklepu', 'polski'),
            self::Privacy => __('Privacy Policy (Polityka prywatności)', 'polski'),
            self::Returns => __('Return Policy (Prawo odstąpienia)', 'polski'),
            self::Complaints => __('Reklamacje', 'polski'),
        };
    }

    public function optionKey(): string
    {
        return 'polski_' . $this->value . '_page_id';
    }
}
