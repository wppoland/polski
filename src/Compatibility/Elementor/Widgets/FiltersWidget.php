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
        return __('AJAX Filters', 'polski');
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

        $this->add_control('preset', [
            'label' => __('Preset slug', 'polski'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'description' => __('Optional named preset from AJAX Filters settings, e.g. fashion', 'polski'),
            'default' => '',
        ]);

        $this->add_control('show_title', [
            'label' => __('Show title', 'polski'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('attribute_taxonomies', [
            'label' => __('Attribute taxonomies', 'polski'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'description' => __('Comma-separated list, e.g. pa_color,pa_size', 'polski'),
            'default' => '',
        ]);

        foreach ([
            'show_categories' => __('Show categories', 'polski'),
            'show_brands' => __('Show brands', 'polski'),
            'show_price' => __('Show price', 'polski'),
            'show_stock' => __('Show availability', 'polski'),
            'show_sale' => __('Show on-sale', 'polski'),
            'show_attributes' => __('Show attributes', 'polski'),
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
        /** @var \Polski\Service\FilterService $service */
        $service = $this->container()->get(\Polski\Service\FilterService::class);
        $preset = sanitize_key((string) ($settings['preset'] ?? ''));
        $overrides = $preset !== '' ? $service->getPreset($preset) : [];

        $html = $service->renderFilterForm(array_merge($overrides, [
            'title' => (string) ($settings['title'] ?? ''),
            'show_title' => ($settings['show_title'] ?? 'yes') === 'yes',
            'attribute_taxonomies' => sanitize_text_field((string) ($settings['attribute_taxonomies'] ?? '')),
            'show_categories' => ($settings['show_categories'] ?? 'yes') === 'yes',
            'show_brands' => ($settings['show_brands'] ?? 'yes') === 'yes',
            'show_price' => ($settings['show_price'] ?? 'yes') === 'yes',
            'show_stock' => ($settings['show_stock'] ?? 'yes') === 'yes',
            'show_sale' => ($settings['show_sale'] ?? 'yes') === 'yes',
            'show_attributes' => ($settings['show_attributes'] ?? 'yes') === 'yes',
        ]));

        if ($html !== '') {
            echo wp_kses_post($html);
        }
    }
}
