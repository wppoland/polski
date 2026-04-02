<?php

declare(strict_types=1);

namespace Spolszczony\Enum;

enum WithdrawalStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Requested => __('Requested', 'spolszczony'),
            self::Confirmed => __('Confirmed', 'spolszczony'),
            self::Completed => __('Completed', 'spolszczony'),
            self::Rejected => __('Rejected', 'spolszczony'),
        };
    }
}
