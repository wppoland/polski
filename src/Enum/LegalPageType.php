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
            self::Terms => __('Terms and Conditions', 'polski'),
            self::Privacy => __('Privacy Policy', 'polski'),
            self::Returns => __('Return Policy', 'polski'),
            self::Complaints => __('Complaints', 'polski'),
        };
    }

    public function optionKey(): string
    {
        return 'polski_' . $this->value . '_page_id';
    }
}
