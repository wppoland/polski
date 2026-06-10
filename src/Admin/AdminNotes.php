<?php

declare(strict_types=1);
namespace Polski\Admin;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * WooCommerce admin notes for onboarding, updates, and tips.
 */
final class AdminNotes implements HasHooks
{
    public function registerHooks(): void
    {
        add_action('admin_init', [$this, 'maybeAddSetupNote']);
        add_action('admin_init', [$this, 'maybeShowDisclaimerNotice']);
        add_action('admin_init', [$this, 'maybeAddOssInstallNote']);
    }

    /**
     * Show a one-time dismissible disclaimer notice after activation.
     */
    public function maybeShowDisclaimerNotice(): void
    {
        if (get_option('polski_disclaimer_dismissed', false)) {
            return;
        }

        add_action('admin_notices', static function (): void {
            $dismissUrl = wp_nonce_url(
                admin_url('admin-post.php?action=polski_dismiss_disclaimer'),
                'polski_dismiss_disclaimer',
            );

            echo '<div class="notice notice-info is-dismissible polski-disclaimer-notice">';
            echo '<p><strong>Polski</strong></p>';
            echo '<p>' . esc_html(self::disclaimerNoticeParagraph()) . '</p>';
            echo '<p><a href="' . esc_url($dismissUrl) . '" class="button button-small">' . esc_html(self::dismissDisclaimerButtonText()) . '</a></p>';
            echo '</div>';
        });

        add_action('admin_post_polski_dismiss_disclaimer', static function (): void {
            if (! current_user_can('manage_woocommerce')) {
                wp_die(esc_html__('Insufficient permissions.', 'polski'));
            }
            check_admin_referer('polski_dismiss_disclaimer');
            update_option('polski_disclaimer_dismissed', true);
            wp_safe_redirect(wp_get_referer() ?: admin_url());
            exit;
        });
    }

    /**
     * Add a setup note if the wizard hasn't been completed.
     */
    public function maybeAddSetupNote(): void
    {
        if (get_option('polski_wizard_complete', false)) {
            return;
        }

        if (get_option('polski_setup_note_shown', false)) {
            return;
        }

        if (! class_exists(\Automattic\WooCommerce\Admin\Notes\Note::class)) {
            return;
        }

        $noteClass = \Automattic\WooCommerce\Admin\Notes\Note::class;
        $existingNote = \Automattic\WooCommerce\Admin\Notes\Notes::get_note_by_name('polski-setup');

        if ($existingNote !== false) {
            return;
        }

        $settings = $this->getGeneralSettings();
        $note = new $noteClass();
        $note->set_title((string) ($settings['admin_setup_note_title'] ?? __('Configure Polski for your store', 'polski')));
        $note->set_content(
            (string) ($settings['admin_setup_note_content'] ?? __('A few clicks away from a fully prepared, professional WooCommerce store. Complete these steps quickly!', 'polski')),
        );
        $note->set_type($noteClass::E_WC_ADMIN_NOTE_INFORMATIONAL);
        $note->set_name('polski-setup');
        $note->set_source('polski');
        $note->add_action(
            'polski-setup-wizard',
            (string) ($settings['admin_setup_note_button'] ?? __('Launch setup wizard', 'polski')),
            admin_url('admin.php?page=polski&tab=setup#/setup-wizard'),
        );
        $note->save();

        update_option('polski_setup_note_shown', true);
    }

    /**
     * Admin note prompting the merchant to install the standalone OSS plugin when
     * the OSS observer module is enabled but the plugin is missing.
     *
     * Mirrors Germanized's `WC_GZD_Admin_Note_OSS_Install` pattern.
     */
    public function maybeAddOssInstallNote(): void
    {
        if (! \Polski\Admin\ModulesPage::isModuleEnabled('oss_observer')) {
            return;
        }

        if (! class_exists(\Polski\Service\OssObserverService::class)) {
            return;
        }

        $service = new \Polski\Service\OssObserverService();
        if (! $service->needsInstall()) {
            return;
        }

        if (! class_exists(\Automattic\WooCommerce\Admin\Notes\Note::class)) {
            return;
        }

        $noteClass = \Automattic\WooCommerce\Admin\Notes\Note::class;
        $existingNote = \Automattic\WooCommerce\Admin\Notes\Notes::get_note_by_name('polski-oss-install');

        if ($existingNote !== false) {
            return;
        }

        $note = new $noteClass();
        $note->set_title(__('OSS plugin is missing', 'polski'));
        $note->set_content(
            __('You enabled the OSS observer, which requires the standalone One Stop Shop plugin. Install it to start monitoring the €10,000 intra-EU B2C delivery threshold automatically.', 'polski'),
        );
        $note->set_type($noteClass::E_WC_ADMIN_NOTE_WARNING);
        $note->set_name('polski-oss-install');
        $note->set_source('polski');
        $note->add_action(
            'polski-oss-install',
            __('Install now', 'polski'),
            $service->getInstallUrl(),
            $noteClass::E_WC_ADMIN_NOTE_UNACTIONED,
            true,
        );
        $note->save();
    }

    /**
     * Disclaimer body: use site language, not only the current user's admin UI language.
     *
     * In wp-admin, __() normally follows get_user_locale(). A Polish store with an English
     * profile would otherwise show English even when polski-pl_PL.mo is present.
     */
    private static function disclaimerNoticeParagraph(): string
    {
        $msgid = 'This plugin provides tools to assist with Polish e-commerce compliance. It does not constitute legal advice. You are solely responsible for ensuring your store complies with all applicable laws. We recommend consulting a qualified legal professional.';

        $siteLocale = get_locale();
        $userLocale = function_exists('get_user_locale') ? get_user_locale() : $siteLocale;

        if ($siteLocale !== $userLocale && str_starts_with($siteLocale, 'pl')) {
            $switched = switch_to_locale($siteLocale);
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            $text = __($msgid, 'polski');
            if ($switched) {
                restore_previous_locale();
            }
        } else {
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            $text = __($msgid, 'polski');
        }

        if ($text === $msgid && str_starts_with($siteLocale, 'pl')) {
            return 'Ta wtyczka udostępnia narzędzia wspierające zgodność sklepu z polskim prawem e-commerce. Nie stanowi porady prawnej. Odpowiedzialność za zgodność sklepu z obowiązującym prawem spoczywa wyłącznie na Tobie. Zalecamy konsultację z wykwalifikowanym prawnikiem.';
        }

        return $text;
    }

    /**
     * Dismiss control: same site-locale rule as the disclaimer paragraph.
     */
    private static function dismissDisclaimerButtonText(): string
    {
        $msgid = 'I understand, dismiss';
        $siteLocale = get_locale();
        $userLocale = function_exists('get_user_locale') ? get_user_locale() : $siteLocale;

        if ($siteLocale !== $userLocale && str_starts_with($siteLocale, 'pl')) {
            $switched = switch_to_locale($siteLocale);
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            $text = __($msgid, 'polski');
            if ($switched) {
                restore_previous_locale();
            }
        } else {
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            $text = __($msgid, 'polski');
        }

        if ($text === $msgid && str_starts_with($siteLocale, 'pl')) {
            return 'Rozumiem, zamknij';
        }

        return $text;
    }

    /**
     * @return array<string, mixed>
     */
    private function getGeneralSettings(): array
    {
        $settings = get_option('polski_general', []);

        return is_array($settings) ? $settings : [];
    }
}
