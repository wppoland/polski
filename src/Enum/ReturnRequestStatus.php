<?php

declare(strict_types=1);

namespace Polski\Enum;

defined('ABSPATH') || exit;

enum ReturnRequestStatus: string
{
    case Submitted = 'submitted';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => __('Submitted', 'polski'),
            self::InProgress => __('In progress', 'polski'),
            self::Resolved => __('Resolved', 'polski'),
            self::Rejected => __('Rejected', 'polski'),
        };
    }
}
