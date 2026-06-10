<?php

declare(strict_types=1);

namespace Polski\Admin;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * Registers the guided setup tab and mounts the React setup wizard inside the
 * unified Polski admin shell.
 */
final class SetupWizard implements HasHooks
{
    public function registerHooks(): void
    {
        add_action('polski_admin_shell_tab_setup', [$this, 'render']);
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

    public function render(): void
    {
        echo '<script>if (window.location.hash !== "#/setup-wizard") { window.location.hash = "/setup-wizard"; }</script>';
        echo '<div id="polski-admin"></div>';
    }
}
