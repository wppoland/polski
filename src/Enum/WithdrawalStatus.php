<?php

declare(strict_types=1);

namespace Polski\Enum;

enum WithdrawalStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Requested => __('Złożony', 'polski'),
            self::Confirmed => __('Potwierdzony', 'polski'),
            self::Completed => __('Zakończony', 'polski'),
            self::Rejected => __('Odrzucony', 'polski'),
        };
    }
}
