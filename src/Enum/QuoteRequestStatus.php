<?php

declare(strict_types=1);

namespace Spolszczony\Enum;

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
            self::New => __('New', 'spolszczony'),
            self::Contacted => __('Contacted', 'spolszczony'),
            self::Quoted => __('Quoted', 'spolszczony'),
            self::Won => __('Won', 'spolszczony'),
            self::Lost => __('Lost', 'spolszczony'),
            self::Archived => __('Archived', 'spolszczony'),
        };
    }
}
