<?php

declare(strict_types=1);

namespace Polski\Admin;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * Setup wizard - a persistent "Setup" tab that turns "40+ modules, which do I
 * need?" into "tell me your store, I'll switch the right ones on".
 *
 * The owner picks a scenario (their kind of store); applying it enables the
 * matching modules in one click. It is NON-DESTRUCTIVE - it only ever switches
 * modules ON, never off - so it is safe to run repeatedly and to combine
 * scenarios. Each card shows how many of its modules are already enabled, so the
 * wizard doubles as an at-a-glance "am I set up for X?" check.
 *
 * Scenario module lists reference real module ids from ModulesPage::getModules().
 */
final class SetupWizard implements HasHooks
{
    public function __construct(
        private readonly ModulesPage $modules,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('admin_post_polski_apply_scenario', [$this, 'handleApply']);
        // Render under the "Setup" shell tab (AdminPage fires this for unknown tabs).
        add_action('polski_admin_shell_tab_setup', [$this, 'render']);
        // Add the tab to the shell nav, right after Dashboard.
        add_filter('polski_admin_shell_tabs', [$this, 'addTab']);
    }

    /**
     * @param array<string, string> $tabs
     * @return array<string, string>
     */
    public function addTab(array $tabs): array
    {
        $out = [];
        foreach ($tabs as $slug => $label) {
            $out[$slug] = $label;
            if ($slug === 'dashboard') {
                $out['setup'] = __('Setup', 'polski');
            }
        }
        if (! isset($out['setup'])) {
            $out['setup'] = __('Setup', 'polski');
        }
        return $out;
    }

    /**
     * Curated store scenarios. Each maps to real module ids; unknown ids are
     * simply skipped when applied, so this stays robust as modules change.
     *
     * @return array<string, array{label: string, description: string, icon: string, modules: list<string>}>
     */
    public function getScenarios(): array
    {
        return [
            'compliance' => [
                'label' => __('Polish legal baseline', 'polski'),
                'description' => __('What almost every Polish store needs: GPSR producer data, the Omnibus lowest price, GDPR cookie consent and logging, the right of withdrawal, the "obligation to pay" button wording, and checkout legal checkboxes. Provides tools, not legal advice.', 'polski'),
                'icon' => 'dashicons-shield',
                'modules' => ['gpsr', 'omnibus', 'consent_manager', 'consent_logging', 'withdrawal', 'checkout_button', 'legal_checkboxes', 'legal_pages', 'tax_display'],
            ],
            'food' => [
                'label' => __('Food & grocery', 'polski'),
                'description' => __('Selling food or drink: allergen and nutrition information, price per unit (per kg / per litre), delivery-time estimates, GPSR data and green-claims support.', 'polski'),
                'icon' => 'dashicons-carrot',
                'modules' => ['food_module', 'unit_price', 'delivery_time', 'gpsr', 'green_claims'],
            ],
            'digital' => [
                'label' => __('Digital goods & downloads', 'polski'),
                'description' => __('Selling downloads, licences or services: the digital-content withdrawal waiver and legal checkboxes, cookie consent and logging, plus double opt-in for accounts.', 'polski'),
                'icon' => 'dashicons-download',
                'modules' => ['legal_checkboxes', 'withdrawal', 'consent_manager', 'consent_logging', 'double_opt_in'],
            ],
            'b2b' => [
                'label' => __('B2B & wholesale', 'polski'),
                'description' => __('Selling to businesses: NIP (tax ID) lookup with GUS company data at checkout, minimum-order rules, VAT display options and OSS threshold monitoring.', 'polski'),
                'icon' => 'dashicons-businessman',
                'modules' => ['nip_lookup', 'minimum_order', 'tax_display', 'oss_observer'],
            ],
            'fashion' => [
                'label' => __('Fashion & apparel', 'polski'),
                'description' => __('Browse-heavy catalogues: brands and manufacturers, wishlist and compare, quick view, gallery zoom and a product carousel.', 'polski'),
                'icon' => 'dashicons-tag',
                'modules' => ['brands', 'manufacturer', 'wishlist', 'compare', 'quick_view', 'gallery_zoom', 'product_slider_carousel'],
            ],
            'conversion' => [
                'label' => __('Conversion boost', 'polski'),
                'description' => __('Help shoppers find and buy faster: predictive search, AJAX product filters, wishlist and compare, quick view, product badges, back-in-stock waitlist and review reminders.', 'polski'),
                'icon' => 'dashicons-cart',
                'modules' => ['ajax_search', 'ajax_filters', 'wishlist', 'compare', 'quick_view', 'badge_management', 'waitlist', 'review_requests'],
            ],
        ];
    }

    /**
     * Apply a scenario: enable its modules (non-destructive) and redirect back
     * to the Setup tab with a result notice.
     */
    public function handleApply(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to do this.', 'polski'), '', ['response' => 403]);
        }

        check_admin_referer('polski_apply_scenario');

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
        $scenarioId = isset($_POST['scenario']) ? sanitize_key((string) wp_unslash($_POST['scenario'])) : '';
        $scenarios = $this->getScenarios();

        $applied = 0;
        if (isset($scenarios[$scenarioId])) {
            $applied = $this->modules->enableModules($scenarios[$scenarioId]['modules']);
        }

        wp_safe_redirect(add_query_arg(
            [
                'page' => 'polski',
                'tab' => 'setup',
                'polski_scenario' => $scenarioId,
                'polski_applied' => $applied,
            ],
            admin_url('admin.php'),
        ));
        exit;
    }

    /**
     * Render the Setup tab: a grid of scenario cards.
     */
    public function render(): void
    {
        $this->renderStyles();

        $scenarios = $this->getScenarios();
        $nameById = array_column($this->modules->getModules(), 'name', 'id');

        // Result notice after applying a scenario.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display.
        $appliedScenario = isset($_GET['polski_scenario']) ? sanitize_key((string) wp_unslash($_GET['polski_scenario'])) : '';
        if ($appliedScenario !== '' && isset($scenarios[$appliedScenario])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $n = isset($_GET['polski_applied']) ? max(0, (int) $_GET['polski_applied']) : 0;
            $label = $scenarios[$appliedScenario]['label'];
            $message = $n > 0
                /* translators: 1: number of modules, 2: scenario name */
                ? sprintf(_n('Enabled %1$d module for "%2$s".', 'Enabled %1$d modules for "%2$s".', $n, 'polski'), $n, $label)
                /* translators: %s: scenario name */
                : sprintf(__('Everything for "%s" was already enabled.', 'polski'), $label);
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html($message),
            );
        }

        echo '<div class="polski-setup">';
        echo '<p class="polski-setup__intro">' . esc_html__('Tell us what kind of store you run and we will switch on the modules that fit. Applying a scenario only ever turns modules on - it never disables anything - so it is safe to combine scenarios and to re-run.', 'polski') . '</p>';

        echo '<div class="polski-setup__grid">';
        foreach ($scenarios as $id => $scenario) {
            $moduleIds = $scenario['modules'];
            $total = count($moduleIds);
            $enabled = 0;
            foreach ($moduleIds as $mid) {
                if (ModulesPage::isModuleEnabled($mid)) {
                    $enabled++;
                }
            }
            $allOn = $total > 0 && $enabled === $total;

            echo '<div class="polski-setup__card">';
            echo '<div class="polski-setup__head">';
            echo '<span class="dashicons ' . esc_attr($scenario['icon']) . '" aria-hidden="true"></span>';
            echo '<h3>' . esc_html($scenario['label']) . '</h3>';
            echo '</div>';

            echo '<p class="polski-setup__desc">' . esc_html($scenario['description']) . '</p>';

            // The modules this scenario covers, with current state.
            echo '<ul class="polski-setup__modules">';
            foreach ($moduleIds as $mid) {
                $on = ModulesPage::isModuleEnabled($mid);
                $name = isset($nameById[$mid]) ? (string) $nameById[$mid] : $mid;
                printf(
                    '<li class="%1$s"><span class="dashicons %2$s" aria-hidden="true"></span>%3$s</li>',
                    $on ? 'is-on' : 'is-off',
                    $on ? 'dashicons-yes-alt' : 'dashicons-marker',
                    esc_html($name),
                );
            }
            echo '</ul>';

            echo '<div class="polski-setup__foot">';
            printf(
                '<span class="polski-setup__count">%s</span>',
                esc_html(sprintf(
                    /* translators: 1: enabled count, 2: total count */
                    __('%1$d of %2$d enabled', 'polski'),
                    $enabled,
                    $total,
                )),
            );

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="polski_apply_scenario">';
            echo '<input type="hidden" name="scenario" value="' . esc_attr($id) . '">';
            wp_nonce_field('polski_apply_scenario');
            printf(
                '<button type="submit" class="button %1$s"%2$s>%3$s</button>',
                $allOn ? '' : 'button-primary',
                $allOn ? ' disabled aria-disabled="true"' : '',
                $allOn ? esc_html__('All enabled', 'polski') : esc_html__('Apply', 'polski'),
            );
            echo '</form>';
            echo '</div>';

            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    }

    private function renderStyles(): void
    {
        wp_enqueue_style(
            'polski-admin-setup',
            plugins_url('assets/css/admin-setup.css', \Polski\PLUGIN_FILE),
            ['polski-brand'],
            \Polski\VERSION,
        );
    }
}
