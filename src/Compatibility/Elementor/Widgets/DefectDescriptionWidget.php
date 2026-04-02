<?php

declare(strict_types=1);

namespace Spolszczony\Compatibility\Elementor\Widgets;

final class DefectDescriptionWidget extends BaseProductWidget
{
    public function get_name(): string
    {
        return 'spolszczony-defect-description';
    }

    public function get_title(): string
    {
        return 'Opis wady';
    }

    protected function register_controls(): void
    {
    }

    protected function render(): void
    {
        $product = $this->getProduct();
        if ($product === null) { return; }
        $html = $this->container()->get(\Spolszczony\Service\\ProductInfoService::class)->getDefectDescriptionHtml($product);

        if ($html !== '') {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
