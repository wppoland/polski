<?php

declare(strict_types=1);

namespace Polski\CRA\Enum;

defined('ABSPATH') || exit;

enum Severity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::Critical => __('Critical', 'polski'),
            self::High => __('High', 'polski'),
            self::Medium => __('Medium', 'polski'),
            self::Low => __('Low', 'polski'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Critical => '#d63638',
            self::High => '#dba617',
            self::Medium => '#2271b1',
            self::Low => '#646970',
        };
    }
}
