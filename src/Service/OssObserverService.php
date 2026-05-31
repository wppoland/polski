<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * Observe the EU OSS (One Stop Shop) delivery threshold.
 *
 * Thin adapter around the standalone "one-stop-shop-woocommerce" plugin
 * (https://wordpress.org/plugins/one-stop-shop-woocommerce/). Detects
 * whether the plugin is active, whether the OSS procedure is enabled,
 * and whether the auto-observer is watching the current-year threshold.
 * Exposes a stable surface (`isOssEnabled`, `isAutoObserverEnabled`) so
 * modules can branch tax logic without coupling to
 * the external plugin.
 *
 * Also provides an admin-post handler to install + activate the plugin
 * on demand from the modules admin row.
 */
final class OssObserverService implements HasHooks
{
    public const PLUGIN_SLUG = 'one-stop-shop-woocommerce';
    public const PLUGIN_FILE = 'one-stop-shop-woocommerce/one-stop-shop-woocommerce.php';

    public function registerHooks(): void
    {
        add_action('admin_post_polski_install_oss', [$this, 'handleInstall']);
    }

    /**
     * Whether the external OSS plugin is installed and active.
     */
    public function isOssPluginActive(): bool
    {
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active(self::PLUGIN_FILE);
    }

    /**
     * Whether the OSS procedure is currently enabled for the store.
     *
     * The external plugin exposes `\Vendidero\OneStopShop\Package::oss_procedure_is_enabled()`
     * once active. Returns false when the plugin is missing so callers get a safe default.
     *
     * Filterable via `polski_tax_oss_enabled` so custom integrations can override
     * the signal for testing, forced modes, or site-specific policies.
     */
    public function isOssEnabled(): bool
    {
        $value = false;

        if ($this->isOssPluginActive() && is_callable(['Vendidero\\OneStopShop\\Package', 'oss_procedure_is_enabled'])) {
            $value = (bool) call_user_func(['Vendidero\\OneStopShop\\Package', 'oss_procedure_is_enabled']);
        }

        /**
         * Filter the OSS-enabled signal used by custom tax branches.
         *
         * @param bool $value True when the external OSS plugin is active and the procedure is on.
         */
        return (bool) apply_filters('polski_tax_oss_enabled', $value);
    }

    /**
     * DI-less convenience for consumers that don't want to resolve the service from the container.
     */
    public static function isOssFeatureActive(): bool
    {
        return (new self())->isOssEnabled();
    }

    /**
     * Whether the auto-observer (threshold monitor) is running.
     */
    public function isAutoObserverEnabled(): bool
    {
        if (! $this->isOssPluginActive()) {
            return false;
        }

        if (is_callable(['Vendidero\\OneStopShop\\Package', 'enable_auto_observer'])) {
            return (bool) call_user_func(['Vendidero\\OneStopShop\\Package', 'enable_auto_observer']);
        }

        return false;
    }

    /**
     * Convenience flag used by the ModulesPage OSS row to decide whether to show
     * the install CTA or the "plugin detected" confirmation.
     */
    public function needsInstall(): bool
    {
        return ! $this->isOssPluginActive();
    }

    /**
     * URL that triggers a safe install + activation of the OSS plugin.
     */
    public function getInstallUrl(): string
    {
        return wp_nonce_url(
            admin_url('admin-post.php?action=polski_install_oss'),
            'polski_install_oss'
        );
    }

    /**
     * URL to the OSS settings page once the plugin is active (WooCommerce → Settings → Tax → OSS).
     */
    public function getSettingsUrl(): string
    {
        return admin_url('admin.php?page=wc-settings&tab=oss');
    }

    /**
     * Install + activate the standalone OSS plugin. Requires `install_plugins`.
     */
    public function handleInstall(): void
    {
        if (! current_user_can('install_plugins')) {
            wp_die(esc_html__('You do not have permission to install plugins.', 'polski'), '', ['response' => 403]);
        }

        check_admin_referer('polski_install_oss');

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $redirect = admin_url('admin.php?page=polski&tab=modules#polski-module-oss_observer');

        // Already installed? Just activate.
        $plugins = get_plugins();
        if (isset($plugins[self::PLUGIN_FILE])) {
            if (! is_plugin_active(self::PLUGIN_FILE)) {
                $activated = activate_plugin(self::PLUGIN_FILE);
                if (is_wp_error($activated)) {
                    wp_safe_redirect(add_query_arg('polski_oss_error', rawurlencode($activated->get_error_message()), $redirect));
                    exit;
                }
            }
            wp_safe_redirect(add_query_arg('polski_oss_installed', '1', $redirect));
            exit;
        }

        $api = plugins_api('plugin_information', [
            'slug'   => self::PLUGIN_SLUG,
            'fields' => ['sections' => false],
        ]);

        if (is_wp_error($api)) {
            wp_safe_redirect(add_query_arg('polski_oss_error', rawurlencode($api->get_error_message()), $redirect));
            exit;
        }

        $upgrader = new \Plugin_Upgrader(new \Automatic_Upgrader_Skin());
        $installed = $upgrader->install($api->download_link);

        if (is_wp_error($installed) || $installed === false) {
            $message = is_wp_error($installed)
                ? $installed->get_error_message()
                : __('Unknown installation error.', 'polski');
            wp_safe_redirect(add_query_arg('polski_oss_error', rawurlencode($message), $redirect));
            exit;
        }

        $activated = activate_plugin(self::PLUGIN_FILE);
        if (is_wp_error($activated)) {
            wp_safe_redirect(add_query_arg('polski_oss_error', rawurlencode($activated->get_error_message()), $redirect));
            exit;
        }

        wp_safe_redirect(add_query_arg('polski_oss_installed', '1', $redirect));
        exit;
    }
}
