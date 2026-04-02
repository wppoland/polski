<?php

declare(strict_types=1);

namespace Spolszczony\Compatibility\Elementor\Widgets;

final class FoodInfoWidget extends BaseProductWidget
{
    public function get_name(): string
    {
        return 'spolszczony-food-info';
    }

    public function get_title(): string
    {
        return 'Informacje o żywności';
    }

    protected function register_controls(): void
    {
    }

    protected function render(): void
    {
        $product = $this->getProduct();
        if ($product === null) { return; }
        $html = $this->container()->get(\Spolszczony\Service\\FoodService::class)->getFoodInfoHtml($product);

        if ($html !== '') {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
