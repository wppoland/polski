<?php

declare(strict_types=1);
namespace Polski\Compatibility\Elementor\Widgets;

defined('ABSPATH') || exit;
final class SearchWidget extends BaseWidget
{
    public function get_name(): string
    {
        return 'polski-ajax-search';
    }

    public function get_title(): string
    {
        return __('AJAX Search', 'polski');
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', [
            'label' => __('Settings', 'polski'),
        ]);

        $this->add_control('placeholder', [
            'label' => __('Placeholder', 'polski'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => '',
        ]);

        $this->add_control('show_submit_button', [
            'label' => __('Show search button', 'polski'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('submit_button_text', [
            'label' => __('Button text', 'polski'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => '',
            'condition' => [
                'show_submit_button' => 'yes',
            ],
        ]);

        $this->add_control('limit', [
            'label' => __('Result limit', 'polski'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 6,
            'min' => 1,
            'max' => 50,
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $html = $this->container()->get(\Polski\Service\SearchService::class)->renderSearchForm([
            'placeholder' => (string) ($settings['placeholder'] ?? ''),
            'show_submit_button' => ($settings['show_submit_button'] ?? 'yes') === 'yes',
            'submit_button_text' => (string) ($settings['submit_button_text'] ?? ''),
            'limit' => (int) ($settings['limit'] ?? 6),
        ]);

        if ($html !== '') {
            echo wp_kses_post($html);
        }
    }
}
