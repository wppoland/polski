<?php

declare(strict_types=1);

namespace Polski\Admin;

use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use const Polski\PLUGIN_FILE;

/**
 * Registers the top-level admin menu page that hosts the React SPA.
 * Falls back to PHP-rendered dashboard when JS is not built.
 */
final class AdminPage implements Bootable, HasHooks
{
    private const PAGE_SLUG = 'polski';
    private const CAPABILITY = 'manage_woocommerce';

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_post_polski_generate_legal_pages', [$this, 'handleGenerateLegalPages']);

        // "Settings" link on plugins page.
        add_filter('plugin_action_links_' . plugin_basename(PLUGIN_FILE), [$this, 'addPluginActionLinks']);
    }

    /**
     * Add "Settings" link to plugins page.
     *
     * @param list<string> $links
     * @return list<string>
     */
    public function addPluginActionLinks(array $links): array
    {
        $settingsLink = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=modules')),
            esc_html__('Ustawienia', 'polski'),
        );

        array_unshift($links, $settingsLink);

        return $links;
    }

    /**
     * Handle the "Generate Legal Pages" form submission.
     */
    public function handleGenerateLegalPages(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(__('Przepraszamy, ale wydaje się, że nie masz dostępu do tej strony.', 'polski'));
        }

        check_admin_referer('polski_generate_pages', '_polski_nonce');

        $legalPages = \Polski\Plugin::instance()->container()->get(\Polski\Service\LegalPageService::class);
        $legalPages->createDefaultPages();

        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG . '&polski_pages_generated=1'));
        exit;
    }

    public function addMenuPage(): void
    {
        add_menu_page(
            __('Polski', 'polski'),
            __('Polski', 'polski'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [$this, 'renderPage'],
            'none',
            58,
        );

        // Submenu items.
        add_submenu_page(
            self::PAGE_SLUG,
            __('Moduły', 'polski'),
            __('Moduły', 'polski'),
            self::CAPABILITY,
            self::PAGE_SLUG, // Same slug = replaces default "Polski" submenu item.
            [$this, 'renderPage'],
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __('Pulpit', 'polski'),
            __('Pulpit', 'polski'),
            self::CAPABILITY,
            self::PAGE_SLUG . '-dashboard',
            [$this, 'renderDashboardPage'],
        );

        // Polish flag via CSS.
        add_action('admin_head', [$this, 'renderMenuIconCSS']);
    }

    /**
     * Dashboard submenu page handler.
     */
    public function renderDashboardPage(): void
    {
        echo '<div class="wrap">';
        echo '<h1>Polski <small>v' . esc_html(\Polski\VERSION) . '</small></h1>';
        $this->renderDashboard();
        echo '</div>';
    }

    /**
     * Render Polish flag as a CSS-based menu icon.
     * WordPress overrides SVG fills, so we use a pseudo-element approach.
     */
    public function renderMenuIconCSS(): void
    {
        echo '<style>
            #adminmenu .toplevel_page_polski .wp-menu-image::before {
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
            #adminmenu .toplevel_page_polski:hover .wp-menu-image::before,
            #adminmenu .toplevel_page_polski.current .wp-menu-image::before {
                border-color: rgba(255,255,255,0.5);
            }
        </style>';
    }

    public function renderPage(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tab = sanitize_key($_GET['tab'] ?? 'modules');

        echo '<div class="wrap">';
        echo '<h1>Polski <small>v' . esc_html(\Polski\VERSION) . '</small></h1>';
        $generalSettings = $this->getGeneralSettings();

        // Success notices.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['polski_pages_generated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html((string) ($generalSettings['admin_pages_generated_notice'] ?? __('Gotowe! Wygenerowaliśmy dla Ciebie wstępne szkice stron prawnych. Przejrzyj je, dostosuj do swoich potrzeb i śmiało opublikuj.', 'polski')));
            echo '</p></div>';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['modules_saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html((string) ($generalSettings['admin_modules_saved_notice'] ?? __('Moduły zapisane.', 'polski')));
            echo '</p></div>';
        }

        // Tab navigation.
        $tabs = [
            'modules' => __('Moduły', 'polski'),
            'dashboard' => __('Pulpit', 'polski'),
        ];

        echo '<nav class="nav-tab-wrapper" style="margin-bottom:20px;">';
        foreach ($tabs as $tabId => $tabLabel) {
            $class = $tab === $tabId ? 'nav-tab nav-tab-active' : 'nav-tab';
            $url = admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=' . $tabId);
            printf('<a href="%s" class="%s">%s</a>', esc_url($url), esc_attr($class), esc_html($tabLabel));
        }
        echo '</nav>';

        match ($tab) {
            'dashboard' => $this->renderDashboard(),
            default => $this->renderModulesTab(),
        };

        echo '</div>';
    }

    private function renderModulesTab(): void
    {
        $modulesPage = \Polski\Plugin::instance()->container()->get(ModulesPage::class);
        $modulesPage->render();
    }

    /**
     * PHP-rendered dashboard shown when the React app is not built.
     */
    private function renderDashboard(): void
    {
        // Gather status data.
        $legalPages = \Polski\Plugin::instance()->container()->get(\Polski\Service\LegalPageService::class);
        $pageStatus = $legalPages->getConfigurationStatus();
        $allPagesConfigured = ! in_array(false, $pageStatus, true);
        $configuredCount = count(array_filter($pageStatus));
        $anyPageExists = $configuredCount > 0 || $this->anyLegalPageDraftExists($legalPages);

        $omnibusSettings = get_option('polski_omnibus', []);
        $omnibusEnabled = is_array($omnibusSettings) && ($omnibusSettings['enabled'] ?? true);

        $checkoutSettings = get_option('polski_checkout', []);
        $buttonText = is_array($checkoutSettings) ? ($checkoutSettings['order_button_text'] ?? '') : '';

        $generalSettings = $this->getGeneralSettings();
        $isSmallBusiness = (bool) ($generalSettings['small_business'] ?? false);

        $doiSettings = get_option('polski_doi', []);
        $doiEnabled = is_array($doiSettings) && ($doiSettings['enabled'] ?? false);

        // Status cards.
        echo '<div class="polski-dashboard" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-top:20px;">';

        $this->renderStatusCard(
            'WooCommerce',
            defined('WC_VERSION') ? sprintf('v%s - OK', WC_VERSION) : __('Nieaktywna', 'polski'),
            defined('WC_VERSION'),
        );

        $this->renderStatusCard(
            __('Strony prawne', 'polski'),
            str_replace(
                ['{done}', '{total}'],
                [(string) $configuredCount, (string) count($pageStatus)],
                (string) ($generalSettings['admin_legal_pages_card_progress'] ?? __('Masz już za sobą %d z %d kroków. Znakomicie!', 'polski'))
            ),
            $allPagesConfigured,
        );

        $this->renderStatusCard(
            __('Dyrektywa Omnibus', 'polski'),
            $omnibusEnabled ? (string) ($generalSettings['admin_status_active'] ?? __('Aktywna', 'polski')) : (string) ($generalSettings['admin_status_inactive'] ?? __('Wyłączona', 'polski')),
            $omnibusEnabled,
        );

        $this->renderStatusCard(
            __('Przycisk zamówienia', 'polski'),
            $buttonText !== '' ? $buttonText : (string) ($generalSettings['admin_status_unconfigured'] ?? __('Nieskonfigurowany', 'polski')),
            $buttonText !== '',
        );

        $this->renderStatusCard(
            (string) ($generalSettings['admin_vat_card_title'] ?? __('Wyświetlanie podatku', 'polski')),
            $isSmallBusiness ? (string) ($generalSettings['admin_vat_small_business_text'] ?? __('Zwolnienie podmiotowe (art. 113)', 'polski')) : (string) ($generalSettings['admin_vat_standard_text'] ?? __('Standardowy VAT', 'polski')),
            true,
        );

        $this->renderStatusCard(
            (string) ($generalSettings['admin_doi_card_title'] ?? __('Podwójna weryfikacja (DOI)', 'polski')),
            $doiEnabled ? (string) ($generalSettings['admin_status_active'] ?? __('Aktywna', 'polski')) : (string) ($generalSettings['admin_status_inactive'] ?? __('Wyłączona', 'polski')),
            null,
        );

        echo '</div>';

        // Legal pages section.
        echo '<div style="margin-top:30px;">';
        echo '<h2>' . esc_html((string) ($generalSettings['admin_legal_pages_section_title'] ?? __('Strony prawne', 'polski'))) . '</h2>';

        if ($anyPageExists) {
            // Show page list with edit links.
            echo '<table class="widefat striped" style="max-width:600px;">';
            echo '<thead><tr><th>' . esc_html((string) ($generalSettings['admin_legal_pages_table_page'] ?? __('Strona', 'polski'))) . '</th><th>' . esc_html((string) ($generalSettings['admin_legal_pages_table_status'] ?? __('Status', 'polski'))) . '</th><th></th></tr></thead><tbody>';

            foreach ($pageStatus as $type => $configured) {
                $pageType = \Polski\Enum\LegalPageType::tryFrom($type);
                if ($pageType === null) {
                    continue;
                }

                $pageId = $legalPages->getPageId($pageType);
                $label = $pageType->label();
                $post = $pageId > 0 ? get_post($pageId) : null;

                echo '<tr>';
                echo '<td><strong>' . esc_html($label) . '</strong></td>';

                if ($post instanceof \WP_Post) {
                    $statusLabel = match ($post->post_status) {
                        'publish' => '<span style="color:#46b450;">' . esc_html((string) ($generalSettings['admin_legal_pages_published'] ?? __('Opublikowana', 'polski'))) . '</span>',
                        'draft' => '<span style="color:#f0ad4e;">' . esc_html((string) ($generalSettings['admin_legal_pages_draft'] ?? __('Szkic', 'polski'))) . '</span>',
                        default => esc_html($post->post_status),
                    };
                    echo '<td>' . $statusLabel . '</td>';
                    echo '<td><a href="' . esc_url(get_edit_post_link($pageId) ?: '#') . '" class="button button-small">' . esc_html((string) ($generalSettings['admin_edit_button_text'] ?? __('Edytuj', 'polski'))) . '</a></td>';
                } else {
                    echo '<td><span style="color:#dc3232;">' . esc_html((string) ($generalSettings['admin_legal_pages_missing'] ?? __('Nie utworzona', 'polski'))) . '</span></td>';
                    echo '<td></td>';
                }

                echo '</tr>';
            }

            echo '</tbody></table>';

            // Show generate button only if some pages are missing.
            if (! $allPagesConfigured || ! $anyPageExists) {
                $this->renderGenerateButton();
            }
        } else {
            // No pages exist at all - show generate button.
            echo '<p>' . esc_html((string) ($generalSettings['admin_generate_pages_empty_text'] ?? __('Nie utworzono jeszcze stron prawnych. Wygeneruj je, aby rozpocząć.', 'polski'))) . '</p>';
            $this->renderGenerateButton();
        }

        echo '</div>';

        // Next steps.
        echo '<div style="margin-top:30px;">';
        echo '<h2>' . esc_html((string) ($generalSettings['admin_next_steps_title'] ?? __('Kolejne kroki', 'polski'))) . '</h2>';
        echo '<ol style="max-width:600px;">';

        if (! $allPagesConfigured) {
            echo '<li>' . esc_html((string) ($generalSettings['admin_next_steps_publish_pages'] ?? __('Publish your legal pages (Regulamin, Polityka prywatności, Prawo odstąpienia, Reklamacje).', 'polski'))) . '</li>';
        }

        echo '<li>' . sprintf(
            (string) ($generalSettings['admin_next_steps_tax'] ?? __('Set up <a href="%s">tax rates</a> in WooCommerce for Polish VAT (23%%, 8%%, 5%%, 0%%).', 'polski')),
            esc_url(admin_url('admin.php?page=wc-settings&tab=tax')),
        ) . '</li>';

        echo '<li>' . sprintf(
            (string) ($generalSettings['admin_next_steps_shipping'] ?? __('Configure <a href="%s">shipping zones</a> for Polish delivery.', 'polski')),
            esc_url(admin_url('admin.php?page=wc-settings&tab=shipping')),
        ) . '</li>';

        echo '<li>' . sprintf(
            (string) ($generalSettings['admin_next_steps_products'] ?? __('Edit product data - add unit prices and delivery times in the <a href="%s">Polski tab</a> of each product.', 'polski')),
            esc_url(admin_url('edit.php?post_type=product')),
        ) . '</li>';

        echo '<li>' . sprintf(
            (string) ($generalSettings['admin_next_steps_checkout'] ?? __('Test the checkout - add a product to cart and verify legal checkboxes and button text at <a href="%s">checkout</a>.', 'polski')),
            esc_url(wc_get_checkout_url()),
        ) . '</li>';

        echo '</ol>';
        echo '</div>';
    }

    /**
     * Check if any legal page exists (even as draft).
     */
    private function anyLegalPageDraftExists(\Polski\Service\LegalPageService $service): bool
    {
        foreach (\Polski\Enum\LegalPageType::cases() as $type) {
            $pageId = $service->getPageId($type);
            if ($pageId > 0 && get_post_status($pageId) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render the "Generate Legal Pages" form button.
     */
    private function renderGenerateButton(): void
    {
        $generalSettings = $this->getGeneralSettings();
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:10px;">';
        wp_nonce_field('polski_generate_pages', '_polski_nonce');
        echo '<input type="hidden" name="action" value="polski_generate_legal_pages" />';
        printf(
            '<button type="submit" class="button button-primary">%s</button>',
            esc_html((string) ($generalSettings['admin_generate_pages_button_text'] ?? __('Wygeneruj strony prawne', 'polski'))),
        );
        echo '</form>';
    }

    /**
     * @return array<string, mixed>
     */
    private function getGeneralSettings(): array
    {
        $settings = get_option('polski_general', []);

        return is_array($settings) ? $settings : [];
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

        $jsFile = \Polski\PLUGIN_DIR . '/build/admin.js';

        // Only enqueue if built.
        if (! file_exists($jsFile)) {
            return;
        }

        $assetFile = \Polski\PLUGIN_DIR . '/build/admin.asset.php';

        if (file_exists($assetFile)) {
            $asset = require $assetFile;
        } else {
            $asset = [
                'dependencies' => ['wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch', 'wp-data'],
                'version' => \Polski\VERSION,
            ];
        }

        wp_enqueue_script(
            'polski-admin',
            plugins_url('build/admin.js', PLUGIN_FILE),
            $asset['dependencies'],
            $asset['version'],
            true,
        );

        wp_enqueue_style(
            'polski-admin',
            plugins_url('build/admin.css', PLUGIN_FILE),
            ['wp-components'],
            $asset['version'],
        );

        $plugin = \Polski\Plugin::instance();

        wp_localize_script('polski-admin', 'polskiAdmin', [
            'restUrl' => rest_url('polski/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'version' => \Polski\VERSION,
            'isWizardComplete' => (bool) get_option('polski_wizard_complete', false),
            'isProActive' => $plugin->isProActive(),
            'proVersion' => $plugin->proVersion(),
        ]);

        wp_set_script_translations('polski-admin', 'polski');
    }
}
