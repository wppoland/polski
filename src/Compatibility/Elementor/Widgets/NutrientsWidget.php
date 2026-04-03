<?php

declare(strict_types=1);

namespace Polski\Compatibility\Elementor\Widgets;

final class NutrientsWidget extends BaseProductWidget
{
    public function get_name(): string
    {
        return 'polski-nutrients';
    }

    public function get_title(): string
    {
        return 'Wartości odżywcze';
    }

    protected function register_controls(): void
    {
    }

    protected function render(): void
    {
        $product = $this->getProduct();
        if ($product === null) { return; }
        $html = $this->container()->get(\Polski\Service\FoodService::class)->getNutrientsHtml($product);

        if ($html !== '') {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
