<?php

declare(strict_types=1);

/**
 * PHPStan-only stubs for Elementor (plugin is optional at runtime; not Composer-installed).
 * Loaded via .phpstan.neon bootstrapFiles - never autoloaded in WordPress.
 *
 * @see https://developers.elementor.com/docs/widgets/
 */
namespace Elementor;

/**
 * Category registration (Elementor\Elements_Manager).
 */
class Elements_Manager
{
    /**
     * @param array<string, mixed> $args
     */
    public function add_category(string $slug, array $args): void
    {
    }
}

/**
 * Widget registration (Elementor\Widgets_Manager).
 */
class Widgets_Manager
{
    public function register(object $widget): void
    {
    }
}

/**
 * Minimal API surface used by Polski\Compatibility\Elementor\Widgets\*.
 */
abstract class Widget_Base
{
    protected function start_controls_section(string $section_id, array $args = [], array $options = []): void
    {
    }

    protected function end_controls_section(): void
    {
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $options
     */
    protected function add_control(string $id, array $args, array $options = []): void
    {
    }

    /**
     * @return array<string, mixed>|mixed
     */
    protected function get_settings_for_display(?string $key = null): mixed
    {
        return $key === null ? [] : null;
    }
}

/**
 * Control type constants referenced in widget register_controls().
 */
final class Controls_Manager
{
    public const TEXT = 'text';

    public const SELECT = 'select';

    public const NUMBER = 'number';

    public const SWITCHER = 'switcher';
}
