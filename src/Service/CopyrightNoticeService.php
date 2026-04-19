<?php

declare(strict_types=1);

namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * Copyright / license notice helpers.
 *
 * Provides three reusable outputs:
 *   - [polski_copyright year="" owner="" license=""]  — a standard copyright line
 *   - [polski_image_credit image_id="" credit="" source=""] — per-image credit footer
 *   - Gutenberg block `polski/copyright` (dynamic)
 *
 * Defaults:
 *   - year: current year (UTC)
 *   - owner: `polski_general.company_name` or site title
 *   - license: empty (appended as " - License: <value>" when present)
 */
final class CopyrightNoticeService implements HasHooks
{
    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('copyright_notice')) {
            return;
        }

        add_shortcode('polski_copyright', [$this, 'renderCopyright']);
        add_shortcode('polski_image_credit', [$this, 'renderImageCredit']);
        add_action('init', [$this, 'registerBlock']);
    }

    public function registerBlock(): void
    {
        if (! function_exists('register_block_type')) {
            return;
        }

        register_block_type('polski/copyright', [
            'title' => __('Copyright notice', 'polski'),
            'description' => __('A standard copyright line (© YYYY Owner).', 'polski'),
            'category' => 'widgets',
            'icon' => 'shield',
            'supports' => ['html' => false, 'align' => ['wide', 'full']],
            'attributes' => [
                'owner' => ['type' => 'string', 'default' => ''],
                'year' => ['type' => 'string', 'default' => ''],
                'license' => ['type' => 'string', 'default' => ''],
            ],
            'render_callback' => [$this, 'renderBlock'],
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function renderBlock(array $attributes = []): string
    {
        return $this->renderCopyright([
            'owner' => (string) ($attributes['owner'] ?? ''),
            'year' => (string) ($attributes['year'] ?? ''),
            'license' => (string) ($attributes['license'] ?? ''),
        ]);
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function renderCopyright(array|string $atts = [], ?string $content = null, string $shortcodeTag = ''): string
    {
        $atts = shortcode_atts([
            'owner' => '',
            'year' => '',
            'license' => '',
            'separator' => ' - ',
        ], is_array($atts) ? $atts : [], $shortcodeTag);

        $owner = $atts['owner'] !== '' ? $atts['owner'] : $this->defaultOwner();
        $year = $atts['year'] !== '' ? $atts['year'] : (string) gmdate('Y');
        $license = $atts['license'];

        $line = sprintf('&copy; %s %s', esc_html($year), esc_html($owner));

        if ($license !== '') {
            $line .= esc_html($atts['separator']) . sprintf(
                /* translators: %s: license identifier */
                esc_html__('License: %s', 'polski'),
                esc_html($license),
            );
        }

        return sprintf('<span class="polski-copyright">%s</span>', $line);
    }

    /**
     * Render a per-image credit block. Usage:
     *
     *   [polski_image_credit image_id="42" credit="Photo: Jan Kowalski" source="https://example.com"]
     *   [polski_image_credit credit="Photo by Jan Kowalski" license="CC BY-SA 4.0"]
     *
     * When `image_id` is provided, outputs the image followed by the credit line.
     * Otherwise only the credit line is rendered.
     *
     * @param array<string, mixed>|string $atts
     */
    public function renderImageCredit(array|string $atts = [], ?string $content = null, string $shortcodeTag = ''): string
    {
        $atts = shortcode_atts([
            'image_id' => '',
            'credit' => '',
            'source' => '',
            'license' => '',
            'size' => 'medium',
        ], is_array($atts) ? $atts : [], $shortcodeTag);

        $credit = trim((string) $atts['credit']);

        if ($credit === '' && $atts['image_id'] === '') {
            return '';
        }

        $html = '<figure class="polski-image-credit">';

        if ($atts['image_id'] !== '') {
            $imgId = (int) $atts['image_id'];
            $img = wp_get_attachment_image($imgId, (string) $atts['size']);

            if ($img !== '') {
                $html .= $img;
            }
        }

        if ($credit !== '') {
            $source = trim((string) $atts['source']);
            $license = trim((string) $atts['license']);

            $parts = [esc_html($credit)];

            if ($source !== '') {
                $parts[] = sprintf(
                    '<a href="%s" rel="nofollow noopener" target="_blank">%s</a>',
                    esc_url($source),
                    esc_html__('source', 'polski'),
                );
            }

            if ($license !== '') {
                $parts[] = sprintf(
                    /* translators: %s: license identifier */
                    esc_html__('License: %s', 'polski'),
                    esc_html($license),
                );
            }

            $html .= sprintf(
                '<figcaption class="polski-image-credit__caption">%s</figcaption>',
                implode(esc_html__(' - ', 'polski'), $parts),
            );
        }

        $html .= '</figure>';

        return $html;
    }

    private function defaultOwner(): string
    {
        $general = get_option('polski_general', []);

        if (is_array($general) && ! empty($general['company_name'])) {
            return (string) $general['company_name'];
        }

        return (string) get_bloginfo('name');
    }
}
