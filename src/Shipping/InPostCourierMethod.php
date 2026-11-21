<?php

declare(strict_types=1);
namespace Polski\Shipping;

defined('ABSPATH') || exit;

// existing-checked: src/Shipping/InPostShippingMethod.php - WooCommerce maps one class per method id,
// so this subclass only carries the id and default title.

final class InPostCourierMethod extends InPostShippingMethod
{
    /** @var string */
    public $id = \Polski\Service\InPostShippingService::COURIER_METHOD;

    protected function defaultTitle(): string
    {
        return __('InPost Courier', 'polski');
    }
}
