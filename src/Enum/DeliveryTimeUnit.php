<?php

declare(strict_types=1);
namespace Polski\Enum;

defined('ABSPATH') || exit;
enum DeliveryTimeUnit: string
{
    case Days = 'days';
    case BusinessDays = 'business_days';
    case Weeks = 'weeks';

    public function label(int $count): string
    {
        /* translators: %d: delivery time count in days */
        $daysLabel = _n('%d day', '%d days', $count, 'polski');
        /* translators: %d: delivery time count in business days */
        $businessDaysLabel = _n('%d business day', '%d business days', $count, 'polski');
        /* translators: %d: delivery time count in weeks */
        $weeksLabel = _n('%d week', '%d weeks', $count, 'polski');

        return match ($this) {
            self::Days => sprintf(
                $daysLabel,
                $count,
            ),
            self::BusinessDays => sprintf(
                $businessDaysLabel,
                $count,
            ),
            self::Weeks => sprintf(
                $weeksLabel,
                $count,
            ),
        };
    }
}
