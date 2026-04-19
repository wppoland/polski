<?php

declare(strict_types=1);

namespace Polski\CRA\Enum;

defined('ABSPATH') || exit;

enum IncidentStatus: string
{
    case Open = 'open';
    case Notified = 'notified';
    case UnderInvestigation = 'investigating';
    case Resolved = 'resolved';
    case FalsePositive = 'false_positive';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open', 'polski'),
            self::Notified => __('Notified to authorities', 'polski'),
            self::UnderInvestigation => __('Under investigation', 'polski'),
            self::Resolved => __('Resolved', 'polski'),
            self::FalsePositive => __('False positive', 'polski'),
        };
    }
}
