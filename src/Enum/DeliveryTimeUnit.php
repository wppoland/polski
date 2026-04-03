<?php

declare(strict_types=1);

namespace Polski\Enum;

enum DeliveryTimeUnit: string
{
    case Days = 'days';
    case BusinessDays = 'business_days';
    case Weeks = 'weeks';

    public function label(int $count): string
    {
        return match ($this) {
            self::Days => sprintf(
                _n('%d day', '%d days', $count, 'polski'),
                $count,
            ),
            self::BusinessDays => sprintf(
                _n('%d business day', '%d business days', $count, 'polski'),
                $count,
            ),
            self::Weeks => sprintf(
                _n('%d week', '%d weeks', $count, 'polski'),
                $count,
            ),
        };
    }
}
