<?php

declare(strict_types=1);
namespace Polski;

use Polski\Contract\Migration;

defined('ABSPATH') || exit;
/**
 * Versioned database migration runner.
 *
 * Discovers migration classes in src/Migration/ and runs any that haven't been executed yet.
 * Each migration class must implement a run() method and define a version constant.
 */
final class Migrator
{
    /** @var list<class-string<Migration>> */
    private array $migrations = [];

    public function __construct()
    {
        $this->discoverMigrations();
    }

    /**
     * Run all pending migrations in version order.
     */
    public function runPending(): void
    {
        $executed = $this->getExecutedVersions();

        foreach ($this->migrations as $migrationClass) {
            if (! is_subclass_of($migrationClass, Migration::class, true)) {
                continue;
            }

            $versionConstant = (new \ReflectionClass($migrationClass))->getConstant('VERSION');
            if (! is_string($versionConstant)) {
                continue;
            }

            if (in_array($versionConstant, $executed, true)) {
                continue;
            }

            $this->newMigration($migrationClass)->run();
            $this->markExecuted($versionConstant);

            /**
             * Fires after a migration is executed.
             *
             * @param string $version The migration version.
             */
            do_action('polski/migrated', $versionConstant);
        }
    }

    /**
     * Get list of already-executed migration versions.
     *
     * @return list<string>
     */
    private function getExecutedVersions(): array
    {
        global $wpdb;

        $table = esc_sql($wpdb->prefix . 'polski_migrations');

        // Table might not exist during first activation.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema probe on custom plugin table, prepared statement below.
        $tableExists = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $table),
        );

        if ($tableExists === null) {
            return [];
        }

        /** @var list<string> */
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        return $wpdb->get_col(
            $wpdb->prepare('SELECT version FROM %i ORDER BY id ASC', $table),
        );
    }

    /**
     * Record a migration version as executed.
     */
    /**
     * @param class-string<Migration> $migrationClass
     */
    private function newMigration(string $migrationClass): Migration
    {
        return new $migrationClass();
    }

    private function markExecuted(string $version): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, using $wpdb->insert() with typed placeholders.
        $wpdb->insert(
            $wpdb->prefix . 'polski_migrations',
            [
                'version' => $version,
                'executed_at' => current_time('mysql', true),
            ],
            ['%s', '%s'],
        );
    }

    /**
     * Discover migration classes from the Migration directory.
     */
    private function discoverMigrations(): void
    {
        $migrationDir = PLUGIN_DIR . '/src/Migration';

        if (! is_dir($migrationDir)) {
            return;
        }

        $files = glob($migrationDir . '/Migration_*.php');

        if ($files === false) {
            return;
        }

        sort($files);

        foreach ($files as $file) {
            $className = 'Polski\\Migration\\' . basename($file, '.php');

            if (class_exists($className) && is_subclass_of($className, Migration::class, true)) {
                $this->migrations[] = $className;
            }
        }
    }
}
