<?php

declare(strict_types=1);
namespace Polski\Admin;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Service\DigitalConsentService;

/**
 * Centralised settings page for the withdrawal module.
 *
 * All values are persisted to the existing `polski_withdrawal` option (shared
 * with the FREE service and Pro extensions). Sections are intentionally
 * server-rendered with the WordPress Settings API - no React build step needed.
 */
final class WithdrawalSettingsPage implements HasHooks
{
    private const CAPABILITY = 'manage_woocommerce';
    private const PAGE_SLUG = 'polski-withdrawal-settings';
    private const OPTION = 'polski_withdrawal';
    private const SETTINGS_GROUP = 'polski_withdrawal_settings';

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'registerMenu'], 66);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Enqueue the presentation stylesheet only on this settings screen.
     */
    public function enqueueAssets(string $hookSuffix): void
    {
        if (! str_contains($hookSuffix, self::PAGE_SLUG)) {
            return;
        }

        // `polski-brand` (the --pl-* token layer) is registered admin-wide by
        // BrandAssets at priority 8; we only depend on it here.
        wp_enqueue_style(
            'polski-admin-withdrawal',
            plugins_url('assets/css/admin-withdrawal.css', \Polski\PLUGIN_FILE),
            ['polski-brand'],
            \Polski\VERSION,
        );
    }

    public function registerMenu(): void
    {
        // Hidden (parent = null): routable by URL, reached from the Withdrawals
        // page header and the Reports & Tools hub, not shown as a flat menu item.
        add_submenu_page(
            '',
            __('Withdrawal settings', 'polski'),
            __('Withdrawal settings', 'polski'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [$this, 'renderPage'],
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
                'default' => [],
            ],
        );

        register_setting(
            self::SETTINGS_GROUP,
            'polski_ai_features_enabled',
            [
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitizeAiFeaturesEnabled'],
                'default' => 'no',
            ],
        );

        register_setting(
            self::SETTINGS_GROUP,
            'polski_ai_label_content',
            [
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitizeAiFeaturesEnabled'],
                'default' => 'yes',
            ],
        );
    }

    public function sanitizeAiFeaturesEnabled(mixed $value): string
    {
        return $value === 'yes' || $value === '1' || $value === true ? 'yes' : 'no';
    }

    /**
     * @param array<string, mixed>|null $input
     * @return array<string, mixed>
     */
    public function sanitize(?array $input): array
    {
        $input = is_array($input) ? $input : [];

        $clean = get_option(self::OPTION, []);
        $clean = is_array($clean) ? $clean : [];

        $clean['period_days'] = isset($input['period_days']) ? max(1, (int) $input['period_days']) : 14;

        $clean['trigger_statuses'] = [];
        if (isset($input['trigger_statuses']) && is_array($input['trigger_statuses'])) {
            foreach ($input['trigger_statuses'] as $status) {
                $key = sanitize_key((string) $status);
                if ($key !== '') {
                    $clean['trigger_statuses'][] = str_starts_with($key, 'wc-') ? substr($key, 3) : $key;
                }
            }
        }
        if ($clean['trigger_statuses'] === []) {
            $clean['trigger_statuses'] = ['completed'];
        }

        $mode = isset($input['digital_consent_mode']) ? sanitize_key((string) $input['digital_consent_mode']) : DigitalConsentService::MODE_OPTIONAL;
        $clean['digital_consent_mode'] = in_array(
            $mode,
            [DigitalConsentService::MODE_REQUIRED, DigitalConsentService::MODE_OPTIONAL, DigitalConsentService::MODE_HIDDEN],
            true,
        ) ? $mode : DigitalConsentService::MODE_OPTIONAL;

        $clean['digital_consent_label'] = isset($input['digital_consent_label'])
            ? sanitize_textarea_field((string) $input['digital_consent_label'])
            : ($clean['digital_consent_label'] ?? '');

        $clean['digital_download_verification'] = ! empty($input['digital_download_verification']) ? '1' : '';

        $clean['lookup_page_id'] = isset($input['lookup_page_id']) ? (int) $input['lookup_page_id'] : 0;
        $clean['my_account_endpoint_slug'] = isset($input['my_account_endpoint_slug'])
            ? sanitize_title((string) $input['my_account_endpoint_slug'])
            : '';

        $clean['annex_locale'] = isset($input['annex_locale']) ? sanitize_key((string) $input['annex_locale']) : '';

        $bundleMode = isset($input['bundle_refund_mode']) ? sanitize_key((string) $input['bundle_refund_mode']) : 'whole_bundle';
        $clean['bundle_refund_mode'] = in_array($bundleMode, ['whole_bundle', 'proportional', 'remove_discount'], true)
            ? $bundleMode
            : 'whole_bundle';

        return $clean;
    }

    public function renderPage(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            return;
        }

        $settings = get_option(self::OPTION, []);
        $settings = is_array($settings) ? $settings : [];
        $statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [];
        $selectedTriggers = (array) ($settings['trigger_statuses'] ?? ['completed']);
        ?>
        <div class="wrap polski-withdrawal-settings">
            <h1><?php esc_html_e('Withdrawal settings', 'polski'); ?></h1>
            <p class="description polski-withdrawal-intro"><?php esc_html_e('Central place for the consumer right of withdrawal flow (Directive 2011/83/EU as amended by 2023/2673).', 'polski'); ?></p>

            <form method="post" action="options.php">
                <?php settings_fields(self::SETTINGS_GROUP); ?>

                <section class="polski-withdrawal-card">
                <h2><?php esc_html_e('General', 'polski'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="polski_period_days"><?php esc_html_e('Withdrawal period (days)', 'polski'); ?></label></th>
                        <td>
                            <input type="number" min="1" id="polski_period_days" name="<?php echo esc_attr(self::OPTION); ?>[period_days]" value="<?php echo esc_attr((string) ($settings['period_days'] ?? 14)); ?>" class="small-text">
                            <p class="description"><?php esc_html_e('Standard EU period is 14 days. Increase if your store offers a longer voluntary period.', 'polski'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Trigger statuses', 'polski'); ?></th>
                        <td>
                            <?php foreach ($statuses as $slug => $label) :
                                $bare = str_starts_with((string) $slug, 'wc-') ? substr((string) $slug, 3) : (string) $slug;
                            ?>
                                <label class="polski-withdrawal-option">
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[trigger_statuses][]" value="<?php echo esc_attr($bare); ?>" <?php checked(in_array($bare, $selectedTriggers, true)); ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description"><?php esc_html_e('The withdrawal 14-day clock starts when an order enters any of the selected statuses.', 'polski'); ?></p>
                        </td>
                    </tr>
                </table>
                </section>

                <section class="polski-withdrawal-card">
                <h2><?php esc_html_e('Guest flow', 'polski'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="polski_lookup_page"><?php esc_html_e('Guest lookup page', 'polski'); ?></label></th>
                        <td>
                            <?php
                            $polski_lookup_args = [
                                'name' => self::OPTION . '[lookup_page_id]',
                                'id' => 'polski_lookup_page',
                                'selected' => (int) ($settings['lookup_page_id'] ?? 0),
                                'show_option_none' => esc_html__('- none -', 'polski'),
                                'option_none_value' => 0,
                            ];
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_dropdown_pages handles its own escaping.
                            wp_dropdown_pages($polski_lookup_args);
                            ?>
                            <p class="description"><?php esc_html_e('Page containing the [polski_withdrawal_lookup] shortcode. Required for guests to file withdrawals.', 'polski'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="polski_my_account_endpoint"><?php esc_html_e('My Account endpoint slug', 'polski'); ?></label></th>
                        <td>
                            <input type="text" id="polski_my_account_endpoint" name="<?php echo esc_attr(self::OPTION); ?>[my_account_endpoint_slug]" value="<?php echo esc_attr((string) ($settings['my_account_endpoint_slug'] ?? '')); ?>" class="regular-text" placeholder="polski-withdrawals">
                            <p class="description"><?php esc_html_e('Leave blank for the default "polski-withdrawals". Flush permalinks after changing.', 'polski'); ?></p>
                        </td>
                    </tr>
                </table>
                </section>

                <section class="polski-withdrawal-card">
                <h2><?php esc_html_e('Digital products (Art. 16(m))', 'polski'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><?php esc_html_e('Consent mode', 'polski'); ?></th>
                        <td>
                            <?php
                            $modes = [
                                DigitalConsentService::MODE_REQUIRED => __('Required - block checkout unless the consumer ticks the consent box.', 'polski'),
                                DigitalConsentService::MODE_OPTIONAL => __('Optional - show an unchecked consent box. Only ticked orders become exempt.', 'polski'),
                                DigitalConsentService::MODE_HIDDEN => __('Hidden - do not collect consent. Digital orders retain the right of withdrawal.', 'polski'),
                            ];
                            $currentMode = (string) ($settings['digital_consent_mode'] ?? DigitalConsentService::MODE_OPTIONAL);
                            foreach ($modes as $value => $label) :
                            ?>
                                <label class="polski-withdrawal-option">
                                    <input type="radio" name="<?php echo esc_attr(self::OPTION); ?>[digital_consent_mode]" value="<?php echo esc_attr($value); ?>" <?php checked($currentMode, $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="polski_consent_label"><?php esc_html_e('Consent text', 'polski'); ?></label></th>
                        <td>
                            <textarea id="polski_consent_label" name="<?php echo esc_attr(self::OPTION); ?>[digital_consent_label]" rows="3" class="large-text"><?php echo esc_textarea((string) ($settings['digital_consent_label'] ?? '')); ?></textarea>
                            <p class="description"><?php esc_html_e('Leave blank to use the default Polish wording aligned with Art. 38 pkt 13.', 'polski'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Pro: download verification', 'polski'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[digital_download_verification]" value="1" <?php checked(! empty($settings['digital_download_verification'])); ?>>
                                <?php esc_html_e('Allow withdrawal of digital products that the consumer never downloaded (Pro).', 'polski'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                </section>

                <section class="polski-withdrawal-card">
                <h2><?php esc_html_e('Annex generator', 'polski'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="polski_annex_locale"><?php esc_html_e('Annex locale', 'polski'); ?></label></th>
                        <td>
                            <select id="polski_annex_locale" name="<?php echo esc_attr(self::OPTION); ?>[annex_locale]">
                                <option value=""><?php esc_html_e('Auto (use site locale)', 'polski'); ?></option>
                                <?php foreach (['pl' => 'Polski', 'de' => 'Deutsch', 'at' => 'Deutsch (Österreich)', 'fr' => 'Français', 'nl' => 'Nederlands', 'it' => 'Italiano', 'es' => 'Español', 'eu' => 'English (EU fallback)'] as $code => $label) : ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected((string) ($settings['annex_locale'] ?? ''), $code); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Pro extension provides non-Polish languages for Annex I(B).', 'polski'); ?></p>
                        </td>
                    </tr>
                </table>
                </section>

                <section class="polski-withdrawal-card">
                <h2><?php esc_html_e('Refund handling (Pro)', 'polski'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="polski_bundle_refund_mode"><?php esc_html_e('Product Bundles refund mode', 'polski'); ?></label></th>
                        <td>
                            <select id="polski_bundle_refund_mode" name="<?php echo esc_attr(self::OPTION); ?>[bundle_refund_mode]">
                                <option value="whole_bundle" <?php selected($settings['bundle_refund_mode'] ?? 'whole_bundle', 'whole_bundle'); ?>><?php esc_html_e('Whole bundle (refund parent + siblings)', 'polski'); ?></option>
                                <option value="proportional" <?php selected($settings['bundle_refund_mode'] ?? '', 'proportional'); ?>><?php esc_html_e('Proportional (refund only the withdrawn child\'s share)', 'polski'); ?></option>
                                <option value="remove_discount" <?php selected($settings['bundle_refund_mode'] ?? '', 'remove_discount'); ?>><?php esc_html_e('Remove bundle discount (refund at standalone price)', 'polski'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                </section>

                <section class="polski-withdrawal-card">
                <h2><?php esc_html_e('AI features (WordPress AI Client)', 'polski'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><?php esc_html_e('Status', 'polski'); ?></th>
                        <td>
                            <?php $polski_ai_available = \Polski\AI\AiClient::isAvailableForText(); ?>
                            <p class="description">
                                <?php if ($polski_ai_available) : ?>
                                    <strong class="polski-status--ok"><?php esc_html_e('Available.', 'polski'); ?></strong>
                                    <?php esc_html_e('WordPress AI Client is loaded and at least one provider is configured for text generation.', 'polski'); ?>
                                <?php else : ?>
                                    <strong class="polski-status--warn"><?php esc_html_e('Not available.', 'polski'); ?></strong>
                                    <?php esc_html_e('Install WordPress 7.0 or higher and at least one AI Client provider plugin (for example Vercel AI Gateway, AI Provider for Anthropic, AI Provider for Google, or AI Provider for OpenAI) to enable AI augmentation.', 'polski'); ?>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Classify withdrawal reasons', 'polski'); ?></th>
                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    name="polski_ai_features_enabled"
                                    value="yes"
                                    <?php checked(get_option('polski_ai_features_enabled', 'no'), 'yes'); ?>
                                    <?php disabled(! $polski_ai_available); ?>
                                />
                                <?php esc_html_e('Ask the AI Client to classify the free-text reason on every new withdrawal into one of a fixed set of categories (defective, wrong item, size mismatch, changed mind, late delivery, damaged in transit, not as described, other).', 'polski'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Disabled by default. No outbound HTTP request is ever made by this plugin; the AI Client provider plugin you install handles the network call. Classification runs silently in the background after a customer or operator files a declaration; failures (no provider, prevented prompt, network error) are absorbed and never block the withdrawal flow.', 'polski'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Label AI-generated content', 'polski'); ?></th>
                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    name="polski_ai_label_content"
                                    value="yes"
                                    <?php checked(get_option('polski_ai_label_content', 'yes'), 'yes'); ?>
                                />
                                <?php esc_html_e('Show a short disclosure on the storefront for product copy generated by AI (for example descriptions written by the Pro AI generator), and add a machine-readable marker to that content.', 'polski'); ?>
                            </label>
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s: link to the AI Act article 50 text. */
                                    esc_html__('On by default. Helps you meet the transparency expectation for synthetic content (%s). This is compliance support, not legal advice; the exact obligation for your store is yours to confirm.', 'polski'),
                                    '<a href="https://eur-lex.europa.eu/eli/reg/2024/1689/oj" target="_blank" rel="noopener">' . esc_html__('AI Act, art. 50', 'polski') . '</a>',
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>
                </section>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
