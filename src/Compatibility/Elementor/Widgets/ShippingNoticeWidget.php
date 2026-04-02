<?php

declare(strict_types=1);

namespace Spolszczony\Compatibility\Elementor\Widgets;

final class ShippingNoticeWidget extends BaseProductWidget
{
    public function get_name(): string
    {
        return 'spolszczony-shipping-notice';
    }

    public function get_title(): string
    {
        return 'Koszty wysyłki';
    }

    protected function register_controls(): void
    {
    }

    protected function render(): void
    {
        $html = $this->container()->get(\Spolszczony\Service\\PriceDisplayService::class)->getShippingNoticeHtml();

        if ($html !== '') {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
