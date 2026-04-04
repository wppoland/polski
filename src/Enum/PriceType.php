<?php

declare(strict_types=1);
namespace Polski\Enum;

defined('ABSPATH') || exit;
enum PriceType: string
{
    case Regular = 'regular';
    case Sale = 'sale';
    case Promotional = 'promo';
}
