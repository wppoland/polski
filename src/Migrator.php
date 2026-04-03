<?php

declare(strict_types=1);

namespace Polski;

/**
 * Versioned database migration runner.
 *
 * Discovers migration classes in src/Migration/ and runs any that haven't been executed yet.
 * Each migration class must implement a run() method and define a version constant.
 */
final class Migrator
{
    /** @var list<class-string> */
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
            /** @var object{VERSION: string} $migration */
            $migration = new $migrationClass();
            $version = $migration::VERSION;

            if (in_array($version, $executed, true)) {
                continue;
            }

            $migration->run();
            $this->markExecuted($version);

            /**
             * Fires after a migration is executed.
             *
             * @param string $version The migration version.
             */
            do_action('polski/migrated', $version);
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

        $table = $wpdb->prefix . 'polski_migrations';

        // Table might not exist during first activation.
        $tableExists = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $table),
        );

        if ($tableExists === null) {
            return [];
        }

        /** @var list<string> */
        return $wpdb->get_col("SELECT version FROM {$table} ORDER BY id ASC");
    }

    /**
     * Record a migration version as executed.
     */
    private function markExecuted(string $version): void
    {
        global $wpdb;

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

            if (class_exists($className)) {
                $this->migrations[] = $className;
            }
        }
    }
}
