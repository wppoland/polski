<?php

declare(strict_types=1);

namespace Spolszczony\Admin;

use Spolszczony\Contract\Bootable;
use Spolszczony\Contract\HasHooks;
use const Spolszczony\PLUGIN_FILE;

/**
 * Registers the top-level admin menu page that hosts the React SPA.
 * Falls back to PHP-rendered dashboard when JS is not built.
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
        add_action('admin_post_spolszczony_generate_legal_pages', [$this, 'handleGenerateLegalPages']);
    }

    /**
     * Handle the "Generate Legal Pages" form submission.
     */
    public function handleGenerateLegalPages(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(__('You do not have permission to access this resource.', 'spolszczony'));
        }

        check_admin_referer('spolszczony_generate_pages', '_spolszczony_nonce');

        $legalPages = \Spolszczony\Plugin::instance()->container()->get(\Spolszczony\Service\LegalPageService::class);
        $legalPages->createDefaultPages();

        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG . '&spolszczony_pages_generated=1'));
        exit;
    }

    public function addMenuPage(): void
    {
        add_menu_page(
            __('Spolszczony', 'spolszczony'),
            __('Spolszczony', 'spolszczony'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [$this, 'renderPage'],
            'none',
            58,
        );

        // Polish flag via CSS - WordPress can't override inline styles.
        add_action('admin_head', [$this, 'renderMenuIconCSS']);
    }

    /**
     * Render Polish flag as a CSS-based menu icon.
     * WordPress overrides SVG fills, so we use a pseudo-element approach.
     */
    public function renderMenuIconCSS(): void
    {
        echo '<style>
            #adminmenu .toplevel_page_spolszczony .wp-menu-image::before {
                content: "" !important;
                display: block !important;
                width: 18px;
                height: 14px;
                margin: 7px auto 0;
                border-radius: 2px;
                background: linear-gradient(to bottom, #fff 50%, #dc143c 50%);
                border: 1px solid rgba(255,255,255,0.25);
                box-sizing: border-box;
            }
            #adminmenu .toplevel_page_spolszczony:hover .wp-menu-image::before,
            #adminmenu .toplevel_page_spolszczony.current .wp-menu-image::before {
                border-color: rgba(255,255,255,0.5);
            }
        </style>';
    }

    public function renderPage(): void
    {
        // If React app is built, render the mount point.
        $jsFile = \Spolszczony\PLUGIN_DIR . '/build/admin.js';

        if (file_exists($jsFile)) {
            echo '<div id="spolszczony-admin" class="spolszczony-admin-app"></div>';
            return;
        }

        // PHP fallback dashboard.
        $this->renderFallbackDashboard();
    }

    /**
     * PHP-rendered dashboard shown when the React app is not built.
     */
    private function renderFallbackDashboard(): void
    {
        $version = \Spolszczony\VERSION;
        $wizardDone = (bool) get_option('spolszczony_wizard_complete', false);

        echo '<div class="wrap spolszczony-admin-fallback">';
        echo '<h1>' . esc_html__('Spolszczony', 'spolszczony') . ' <small>v' . esc_html($version) . '</small></h1>';

        // Success notice after generating pages.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['spolszczony_pages_generated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Legal pages have been generated as drafts. Edit and publish them.', 'spolszczony');
            echo '</p></div>';
        }

        // Setup wizard prompt.
        if (! $wizardDone) {
            echo '<div class="notice notice-info"><p>';
            echo esc_html__('Welcome to Spolszczony. Configure your store for Polish legal compliance.', 'spolszczony');
            echo '</p></div>';
        }

        // Status cards.
        echo '<div class="spolszczony-dashboard" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-top:20px;">';

        $this->renderStatusCard(
            __('WooCommerce', 'spolszczony'),
            defined('WC_VERSION') ? sprintf('v%s - OK', WC_VERSION) : __('Not active', 'spolszczony'),
            defined('WC_VERSION'),
        );

        $legalPages = \Spolszczony\Plugin::instance()->container()->get(\Spolszczony\Service\LegalPageService::class);
        $pageStatus = $legalPages->getConfigurationStatus();
        $allConfigured = ! in_array(false, $pageStatus, true);
        $configuredCount = count(array_filter($pageStatus));

        $this->renderStatusCard(
            __('Legal Pages', 'spolszczony'),
            sprintf(__('%d of %d configured', 'spolszczony'), $configuredCount, count($pageStatus)),
            $allConfigured,
        );

        $omnibusSettings = get_option('spolszczony_omnibus', []);
        $omnibusEnabled = is_array($omnibusSettings) && ($omnibusSettings['enabled'] ?? true);

        $this->renderStatusCard(
            __('Omnibus Directive', 'spolszczony'),
            $omnibusEnabled ? __('Active', 'spolszczony') : __('Disabled', 'spolszczony'),
            $omnibusEnabled,
        );

        $checkoutSettings = get_option('spolszczony_checkout', []);
        $buttonText = is_array($checkoutSettings) ? ($checkoutSettings['order_button_text'] ?? '') : '';

        $this->renderStatusCard(
            __('Checkout Button', 'spolszczony'),
            $buttonText !== '' ? $buttonText : __('Not configured', 'spolszczony'),
            $buttonText !== '',
        );

        $taxSettings = get_option('spolszczony_taxes', []);
        $generalSettings = get_option('spolszczony_general', []);
        $isSmallBusiness = is_array($generalSettings) && ($generalSettings['small_business'] ?? false);

        $this->renderStatusCard(
            __('Tax Display', 'spolszczony'),
            $isSmallBusiness ? __('Small business (ZP)', 'spolszczony') : __('Standard VAT', 'spolszczony'),
            true,
        );

        $doiSettings = get_option('spolszczony_doi', []);
        $doiEnabled = is_array($doiSettings) && ($doiSettings['enabled'] ?? false);

        $this->renderStatusCard(
            __('Double Opt-In', 'spolszczony'),
            $doiEnabled ? __('Active', 'spolszczony') : __('Disabled', 'spolszczony'),
            null,
        );

        echo '</div>'; // .spolszczony-dashboard

        // Quick links.
        echo '<div style="margin-top:30px;">';
        echo '<h2>' . esc_html__('Quick Links', 'spolszczony') . '</h2>';
        echo '<ul style="list-style:disc;padding-left:20px;">';

        foreach ($pageStatus as $type => $configured) {
            $pageType = \Spolszczony\Enum\LegalPageType::tryFrom($type);
            if ($pageType === null) {
                continue;
            }

            $pageId = $legalPages->getPageId($pageType);
            $label = $pageType->label();

            if ($pageId > 0) {
                printf(
                    '<li>%s - <a href="%s">%s</a></li>',
                    esc_html($label),
                    esc_url(get_edit_post_link($pageId) ?: '#'),
                    esc_html__('Edit', 'spolszczony'),
                );
            } else {
                printf(
                    '<li>%s - <em>%s</em></li>',
                    esc_html($label),
                    esc_html__('Not created', 'spolszczony'),
                );
            }
        }

        echo '</ul>';

        // Generate legal pages button (POST via form with nonce).
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('spolszczony_generate_pages', '_spolszczony_nonce');
        echo '<input type="hidden" name="action" value="spolszczony_generate_legal_pages" />';
        printf(
            '<p><button type="submit" class="button button-primary">%s</button></p>',
            esc_html__('Generate Legal Pages', 'spolszczony'),
        );
        echo '</form>';

        echo '</div>';
        echo '</div>'; // .wrap
    }

    /**
     * Render a single status card.
     *
     * @param string    $title
     * @param string    $value
     * @param bool|null $ok True = green, false = red, null = neutral.
     */
    private function renderStatusCard(string $title, string $value, ?bool $ok): void
    {
        $color = match ($ok) {
            true => '#46b450',
            false => '#dc3232',
            null => '#999',
        };

        printf(
            '<div style="background:#fff;border:1px solid #ccd0d4;border-left:4px solid %s;padding:16px;">
                <h3 style="margin:0 0 8px;">%s</h3>
                <p style="margin:0;font-size:14px;">%s</p>
            </div>',
            esc_attr($color),
            esc_html($title),
            esc_html($value),
        );
    }

    public function enqueueAssets(string $hookSuffix): void
    {
        if (! str_contains($hookSuffix, self::PAGE_SLUG)) {
            return;
        }

        $jsFile = \Spolszczony\PLUGIN_DIR . '/build/admin.js';

        // Only enqueue if built.
        if (! file_exists($jsFile)) {
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
