<?php

declare(strict_types=1);

namespace Spolszczony\Compatibility\Elementor\Widgets;

final class NutriScoreWidget extends BaseProductWidget
{
    public function get_name(): string
    {
        return 'spolszczony-nutri-score';
    }

    public function get_title(): string
    {
        return 'Nutri-Score';
    }

    protected function register_controls(): void
    {
    }

    protected function render(): void
    {
        $product = $this->getProduct();
        if ($product === null) { return; }
        $html = $this->container()->get(\Spolszczony\Service\\FoodService::class)->getNutriScoreHtml($product);

        if ($html !== '') {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
