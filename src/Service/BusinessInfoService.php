<?php

declare(strict_types=1);

namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * Render the shop's business identification (name, address, NIP, REGON,
 * email, phone) as a footer block, shortcode or dynamic Gutenberg block.
 *
 * Polish consumer law (Art. 12 Ustawy o prawach konsumenta + Art. 206
 * KSH) requires e-commerce sellers to display business identification
 * in the footer of the shop. This module centralises that output and
 * reads the data from `polski_general` set by the setup wizard.
 */
final class BusinessInfoService implements HasHooks
{
    private const SHORTCODE = 'polski_business_info';

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('business_info')) {
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
            'format' => 'block',
            'separator' => ' | ',
            'show_label' => '1',
            'show_regon' => '0',
            'show_bdo' => '0',
        ], is_array($atts) ? $atts : [], $shortcodeTag);

        return $this->render($atts);
    }

    public function registerBlock(): void
    {
        if (! function_exists('register_block_type')) {
            return;
        }

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/business-info', [
            'title' => __('Business identification', 'polski'),
            'description' => __('Displays the shop\'s business data (name, NIP, address, contact) from the setup wizard.', 'polski'),
            'render_callback' => [$this, 'renderBlock'],
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function renderBlock(array $attributes = []): string
    {
        return $this->render([
            'format' => (string) ($attributes['format'] ?? 'block'),
            'separator' => (string) ($attributes['separator'] ?? ' | '),
            'show_label' => ! empty($attributes['show_label']) ? '1' : '0',
            'show_regon' => ! empty($attributes['show_regon']) ? '1' : '0',
            'show_bdo' => ! empty($attributes['show_bdo']) ? '1' : '0',
        ]);
    }

    /**
     * @param array<string, string> $atts
     */
    public function render(array $atts): string
    {
        $info = $this->loadInfo();

        if ($info === []) {
            return '';
        }

        $format = $atts['format'] ?? 'block';
        $separator = (string) ($atts['separator'] ?? ' | ');
        $showLabel = ! empty($atts['show_label']) && $atts['show_label'] !== '0';
        $showRegon = ! empty($atts['show_regon']) && $atts['show_regon'] !== '0';
        $showBdo = ! empty($atts['show_bdo']);

        $lines = [];

        if (! empty($info['name'])) {
            $lines['name'] = $info['name'];
        }
        if (! empty($info['address'])) {
            $lines['address'] = $info['address'];
        }
        if (! empty($info['nip'])) {
            $lines['nip'] = ($showLabel ? __('NIP:', 'polski') . ' ' : '') . $info['nip'];
        }
        if ($showRegon && ! empty($info['regon'])) {
            $lines['regon'] = ($showLabel ? __('REGON:', 'polski') . ' ' : '') . $info['regon'];
        }
        if ($showBdo && ! empty($info['bdo'])) {
            $lines['bdo'] = ($showLabel ? __('BDO:', 'polski') . ' ' : '') . $info['bdo'];
        }
        if (! empty($info['email'])) {
            $lines['email'] = $this->mailtoLink($info['email']);
        }
        if (! empty($info['phone'])) {
            $lines['phone'] = esc_html($info['phone']);
        }

        if ($lines === []) {
            return '';
        }

        if ($format === 'inline') {
            return sprintf(
                '<span class="polski-business-info polski-business-info--inline">%s</span>',
                implode(esc_html($separator), array_map(
                    static fn (string $line): string => wp_kses_post($line),
                    $lines,
                )),
            );
        }

        $html = '<div class="polski-business-info polski-business-info--block">';
        foreach ($lines as $key => $value) {
            $html .= sprintf(
                '<div class="polski-business-info__line polski-business-info__line--%s">%s</div>',
                esc_attr($key),
                wp_kses_post($value),
            );
        }
        $html .= '</div>';

        return $html;
    }

    private function mailtoLink(string $email): string
    {
        $sanitized = sanitize_email($email);

        if ($sanitized === '') {
            return '';
        }

        return sprintf(
            '<a href="mailto:%s">%s</a>',
            esc_attr(antispambot($sanitized)),
            esc_html(antispambot($sanitized)),
        );
    }

    /**
     * @return array<string, string>
     */
    private function loadInfo(): array
    {
        $general = get_option('polski_general', []);

        if (! is_array($general)) {
            return [];
        }

        return [
            'name' => trim((string) ($general['company_name'] ?? '')),
            'address' => trim((string) ($general['company_address'] ?? '')),
            'nip' => trim((string) ($general['company_nip'] ?? '')),
            'regon' => trim((string) ($general['company_regon'] ?? '')),
            'email' => trim((string) ($general['company_email'] ?? '')),
            'phone' => trim((string) ($general['company_phone'] ?? '')),
            'bdo' => trim((string) ($general['bdo_number'] ?? '')),
        ];
    }
}
