<?php

declare(strict_types=1);

/**
 * Plugin Name:       Spolszczony
 * Plugin URI:        https://wppoland.com/spolszczony
 * Description:       Polish e-commerce legal compliance for WooCommerce — unit prices, Omnibus directive, legal checkboxes, withdrawal rights, tax display, and more.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            WP Poland
 * Author URI:        https://wppoland.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       spolszczony
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 *
 * WC requires at least: 8.0
 * WC tested up to:      9.6
 */

namespace Spolszczony;

defined('ABSPATH') || exit;

const VERSION = '1.0.0';
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
                __('Spolszczony requires PHP %1$s or higher. You are running PHP %2$s.', 'spolszczony'),
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
$autoloader = PLUGIN_DIR . '/vendor/autoload.php';

if (! file_exists($autoloader)) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__('Spolszczony requires Composer autoloader. Run "composer install" in the plugin directory.', 'spolszczony'),
        );
    });
    return;
}

require_once $autoloader;

/**
 * Check WooCommerce version on plugins_loaded.
 */
add_action('plugins_loaded', static function (): void {
    if (! defined('WC_VERSION')) {
        add_action('admin_notices', static function (): void {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__('Spolszczony requires WooCommerce to be installed and activated.', 'spolszczony'),
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
                    __('Spolszczony requires WooCommerce %1$s or higher. You are running WooCommerce %2$s.', 'spolszczony'),
                    MIN_WC_VERSION,
                    WC_VERSION,
                )),
            );
        });
        return;
    }

    Plugin::instance()->boot();

    // Register WP-CLI commands.
    CLI\SpolszczonyCommand::register();
}, 10);

/**
 * Activation hook.
 */
register_activation_hook(PLUGIN_FILE, static function (): void {
    require_once $GLOBALS['spolszczony_autoloader'] ?? PLUGIN_DIR . '/vendor/autoload.php';
    Activator::activate();
});

/**
 * Deactivation hook.
 */
register_deactivation_hook(PLUGIN_FILE, static function (): void {
    Deactivator::deactivate();
});
