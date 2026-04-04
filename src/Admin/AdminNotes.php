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
            echo '<p>' . esc_html__('This plugin provides tools to assist with Polish e-commerce compliance. It does not constitute legal advice. You are solely responsible for ensuring your store complies with all applicable laws. We recommend consulting a qualified legal professional.', 'polski') . '</p>';
            echo '<p><a href="' . esc_url($dismissUrl) . '" class="button button-small">' . esc_html__('I understand, dismiss', 'polski') . '</a></p>';
            echo '</div>';
        });

        add_action('admin_post_polski_dismiss_disclaimer', static function (): void {
            if (! current_user_can('manage_woocommerce')) {
                wp_die(esc_html__('Brak uprawnien.', 'polski'));
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
        $note->set_title((string) ($settings['admin_setup_note_title'] ?? __('Skonfiguruj Polski dla Twojego sklepu', 'polski')));
        $note->set_content(
            (string) ($settings['admin_setup_note_content'] ?? __('Zaledwie kilka kliknięć dzieli Cię od w pełni przygotowanego, profesjonalnego sklepu WooCommerce. Wypełnij poniższe punkty w mig!', 'polski')),
        );
        $note->set_type($noteClass::E_WC_ADMIN_NOTE_INFORMATIONAL);
        $note->set_name('polski-setup');
        $note->set_source('polski');
        $note->add_action(
            'polski-setup-wizard',
            (string) ($settings['admin_setup_note_button'] ?? __('Uruchom kreator konfiguracji', 'polski')),
            admin_url('admin.php?page=polski&tab=wizard#/setup-wizard'),
        );
        $note->save();

        update_option('polski_setup_note_shown', true);
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
