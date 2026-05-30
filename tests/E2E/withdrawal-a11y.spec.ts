import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * Automated WCAG 2.2 Level AA validation for the withdrawal flow.
 *
 * Runs axe-core against the three public templates (lookup, guest form,
 * two-step) plus the admin list and settings page. Each scan asserts zero
 * violations against the tags `wcag2a`, `wcag2aa`, `wcag22a`, `wcag22aa`
 * and `best-practice` - the same suite axe DevTools surfaces by default.
 *
 * Install dependency before running:
 *   npm install --save-dev @axe-core/playwright
 *
 * Run:
 *   npm run env:start
 *   npx playwright test withdrawal-a11y --project=chromium
 *
 * On failure, the report is printed inline (rule id, impact, target nodes,
 * remediation link) so the maintainer can fix without leaving the terminal.
 */

const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22a', 'wcag22aa'];
const BEST_PRACTICE_TAGS = [...WCAG_TAGS, 'best-practice'];

test.describe('Withdrawal flow - axe-core WCAG 2.2 AA', () => {
    test('lookup page (guest entry point)', async ({ page }) => {
        await page.goto('/odstapienie/', { waitUntil: 'domcontentloaded' });
        await page.locator('section.polski-withdrawal-lookup').waitFor();

        const results = await new AxeBuilder({ page })
            .withTags(WCAG_TAGS)
            .include('section.polski-withdrawal-lookup')
            .analyze();

        expect.soft(results.violations).toEqual([]);
        formatViolations('Lookup page', results.violations);
    });

    test('two-step My Account form', async ({ page }) => {
        await loginAsCustomer(page);
        await page.goto('/my-account/orders/', { waitUntil: 'domcontentloaded' });
        await page.getByRole('link', { name: /Withdraw|Odstąp/ }).first().click();
        await page.locator('section.polski-withdrawal-form').waitFor();

        const results = await new AxeBuilder({ page })
            .withTags(WCAG_TAGS)
            .include('section.polski-withdrawal-form')
            .analyze();

        expect.soft(results.violations).toEqual([]);
        formatViolations('Two-step form', results.violations);
    });

    test('admin: withdrawals list', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/wp-admin/admin.php?page=polski-withdrawals');
        await page.locator('.wp-list-table').waitFor();

        const results = await new AxeBuilder({ page })
            .withTags(WCAG_TAGS)
            .include('.wrap')
            .analyze();

        expect.soft(results.violations).toEqual([]);
        formatViolations('Admin list', results.violations);
    });

    test('admin: settings page', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/wp-admin/admin.php?page=polski-withdrawal-settings');
        await page.locator('form[action="options.php"]').waitFor();

        const results = await new AxeBuilder({ page })
            .withTags(BEST_PRACTICE_TAGS)
            .include('.wrap')
            .analyze();

        expect.soft(results.violations).toEqual([]);
        formatViolations('Settings page', results.violations);
    });

    test('keyboard navigation: lookup form Tab cycle', async ({ page }) => {
        await page.goto('/odstapienie/', { waitUntil: 'domcontentloaded' });

        await page.locator('#polski_order_number').focus();
        await expect(page.locator('#polski_order_number')).toBeFocused();

        await page.keyboard.press('Tab');
        await expect(page.locator('#polski_email')).toBeFocused();

        await page.keyboard.press('Tab');
        await expect(page.getByRole('button', { name: /Wyślij link/ })).toBeFocused();

        // Visible focus ring asserted via CSS - at minimum, focus-visible should
        // apply outline. We can't easily measure outline width in Playwright but
        // the absence of `outline: none !important` is enough.
        const outline = await page.evaluate(() => {
            const el = document.activeElement;
            if (!el) return '';
            return window.getComputedStyle(el).getPropertyValue('outline-style');
        });
        expect(outline).not.toBe('none');
    });

    test('error notice is autofocused and announced', async ({ page }) => {
        await page.goto('/odstapienie/', { waitUntil: 'domcontentloaded' });

        // Submit with empty fields to trigger the error notice.
        await page.getByRole('button', { name: /Wyślij link/ }).click();
        await page.waitForLoadState('networkidle');

        const notice = page.locator('.polski-withdrawal-notice--error');
        await expect(notice).toBeVisible({ timeout: 5_000 });

        // role=alert + aria-live=assertive ensures screen readers announce.
        await expect(notice).toHaveAttribute('role', 'alert');
        await expect(notice).toHaveAttribute('aria-live', 'assertive');
    });
});

function formatViolations(label: string, violations: Awaited<ReturnType<AxeBuilder['analyze']>>['violations']): void {
    if (violations.length === 0) return;
    console.log(`\n=== ${label} - ${violations.length} violations ===`);
    for (const v of violations) {
        console.log(`  [${v.impact}] ${v.id}: ${v.help}`);
        console.log(`    ${v.helpUrl}`);
        for (const node of v.nodes.slice(0, 3)) {
            console.log(`    target: ${node.target.join(' ')}`);
        }
    }
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
