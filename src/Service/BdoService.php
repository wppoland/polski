<?php

declare(strict_types=1);

namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * Display the shop's BDO registration number (Baza Danych o Odpadach) as a
 * shortcode or dynamic Gutenberg block.
 *
 * Businesses that introduce products or packaging onto the Polish market are
 * registered in the BDO and commonly present their BDO number on the website,
 * in the footer or on documents. This module reads the number entered in the
 * Polski settings (`polski_general`) and renders it. It does not file BDO
 * reports or determine whether a given business must register, it only displays
 * the number the merchant provides.
 */
final class BdoService implements HasHooks
{
    private const SHORTCODE = 'polski_bdo';
    private const BLOCK = 'polski/bdo';

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('bdo')) {
            return;
        }

        add_shortcode(self::SHORTCODE, [$this, 'renderShortcode']);
        add_action('init', [$this, 'registerBlock']);
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function renderShortcode(array|string $atts = [], ?string $content = null, string $shortcodeTag = ''): string
    {
        $atts = shortcode_atts([
            'show_label' => '1',
            'label' => '',
        ], is_array($atts) ? $atts : [], $shortcodeTag);

        return $this->render($atts);
    }

    public function registerBlock(): void
    {
        if (! function_exists('register_block_type')) {
            return;
        }

        register_block_type(self::BLOCK, [
            'title' => __('BDO number', 'polski'),
            'description' => __('Displays the shop\'s BDO registration number from the Polski settings.', 'polski'),
            'category' => 'widgets',
            'icon' => 'id',
            'supports' => [
                'html' => false,
                'align' => ['wide', 'full'],
            ],
            'attributes' => [
                'show_label' => ['type' => 'boolean', 'default' => true],
                'label' => ['type' => 'string', 'default' => ''],
            ],
            'render_callback' => [$this, 'renderBlock'],
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function renderBlock(array $attributes = []): string
    {
        return $this->render([
            'show_label' => ! empty($attributes['show_label']) ? '1' : '0',
            'label' => (string) ($attributes['label'] ?? ''),
        ]);
    }

    /**
     * @param array<string, string> $atts
     */
    public function render(array $atts): string
    {
        $number = $this->loadNumber();

        if ($number === '') {
            return '';
        }

        $showLabel = ! empty($atts['show_label']);
        $label = trim((string) ($atts['label'] ?? ''));

        if ($label === '') {
            $label = __('BDO:', 'polski');
        }

        return sprintf(
            '<span class="polski-bdo">%s<span class="polski-bdo__number">%s</span></span>',
            $showLabel ? '<span class="polski-bdo__label">' . esc_html($label) . '</span> ' : '',
            esc_html($number),
        );
    }

    private function loadNumber(): string
    {
        $general = get_option('polski_general', []);

        if (! is_array($general)) {
            return '';
        }

        return trim((string) ($general['bdo_number'] ?? ''));
    }
}
