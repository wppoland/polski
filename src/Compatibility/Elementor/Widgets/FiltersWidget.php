<?php

declare(strict_types=1);
namespace Polski\Compatibility\Elementor\Widgets;

defined('ABSPATH') || exit;
final class FiltersWidget extends BaseWidget
{
    public function get_name(): string
    {
        return 'polski-ajax-filters';
    }

    public function get_title(): string
    {
        return __('Filtry AJAX', 'polski');
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

        $this->add_control('show_title', [
            'label' => __('Pokaż tytuł', 'polski'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        foreach ([
            'show_categories' => __('Pokaż kategorie', 'polski'),
            'show_brands' => __('Pokaż marki', 'polski'),
            'show_price' => __('Pokaż cenę', 'polski'),
            'show_stock' => __('Pokaż dostępność', 'polski'),
            'show_sale' => __('Pokaż promocje', 'polski'),
            'show_attributes' => __('Pokaż atrybuty', 'polski'),
        ] as $key => $label) {
            $this->add_control($key, [
                'label' => $label,
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]);
        }

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $html = $this->container()->get(\Polski\Service\FilterService::class)->renderFilterForm([
            'title' => (string) ($settings['title'] ?? ''),
            'show_title' => ($settings['show_title'] ?? 'yes') === 'yes',
            'show_categories' => ($settings['show_categories'] ?? 'yes') === 'yes',
            'show_brands' => ($settings['show_brands'] ?? 'yes') === 'yes',
            'show_price' => ($settings['show_price'] ?? 'yes') === 'yes',
            'show_stock' => ($settings['show_stock'] ?? 'yes') === 'yes',
            'show_sale' => ($settings['show_sale'] ?? 'yes') === 'yes',
            'show_attributes' => ($settings['show_attributes'] ?? 'yes') === 'yes',
        ]);

        if ($html !== '') {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
