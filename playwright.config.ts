import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E test configuration for Polski plugin.
 *
 * Requires wp-env running: `npm run env:start`
 * Run tests: `npm run test:e2e`
 */
export default defineConfig({
    testDir: './tests/E2E',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: 'html',
    use: {
        baseURL: 'http://localhost:8888',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
