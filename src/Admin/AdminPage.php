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
        add_filter('submenu_file', [$this, 'highlightPolskiShellSubmenu'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueMenuIconStyle']);
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
            esc_html__('Settings', 'polski'),
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

        $message = sanitize_textarea_field((string) wp_unslash($_POST['message'] ?? ''));
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
        $name = sanitize_text_field((string) wp_unslash($_POST['name'] ?? ''));
        $email = sanitize_email((string) wp_unslash($_POST['email'] ?? ''));

        $feedback[] = [
            'timestamp' => current_time('mysql'),
            'name' => $name !== '' ? $name : $currentUser->display_name,
            'email' => $email !== '' ? $email : $currentUser->user_email,
            'topic' => sanitize_key((string) wp_unslash($_POST['topic'] ?? 'general_feedback')),
            'screen' => sanitize_key((string) wp_unslash($_POST['admin_screen'] ?? 'dashboard')),
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
            wp_die(esc_html__('Sorry, but you do not have permission to access this page.', 'polski'));
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
            'company_name' => isset($_POST['company_name']) ? sanitize_text_field((string) wp_unslash($_POST['company_name'])) : '',
            'company_address' => isset($_POST['company_address']) ? sanitize_text_field((string) wp_unslash($_POST['company_address'])) : '',
            'company_nip' => isset($_POST['company_nip']) ? sanitize_text_field((string) wp_unslash($_POST['company_nip'])) : '',
            'company_email' => isset($_POST['company_email']) ? sanitize_email((string) wp_unslash($_POST['company_email'])) : '',
            'company_phone' => isset($_POST['company_phone']) ? sanitize_text_field((string) wp_unslash($_POST['company_phone'])) : '',
            'terms_enabled' => ! empty($_POST['terms_enabled']),
            'privacy_enabled' => ! empty($_POST['privacy_enabled']),
            'withdrawal_enabled' => ! empty($_POST['withdrawal_enabled']),
            'digital_waiver_enabled' => ! empty($_POST['digital_waiver_enabled']),
            'marketing_enabled' => ! empty($_POST['marketing_enabled']),
            'order_button_text' => isset($_POST['order_button_text']) ? sanitize_text_field((string) wp_unslash($_POST['order_button_text'])) : '',
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
            wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=modules'));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
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

        $moduleId = isset($_POST['module_id']) ? sanitize_text_field((string) wp_unslash($_POST['module_id'])) : '';
        $groupSlug = isset($_POST['group_slug']) ? sanitize_text_field((string) wp_unslash($_POST['group_slug'])) : '';
        $postSettings = isset($_POST['polski_setting']) ? wp_unslash($_POST['polski_setting']) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- individual values sanitized below

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

        $redirectUrl = $groupSlug !== ''
            ? admin_url('admin.php?page=polski-settings&bucket=' . $groupSlug . '&saved=1&module=' . $moduleId)
            : admin_url('admin.php?page=' . self::PAGE_SLUG . '&saved=1&module=' . $moduleId);
        if ($moduleId !== '') {
            $redirectUrl .= '#polski-module-' . $moduleId;
        }
        wp_safe_redirect($redirectUrl);
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
            __('Dashboard', 'polski'),
            __('Dashboard', 'polski'),
            self::CAPABILITY,
            self::PAGE_SLUG, // Replaces default "Polski" submenu item with Dashboard.
            [$this, 'renderDashboardPage'],
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __('Modules', 'polski'),
            __('Modules', 'polski'),
            self::CAPABILITY,
            self::PAGE_SLUG . '-modules',
            [$this, 'renderPage'],
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __('Reports & Tools', 'polski'),
            __('Reports & Tools', 'polski'),
            self::CAPABILITY,
            self::PAGE_SLUG . '-reports',
            [$this, 'renderReportsHubPage'],
        );

        // Single "Settings" page with one tab per MoSCoW bucket, instead of a
        // flat submenu item per bucket. Pencil links from the modules table point
        // to ?page=polski-settings&bucket=<key> so they always resolve.
        $modulesPage = \Polski\Plugin::instance()->container()->get(\Polski\Admin\ModulesPage::class);

        $hasAnySettings = false;
        foreach ($modulesPage->getBucketedModules() as $bucket) {
            foreach ($bucket['modules'] as $module) {
                if (! empty($module['settings'])) {
                    $hasAnySettings = true;
                    break 2;
                }
            }
        }

        if ($hasAnySettings) {
            add_submenu_page(
                self::PAGE_SLUG,
                __('Settings', 'polski'),
                __('Settings', 'polski'),
                self::CAPABILITY,
                'polski-settings',
                [$this, 'renderSettingsHubPage'],
            );
        }
    }

    /**
     * Highlight the matching Polski submenu entry when using the unified ?tab= shell.
     *
     * @param string|false $submenu_file
     * @param string       $parent_file
     * @return string|false
     */
    public function highlightPolskiShellSubmenu($submenu_file, string $parent_file)
    {
        unset($parent_file);

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin routing context.
        if (
            ! isset($_GET['page'], $_GET['tab'])
            || sanitize_key((string) wp_unslash($_GET['page'])) !== self::PAGE_SLUG
        ) {
            return $submenu_file;
        }

        $tab = sanitize_key((string) wp_unslash($_GET['tab']));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        return match ($tab) {
            'modules' => self::PAGE_SLUG . '-modules',
            'reports' => self::PAGE_SLUG . '-reports',
            default => $submenu_file,
        };
    }

    /**
     * Tab definitions for the unified Polski admin screen (?page=polski&tab=).
     *
     * @return array<string, string> tab slug => label
     */
    private function getShellTabs(): array
    {
        return apply_filters(
            'polski_admin_shell_tabs',
            [
                'dashboard' => __('Dashboard', 'polski'),
                'modules' => __('Modules', 'polski'),
                'reports' => __('Reports & Tools', 'polski'),
            ],
        );
    }

    /**
     * Render a settings page for a group of modules.
     *
     * @param ModulesPage          $modulesPage
     * @param string               $groupName
     * @param array<int, mixed[]>  $modules
     * @param string               $groupSlug
     */
    /**
     * Unified Settings page: every bucket that has modules with settings becomes
     * a tab, so the menu stays short instead of one flat item per bucket.
     */
    public function renderSettingsHubPage(): void
    {
        $modulesPage = \Polski\Plugin::instance()->container()->get(\Polski\Admin\ModulesPage::class);

        $tabs = [];
        foreach ($modulesPage->getBucketedModules() as $bucketKey => $bucket) {
            $withSettings = array_values(array_filter(
                $bucket['modules'],
                static fn (array $module): bool => ! empty($module['settings']),
            ));
            if ($withSettings !== []) {
                $tabs[$bucketKey] = ['label' => $bucket['label'], 'modules' => $withSettings];
            }
        }

        if ($tabs === []) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab routing.
        $active = isset($_GET['bucket']) ? sanitize_key((string) wp_unslash($_GET['bucket'])) : '';
        if (! isset($tabs[$active])) {
            $active = (string) array_key_first($tabs);
        }

        echo '<div class="wrap">';
        echo '<h1>Polski &rsaquo; ' . esc_html__('Settings', 'polski') . '</h1>';

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only success flag.
        if (isset($_GET['saved'])) {
            // phpcs:enable WordPress.Security.NonceVerification.Recommended
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings have been saved.', 'polski') . '</p></div>';
        }

        echo '<nav class="nav-tab-wrapper" style="margin-top:12px;">';
        foreach ($tabs as $bucketKey => $tab) {
            printf(
                '<a href="%s" class="nav-tab%s">%s</a>',
                esc_url(add_query_arg(
                    ['page' => 'polski-settings', 'bucket' => $bucketKey],
                    admin_url('admin.php'),
                )),
                $bucketKey === $active ? ' nav-tab-active' : '',
                esc_html($tab['label']),
            );
        }
        echo '</nav>';

        echo '<p style="margin:12px 0;"><a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=modules')) . '">&larr; ' . esc_html__('Back to modules', 'polski') . '</a></p>';

        $this->renderModuleSettingsFormsList($modulesPage, $tabs[$active]['modules'], $active);

        echo '</div>';
    }

    /**
     * Render the per-module settings forms for one bucket (no page wrapper).
     *
     * @param array<int, array<string, mixed>> $modules
     */
    private function renderModuleSettingsFormsList(ModulesPage $modulesPage, array $modules, string $groupSlug): void
    {
        foreach ($modules as $module) {
            $moduleId = (string) $module['id'];
            $isEnabled = ModulesPage::isModuleEnabled($moduleId);
            $statusLabel = $isEnabled ? __('Enabled', 'polski') : __('Disabled', 'polski');
            $statusColor = $isEnabled ? '#46b450' : '#d63638';

            echo '<div id="polski-module-' . esc_attr($moduleId) . '" class="polski-module-settings-section" style="background:#fff; border:1px solid #ccd0d4; padding:20px; margin-top:20px;">';
            echo '<div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #eee;padding-bottom:10px;margin-bottom:12px;">';
            echo '<h2 style="margin:0;">' . esc_html($module['name']) . '</h2>';
            echo '<span style="font-size:12px;color:' . esc_attr($statusColor) . ';font-weight:600;">' . esc_html($statusLabel) . '</span>';
            echo '</div>';
            echo '<p>' . esc_html($module['description']) . '</p>';

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('polski_save_module_settings', '_polski_module_nonce');
            echo '<input type="hidden" name="action" value="polski_save_module_settings" />';
            echo '<input type="hidden" name="module_id" value="' . esc_attr($moduleId) . '" />';
            echo '<input type="hidden" name="group_slug" value="' . esc_attr($groupSlug) . '" />';

            echo '<table class="form-table" role="presentation"><tbody>';

            foreach ($module['settings'] as $field) {
                $modulesPage->renderSettingsField($field, true);
            }

            echo '</tbody></table>';
            submit_button(__('Save Settings', 'polski'));
            echo '</form>';
            echo '</div>';
        }
    }

    /**
     * Dashboard submenu page handler (unified with modules via ?tab=).
     */
    public function renderDashboardPage(): void
    {
        $tabs = $this->getShellTabs();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'dashboard';
        if (! array_key_exists($tab, $tabs)) {
            $tab = 'dashboard';
        }

        $this->renderPolskiShell($tab);
    }

    /**
     * Enqueue the Polish flag menu icon stylesheet on every admin screen.
     * WordPress overrides SVG fills, so we use a pseudo-element approach.
     */
    public function enqueueMenuIconStyle(): void
    {
        wp_enqueue_style(
            'polski-admin-menu-icon',
            plugins_url('assets/css/admin-menu-icon.css', PLUGIN_FILE),
            ['polski-brand'],
            \Polski\VERSION,
        );
    }

    /**
     * Legacy "Modules" menu slug: redirect to the unified Polski screen.
     */
    public function renderPage(): void
    {
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=modules'));
        exit;
    }

    /**
     * Shared shell: Dashboard, Modules, Reports, and extensible tabs.
     */
    private function renderPolskiShell(string $tab): void
    {
        $generalSettings = $this->getGeneralSettings();

        echo '<div class="wrap polski-admin-shell">';
        echo '<h1>Polski <small>v' . esc_html(\Polski\VERSION) . '</small></h1>';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['polski_pages_generated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html((string) ($generalSettings['admin_pages_generated_notice'] ?? __('Ready! We have generated draft legal pages for you. Please review, adjust, and publish them.', 'polski')));
            echo '</p></div>';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['modules_saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html((string) ($generalSettings['admin_modules_saved_notice'] ?? __('Modules saved.', 'polski')));
            echo '</p></div>';
        }

        $tabs = $this->getShellTabs();

        echo '<nav class="nav-tab-wrapper polski-admin-shell__tabs" style="margin-bottom:20px;">';
        foreach ($tabs as $tabId => $tabLabel) {
            $class = $tab === $tabId ? 'nav-tab nav-tab-active' : 'nav-tab';
            $url = add_query_arg(
                [
                    'page' => self::PAGE_SLUG,
                    'tab' => $tabId,
                ],
                admin_url('admin.php'),
            );
            printf('<a href="%s" class="%s">%s</a>', esc_url($url), esc_attr($class), esc_html($tabLabel));
        }
        echo '</nav>';

        if ($tab === 'dashboard') {
            echo '<div class="polski-admin-shell__layout" style="display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:24px;align-items:start;">';
            echo '<div class="polski-admin-shell__main">';
            $this->renderDashboard();
            echo '</div>';
            $this->renderHelpSidebar('dashboard');
            echo '</div>';
        } elseif ($tab === 'modules') {
            $this->renderModulesTab();
        } elseif ($tab === 'reports') {
            $this->renderReportsHubInner();
        } else {
            do_action('polski_admin_shell_tab_' . $tab);
        }

        echo '</div>';
    }

    private function renderModulesTab(): void
    {
        $modulesPage = \Polski\Plugin::instance()->container()->get(ModulesPage::class);
        $modulesPage->render();
    }

    /**
     * Legacy "Reports" menu slug: redirect to the unified Polski screen.
     */
    public function renderReportsHubPage(): void
    {
        $args = [
            'page' => self::PAGE_SLUG,
            'tab' => 'reports',
        ];

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing flag.
        if (isset($_GET['view'])) {
            $args['view'] = sanitize_key((string) wp_unslash($_GET['view']));
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Reports & tools content inside the unified shell (no duplicate wrap/h1).
     */
    private function renderReportsHubInner(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing flag.
        $view = isset($_GET['view']) ? sanitize_key((string) wp_unslash($_GET['view'])) : 'overview';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ($view === 'overview') {
            echo '<p class="description" style="margin:0 0 16px;">' . esc_html__('Reports, audits, and store setup tools for your shop.', 'polski') . '</p>';
            echo '<div style="display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:24px;align-items:start;">';
            echo '<div>';
            $this->renderReportsOverview();
            echo '</div>';
            $this->renderHelpSidebar('reports');
            echo '</div>';

            return;
        }

        echo '<div class="polski-reports-detail">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=reports')) . '" class="button" style="margin-bottom:20px;">&larr; ' . esc_html__('Back to reports', 'polski') . '</a>';

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
            case 'health':
                $service = \Polski\Plugin::instance()->container()->get(\Polski\Service\StoreHealthMonitorService::class);
                $service->renderPage();
                break;
            case 'consent':
                \Polski\Plugin::instance()->container()->get(\Polski\Admin\ConsentRecordsPage::class)->render();
                break;
            case 'feedback':
                $handler = \Polski\Plugin::instance()->container()->get(\Polski\Admin\DeactivationHandler::class);
                $handler->renderFeedbackLog();
                echo '<div style="margin-top:24px;"></div>';
                $this->renderAdminFeedbackLog();
                break;
        }

        echo '</div>';
    }

    private function renderHelpSidebar(string $screen): void
    {
        $formId = 'polski-feedback-form-' . sanitize_html_class($screen);
        $generalSettings = $this->getGeneralSettings();
        $removeDataOnUninstall = (bool) ($generalSettings['remove_data_on_uninstall'] ?? false);
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only UI flags.
        $feedbackSaved = isset($_GET['polski_feedback_saved']);
        $feedbackError = isset($_GET['polski_feedback_error']);
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        echo '<aside aria-label="' . esc_attr__('Help and feedback', 'polski') . '">';

        echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">';
        echo '<h2 style="margin-top:0;">' . esc_html__('Need help?', 'polski') . '</h2>';
        echo '<p style="color:#50575e;margin-bottom:0;">' . esc_html__('Use the feedback form below for questions, ideas, or workflow problems. Messages stay in WordPress and are not sent to any external service.', 'polski') . '</p>';
        echo '</div>';

        // Feedback form - collapsible
        echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">';
        $feedbackOpen = ($feedbackSaved || $feedbackError) ? ' open' : '';
        echo '<details' . esc_attr($feedbackOpen) . '>';
        echo '<summary style="cursor:pointer;font-weight:600;font-size:14px;padding:4px 0;">' . esc_html__('Share feedback', 'polski') . '</summary>';
        echo '<p style="color:#50575e;margin-top:12px;">' . esc_html__('Not technical? Send a quick note straight from the plugin. Tell us what feels unclear, what should be easier, or what we should add next.', 'polski') . '</p>';
        if ($feedbackSaved) {
            echo '<div class="notice notice-success inline"><p>' . esc_html__('Thanks, your feedback has been saved.', 'polski') . '</p></div>';
        } elseif ($feedbackError) {
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
        echo '</details>';
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
                'id' => 'health',
                'name' => __('Kondycja sklepu', 'polski'),
                'desc' => __('Ciągły monitoring błędów krytycznych, nieudanych płatności i anomalii sprzedaży, z alertami.', 'polski'),
                'icon' => 'dashicons-heart',
                'module' => 'store_health',
            ],
            [
                'id' => 'dpa',
                'name' => __('Rejestr DPA (RODO)', 'polski'),
                'desc' => __('Ewidencja umów powierzenia przetwarzania danych osobowych z podmiotami trzecimi.', 'polski'),
                'icon' => 'dashicons-clipboard',
                'module' => 'dpa_tracker',
            ],
            [
                'id' => 'consent',
                'name' => __('Rejestr zgód', 'polski'),
                'desc' => __('Zapisane decyzje odwiedzających z baneru zgód, z eksportem CSV.', 'polski'),
                'icon' => 'dashicons-list-view',
                'module' => 'consent_manager',
            ],
            [
                'id' => 'withdrawals',
                'name' => __('Odstąpienia', 'polski'),
                'desc' => __('Wnioski o odstąpienie od umowy i zwroty: lista, rejestracja ręczna i ustawienia.', 'polski'),
                'icon' => 'dashicons-undo',
                'module' => null,
                'url' => admin_url('admin.php?page=polski-withdrawals'),
            ],
            [
                'id' => 'cra_incidents',
                'name' => __('Incydenty CRA', 'polski'),
                'desc' => __('Rejestr podatności, naruszeń i awarii na potrzeby Cyber Resilience Act, ze statusami i eksportem CSV.', 'polski'),
                'icon' => 'dashicons-shield-alt',
                'module' => 'cra_readiness',
                'url' => admin_url('admin.php?page=polski-cra-incidents'),
            ],
            [
                'id' => 'sbom',
                'name' => __('SBOM', 'polski'),
                'desc' => __('Zestawienie komponentów oprogramowania (Software Bill of Materials) na potrzeby CRA.', 'polski'),
                'icon' => 'dashicons-media-code',
                'module' => 'sbom',
                'url' => admin_url('admin.php?page=polski-sbom'),
            ],
            [
                'id' => 'complaint_template',
                'name' => __('Szablon reklamacji', 'polski'),
                'desc' => __('Generator i ustawienia szablonu reklamacji dla klientów.', 'polski'),
                'icon' => 'dashicons-media-document',
                'module' => 'complaint_template',
                'url' => admin_url('admin.php?page=polski-complaint-template'),
            ],
            [
                'id' => 'rodo_training',
                'name' => __('Szkolenia RODO', 'polski'),
                'desc' => __('Materiały szkoleniowe RODO dla zespołu sklepu.', 'polski'),
                'icon' => 'dashicons-welcome-learn-more',
                'module' => 'rodo_training_docs',
                'url' => admin_url('admin.php?page=polski-rodo-training'),
            ],
            [
                'id' => 'feedback',
                'name' => __('Feedback Logs', 'polski'),
                'desc' => __('Deactivation feedback and local admin feedback from the plugin sidebar.', 'polski'),
                'icon' => 'dashicons-format-chat',
                'module' => null, // Always enabled
            ],
        ];

        echo '<p>' . esc_html__('Select a report or tool to review your store setup status.', 'polski') . '</p>';

        echo '<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:20px;margin-top:20px;">';

        foreach ($reports as $report) {
            $isEnabled = $report['module'] ? ModulesPage::isModuleEnabled($report['module']) : true;
            $opacity = $isEnabled ? '1' : '0.5';
            $url = ! $isEnabled
                ? '#'
                : (isset($report['url'])
                    ? (string) $report['url']
                    : add_query_arg(
                        [
                            'page' => self::PAGE_SLUG,
                            'tab' => 'reports',
                            'view' => $report['id'],
                        ],
                        admin_url('admin.php'),
                    ));

            $moduleToggleUrl = '';
            if ($report['module'] !== null) {
                $moduleToggleUrl = esc_url(
                    add_query_arg(
                        [
                            'page' => self::PAGE_SLUG,
                            'tab' => 'modules',
                        ],
                        admin_url('admin.php'),
                    ),
                ) . '#polski-module-' . sanitize_key($report['module']);
            }

            echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:24px;opacity:' . esc_attr($opacity) . ';position:relative;">';
            echo '<div class="dashicons ' . esc_attr($report['icon']) . '" style="font-size:32px;width:32px;height:32px;margin-bottom:12px;color:#0071a1;"></div>';
            echo '<h3 style="margin:0 0 10px;">' . esc_html($report['name']) . '</h3>';
            echo '<p style="margin:0 0 20px;color:#666;font-size:13px;">' . esc_html($report['desc']) . '</p>';

            if ($isEnabled) {
                echo '<a href="' . esc_url($url) . '" class="button button-primary">' . esc_html__('Open Report', 'polski') . '</a>';
            } else {
                echo '<p class="description" style="margin:0 0 12px;color:#646970;">' . esc_html__('This report needs its module enabled first (optional features are off by default).', 'polski') . '</p>';
                if ($moduleToggleUrl !== '') {
                    echo '<a href="' . esc_url($moduleToggleUrl) . '" class="button button-primary">' . esc_html__('Enable module', 'polski') . '</a>';
                } else {
                    echo '<span class="description" style="color:#dc3232;">' . esc_html__('Module disabled', 'polski') . '</span>';
                }
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

        echo '<h3>' . esc_html__('Admin Feedback Logs', 'polski') . '</h3>';

        if ($feedback === []) {
            echo '<p>' . esc_html__('No internal feedback to display.', 'polski') . '</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Date', 'polski') . '</th>';
        echo '<th>' . esc_html__('Person', 'polski') . '</th>';
        echo '<th>' . esc_html__('Topic', 'polski') . '</th>';
        echo '<th>' . esc_html__('Screen', 'polski') . '</th>';
        echo '<th>' . esc_html__('Message', 'polski') . '</th>';
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
        echo '<h2 style="color:#fff; margin:0 0 10px; font-size:28px; font-weight:700;">' . esc_html__('Hello!', 'polski') . '</h2>';
        echo '<p style="font-size:16px; margin:0; opacity:0.9;">' . esc_html__('Narzędzia wspomagające dostosowanie sklepu do polskich wymagań.', 'polski') . '</p>';
        echo '</div>';

        // Quick Setup Alert
        if (! $isWizardComplete || ! $allPagesConfigured) {
            echo '<div style="background:#fff; border-left:4px solid #f0ad4e; padding:20px; margin-bottom:30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">';
            echo '<h3 style="margin-top:0; color:#856404;">' . esc_html__('Finish configuration', 'polski') . '</h3>';
            echo '<p>' . esc_html__('Some key elements of your store still require attention.', 'polski') . '</p>';
            echo '<ul style="margin:10px 0 15px 20px;color:#856404;">';
            if (! $allPagesConfigured) {
                echo '<li>' . esc_html__('Publish legal pages (Terms, Privacy Policy, Right of Withdrawal)', 'polski') . '</li>';
            }
            if (! $isWizardComplete) {
                echo '<li>' . esc_html__('Go through the checklist below', 'polski') . '</li>';
            }
            echo '</ul>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '-group-content_trust')) . '" class="button button-primary">' . esc_html__('Complete product data', 'polski') . '</a> ';
            echo '<a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=modules')) . '" class="button">' . esc_html__('Manage modules', 'polski') . '</a>';
            echo '</div>';
        } else {
            // Wizard already finished: offer a safety-net relaunch link for merchants
            // who want to rerun the guided setup without digging through the modules page.
            echo '<div class="polski-dashboard-relaunch" style="margin-bottom:24px;font-size:13px;color:#646970;">';
            echo '<a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=wizard#/setup-wizard')) . '" class="button button-small">';
            echo '<span class="dashicons dashicons-update" style="vertical-align:text-bottom;"></span> ';
            echo esc_html__('Relaunch setup wizard', 'polski');
            echo '</a>';
            echo '</div>';
        }

        // Status cards.
        echo '<div class="polski-dashboard" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;margin-top:20px;">';

        $this->renderStatusCard(
            'WooCommerce',
            defined('WC_VERSION') ? sprintf('v%s - OK', WC_VERSION) : __('Inactive', 'polski'),
            defined('WC_VERSION'),
        );

        $this->renderStatusCard(
            __('Legal Pages', 'polski'),
            /* translators: 1: number of configured legal pages, 2: total number of required legal pages. */
            sprintf(__('%1$d of %2$d ready', 'polski'), $configuredCount, count($pageStatus)),
            $allPagesConfigured,
        );

        $this->renderStatusCard(
            __('Omnibus Directive', 'polski'),
            $omnibusEnabled ? __('Active', 'polski') : __('Disabled', 'polski'),
            $omnibusEnabled,
        );

        $this->renderStatusCard(
            __('Store Analysis', 'polski'),
            __('Check reports', 'polski'),
            null,
            add_query_arg(
                [
                    'page' => self::PAGE_SLUG,
                    'tab' => 'reports',
                ],
                admin_url('admin.php'),
            )
        );

        echo '</div>';

        // Legal pages section.
        echo '<div style="margin-top:30px;">';
        echo '<h2>' . esc_html((string) ($generalSettings['admin_legal_pages_section_title'] ?? __('Legal Pages', 'polski'))) . '</h2>';

        if ($anyPageExists) {
            // Show page list with edit links.
            echo '<table class="widefat striped" style="max-width:600px;">';
            echo '<thead><tr><th>' . esc_html((string) ($generalSettings['admin_legal_pages_table_page'] ?? __('Page', 'polski'))) . '</th><th>' . esc_html((string) ($generalSettings['admin_legal_pages_table_status'] ?? __('Status', 'polski'))) . '</th><th></th></tr></thead><tbody>';

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
                        'publish' => '<span style="color:#46b450;">' . esc_html((string) ($generalSettings['admin_legal_pages_published'] ?? __('Published', 'polski'))) . '</span>',
                        'draft' => '<span style="color:#f0ad4e;">' . esc_html((string) ($generalSettings['admin_legal_pages_draft'] ?? __('Draft', 'polski'))) . '</span>',
                        default => esc_html($post->post_status),
                    };
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $statusLabel contains pre-escaped HTML spans
                    echo '<td>' . $statusLabel . '</td>';
                    echo '<td><a href="' . esc_url(get_edit_post_link($pageId) ?: '#') . '" class="button button-small">' . esc_html((string) ($generalSettings['admin_edit_button_text'] ?? __('Edit', 'polski'))) . '</a></td>';
                } else {
                    echo '<td><span style="color:#dc3232;">' . esc_html((string) ($generalSettings['admin_legal_pages_missing'] ?? __('Not created', 'polski'))) . '</span></td>';
                    echo '<td></td>';
                }

                echo '</tr>';
            }

            echo '</tbody></table>';

            // Show generate button only if some pages are missing.
            if (! $allPagesConfigured) {
                $this->renderGenerateButton();
            }
        } else {
            // No pages exist at all - show generate button.
            echo '<p>' . esc_html((string) ($generalSettings['admin_generate_pages_empty_text'] ?? __('No legal pages have been created yet. Generate them to get started.', 'polski'))) . '</p>';
            $this->renderGenerateButton();
        }

        echo '</div>';

        // Next steps checklist.
        echo '<div style="margin-top:30px;">';
        echo '<h2>' . esc_html((string) ($generalSettings['admin_next_steps_title'] ?? __('Next steps', 'polski'))) . '</h2>';
        echo '<ul style="max-width:700px;list-style:none;padding:0;margin:0;">';

        $steps = $this->buildNextSteps($generalSettings, $allPagesConfigured);

        foreach ($steps as $step) {
            $icon = $step['done']
                ? '<span style="color:#46b450;margin-right:8px;">&#10003;</span>'
                : '<span style="color:#ccc;margin-right:8px;">&#9744;</span>';
            $style = $step['done'] ? 'color:#666;' : '';

            echo '<li style="padding:6px 0;border-bottom:1px solid #f0f0f0;' . esc_attr($style) . '">';
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
                    ?? __('Publish your legal pages (Terms, Privacy Policy, Right of Withdrawal, Complaints).', 'polski')),
            ),
            'done' => $allPagesConfigured,
        ];

        // 2. VAT rates.
        $taxEnabled = get_option('woocommerce_calc_taxes') === 'yes';
        /* translators: %s: tax settings URL */
        $taxText = __('Configure <a href="%s">tax rates</a> in WooCommerce for Polish VAT (23%%, 8%%, 5%%, 0%%).', 'polski');
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
        $shippingText = __('Configure <a href="%s">shipping zones</a> for deliveries in Poland.', 'polski');
        $steps[] = [
            'html' => sprintf(
                (string) ($generalSettings['admin_next_steps_shipping'] ?? $shippingText),
                esc_url(admin_url('admin.php?page=wc-settings&tab=shipping')),
            ),
            'done' => count($shippingZones) > 0,
        ];

        // 4. Product data.
        /* translators: %s: product list URL */
        $productsText = __('Complete product data - add unit prices and delivery time in the <a href="%s">Polski tab</a> for each product.', 'polski');
        $steps[] = [
            'html' => sprintf(
                (string) ($generalSettings['admin_next_steps_products'] ?? $productsText),
                esc_url(admin_url('edit.php?post_type=product')),
            ),
            'done' => false, // Cannot auto-detect per-product completion.
        ];

        // 5. Checkout test.
        /* translators: %s: checkout URL */
        $checkoutText = __('Test the order process - add a product to the cart and check the checkboxes and button text in <a href="%s">checkout</a>.', 'polski');
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
            esc_html((string) ($generalSettings['admin_generate_pages_button_text'] ?? __('Generate legal pages', 'polski'))),
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
            echo '<div style="position:absolute;bottom:10px;right:10px;font-size:12px;color:#0073aa;font-weight:600;">' . esc_html__('Open &rarr;', 'polski') . '</div>';
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
            ['wp-components', 'polski-brand'],
            $asset['version'],
        );

        wp_localize_script('polski-admin', 'polskiAdmin', [
            'restUrl' => rest_url('polski/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'version' => \Polski\VERSION,
            'isWizardComplete' => (bool) get_option('polski_wizard_complete', false),
            'adminUrl' => admin_url('admin.php?page=' . self::PAGE_SLUG),
        ]);

        wp_set_script_translations('polski-admin', 'polski', \Polski\PLUGIN_DIR . '/languages');
    }
}
