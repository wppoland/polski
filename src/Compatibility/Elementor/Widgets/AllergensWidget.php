<?php

declare(strict_types=1);

namespace Polski\Compatibility\Elementor\Widgets;

final class AllergensWidget extends BaseProductWidget
{
    public function get_name(): string
    {
        return 'polski-allergens';
    }

    public function get_title(): string
    {
        return 'Alergeny';
    }

    protected function register_controls(): void
    {
    }

    protected function render(): void
    {
        $product = $this->getProduct();
        if ($product === null) { return; }
        $html = $this->container()->get(\Polski\Service\FoodService::class)->getAllergensHtml($product);

        if ($html !== '') {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
