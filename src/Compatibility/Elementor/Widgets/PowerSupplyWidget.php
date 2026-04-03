<?php

declare(strict_types=1);

namespace Polski\Compatibility\Elementor\Widgets;

final class PowerSupplyWidget extends BaseProductWidget
{
    public function get_name(): string
    {
        return 'polski-power-supply';
    }

    public function get_title(): string
    {
        return 'Zasilanie';
    }

    protected function register_controls(): void
    {
    }

    protected function render(): void
    {
        $product = $this->getProduct();
        if ($product === null) { return; }
        $html = $this->container()->get(\Polski\Service\ProductInfoService::class)->getPowerSupplyHtml($product);

        if ($html !== '') {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
