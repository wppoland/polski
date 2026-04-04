<?php

declare(strict_types=1);
namespace Polski\Compatibility\Elementor\Widgets;

defined('ABSPATH') || exit;
final class ShippingNoticeWidget extends BaseProductWidget
{
    public function get_name(): string
    {
        return 'polski-shipping-notice';
    }

    public function get_title(): string
    {
        return __('Koszty wysyłki', 'polski');
    }

    protected function register_controls(): void
    {
    }

    protected function render(): void
    {
        $html = $this->container()->get(\Polski\Service\PriceDisplayService::class)->getShippingNoticeHtml();

        if ($html !== '') {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
