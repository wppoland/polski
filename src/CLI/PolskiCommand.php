<?php

declare(strict_types=1);

namespace Polski\CLI;

use Polski\Migrator;
use Polski\Service\CacheHelper;

/**
 * WP-CLI commands for Polski.
 *
 * ## EXAMPLES
 *
 *     wp polski migrate
 *     wp polski cache-flush
 *     wp polski omnibus-prune
 *     wp polski status
 */
class PolskiCommand
{
    /**
     * Run pending database migrations.
     *
     * ## EXAMPLES
     *
     *     wp polski migrate
     *
     * @subcommand migrate
     */
    public function migrate(array $args, array $assocArgs): void
    {
        \WP_CLI::log('Running Polski migrations...');

        $migrator = new Migrator();
        $migrator->runPending();

        \WP_CLI::success('Migrations complete.');
    }

    /**
     * Flush all caches.
     *
     * ## EXAMPLES
     *
     *     wp polski cache-flush
     *
     * @subcommand cache-flush
     */
    public function cacheFlush(array $args, array $assocArgs): void
    {
        CacheHelper::flush();
        \WP_CLI::success('All caches flushed.');
    }

    /**
     * Prune old Omnibus price records.
     *
     * ## OPTIONS
     *
     * [--days=<days>]
     * : Delete records older than this many days. Default: 90.
     *
     * ## EXAMPLES
     *
     *     wp polski omnibus-prune
     *     wp polski omnibus-prune --days=60
     *
     * @subcommand omnibus-prune
     */
    public function omnibusPrune(array $args, array $assocArgs): void
    {
        $days = (int) ($assocArgs['days'] ?? 90);

        $container = \Polski\Plugin::instance()->container();
        $repo = $container->get(\Polski\Repository\OmnibusPriceRepository::class);

        $deleted = $repo->deleteOlderThan($days);

        \WP_CLI::success(sprintf('Pruned %d records older than %d days.', $deleted, $days));
    }

    /**
     * Show Polski status.
     *
     * ## EXAMPLES
     *
     *     wp polski status
     */
    public function status(array $args, array $assocArgs): void
    {
        $version = \Polski\VERSION;
        $installed = get_option('polski_version', 'unknown');
        $wizardDone = get_option('polski_wizard_complete', false) ? 'yes' : 'no';

        $table = [
            ['Setting', 'Value'],
            ['Plugin version', $version],
            ['Installed version', $installed],
            ['Setup wizard complete', $wizardDone],
            ['PHP version', PHP_VERSION],
            ['WooCommerce version', defined('WC_VERSION') ? WC_VERSION : 'not loaded'],
        ];

        \WP_CLI\Utils\format_items('table', array_slice($table, 1), $table[0]);
    }

    /**
     * Register the WP-CLI command.
     */
    public static function register(): void
    {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('polski', self::class);
        }
    }
}
