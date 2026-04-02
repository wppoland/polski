<?php

declare(strict_types=1);

namespace Spolszczony\Enum;

enum PriceType: string
{
    case Regular = 'regular';
    case Sale = 'sale';
    case Promotional = 'promo';
}
