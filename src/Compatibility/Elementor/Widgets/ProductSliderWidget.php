<?php

declare(strict_types=1);
namespace Polski\Compatibility\Elementor\Widgets;

defined('ABSPATH') || exit;
final class ProductSliderWidget extends BaseWidget
{
    public function get_name(): string
    {
        return 'polski-product-slider';
    }

    public function get_title(): string
    {
        return __('Product Slider', 'polski');
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', [
            'label' => __('Settings', 'polski'),
        ]);

        $this->add_control('title', [
            'label' => __('Title', 'polski'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => '',
        ]);

        $this->add_control('source', [
            'label' => __('Product source', 'polski'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'related',
            'options' => [
                'related' => __('Related products', 'polski'),
                'upsell' => __('Upsells', 'polski'),
                'sale' => __('On Sale', 'polski'),
                'featured' => __('Featured', 'polski'),
            ],
        ]);

        $this->add_control('product_id', [
            'label' => __('Product ID', 'polski'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 0,
            'min' => 0,
            'description' => __('Optional: manually set ID for related/upsell sources outside product details page.', 'polski'),
        ]);

        $this->add_control('limit', [
            'label' => __('Product count', 'polski'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 8,
            'min' => 1,
            'max' => 12,
        ]);

        $this->add_control('show_title', [
            'label' => __('Show title', 'polski'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_price', [
            'label' => __('Show price', 'polski'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_add_to_cart', [
            'label' => __('Show add to cart button', 'polski'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $html = $this->container()->get(\Polski\Service\ProductSliderService::class)->renderSlider([
            'title' => (string) ($settings['title'] ?? ''),
            'source' => sanitize_key((string) ($settings['source'] ?? 'related')),
            'product_id' => (int) ($settings['product_id'] ?? 0),
            'limit' => (int) ($settings['limit'] ?? 8),
            'show_title' => ($settings['show_title'] ?? 'yes') === 'yes',
            'show_price' => ($settings['show_price'] ?? 'yes') === 'yes',
            'show_add_to_cart' => ($settings['show_add_to_cart'] ?? 'yes') === 'yes',
        ]);

        if ($html !== '') {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
