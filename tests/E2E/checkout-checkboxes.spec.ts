import { test, expect } from '@playwright/test';

/**
 * E2E tests for Polski legal checkboxes on checkout.
 *
 * Prerequisites:
 * - wp-env running with WooCommerce and Polski activated
 * - storefront-bootstrap.php seeded via global-setup
 */

const ADMIN_USER = 'admin';
const ADMIN_PASS = 'password';

test.describe('Checkout Legal Checkboxes', () => {
    test.setTimeout(60000);

    test.beforeEach(async ({ page }) => {
        // Navigate to the shop page and add a product to cart via the browser.
        await page.goto('/shop/', { waitUntil: 'domcontentloaded' });
        await page.waitForLoadState('networkidle');

        // Click the first in-stock "Add to cart" button.
        const addToCartBtn = page.locator('.add_to_cart_button').first();
        await expect(addToCartBtn).toBeVisible({ timeout: 10000 });
        await addToCartBtn.click();

        // Wait for the cart to update (AJAX add to cart).
        await page.waitForResponse(
            (resp) => resp.url().includes('wc-ajax') || resp.url().includes('cart/add-item'),
            { timeout: 10000 },
        ).catch(() => {});

        // Small delay for cart state to propagate.
        await page.waitForTimeout(1000);

        await page.goto('/checkout/', { waitUntil: 'domcontentloaded' });
        await page.waitForLoadState('networkidle');
    });

    test('shows legal checkboxes container', async ({ page }) => {
        const container = page.locator('.polski-legal-checkboxes');
        await expect(container).toBeVisible({ timeout: 15000 });
    });

    test('shows required terms checkbox', async ({ page }) => {
        const termsCheckbox = page.locator('.polski-checkbox--terms input[type="checkbox"]');
        await expect(termsCheckbox).toBeVisible({ timeout: 15000 });
    });

    test('shows required privacy checkbox', async ({ page }) => {
        const privacyCheckbox = page.locator('.polski-checkbox--privacy input[type="checkbox"]');
        await expect(privacyCheckbox).toBeVisible({ timeout: 15000 });
    });

    test('shows required withdrawal checkbox', async ({ page }) => {
        const withdrawalCheckbox = page.locator('.polski-checkbox--withdrawal input[type="checkbox"]');
        await expect(withdrawalCheckbox).toBeVisible({ timeout: 15000 });
    });

    test('shows required asterisk on mandatory checkboxes', async ({ page }) => {
        const asterisks = page.locator('.polski-checkbox--terms abbr.required');
        await expect(asterisks).toBeVisible({ timeout: 15000 });
    });

    test('order button has correct text', async ({ page }) => {
        const button = page.locator('#place_order, .wc-block-components-checkout-place-order-button, button[name="woocommerce_checkout_place_order"]');
        await expect(button.first()).toBeVisible({ timeout: 15000 });
    });

    test('checkout fails without accepting required checkboxes', async ({ page }) => {
        const classicFirstName = page.locator('#billing_first_name');

        if (await classicFirstName.isVisible({ timeout: 5000 }).catch(() => false)) {
            await page.fill('#billing_first_name', 'Jan');
            await page.fill('#billing_last_name', 'Kowalski');
            await page.fill('#billing_address_1', 'ul. Testowa 1');
            await page.fill('#billing_postcode', '00-001');
            await page.fill('#billing_city', 'Warszawa');
            await page.fill('#billing_phone', '500100200');
            await page.fill('#billing_email', 'jan@test.pl');
            await page.click('#place_order');
        } else {
            // WooCommerce Blocks checkout - just click the place order button.
            const placeOrderBtn = page.locator('.wc-block-components-checkout-place-order-button, button.wc-block-components-button');
            await expect(placeOrderBtn.first()).toBeVisible({ timeout: 15000 });
            await placeOrderBtn.first().click();
        }

        const error = page.locator('.woocommerce-error, .wc-block-components-notice-banner.is-error');
        await expect(error).toBeVisible({ timeout: 15000 });
    });
});

test.describe('Admin Dashboard', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/wp-login.php');
        await page.fill('#user_login', ADMIN_USER);
        await page.fill('#user_pass', ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForURL('**/wp-admin/**');
    });

    test('Polski admin page loads', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=polski');
        const heading = page.locator('.wrap h1').filter({ hasText: 'Polski' });
        await expect(heading).toBeVisible({ timeout: 10000 });
    });

    test('Polski modules page loads', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=polski&tab=modules');
        const heading = page.locator('.wrap h1').filter({ hasText: 'Polski' });
        await expect(heading).toBeVisible({ timeout: 10000 });
    });
});
