<?php

declare(strict_types=1);
namespace Polski\Enum;

defined('ABSPATH') || exit;
enum WithdrawalStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Requested => __('Submitted', 'polski'),
            self::Confirmed => __('Confirmed', 'polski'),
            self::Completed => __('Completed', 'polski'),
            self::Rejected => __('Rejected', 'polski'),
        };
    }
}
