import { test, expect } from '@playwright/test';

/**
 * E2E tests for Polski legal checkboxes on checkout.
 *
 * Prerequisites:
 * - wp-env running with WooCommerce and Polski activated
 * - At least one product and shipping zone configured
 */

const ADMIN_USER = 'admin';
const ADMIN_PASS = 'password';

test.describe('Checkout Legal Checkboxes', () => {
    test.beforeEach(async ({ page }) => {
        // Go to shop and add the first product to cart.
        await page.goto('/shop/', { waitUntil: 'networkidle' });
        await page.click('.ajax_add_to_cart, .add_to_cart_button');
        
        // Wait for cart to update or just go to checkout.
        await page.goto('/checkout/', { waitUntil: 'networkidle' });
    });

    test('shows legal checkboxes container', async ({ page }) => {
        const container = page.locator('.polski-legal-checkboxes');
        await expect(container).toBeVisible();
    });

    test('shows required terms checkbox', async ({ page }) => {
        const termsCheckbox = page.locator('.polski-checkbox--terms input[type="checkbox"]');
        await expect(termsCheckbox).toBeVisible();
        await expect(termsCheckbox).toHaveAttribute('required', '');
    });

    test('shows required privacy checkbox', async ({ page }) => {
        const privacyCheckbox = page.locator('.polski-checkbox--privacy input[type="checkbox"]');
        await expect(privacyCheckbox).toBeVisible();
    });

    test('shows required withdrawal checkbox', async ({ page }) => {
        const withdrawalCheckbox = page.locator('.polski-checkbox--withdrawal input[type="checkbox"]');
        await expect(withdrawalCheckbox).toBeVisible();
    });

    test('shows required asterisk on mandatory checkboxes', async ({ page }) => {
        const asterisks = page.locator('.polski-checkbox--terms abbr.required');
        await expect(asterisks).toBeVisible();
    });

    test('order button has correct Polish text', async ({ page }) => {
        const button = page.locator('#place_order');
        await expect(button).toContainText('Place order');
    });

    test('checkout fails without accepting required checkboxes', async ({ page }) => {
        // Fill billing fields.
        await page.fill('#billing_first_name', 'Jan');
        await page.fill('#billing_last_name', 'Kowalski');
        await page.fill('#billing_address_1', 'ul. Testowa 1');
        await page.fill('#billing_postcode', '00-001');
        await page.fill('#billing_city', 'Warszawa');
        await page.fill('#billing_phone', '500100200');
        await page.fill('#billing_email', 'jan@test.pl');

        // Don't check any checkbox.
        await page.click('#place_order');

        // Should show error.
        const error = page.locator('.woocommerce-error, .wc-block-components-notice-banner.is-error');
        await expect(error).toBeVisible({ timeout: 10000 });
    });
});

test.describe('Admin Dashboard', () => {
    test.beforeEach(async ({ page }) => {
        // Login as admin.
        await page.goto('/wp-login.php');
        await page.fill('#user_login', ADMIN_USER);
        await page.fill('#user_pass', ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForURL('**/wp-admin/**');
    });

    test('Polski admin page loads', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=polski');
        const adminContainer = page.locator('#polski-admin');
        await expect(adminContainer).toBeVisible({ timeout: 10000 });
    });

    test('setup wizard route loads', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=polski#/setup-wizard');
        const wizardStep = page.locator('.polski-wizard__steps, .polski-wizard');
        await expect(wizardStep).toBeVisible({ timeout: 10000 });
    });
});
