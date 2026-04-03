<?php

declare(strict_types=1);

namespace Polski\Compatibility\Elementor\Widgets;

final class OmnibusPriceWidget extends BaseProductWidget
{
    public function get_name(): string
    {
        return 'polski-omnibus-price';
    }

    public function get_title(): string
    {
        return 'Najniższa cena (Omnibus)';
    }

    protected function register_controls(): void
    {
    }

    protected function render(): void
    {
        $product = $this->getProduct();
        if ($product === null) { return; }
        $html = $this->container()->get(\Polski\Service\PriceDisplayService::class)->getOmnibusPriceHtml($product);

        if ($html !== '') {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
