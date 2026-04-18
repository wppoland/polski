<?php

declare(strict_types=1);
namespace Polski\Compatibility\Elementor\Widgets;

defined('ABSPATH') || exit;
final class PowerSupplyWidget extends BaseProductWidget
{
    public function get_name(): string
    {
        return 'polski-power-supply';
    }

    public function get_title(): string
    {
        return __('Power Supply', 'polski');
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
            echo wp_kses_post($html);
        }
    }
}
