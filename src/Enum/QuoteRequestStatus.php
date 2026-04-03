<?php

declare(strict_types=1);

namespace Polski\Enum;

enum QuoteRequestStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Quoted = 'quoted';
    case Won = 'won';
    case Lost = 'lost';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New', 'polski'),
            self::Contacted => __('Contacted', 'polski'),
            self::Quoted => __('Quoted', 'polski'),
            self::Won => __('Won', 'polski'),
            self::Lost => __('Lost', 'polski'),
            self::Archived => __('Archived', 'polski'),
        };
    }
}
