<?php

declare(strict_types=1);

namespace Spolszczony\Admin;

use Spolszczony\Contract\HasHooks;

/**
 * WooCommerce admin notes for onboarding, updates, and tips.
 */
final class AdminNotes implements HasHooks
{
    public function registerHooks(): void
    {
        add_action('admin_init', [$this, 'maybeAddSetupNote']);
    }

    /**
     * Add a setup note if the wizard hasn't been completed.
     */
    public function maybeAddSetupNote(): void
    {
        if (get_option('spolszczony_wizard_complete', false)) {
            return;
        }

        if (get_option('spolszczony_setup_note_shown', false)) {
            return;
        }

        if (! class_exists(\Automattic\WooCommerce\Admin\Notes\Note::class)) {
            return;
        }

        $noteClass = \Automattic\WooCommerce\Admin\Notes\Note::class;
        $existingNote = \Automattic\WooCommerce\Admin\Notes\Notes::get_note_by_name('spolszczony-setup');

        if ($existingNote !== false) {
            return;
        }

        $note = new $noteClass();
        $note->set_title(__('Configure Spolszczony for your store', 'spolszczony'));
        $note->set_content(
            __('Complete the setup wizard to configure Polish legal compliance for your WooCommerce store. This includes legal pages, checkout settings, and price display options.', 'spolszczony'),
        );
        $note->set_type($noteClass::E_WC_ADMIN_NOTE_INFORMATIONAL);
        $note->set_name('spolszczony-setup');
        $note->set_source('spolszczony');
        $note->add_action(
            'spolszczony-setup-wizard',
            __('Run Setup Wizard', 'spolszczony'),
            admin_url('admin.php?page=spolszczony#/setup-wizard'),
        );
        $note->save();

        update_option('spolszczony_setup_note_shown', true);
    }
}
