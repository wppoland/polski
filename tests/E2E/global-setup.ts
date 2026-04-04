import { execFileSync } from 'node:child_process';
import * as path from 'node:path';

/**
 * Seed deterministic storefront fixtures before browser tests run.
 *
 * Requires wp-env to be running already.
 */
export default async function globalSetup(): Promise<void> {
    const projectRoot = path.resolve(__dirname, '..', '..');

    execFileSync(
        'npm',
        [
            'run',
            'env:cli',
            '--',
            'wp',
            'plugin',
            'activate',
            'polski',
        ],
        {
            cwd: projectRoot,
            encoding: 'utf8',
        },
    );

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
