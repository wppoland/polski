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
        return __('Slider produktów', 'polski');
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', [
            'label' => __('Ustawienia', 'polski'),
        ]);

        $this->add_control('title', [
            'label' => __('Tytuł', 'polski'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => '',
        ]);

        $this->add_control('source', [
            'label' => __('Źródło produktów', 'polski'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'related',
            'options' => [
                'related' => __('Powiązane', 'polski'),
                'upsell' => __('Upsell', 'polski'),
                'sale' => __('Promocje', 'polski'),
                'featured' => __('Wyróżnione', 'polski'),
            ],
        ]);

        $this->add_control('product_id', [
            'label' => __('ID produktu', 'polski'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 0,
            'min' => 0,
            'description' => __('Opcjonalnie, ustaw ręcznie dla źródeł powiązanych lub upsell poza kartą produktu.', 'polski'),
        ]);

        $this->add_control('limit', [
            'label' => __('Liczba produktów', 'polski'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 8,
            'min' => 1,
            'max' => 12,
        ]);

        $this->add_control('show_title', [
            'label' => __('Pokaż tytuł', 'polski'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_price', [
            'label' => __('Pokaż cenę', 'polski'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_add_to_cart', [
            'label' => __('Pokaż przycisk koszyka', 'polski'),
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
