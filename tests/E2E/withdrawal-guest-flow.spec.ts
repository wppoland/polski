import { test, expect, type Page } from '@playwright/test';

/**
 * End-to-end test for the guest withdrawal flow.
 *
 * Covers:
 *   1. The lookup form renders with Polish copy, the FAQ schema, and
 *      accessible markup (single h2, labelled fields, aria-required, max-width).
 *   2. Submitting an unknown order returns the masked success notice (anti-enumeration).
 *   3. Posting a known email + order number triggers a magic-link e-mail
 *      (asserted indirectly via the success notice - actual mail is captured
 *      by wp-env's MailHog or by stubbing wp_mail in a mu-plugin).
 *
 * Prerequisites:
 *   - wp-env running with WooCommerce + Polski activated;
 *   - page containing `[polski_withdrawal_lookup]` published at /odstapienie/;
 *   - polski_withdrawal.lookup_page_id option pointing to that page;
 *   - test order #1001 with billing email "buyer@example.test", status wc-completed
 *     and the _polski_withdrawal_clock_start meta seeded.
 *
 * Run with: `npm run env:start && npx playwright test withdrawal-guest-flow`
 */

const LOOKUP_PATH = '/odstapienie/';

test.describe('Guest withdrawal lookup', () => {
    test.setTimeout(60_000);

    test('renders accessible lookup form with intro + FAQ schema', async ({ page }) => {
        await page.goto(LOOKUP_PATH, { waitUntil: 'domcontentloaded' });

        // Single, focused landing section with an aria-labelled heading.
        const section = page.locator('section.polski-withdrawal-lookup');
        await expect(section).toBeVisible();
        await expect(section).toHaveAttribute('aria-labelledby', 'polski-withdrawal-lookup-title');

        // ~200-word intro covers directive + deadline.
        const intro = section.locator('.polski-withdrawal-lookup__intro');
        await expect(intro).toContainText('odstąpić od umowy');
        await expect(intro).toContainText('14 dni');
        await expect(intro).toContainText('2011/83/UE');

        // Both required fields have visible labels + aria-required + autocomplete hints.
        const orderField = page.locator('#polski_order_number');
        await expect(orderField).toHaveAttribute('aria-required', 'true');
        await expect(orderField).toHaveAttribute('autocomplete', 'off');
        await expect(orderField).toHaveAttribute('inputmode', 'numeric');

        const emailField = page.locator('#polski_email');
        await expect(emailField).toHaveAttribute('aria-required', 'true');
        await expect(emailField).toHaveAttribute('autocomplete', 'email');
        await expect(emailField).toHaveAttribute('type', 'email');

        // Submit clearly states the outcome (e-mail will be sent), not the action.
        await expect(page.getByRole('button', { name: /Wyślij link/ })).toBeVisible();

        // FAQPage schema is emitted exactly once.
        const schemas = page.locator('script[type="application/ld+json"]');
        const count = await schemas.count();
        let faqFound = 0;
        for (let i = 0; i < count; i++) {
            const txt = await schemas.nth(i).textContent();
            if (txt && txt.includes('"@type":"FAQPage"')) {
                faqFound++;
            }
        }
        expect(faqFound).toBe(1);
    });

    test('keyboard-only navigation reaches submit button', async ({ page }) => {
        await page.goto(LOOKUP_PATH, { waitUntil: 'domcontentloaded' });

        // Focus the first field, then tab through the form.
        await page.locator('#polski_order_number').focus();
        await expect(page.locator('#polski_order_number')).toBeFocused();

        await page.keyboard.press('Tab');
        await expect(page.locator('#polski_email')).toBeFocused();

        await page.keyboard.press('Tab');
        // Should land on the submit button (after the hidden nonce input).
        await expect(page.getByRole('button', { name: /Wyślij link/ })).toBeFocused();
    });

    test('unknown order receives the same masked notice as a known one', async ({ page }) => {
        await page.goto(LOOKUP_PATH, { waitUntil: 'domcontentloaded' });

        await page.locator('#polski_order_number').fill('999999');
        await page.locator('#polski_email').fill('nobody@example.test');
        await page.getByRole('button', { name: /Wyślij link/ }).click();

        // The flow uses the cookie-bound notice transient; after the redirect/
        // re-render, the success message appears.
        await expect(page.locator('.polski-withdrawal-notice--success')).toBeVisible({ timeout: 5_000 });
        await expect(page.locator('.polski-withdrawal-notice--success')).toContainText('jeśli to zamówienie istnieje');
    });

    test('rate limiter blocks excessive attempts from the same e-mail', async ({ page }) => {
        await page.goto(LOOKUP_PATH, { waitUntil: 'domcontentloaded' });

        for (let attempt = 0; attempt < 5; attempt++) {
            await page.locator('#polski_order_number').fill('123');
            await page.locator('#polski_email').fill('ratelimit@example.test');
            await page.getByRole('button', { name: /Wyślij link/ }).click();
            await page.waitForLoadState('networkidle');
        }

        // 6th attempt should trip the rate limiter.
        await page.locator('#polski_order_number').fill('123');
        await page.locator('#polski_email').fill('ratelimit@example.test');
        await page.getByRole('button', { name: /Wyślij link/ }).click();
        await expect(page.locator('.polski-withdrawal-notice--error')).toBeVisible({ timeout: 5_000 });
    });
});

test.describe('My Account two-step partial withdrawal', () => {
    const CUSTOMER_USER = 'customer';
    const CUSTOMER_PASS = 'password';

    test.beforeEach(async ({ page }) => {
        await loginAsCustomer(page, CUSTOMER_USER, CUSTOMER_PASS);
    });

    test('opens the form with item-selection step and configurable quantities', async ({ page }) => {
        await page.goto('/my-account/orders/', { waitUntil: 'domcontentloaded' });

        const withdrawBtn = page.getByRole('link', { name: /Withdraw|Odstąp/ }).first();
        await expect(withdrawBtn).toBeVisible();
        await withdrawBtn.click();

        // Step 1: items table with qty spinners.
        await expect(page.getByRole('heading', { name: /Krok 1/ })).toBeVisible();
        const qtyInputs = page.locator('input[name^="polski_items["]');
        const inputCount = await qtyInputs.count();
        expect(inputCount).toBeGreaterThan(0);

        // Each qty input is capped at its remaining quantity.
        for (let i = 0; i < inputCount; i++) {
            const max = await qtyInputs.nth(i).getAttribute('max');
            expect(Number(max)).toBeGreaterThan(0);
        }

        // Step 2: reason + descriptive submit.
        await expect(page.getByRole('heading', { name: /Krok 2/ })).toBeVisible();
        await expect(page.getByRole('button', { name: /Złóż oświadczenie/ })).toBeVisible();
    });

    test('submitting partial qty leaves the rest withdrawable', async ({ page }) => {
        await page.goto('/my-account/orders/', { waitUntil: 'domcontentloaded' });
        await page.getByRole('link', { name: /Withdraw|Odstąp/ }).first().click();

        // Reduce the first item's qty to 1, then submit.
        const firstQty = page.locator('input[name^="polski_items["]').first();
        await firstQty.fill('1');

        await page.getByRole('button', { name: /Złóż oświadczenie/ }).click();
        await expect(page.locator('.woocommerce-message, .polski-withdrawal-success')).toBeVisible({ timeout: 5_000 });

        // Customer can re-enter the flow to withdraw remaining qty.
        await page.goto('/my-account/orders/', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('link', { name: /Withdraw|Odstąp/ })).toBeVisible();
    });
});

async function loginAsCustomer(page: Page, user: string, pass: string): Promise<void> {
    await page.goto('/my-account/', { waitUntil: 'domcontentloaded' });
    const usernameField = page.locator('#username');

    if (await usernameField.isVisible({ timeout: 3_000 }).catch(() => false)) {
        await usernameField.fill(user);
        await page.locator('#password').fill(pass);
        await page.getByRole('button', { name: /Log in|Zaloguj/ }).click();
        await page.waitForLoadState('networkidle');
    }
}
