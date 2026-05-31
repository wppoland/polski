<?php

declare(strict_types=1);

namespace Polski\Admin;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\SBOM\SBOMGenerator;

/**
 * Admin entry point for downloading a CycloneDX SBOM for this plugin.
 */
final class SBOMPage implements HasHooks
{
    private const SLUG = 'polski-sbom';
    private const NONCE = 'polski_sbom_download';

    public function __construct(
        private readonly SBOMGenerator $generator,
    ) {
    }

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('sbom')) {
            return;
        }

        add_action('admin_menu', [$this, 'registerPage'], 80);
        add_action('admin_post_polski_sbom_download', [$this, 'handleDownload']);
    }

    public function registerPage(): void
    {
        add_submenu_page(
            'polski',
            __('SBOM', 'polski'),
            __('SBOM', 'polski'),
            'manage_woocommerce',
            self::SLUG,
            [$this, 'render'],
        );
    }

    public function render(): void
    {
        echo '<div class="wrap"><h1>' . esc_html__('Software Bill of Materials (SBOM)', 'polski') . '</h1>';
        echo '<p>' . esc_html__('Generate a CycloneDX 1.4 JSON document listing PHP (composer) and JS (npm) dependencies. Useful for security audits, CRA compliance packages and vulnerability scanners.', 'polski') . '</p>';

        foreach ($this->targets() as $slug => $target) {
            printf(
                '<h2>%s</h2><p>%s: <code>%s</code></p>',
                esc_html($target['label']),
                esc_html__('Path', 'polski'),
                esc_html($target['dir']),
            );

            $this->renderDownloadForm($slug);
        }

        echo '</div>';
    }

    private function renderDownloadForm(string $slug): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="action" value="polski_sbom_download">';
        printf('<input type="hidden" name="slug" value="%s">', esc_attr($slug));
        submit_button(__('Download SBOM (JSON)', 'polski'), 'secondary', 'submit', false);
        echo '</form>';
    }

    public function handleDownload(): void
    {
        check_admin_referer(self::NONCE);

        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'polski'));
        }

        $slug = sanitize_key((string) ($_POST['slug'] ?? 'polski'));
        $targets = $this->targets();

        if (! isset($targets[$slug])) {
            wp_die(esc_html__('Unknown plugin target.', 'polski'));
        }

        $target = $targets[$slug];
        $sbom = $this->generator->generate($target['dir'], $slug, $target['version']);
        $json = (string) wp_json_encode($sbom, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $filename = sprintf('%s-sbom-%s-%s.cdx.json', $slug, $target['version'], gmdate('Ymd-His'));

        nocache_headers();
        header('Content-Type: application/vnd.cyclonedx+json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON payload under Content-Type.
        echo $json;
        exit;
    }

    /**
     * @return array<string, array{label: string, dir: string, version: string}>
     */
    private function targets(): array
    {
        $targets = [
            'polski' => [
                'label' => __('Polski for WooCommerce', 'polski'),
                'dir' => defined('Polski\\PLUGIN_DIR') ? \Polski\PLUGIN_DIR : WP_PLUGIN_DIR . '/polski',
                'version' => defined('Polski\\VERSION') ? \Polski\VERSION : '',
            ],
        ];

        return $targets;
    }
}
