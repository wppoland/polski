<?php

declare(strict_types=1);
namespace Polski\Compatibility\Elementor\Widgets;

defined('ABSPATH') || exit;

use Elementor\Widget_Base;

abstract class BaseWidget extends Widget_Base
{
    /**
     * @return list<string>
     */
    public function get_categories(): array
    {
        return ['polski'];
    }

    /**
     * Elementor enqueues these when the widget renders, so frontend.css follows the widget to any page.
     *
     * @return list<string>
     */
    public function get_style_depends(): array
    {
        return ['polski-frontend'];
    }

    public function get_icon(): string
    {
        return 'eicon-woocommerce';
    }

    /**
     * Get the Polski DI container.
     */
    protected function container(): \Polski\Container
    {
        return \Polski\Plugin::instance()->container();
    }
}
