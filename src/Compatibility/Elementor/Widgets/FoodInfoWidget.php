<?php

declare(strict_types=1);
namespace Polski\Compatibility\Elementor\Widgets;

defined('ABSPATH') || exit;
final class FoodInfoWidget extends BaseProductWidget
{
    public function get_name(): string
    {
        return 'polski-food-info';
    }

    public function get_title(): string
    {
        return __('Food Information', 'polski');
    }

    protected function register_controls(): void
    {
    }

    protected function render(): void
    {
        $product = $this->getProduct();
        if ($product === null) { return; }
        $html = $this->container()->get(\Polski\Service\FoodService::class)->getFoodInfoHtml($product);

        if ($html !== '') {
            echo wp_kses_post($html);
        }
    }
}
