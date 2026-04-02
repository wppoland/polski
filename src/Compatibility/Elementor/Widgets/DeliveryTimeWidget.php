<?php

declare(strict_types=1);

namespace Spolszczony\Compatibility\Elementor\Widgets;

final class DeliveryTimeWidget extends BaseProductWidget
{
    public function get_name(): string
    {
        return 'spolszczony-delivery-time';
    }

    public function get_title(): string
    {
        return 'Czas dostawy';
    }

    protected function register_controls(): void
    {
    }

    protected function render(): void
    {
        $product = $this->getProduct();
        if ($product === null) { return; }
        $html = $this->container()->get(\Spolszczony\Service\\DeliveryTimeService::class)->getDeliveryTimeHtml($product);

        if ($html !== '') {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
