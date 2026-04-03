<?php

declare(strict_types=1);

namespace Polski\Shopmark;

enum Location: string
{
    case SingleProduct = 'single_product';
    case Loop = 'loop';
    case Cart = 'cart';
    case Checkout = 'checkout';
    case MiniCart = 'mini_cart';
}
