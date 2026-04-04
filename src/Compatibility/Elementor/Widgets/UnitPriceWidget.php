<?php

declare(strict_types=1);
namespace Polski\Compatibility\Elementor\Widgets;

defined('ABSPATH') || exit;
final class UnitPriceWidget extends BaseProductWidget
{
    public function get_name(): string
    {
        return 'polski-unit-price';
    }

    public function get_title(): string
    {
        return __('Cena jednostkowa', 'polski');
    }

    protected function register_controls(): void
    {
    }

    protected function render(): void
    {
        $product = $this->getProduct();
        if ($product === null) { return; }
        $html = $this->container()->get(\Polski\Service\PriceDisplayService::class)->getUnitPriceHtml($product);

        if ($html !== '') {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
