<?php

declare(strict_types=1);

namespace Spolszczony\Admin;

use Spolszczony\Contract\Bootable;
use Spolszczony\Contract\HasHooks;
use Spolszczony\PLUGIN_FILE;

/**
 * Registers the top-level admin menu page that hosts the React SPA.
 */
final class AdminPage implements Bootable, HasHooks
{
    private const PAGE_SLUG = 'spolszczony';
    private const CAPABILITY = 'manage_woocommerce';

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMenuPage(): void
    {
        add_menu_page(
            __('Spolszczony', 'spolszczony'),
            __('Spolszczony', 'spolszczony'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [$this, 'renderPage'],
            'dashicons-store',
            58, // After WooCommerce.
        );
    }

    public function renderPage(): void
    {
        echo '<div id="spolszczony-admin" class="spolszczony-admin-app"></div>';
    }

    public function enqueueAssets(string $hookSuffix): void
    {
        if (! str_contains($hookSuffix, self::PAGE_SLUG)) {
            return;
        }

        $assetFile = \Spolszczony\PLUGIN_DIR . '/build/admin.asset.php';

        if (file_exists($assetFile)) {
            $asset = require $assetFile;
        } else {
            $asset = [
                'dependencies' => ['wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch', 'wp-data'],
                'version' => \Spolszczony\VERSION,
            ];
        }

        wp_enqueue_script(
            'spolszczony-admin',
            plugins_url('build/admin.js', PLUGIN_FILE),
            $asset['dependencies'],
            $asset['version'],
            true,
        );

        wp_enqueue_style(
            'spolszczony-admin',
            plugins_url('build/admin.css', PLUGIN_FILE),
            ['wp-components'],
            $asset['version'],
        );

        wp_localize_script('spolszczony-admin', 'spolszczonyAdmin', [
            'restUrl' => rest_url('spolszczony/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'version' => \Spolszczony\VERSION,
            'isWizardComplete' => (bool) get_option('spolszczony_wizard_complete', false),
        ]);

        wp_set_script_translations('spolszczony-admin', 'spolszczony');
    }
}
