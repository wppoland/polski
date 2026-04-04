<?php

declare(strict_types=1);
namespace Polski\Compatibility\Elementor\Widgets;

defined('ABSPATH') || exit;
final class TaxInfoWidget extends BaseProductWidget
{
    public function get_name(): string
    {
        return 'polski-tax-info';
    }

    public function get_title(): string
    {
        return __('Tax Information', 'polski');
    }

    protected function register_controls(): void
    {
    }

    protected function render(): void
    {
        $product = $this->getProduct();
        if ($product === null) { return; }
        $html = $this->container()->get(\Polski\Service\PriceDisplayService::class)->getVatNoticeHtml($product);

        if ($html !== '') {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
