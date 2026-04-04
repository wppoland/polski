# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: checkout-checkboxes.spec.ts >> Checkout Legal Checkboxes >> shows required terms checkbox
- Location: tests/E2E/checkout-checkboxes.spec.ts:29:9

# Error details

```
Test timeout of 30000ms exceeded while running "beforeEach" hook.
```

```
Error: page.click: Test timeout of 30000ms exceeded.
Call log:
  - waiting for locator('.ajax_add_to_cart, .add_to_cart_button')

```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - heading "Not Found" [level=1] [ref=e2]
  - paragraph [ref=e3]: The requested URL was not found on this server.
  - separator [ref=e4]
  - generic [ref=e5]: Apache/2.4.65 (Debian) Server at localhost Port 8888
```

# Test source

```ts
  1  | import { test, expect } from '@playwright/test';
  2  | 
  3  | /**
  4  |  * E2E tests for Polski legal checkboxes on checkout.
  5  |  *
  6  |  * Prerequisites:
  7  |  * - wp-env running with WooCommerce and Polski activated
  8  |  * - At least one product and shipping zone configured
  9  |  */
  10 | 
  11 | const ADMIN_USER = 'admin';
  12 | const ADMIN_PASS = 'password';
  13 | 
  14 | test.describe('Checkout Legal Checkboxes', () => {
  15 |     test.beforeEach(async ({ page }) => {
  16 |         // Go to shop and add the first product to cart.
  17 |         await page.goto('/shop/', { waitUntil: 'networkidle' });
> 18 |         await page.click('.ajax_add_to_cart, .add_to_cart_button');
     |                    ^ Error: page.click: Test timeout of 30000ms exceeded.
  19 |         
  20 |         // Wait for cart to update or just go to checkout.
  21 |         await page.goto('/checkout/', { waitUntil: 'networkidle' });
  22 |     });
  23 | 
  24 |     test('shows legal checkboxes container', async ({ page }) => {
  25 |         const container = page.locator('.polski-legal-checkboxes');
  26 |         await expect(container).toBeVisible();
  27 |     });
  28 | 
  29 |     test('shows required terms checkbox', async ({ page }) => {
  30 |         const termsCheckbox = page.locator('.polski-checkbox--terms input[type="checkbox"]');
  31 |         await expect(termsCheckbox).toBeVisible();
  32 |         await expect(termsCheckbox).toHaveAttribute('required', '');
  33 |     });
  34 | 
  35 |     test('shows required privacy checkbox', async ({ page }) => {
  36 |         const privacyCheckbox = page.locator('.polski-checkbox--privacy input[type="checkbox"]');
  37 |         await expect(privacyCheckbox).toBeVisible();
  38 |     });
  39 | 
  40 |     test('shows required withdrawal checkbox', async ({ page }) => {
  41 |         const withdrawalCheckbox = page.locator('.polski-checkbox--withdrawal input[type="checkbox"]');
  42 |         await expect(withdrawalCheckbox).toBeVisible();
  43 |     });
  44 | 
  45 |     test('shows required asterisk on mandatory checkboxes', async ({ page }) => {
  46 |         const asterisks = page.locator('.polski-checkbox--terms abbr.required');
  47 |         await expect(asterisks).toBeVisible();
  48 |     });
  49 | 
  50 |     test('order button has correct Polish text', async ({ page }) => {
  51 |         const button = page.locator('#place_order');
  52 |         await expect(button).toContainText('Place order');
  53 |     });
  54 | 
  55 |     test('checkout fails without accepting required checkboxes', async ({ page }) => {
  56 |         // Fill billing fields.
  57 |         await page.fill('#billing_first_name', 'Jan');
  58 |         await page.fill('#billing_last_name', 'Kowalski');
  59 |         await page.fill('#billing_address_1', 'ul. Testowa 1');
  60 |         await page.fill('#billing_postcode', '00-001');
  61 |         await page.fill('#billing_city', 'Warszawa');
  62 |         await page.fill('#billing_phone', '500100200');
  63 |         await page.fill('#billing_email', 'jan@test.pl');
  64 | 
  65 |         // Don't check any checkbox.
  66 |         await page.click('#place_order');
  67 | 
  68 |         // Should show error.
  69 |         const error = page.locator('.woocommerce-error, .wc-block-components-notice-banner.is-error');
  70 |         await expect(error).toBeVisible({ timeout: 10000 });
  71 |     });
  72 | });
  73 | 
  74 | test.describe('Admin Dashboard', () => {
  75 |     test.beforeEach(async ({ page }) => {
  76 |         // Login as admin.
  77 |         await page.goto('/wp-login.php');
  78 |         await page.fill('#user_login', ADMIN_USER);
  79 |         await page.fill('#user_pass', ADMIN_PASS);
  80 |         await page.click('#wp-submit');
  81 |         await page.waitForURL('**/wp-admin/**');
  82 |     });
  83 | 
  84 |     test('Polski admin page loads', async ({ page }) => {
  85 |         await page.goto('/wp-admin/admin.php?page=polski');
  86 |         const adminContainer = page.locator('#polski-admin');
  87 |         await expect(adminContainer).toBeVisible({ timeout: 10000 });
  88 |     });
  89 | 
  90 |     test('setup wizard route loads', async ({ page }) => {
  91 |         await page.goto('/wp-admin/admin.php?page=polski#/setup-wizard');
  92 |         const wizardStep = page.locator('.polski-wizard__steps, .polski-wizard');
  93 |         await expect(wizardStep).toBeVisible({ timeout: 10000 });
  94 |     });
  95 | });
  96 | 
```