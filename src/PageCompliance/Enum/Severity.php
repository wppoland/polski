<?php

declare(strict_types=1);

namespace Polski\PageCompliance\Enum;

defined('ABSPATH') || exit;

enum Severity: string
{
    case Required = 'required';
    case Recommended = 'recommended';
    case Optional = 'optional';

    public function label(): string
    {
        return match ($this) {
            self::Required => __('Required', 'polski'),
            self::Recommended => __('Recommended', 'polski'),
            self::Optional => __('Optional', 'polski'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Required => '#d63638',
            self::Recommended => '#dba617',
            self::Optional => '#646970',
        };
    }
}
