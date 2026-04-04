<?php

declare(strict_types=1);
namespace Polski\Admin;

defined('ABSPATH') || exit;

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
    private const GITHUB_DISCUSSIONS_URL = 'https://github.com/wppoland/polski/discussions';
    private const GITHUB_ISSUES_URL = 'https://github.com/wppoland/polski/issues?q=sort%3Aupdated-desc+is%3Aissue+is%3Aopen';
    private const ADMIN_FEEDBACK_OPTION = 'polski_admin_feedback';
    private const ADMIN_FEEDBACK_NONCE_ACTION = 'polski_admin_feedback_nonce';
    private const ADMIN_FEEDBACK_NONCE_FIELD = '_polski_admin_feedback_nonce';
    private const ADMIN_FEEDBACK_ACTION = 'polski_submit_admin_feedback';
    private const MAX_ADMIN_FEEDBACK_ENTRIES = 100;

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage'], 1);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_post_polski_generate_legal_pages', [$this, 'handleGenerateLegalPages']);
        add_action('admin_post_polski_complete_wizard', [$this, 'handleWizardCompletion']);
        add_action('admin_post_polski_save_module_settings', [$this, 'handleModuleSettingsSave']);
        add_action('admin_post_' . self::ADMIN_FEEDBACK_ACTION, [$this, 'handleAdminFeedbackSubmit']);

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

    public function handleAdminFeedbackSubmit(): void
    {
        check_admin_referer(self::ADMIN_FEEDBACK_NONCE_ACTION, self::ADMIN_FEEDBACK_NONCE_FIELD);

        if (! current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to send feedback.', 'polski'));
        }

        $message = sanitize_textarea_field((string) ($_POST['message'] ?? ''));
        $redirectUrl = wp_get_referer() ?: admin_url('admin.php?page=' . self::PAGE_SLUG);

        if ($message === '') {
            wp_safe_redirect(add_query_arg('polski_feedback_error', '1', $redirectUrl));
            exit;
        }

        $feedback = get_option(self::ADMIN_FEEDBACK_OPTION, []);

        if (! is_array($feedback)) {
            $feedback = [];
        }

        $currentUser = wp_get_current_user();
        $name = sanitize_text_field((string) ($_POST['name'] ?? ''));
        $email = sanitize_email((string) ($_POST['email'] ?? ''));

        $feedback[] = [
            'timestamp' => current_time('mysql'),
            'name' => $name !== '' ? $name : $currentUser->display_name,
            'email' => $email !== '' ? $email : $currentUser->user_email,
            'topic' => sanitize_key((string) ($_POST['topic'] ?? 'general_feedback')),
            'screen' => sanitize_key((string) ($_POST['admin_screen'] ?? 'dashboard')),
            'message' => $message,
            'plugin_version' => \Polski\VERSION,
            'site_url' => home_url(),
        ];

        if (count($feedback) > self::MAX_ADMIN_FEEDBACK_ENTRIES) {
            $feedback = array_slice($feedback, -self::MAX_ADMIN_FEEDBACK_ENTRIES);
        }

        update_option(self::ADMIN_FEEDBACK_OPTION, $feedback);

        wp_safe_redirect(add_query_arg('polski_feedback_saved', '1', $redirectUrl));
        exit;
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

    public function handleWizardCompletion(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Brak uprawnień.', 'polski'));
        }

        check_admin_referer('polski_complete_wizard', '_polski_wizard_nonce');

        $payload = [
            'company_name' => sanitize_text_field((string) ($_POST['company_name'] ?? '')),
            'company_address' => sanitize_text_field((string) ($_POST['company_address'] ?? '')),
            'company_nip' => sanitize_text_field((string) ($_POST['company_nip'] ?? '')),
            'company_email' => sanitize_email((string) ($_POST['company_email'] ?? '')),
            'company_phone' => sanitize_text_field((string) ($_POST['company_phone'] ?? '')),
            'terms_enabled' => ! empty($_POST['terms_enabled']),
            'privacy_enabled' => ! empty($_POST['privacy_enabled']),
            'withdrawal_enabled' => ! empty($_POST['withdrawal_enabled']),
            'digital_waiver_enabled' => ! empty($_POST['digital_waiver_enabled']),
            'marketing_enabled' => ! empty($_POST['marketing_enabled']),
            'order_button_text' => sanitize_text_field((string) ($_POST['order_button_text'] ?? '')),
            'generate_legal_pages' => ! empty($_POST['generate_legal_pages']),
            'omnibus_enabled' => ! empty($_POST['omnibus_enabled']),
        ];

        $request = new \WP_REST_Request('POST', '/polski/v1/wizard/complete');
        $request->set_header('content-type', 'application/json');
        $request->set_body(wp_json_encode($payload) ?: '{}');

        /** @var \Polski\Rest\SettingsController $controller */
        $controller = \Polski\Plugin::instance()->container()->get(\Polski\Rest\SettingsController::class);
        $response = $controller->completeWizard($request);

        if ($response->get_status() >= 400) {
            wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=wizard'));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=dashboard'));
        exit;
    }

    /**
     * Save settings submitted from a single module's dedicated settings page.
     */
    public function handleModuleSettingsSave(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Brak uprawnień.', 'polski'));
        }

        check_admin_referer('polski_save_module_settings', '_polski_module_nonce');

        $moduleId = sanitize_text_field((string) ($_POST['module_id'] ?? ''));
        $groupSlug = sanitize_text_field((string) ($_POST['group_slug'] ?? ''));
        $postSettings = $_POST['polski_setting'] ?? [];

        if (is_array($postSettings)) {
            foreach ($postSettings as $optionName => $fields) {
                if (! is_array($fields)) {
                    continue;
                }

                $optionName = sanitize_text_field($optionName);
                $current = get_option($optionName, []);

                if (! is_array($current)) {
                    $current = [];
                }

                foreach ($fields as $key => $value) {
                    $current[sanitize_text_field($key)] = sanitize_text_field($value);
                }

                update_option($optionName, $current);
            }
        }

        $redirectPage = $groupSlug ? self::PAGE_SLUG . '-group-' . $groupSlug : self::PAGE_SLUG;
        wp_safe_redirect(admin_url('admin.php?page=' . $redirectPage . '&saved=1&module=' . $moduleId));
        exit;
    }

    public function addMenuPage(): void
    {
        add_menu_page(
            __('Polski', 'polski'),
            __('Polski', 'polski'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [$this, 'renderDashboardPage'],
            'none',
            58,
        );

        // Submenu items.
        add_submenu_page(
            self::PAGE_SLUG,
            __('Pulpit', 'polski'),
            __('Pulpit', 'polski'),
            self::CAPABILITY,
            self::PAGE_SLUG, // Replaces default "Polski" submenu item with Dashboard.
            [$this, 'renderDashboardPage'],
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __('Moduły', 'polski'),
            __('Moduły', 'polski'),
            self::CAPABILITY,
            self::PAGE_SLUG . '-modules',
            [$this, 'renderPage'],
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __('Raporty i narzędzia', 'polski'),
            __('Raporty i narzędzia', 'polski'),
            self::CAPABILITY,
            self::PAGE_SLUG . '-reports',
            [$this, 'renderReportsHubPage'],
        );

        // Dynamic module settings submenus grouped by category.
        $modulesPage = \Polski\Plugin::instance()->container()->get(\Polski\Admin\ModulesPage::class);
        $groups = [];

        foreach ($modulesPage->getModules() as $module) {
            if (empty($module['settings'])) {
                continue;
            }

            if (! ModulesPage::isModuleEnabled($module['id'])) {
                continue;
            }

            $groups[$module['group']][] = $module;
        }

        foreach ($groups as $groupName => $groupModules) {
            $groupSlug = sanitize_title($groupName);

            add_submenu_page(
                self::PAGE_SLUG,
                $groupName,
                $groupName,
                self::CAPABILITY,
                self::PAGE_SLUG . '-group-' . $groupSlug,
                function () use ($modulesPage, $groupName, $groupModules, $groupSlug): void {
                    $this->renderGroupSettingsPage($modulesPage, $groupName, $groupModules, $groupSlug);
                },
            );
        }

        // Polish flag via CSS.
        add_action('admin_head', [$this, 'renderMenuIconCSS']);
    }

    /**
     * Render a settings page for a group of modules.
     *
     * @param ModulesPage          $modulesPage
     * @param string               $groupName
     * @param array<int, mixed[]>  $modules
     * @param string               $groupSlug
     */
    private function renderGroupSettingsPage(ModulesPage $modulesPage, string $groupName, array $modules, string $groupSlug): void
    {
        echo '<div class="wrap">';
        echo '<h1>Polski &rsaquo; ' . esc_html($groupName) . '</h1>';

        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Ustawienia zostały zapisane.', 'polski') . '</p></div>';
        }

        foreach ($modules as $module) {
            echo '<div class="polski-module-settings-section" style="background:#fff; border:1px solid #ccd0d4; padding:20px; margin-top:20px;">';
            echo '<h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">' . esc_html($module['name']) . '</h2>';
            echo '<p>' . esc_html($module['description']) . '</p>';

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('polski_save_module_settings', '_polski_module_nonce');
            echo '<input type="hidden" name="action" value="polski_save_module_settings" />';
            echo '<input type="hidden" name="module_id" value="' . esc_attr($module['id']) . '" />';
            echo '<input type="hidden" name="group_slug" value="' . esc_attr($groupSlug) . '" />';

            echo '<table class="form-table" role="presentation"><tbody>';

            foreach ($module['settings'] as $field) {
                $modulesPage->renderSettingsField($field, true);
            }

            echo '</tbody></table>';
            submit_button(__('Zapisz ustawienia', 'polski'));
            echo '</form>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Dashboard submenu page handler.
     */
    public function renderDashboardPage(): void
    {
        echo '<div class="wrap">';
        echo '<h1>Polski <small>v' . esc_html(\Polski\VERSION) . '</small></h1>';
        echo '<div style="display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:24px;align-items:start;">';
        echo '<div>';
        $this->renderDashboard();
        echo '</div>';
        $this->renderHelpSidebar('dashboard');
        echo '</div>';
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
     * Render the Reports & Tools Hub page.
     */
    public function renderReportsHubPage(): void
    {
        $view = sanitize_key($_GET['view'] ?? 'overview');

        echo '<div class="wrap">';
        echo '<h1>Polski &rsaquo; ' . esc_html__('Raporty i narzędzia', 'polski') . '</h1>';

        if ($view === 'overview') {
            echo '<div style="display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:24px;align-items:start;">';
            echo '<div>';
            $this->renderReportsOverview();
            echo '</div>';
            $this->renderHelpSidebar('reports');
            echo '</div>';
        } else {
            echo '<a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '-reports')) . '" class="button" style="margin-bottom:20px;">&larr; ' . esc_html__('Wróć do raportów', 'polski') . '</a>';
            
            switch ($view) {
                case 'audit':
                    $service = \Polski\Plugin::instance()->container()->get(\Polski\Service\SiteAuditService::class);
                    $service->renderAuditPage();
                    break;
                case 'dsa':
                    $service = \Polski\Plugin::instance()->container()->get(\Polski\Service\DSAService::class);
                    $service->renderReportsPage();
                    break;
                case 'incidents':
                    $service = \Polski\Plugin::instance()->container()->get(\Polski\Service\SecurityIncidentService::class);
                    $service->renderPage();
                    break;
                case 'dpa':
                    $service = \Polski\Plugin::instance()->container()->get(\Polski\Service\DPATrackerService::class);
                    $service->renderTrackerPage();
                    break;
                case 'feedback':
                    $handler = \Polski\Plugin::instance()->container()->get(\Polski\Admin\DeactivationHandler::class);
                    $handler->renderFeedbackLog();
                    echo '<div style="margin-top:24px;"></div>';
                    $this->renderAdminFeedbackLog();
                    break;
            }
        }

        echo '</div>';
    }

    private function renderHelpSidebar(string $screen): void
    {
        $formId = 'polski-feedback-form-' . sanitize_html_class($screen);
        $generalSettings = $this->getGeneralSettings();
        $removeDataOnUninstall = (bool) ($generalSettings['remove_data_on_uninstall'] ?? false);

        echo '<aside aria-label="' . esc_attr__('Help and feedback', 'polski') . '">';

        echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">';
        echo '<h2 style="margin-top:0;">' . esc_html__('Share feedback', 'polski') . '</h2>';
        echo '<p style="color:#50575e;">' . esc_html__('Not technical? Send a quick note straight from the plugin. Tell us what feels unclear, what should be easier, or what we should add next.', 'polski') . '</p>';
        if (isset($_GET['polski_feedback_saved'])) {
            echo '<div class="notice notice-success inline"><p>' . esc_html__('Thanks, your feedback has been saved.', 'polski') . '</p></div>';
        } elseif (isset($_GET['polski_feedback_error'])) {
            echo '<div class="notice notice-error inline"><p>' . esc_html__('Please enter your message before sending feedback.', 'polski') . '</p></div>';
        }

        echo '<form id="' . esc_attr($formId) . '" action="' . esc_url(admin_url('admin-post.php')) . '" method="post">';
        echo '<p><label for="' . esc_attr($formId . '-name') . '" style="display:block;font-weight:600;margin-bottom:6px;">' . esc_html__('Your name', 'polski') . '</label>';
        echo '<input id="' . esc_attr($formId . '-name') . '" type="text" name="name" class="regular-text" style="width:100%;" /></p>';
        echo '<p><label for="' . esc_attr($formId . '-email') . '" style="display:block;font-weight:600;margin-bottom:6px;">' . esc_html__('Your email', 'polski') . '</label>';
        echo '<input id="' . esc_attr($formId . '-email') . '" type="email" name="email" class="regular-text" style="width:100%;" /></p>';
        echo '<p><label for="' . esc_attr($formId . '-topic') . '" style="display:block;font-weight:600;margin-bottom:6px;">' . esc_html__('Topic', 'polski') . '</label>';
        echo '<select id="' . esc_attr($formId . '-topic') . '" name="topic" style="width:100%;">';
        echo '<option value="general_feedback">' . esc_html__('General feedback', 'polski') . '</option>';
        echo '<option value="idea">' . esc_html__('Feature idea', 'polski') . '</option>';
        echo '<option value="workflow">' . esc_html__('Workflow problem', 'polski') . '</option>';
        echo '<option value="support_question">' . esc_html__('Support question', 'polski') . '</option>';
        echo '</select></p>';
        echo '<p><label for="' . esc_attr($formId . '-message') . '" style="display:block;font-weight:600;margin-bottom:6px;">' . esc_html__('Message', 'polski') . '</label>';
        echo '<textarea id="' . esc_attr($formId . '-message') . '" name="message" rows="6" style="width:100%;" required></textarea></p>';
        echo '<input type="hidden" name="plugin" value="Polski for WooCommerce" />';
        echo '<input type="hidden" name="plugin_version" value="' . esc_attr(\Polski\VERSION) . '" />';
        echo '<input type="hidden" name="site_url" value="' . esc_url(home_url()) . '" />';
        echo '<input type="hidden" name="admin_screen" value="' . esc_attr($screen) . '" />';
        echo '<input type="hidden" name="source" value="wordpress_admin_sidebar" />';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ADMIN_FEEDBACK_ACTION) . '" />';
        wp_nonce_field(self::ADMIN_FEEDBACK_NONCE_ACTION, self::ADMIN_FEEDBACK_NONCE_FIELD);
        echo '<p style="margin-bottom:12px;color:#646970;font-size:12px;">' . esc_html__('This form stores your feedback locally in WordPress. It is not sent to any external service.', 'polski') . '</p>';
        echo '<p style="margin-top:-4px;margin-bottom:12px;color:#646970;font-size:12px;">' . esc_html__('Do not include passwords, licence keys, or customer personal data.', 'polski') . '</p>';
        echo '<p style="margin-bottom:0;"><button type="submit" class="button button-primary">' . esc_html__('Send feedback', 'polski') . '</button></p>';
        echo '</form>';
        echo '</div>';

        echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">';
        echo '<h2 style="margin-top:0;">' . esc_html__('Need help or want to report something?', 'polski') . '</h2>';
        echo '<p style="color:#50575e;">' . esc_html__('Use the path that fits the type of feedback. This keeps support easier for everyone.', 'polski') . '</p>';
        echo '<p><a class="button" href="' . esc_url(self::GITHUB_DISCUSSIONS_URL) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Open Discussions', 'polski') . '</a></p>';
        echo '<p style="margin-top:-4px;color:#646970;font-size:12px;">' . esc_html__('Best for questions, ideas, edge cases, and non-urgent conversations.', 'polski') . '</p>';
        echo '<p><a class="button" href="' . esc_url(self::GITHUB_ISSUES_URL) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Open Issues', 'polski') . '</a></p>';
        echo '<p style="margin-top:-4px;color:#646970;font-size:12px;">' . esc_html__('Best for reproducible bugs and clear technical problems.', 'polski') . '</p>';
        echo '</div>';

        echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">';
        echo '<h2 style="margin-top:0;">' . esc_html__('Deactivate vs uninstall', 'polski') . '</h2>';
        echo '<ul style="margin:0 0 12px 18px;">';
        echo '<li>' . esc_html__('Deactivating the plugin keeps your settings and stored data.', 'polski') . '</li>';
        echo '<li>' . esc_html__('Uninstalling removes plugin files. Data is deleted only when the remove-data setting is enabled.', 'polski') . '</li>';
        echo '<li>' . esc_html__('Your deactivation feedback stays in the local admin log unless data removal on uninstall is enabled.', 'polski') . '</li>';
        echo '</ul>';
        echo '<p style="margin:0;color:#646970;font-size:12px;">';
        echo $removeDataOnUninstall
            ? esc_html__('Current setting: plugin data will be removed on uninstall.', 'polski')
            : esc_html__('Current setting: plugin data will be kept on uninstall.', 'polski');
        echo '</p>';
        echo '</div>';

        echo '</aside>';
    }

    /**
     * Render the overview of available reports and logs.
     */
    private function renderReportsOverview(): void
    {
        $reports = [
            [
                'id' => 'audit',
                'name' => __('Audyt zgodności', 'polski'),
                'desc' => __('Automatyczna weryfikacja najczęstszych problemów prawnych w polskim eCommerce.', 'polski'),
                'icon' => 'dashicons-search',
                'module' => 'site_audit',
            ],
            [
                'id' => 'dsa',
                'name' => __('Raporty DSA', 'polski'),
                'desc' => __('Zarządzanie zgłoszeniami naruszenia treści cyfrowych (Digital Services Act).', 'polski'),
                'icon' => 'dashicons-megaphone',
                'module' => 'dsa_toolkit',
            ],
            [
                'id' => 'incidents',
                'name' => __('Logi incydentów', 'polski'),
                'desc' => __('Rejestr problemów z bezpieczeństwem, wycieków danych i awarii infrastruktury.', 'polski'),
                'icon' => 'dashicons-shield',
                'module' => 'security_incidents',
            ],
            [
                'id' => 'dpa',
                'name' => __('Rejestr DPA (RODO)', 'polski'),
                'desc' => __('Ewidencja umów powierzenia przetwarzania danych osobowych z podmiotami trzecimi.', 'polski'),
                'icon' => 'dashicons-clipboard',
                'module' => 'dpa_tracker',
            ],
            [
                'id' => 'feedback',
                'name' => __('Logi opinii', 'polski'),
                'desc' => __('Deactivation feedback and local admin feedback from the plugin sidebar.', 'polski'),
                'icon' => 'dashicons-format-chat',
                'module' => null, // Always enabled
            ],
        ];

        echo '<p>' . esc_html__('Wybierz raport lub narzędzie, aby sprawdzić status zgodności Twojego sklepu.', 'polski') . '</p>';

        echo '<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:20px;margin-top:20px;">';

        foreach ($reports as $report) {
            $isEnabled = $report['module'] ? ModulesPage::isModuleEnabled($report['module']) : true;
            $opacity = $isEnabled ? '1' : '0.5';
            $url = $isEnabled ? admin_url('admin.php?page=' . self::PAGE_SLUG . '-reports&view=' . $report['id']) : '#';
            
            echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:24px;opacity:' . $opacity . ';position:relative;">';
            echo '<div class="dashicons ' . esc_attr($report['icon']) . '" style="font-size:32px;width:32px;height:32px;margin-bottom:12px;color:#0071a1;"></div>';
            echo '<h3 style="margin:0 0 10px;">' . esc_html($report['name']) . '</h3>';
            echo '<p style="margin:0 0 20px;color:#666;font-size:13px;">' . esc_html($report['desc']) . '</p>';
            
            if ($isEnabled) {
                echo '<a href="' . esc_url($url) . '" class="button button-primary">' . esc_html__('Otwórz raport', 'polski') . '</a>';
            } else {
                echo '<span class="description" style="color:#dc3232;">' . esc_html__('Moduł wyłączony', 'polski') . '</span>';
            }
            echo '</div>';
        }

        echo '</div>';
    }

    private function renderAdminFeedbackLog(): void
    {
        $feedback = get_option(self::ADMIN_FEEDBACK_OPTION, []);

        if (! is_array($feedback)) {
            $feedback = [];
        }

        $feedback = array_reverse($feedback);

        echo '<h3>' . esc_html__('Logi opinii z panelu', 'polski') . '</h3>';

        if ($feedback === []) {
            echo '<p>' . esc_html__('Brak opinii z panelu do wyświetlenia.', 'polski') . '</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Data', 'polski') . '</th>';
        echo '<th>' . esc_html__('Osoba', 'polski') . '</th>';
        echo '<th>' . esc_html__('Temat', 'polski') . '</th>';
        echo '<th>' . esc_html__('Ekran', 'polski') . '</th>';
        echo '<th>' . esc_html__('Wiadomość', 'polski') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($feedback as $entry) {
            echo '<tr>';
            echo '<td>' . esc_html((string) ($entry['timestamp'] ?? '')) . '</td>';
            echo '<td><strong>' . esc_html((string) ($entry['name'] ?? '')) . '</strong><br><small>' . esc_html((string) ($entry['email'] ?? '')) . '</small></td>';
            echo '<td>' . esc_html($this->getAdminFeedbackTopicLabel((string) ($entry['topic'] ?? 'general_feedback'))) . '</td>';
            echo '<td>' . esc_html((string) ($entry['screen'] ?? 'dashboard')) . '</td>';
            echo '<td>' . nl2br(esc_html((string) ($entry['message'] ?? ''))) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function getAdminFeedbackTopicLabel(string $topic): string
    {
        $labels = [
            'general_feedback' => __('General feedback', 'polski'),
            'idea' => __('Feature idea', 'polski'),
            'workflow' => __('Workflow problem', 'polski'),
            'support_question' => __('Support question', 'polski'),
        ];

        return $labels[$topic] ?? $topic;
    }

    private function renderWizardInput(string $name, string $label, string $value, bool $required = false, string $type = 'text'): void
    {
        echo '<tr>';
        echo '<th scope="row"><label for="polski-wizard-' . esc_attr($name) . '">' . esc_html($label) . '</label></th>';
        echo '<td><input class="regular-text" type="' . esc_attr($type) . '" id="polski-wizard-' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . ($required ? ' required' : '') . ' /></td>';
        echo '</tr>';
    }

    private function renderWizardCheckbox(string $name, string $label, bool $checked): void
    {
        echo '<tr>';
        echo '<th scope="row">' . esc_html($label) . '</th>';
        echo '<td><label><input type="checkbox" name="' . esc_attr($name) . '" value="1"' . checked($checked, true, false) . ' /> ' . esc_html__('Włącz', 'polski') . '</label></td>';
        echo '</tr>';
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

        $isWizardComplete = (bool) get_option('polski_wizard_complete', false);

        // Dashboard header
        echo '<div style="background: linear-gradient(135deg, #0073aa 0%, #00a0d2 100%); padding: 40px; border-radius: 12px; color: #fff; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">';
        echo '<h2 style="color:#fff; margin:0 0 10px; font-size:28px; font-weight:700;">' . esc_html__('Dzień dobry!', 'polski') . '</h2>';
        echo '<p style="font-size:16px; margin:0; opacity:0.9;">' . esc_html__('Narzedzia wspomagajace dostosowanie sklepu do polskich wymagan.', 'polski') . '</p>';
        echo '</div>';

        // Quick Setup Alert
        if (! $isWizardComplete || ! $allPagesConfigured) {
            echo '<div style="background:#fff; border-left:4px solid #f0ad4e; padding:20px; margin-bottom:30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">';
            echo '<h3 style="margin-top:0; color:#856404;">' . esc_html__('Dokończ konfigurację', 'polski') . '</h3>';
            echo '<p>' . esc_html__('Niektóre kluczowe elementy Twojego sklepu jeszcze wymagają uwagi.', 'polski') . '</p>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=modules')) . '" class="button button-primary">' . esc_html__('Uzupełnij dane firmy', 'polski') . '</a>';
            echo '</div>';
        }

        // Status cards.
        echo '<div class="polski-dashboard" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;margin-top:20px;">';

        $this->renderStatusCard(
            'WooCommerce',
            defined('WC_VERSION') ? sprintf('v%s - OK', WC_VERSION) : __('Nieaktywna', 'polski'),
            defined('WC_VERSION'),
        );

        $this->renderStatusCard(
            __('Strony prawne', 'polski'),
            /* translators: 1: number of configured legal pages, 2: total number of required legal pages. */
            sprintf(__('%1$d z %2$d gotowych', 'polski'), $configuredCount, count($pageStatus)),
            $allPagesConfigured,
        );

        $this->renderStatusCard(
            __('Dyrektywa Omnibus', 'polski'),
            $omnibusEnabled ? __('Aktywna', 'polski') : __('Wyłączona', 'polski'),
            $omnibusEnabled,
        );

        $this->renderStatusCard(
            __('Analiza sklepu', 'polski'),
            __('Sprawdź raporty', 'polski'),
            null,
            admin_url('admin.php?page=' . self::PAGE_SLUG . '-reports')
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

        // Next steps checklist.
        echo '<div style="margin-top:30px;">';
        echo '<h2>' . esc_html((string) ($generalSettings['admin_next_steps_title'] ?? __('Kolejne kroki', 'polski'))) . '</h2>';
        echo '<ul style="max-width:700px;list-style:none;padding:0;margin:0;">';

        $steps = $this->buildNextSteps($generalSettings, $allPagesConfigured);

        foreach ($steps as $step) {
            $icon = $step['done']
                ? '<span style="color:#46b450;margin-right:8px;">&#10003;</span>'
                : '<span style="color:#ccc;margin-right:8px;">&#9744;</span>';
            $style = $step['done'] ? 'color:#666;' : '';

            echo '<li style="padding:6px 0;border-bottom:1px solid #f0f0f0;' . $style . '">';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML links are built with esc_url()
            echo $icon . $step['html'];
            echo '</li>';
        }

        echo '</ul>';
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
     * Build the next-steps checklist with completion status.
     *
     * @param array<string, mixed> $generalSettings
     * @param bool                 $allPagesConfigured
     * @return list<array{html: string, done: bool}>
     */
    private function buildNextSteps(array $generalSettings, bool $allPagesConfigured): array
    {
        $steps = [];

        // 1. Legal pages.
        $steps[] = [
            'html' => sprintf(
                '%s',
                (string) ($generalSettings['admin_next_steps_publish_pages']
                    ?? __('Opublikuj swoje strony prawne (Regulamin, Polityka prywatnosci, Prawo odstapienia, Reklamacje).', 'polski')),
            ),
            'done' => $allPagesConfigured,
        ];

        // 2. VAT rates.
        $taxEnabled = get_option('woocommerce_calc_taxes') === 'yes';
        /* translators: %s: tax settings URL */
        $taxText = __('Skonfiguruj <a href="%s">stawki podatkowe</a> w WooCommerce dla polskiego VAT (23%%, 8%%, 5%%, 0%%).', 'polski');
        $steps[] = [
            'html' => sprintf(
                (string) ($generalSettings['admin_next_steps_tax'] ?? $taxText),
                esc_url(admin_url('admin.php?page=wc-settings&tab=tax')),
            ),
            'done' => $taxEnabled,
        ];

        // 3. Shipping zones.
        $shippingZones = function_exists('WC') ? \WC_Shipping_Zones::get_zones() : [];
        /* translators: %s: shipping settings URL */
        $shippingText = __('Skonfiguruj <a href="%s">strefy wysylki</a> dla dostaw w Polsce.', 'polski');
        $steps[] = [
            'html' => sprintf(
                (string) ($generalSettings['admin_next_steps_shipping'] ?? $shippingText),
                esc_url(admin_url('admin.php?page=wc-settings&tab=shipping')),
            ),
            'done' => count($shippingZones) > 0,
        ];

        // 4. Product data.
        /* translators: %s: product list URL */
        $productsText = __('Uzupelnij dane produktow - dodaj ceny jednostkowe i czas dostawy w <a href="%s">zakladce Polski</a> przy kazdym produkcie.', 'polski');
        $steps[] = [
            'html' => sprintf(
                (string) ($generalSettings['admin_next_steps_products'] ?? $productsText),
                esc_url(admin_url('edit.php?post_type=product')),
            ),
            'done' => false, // Cannot auto-detect per-product completion.
        ];

        // 5. Checkout test.
        /* translators: %s: checkout URL */
        $checkoutText = __('Przetestuj proces zamowienia - dodaj produkt do koszyka i sprawdz pola wyboru oraz tekst przycisku w <a href="%s">zamowieniu</a>.', 'polski');
        $steps[] = [
            'html' => sprintf(
                (string) ($generalSettings['admin_next_steps_checkout'] ?? $checkoutText),
                esc_url(function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '#'),
            ),
            'done' => (bool) get_option('polski_wizard_complete', false),
        ];

        return $steps;
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
     * @param string    $link Optional link for the card.
     */
    private function renderStatusCard(string $title, string $value, ?bool $ok, string $link = ''): void
    {
        $color = match ($ok) {
            true => '#46b450',
            false => '#dc3232',
            null => '#0073aa', // Use primary color for neutral/action cards
        };

        $styles = 'background:#fff;border:1px solid #ccd0d4;border-left:4px solid ' . $color . ';padding:16px;position:relative;';
        if ($link !== '') {
            $styles .= 'cursor:pointer;transition:transform 0.2s;';
        }

        printf(
            '<div style="%s" %s>
                <h3 style="margin:0 0 8px;color:#23282d;">%s</h3>
                <p style="margin:0;font-size:14px;color:#50575e;">%s</p>',
            esc_attr($styles),
            $link !== '' ? 'onclick="window.location.href=\'' . esc_url($link) . '\'"' : '',
            esc_html($title),
            esc_html($value)
        );

        if ($link !== '') {
            echo '<div style="position:absolute;bottom:10px;right:10px;font-size:12px;color:#0073aa;font-weight:600;">' . esc_html__('Otwórz &rarr;', 'polski') . '</div>';
        }

        echo '</div>';
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

        wp_localize_script('polski-admin', 'polskiAdmin', [
            'restUrl' => rest_url('polski/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'version' => \Polski\VERSION,
            'isWizardComplete' => (bool) get_option('polski_wizard_complete', false),
        ]);

        wp_set_script_translations('polski-admin', 'polski');
    }
}
