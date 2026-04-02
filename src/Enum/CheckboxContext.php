<?php

declare(strict_types=1);

namespace Spolszczony\Enum;

enum CheckboxContext: string
{
    case Checkout = 'checkout';
    case Registration = 'registration';
    case Review = 'review';
    case PayForOrder = 'pay_for_order';
    case Quote = 'quote';
}
