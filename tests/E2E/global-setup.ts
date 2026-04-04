import { execFileSync } from 'node:child_process';
import * as path from 'node:path';

/**
 * Seed deterministic storefront fixtures before browser tests run.
 *
 * Requires wp-env to be running already.
 */
export default async function globalSetup(): Promise<void> {
    const projectRoot = path.resolve(__dirname, '..', '..');

    const run = (...args: string[]) =>
        execFileSync('npm', ['run', 'env:cli', '--', ...args], {
            cwd: projectRoot,
            encoding: 'utf8',
        });

    // Activate plugin.
    run('wp', 'plugin', 'activate', 'polski');

    // Ensure English locale for consistent test selectors.
    run('wp', 'language', 'core', 'install', 'en_US');
    run('wp', 'site', 'switch-language', 'en_US');

    // Enable pretty permalinks and flush rewrite rules so /shop/, /checkout/ etc. work.
    run('wp', 'rewrite', 'structure', '/%postname%/', '--hard');
    run('wp', 'rewrite', 'flush', '--hard');

    // Run the storefront bootstrap fixture.
    const output = execFileSync(
        'npm',
        [
            'run',
            'env:cli',
            '--',
            '--env-cwd=wp-content/plugins/polski',
            'wp',
            'eval-file',
            'tests/E2E/fixtures/storefront-bootstrap.php',
        ],
        {
            cwd: projectRoot,
            encoding: 'utf8',
        },
    );

    process.stdout.write(output);
}
