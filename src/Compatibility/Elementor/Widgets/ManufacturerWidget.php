<?php

declare(strict_types=1);

namespace Polski\Compatibility\Elementor\Widgets;

final class ManufacturerWidget extends BaseProductWidget
{
    public function get_name(): string
    {
        return 'polski-manufacturer';
    }

    public function get_title(): string
    {
        return 'Producent';
    }

    protected function register_controls(): void
    {
    }

    protected function render(): void
    {
        $product = $this->getProduct();
        if ($product === null) { return; }
        $html = $this->container()->get(\Polski\Service\ProductInfoService::class)->getManufacturerHtml($product);

        if ($html !== '') {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
