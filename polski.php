<?php

declare(strict_types=1);

/**
 * Plugin Name:       Polski for WooCommerce
 * Plugin URI:        https://wppoland.com/polski
 * Description:       Polish legal compliance for WooCommerce: GDPR, Omnibus, withdrawal forms, unit prices, and 40+ shop features. Free and open source.
 * Version:           1.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            WP Poland
 * Author URI:        https://wppoland.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       polski
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 *
 * WC requires at least: 8.0
 * WC tested up to:      9.6
 *
 * DISCLAIMER: This plugin is provided "as is" without any warranty. WP Poland
 * (wppoland.com) is not liable for any damages arising from its use. This plugin
 * does not constitute legal advice. You are solely responsible for ensuring your
 * store complies with all applicable laws. Consult a qualified legal professional.
 */

namespace Polski;

defined('ABSPATH') || exit;

const VERSION = '1.1.0';
const PLUGIN_FILE = __FILE__;
const PLUGIN_DIR = __DIR__;
const MIN_PHP_VERSION = '8.1.0';
const MIN_WC_VERSION = '8.0.0';

/**
 * Declare HPOS (Custom Order Tables) compatibility.
 */
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            PLUGIN_FILE,
            true,
        );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            PLUGIN_FILE,
            true,
        );
    }
});

/**
 * Check PHP version before anything else.
 */
if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html(sprintf(
                /* translators: 1: Required PHP version, 2: Current PHP version */
                __('Polski requires PHP %1$s or higher. You are running PHP %2$s.', 'polski'),
                MIN_PHP_VERSION,
                PHP_VERSION,
            )),
        );
    });
    return;
}

/**
 * Autoloader.
 */
require_once PLUGIN_DIR . '/autoload.php';

/**
 * Check WooCommerce version on plugins_loaded.
 */
add_action('plugins_loaded', static function (): void {
    if (! defined('WC_VERSION')) {
        add_action('admin_notices', static function (): void {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__('Polski requires WooCommerce to be installed and activated.', 'polski'),
            );
        });
        return;
    }

    if (version_compare(WC_VERSION, MIN_WC_VERSION, '<')) {
        add_action('admin_notices', static function (): void {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html(sprintf(
                    /* translators: 1: Required WC version, 2: Current WC version */
                    __('Polski requires WooCommerce %1$s or higher. You are running WooCommerce %2$s.', 'polski'),
                    MIN_WC_VERSION,
                    WC_VERSION,
                )),
            );
        });
        return;
    }

    Plugin::instance()->boot();

    // Register WP-CLI commands.
    CLI\PolskiCommand::register();
}, 10);

/**
 * Activation hook.
 */
register_activation_hook(PLUGIN_FILE, static function (): void {
    require_once PLUGIN_DIR . '/autoload.php';
    Activator::activate();
});

/**
 * Deactivation hook.
 */
register_deactivation_hook(PLUGIN_FILE, static function (): void {
    Deactivator::deactivate();
});
