<?php

declare(strict_types=1);

namespace Spolszczony\Compatibility\Elementor\Widgets;

final class SafetyDocsWidget extends BaseProductWidget
{
    public function get_name(): string
    {
        return 'spolszczony-safety-docs';
    }

    public function get_title(): string
    {
        return 'Dokumenty bezpieczeństwa';
    }

    protected function register_controls(): void
    {
    }

    protected function render(): void
    {
        $product = $this->getProduct();
        if ($product === null) { return; }
        $html = $this->container()->get(\Spolszczony\Service\\ProductInfoService::class)->getSafetyDocumentsHtml($product);

        if ($html !== '') {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
