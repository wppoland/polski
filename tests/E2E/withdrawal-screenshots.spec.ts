import { test, expect, type Page } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Automated capture for the 8 wp.org screenshots covering the withdrawal module.
 * Mirrors docs/wporg-assets/withdrawal-screenshots.md - see that file for the
 * setup-state contract per shot.
 *
 * Run with wp-env up + plugin activated + seeded fixture orders:
 *   npm run env:start
 *   npx playwright test withdrawal-screenshots --project=chromium
 *
 * Output: docs/wporg-assets/screenshot-{9..16}-*.png (Retina ×2 via deviceScaleFactor).
 */

const OUTPUT_DIR = path.resolve(__dirname, '../../docs/wporg-assets');
const VIEWPORT = { width: 1440, height: 900 };

test.use({
    viewport: VIEWPORT,
    deviceScaleFactor: 2,
    locale: 'pl-PL',
});

test.describe('Withdrawal - wp.org screenshots', () => {
    test.beforeAll(() => {
        fs.mkdirSync(OUTPUT_DIR, { recursive: true });
    });

    test('9 - lookup form (guest)', async ({ page }) => {
        await page.goto('/odstapienie/', { waitUntil: 'domcontentloaded' });
        await page.locator('section.polski-withdrawal-lookup').waitFor();
        await captureSection(page, 'section.polski-withdrawal-lookup', 'screenshot-9-withdrawal-lookup-form.png');
    });

    test('10 - two-step form (My Account)', async ({ page }) => {
        await loginAsCustomer(page);
        await page.goto('/my-account/orders/', { waitUntil: 'domcontentloaded' });
        await page.getByRole('link', { name: /Withdraw|Odstąp/ }).first().click();
        await page.locator('section.polski-withdrawal-form').waitFor();
        await captureSection(page, 'section.polski-withdrawal-form', 'screenshot-10-withdrawal-two-step-form.png');
    });

    test('11 - admin list', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/wp-admin/admin.php?page=polski-withdrawals');
        await page.locator('.wp-list-table').waitFor();
        await captureFullPage(page, 'screenshot-11-withdrawal-admin-list.png');
    });

    test('12 - admin manual registration', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/wp-admin/admin.php?page=polski-withdrawals-new');
        await page.locator('table.form-table').waitFor();
        await captureFullPage(page, 'screenshot-12-withdrawal-admin-manual.png');
    });

    test('13 - settings page', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/wp-admin/admin.php?page=polski-withdrawal-settings');
        await page.locator('form[action="options.php"]').waitFor();
        await captureFullPage(page, 'screenshot-13-withdrawal-settings.png');
    });

    test('14 - product exemption metabox', async ({ page }) => {
        await loginAsAdmin(page);
        // Assumes a product id 42 exists (seeded by global-setup).
        await page.goto('/wp-admin/post.php?post=42&action=edit');
        await page.locator('#polski_withdrawal_meta').waitFor({ timeout: 8_000 }).catch(() => {});
        await captureFullPage(page, 'screenshot-14-withdrawal-product-exemption.png');
    });

    test('15 - category exemption term-edit (PRO)', async ({ page }) => {
        await loginAsAdmin(page);
        // Assumes a product_cat term id 5 exists.
        await page.goto('/wp-admin/term.php?taxonomy=product_cat&tag_ID=5&post_type=product');
        await page.locator('#polski_withdrawal_exempt').waitFor({ timeout: 8_000 }).catch(() => {});
        await captureFullPage(page, 'screenshot-15-withdrawal-category-exemption.png');
    });

    test('16 - reports dashboard (PRO)', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/wp-admin/admin.php?page=polski-pro-withdrawal-reports');
        await page.locator('.polski-pro-scorecards').waitFor({ timeout: 8_000 }).catch(() => {});
        await captureFullPage(page, 'screenshot-16-withdrawal-reports-dashboard.png');
    });
});

async function captureSection(page: Page, selector: string, filename: string): Promise<void> {
    const locator = page.locator(selector);
    await expect(locator).toBeVisible();
    await locator.screenshot({ path: path.join(OUTPUT_DIR, filename) });
}

async function captureFullPage(page: Page, filename: string): Promise<void> {
    await page.screenshot({
        path: path.join(OUTPUT_DIR, filename),
        fullPage: false, // Stick to viewport so screenshots fit wp.org guidelines.
    });
}

async function loginAsAdmin(page: Page): Promise<void> {
    await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
    await page.locator('#user_login').fill('admin');
    await page.locator('#user_pass').fill('password');
    await page.locator('#wp-submit').click();
    await page.waitForLoadState('networkidle');
}

async function loginAsCustomer(page: Page): Promise<void> {
    await page.goto('/my-account/', { waitUntil: 'domcontentloaded' });
    const usernameField = page.locator('#username');
    if (await usernameField.isVisible({ timeout: 3_000 }).catch(() => false)) {
        await usernameField.fill('customer');
        await page.locator('#password').fill('password');
        await page.getByRole('button', { name: /Log in|Zaloguj/ }).click();
        await page.waitForLoadState('networkidle');
    }
}
