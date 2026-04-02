<?php

declare(strict_types=1);

namespace Spolszczony\Compatibility\Elementor\Widgets;

final class TaxInfoWidget extends BaseProductWidget
{
    public function get_name(): string
    {
        return 'spolszczony-tax-info';
    }

    public function get_title(): string
    {
        return 'Informacja o VAT';
    }

    protected function register_controls(): void
    {
    }

    protected function render(): void
    {
        $product = $this->getProduct();
        if ($product === null) { return; }
        $html = $this->container()->get(\Spolszczony\Service\\PriceDisplayService::class)->getVatNoticeHtml($product);

        if ($html !== '') {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
