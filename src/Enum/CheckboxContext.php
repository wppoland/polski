<?php

declare(strict_types=1);
namespace Polski\Enum;

defined('ABSPATH') || exit;
enum CheckboxContext: string
{
    case Checkout = 'checkout';
    case Registration = 'registration';
    case Review = 'review';
    case PayForOrder = 'pay_for_order';
}
