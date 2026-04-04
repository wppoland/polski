# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: deactivation-feedback.spec.ts >> Deactivation feedback >> submits sidebar feedback form for non-technical users
- Location: tests/E2E/deactivation-feedback.spec.ts:82:9

# Error details

```
Test timeout of 60000ms exceeded while running "beforeEach" hook.
```

```
Error: locator.fill: Test timeout of 60000ms exceeded.
Call log:
  - waiting for getByLabel('Username or Email Address')

```

# Page snapshot

```yaml
- generic [ref=e1]:
  - heading "Zaloguj się" [level=1] [ref=e2]
  - generic [ref=e3]:
    - link "Oparte na WordPressie" [ref=e4] [cursor=pointer]:
      - /url: https://pl.wordpress.org/
    - generic [ref=e5]:
      - paragraph [ref=e6]:
        - generic [ref=e7]: Nazwa użytkownika lub adres e-mail
        - textbox "Nazwa użytkownika lub adres e-mail" [active] [ref=e8]
      - generic [ref=e9]:
        - generic [ref=e10]: Hasło
        - generic [ref=e11]:
          - textbox "Hasło" [ref=e12]
          - button "Pokaż hasło" [ref=e13] [cursor=pointer]:
            - generic [ref=e14]: 
      - paragraph [ref=e15]:
        - checkbox "Zapamiętaj mnie" [ref=e16] [cursor=pointer]
        - generic [ref=e17]: Zapamiętaj mnie
      - paragraph:
        - button "Zaloguj się" [ref=e18] [cursor=pointer]
    - paragraph [ref=e19]:
      - link "Nie pamiętasz hasła?" [ref=e20] [cursor=pointer]:
        - /url: http://localhost:8888/wp-login.php?action=lostpassword
    - paragraph [ref=e21]:
      - link "← Przejdź do polski" [ref=e22] [cursor=pointer]:
        - /url: http://localhost:8888/
  - generic [ref=e24]:
    - generic [ref=e25]:
      - generic [ref=e26]: 
      - generic [ref=e27]: Język
    - combobox "Język" [ref=e28] [cursor=pointer]:
      - option "English (United States)"
      - option "Polski" [selected]
    - button "Zmień" [ref=e29] [cursor=pointer]
```

# Test source

```ts
  1  | import { test, expect, type Page } from '@playwright/test';
  2  | 
  3  | const ADMIN_USER = 'admin';
  4  | const ADMIN_PASS = 'password';
  5  | 
  6  | async function loginToAdmin(page: Page): Promise<void> {
  7  |     await page.goto('/wp-admin/');
  8  |     await page.waitForLoadState('domcontentloaded');
  9  | 
  10 |     if (page.url().includes('/wp-login.php') || await page.locator('#user_login').count() > 0) {
  11 |         await page.locator('#user_login').fill(ADMIN_USER);
  12 |         await page.locator('#user_pass').fill(ADMIN_PASS);
> 13 |         await page.click('#wp-submit');
     |                                                        ^ Error: locator.fill: Test timeout of 60000ms exceeded.
  14 |         await page.waitForLoadState('networkidle');
  15 |     }
  16 | 
  17 |     await expect(page).toHaveURL(/\/wp-admin\//);
  18 | }
  19 | 
  20 | async function setPostSubmitHref(page: Page, selector: string, href: string): Promise<void> {
  21 |     await page.locator(selector).evaluate((link: HTMLAnchorElement, nextHref: string) => {
  22 |         link.href = nextHref;
  23 |     }, href);
  24 | }
  25 | 
  26 | function requestMatchesExpectedFeedback(
  27 |     postData: string,
  28 |     expectedAction: string,
  29 |     expectedReason: string,
  30 |     expectedFeature: string,
  31 | ): boolean {
  32 |     const params = new URLSearchParams(postData);
  33 | 
  34 |     return params.get('action') === expectedAction
  35 |         && params.get('reason') === expectedReason
  36 |         && params.get('requested_feature') === expectedFeature;
  37 | }
  38 | 
  39 | test.describe('Deactivation feedback', () => {
  40 |     test.setTimeout(60000);
  41 | 
  42 |     test.beforeEach(async ({ page }) => {
  43 |         await loginToAdmin(page);
  44 |         await page.goto('/wp-admin/plugins.php');
  45 |     });
  46 | 
  47 |     test('captures free plugin deactivation feedback and shows it in reports', async ({ page }) => {
  48 |         const deactivateSelector = 'tr[data-plugin="polski/polski.php"] .deactivate a, tr[data-plugin="polski/polski.php"] a.deactivate';
  49 | 
  50 |         await setPostSubmitHref(page, deactivateSelector, '/wp-admin/admin.php?page=polski-reports&view=feedback');
  51 |         await page.click(deactivateSelector);
  52 | 
  53 |         const modal = page.locator('#polski-deactivation-modal');
  54 |         await expect(modal).toBeVisible();
  55 |         await expect(modal.getByText('Before you deactivate Polski')).toBeVisible();
  56 | 
  57 |         await modal.locator('input[value="missing_feature"]').check();
  58 |         await modal.locator('#polski-feedback-improvement').fill('The setup could be clearer for first-time merchants.');
  59 |         await modal.locator('#polski-feedback-feature').fill('Please add more merchant onboarding presets.');
  60 | 
  61 |         const requestPromise = page.waitForRequest((request) => {
  62 |             if (!request.url().includes('/wp-admin/admin-ajax.php') || request.method() !== 'POST') {
  63 |                 return false;
  64 |             }
  65 | 
  66 |             return requestMatchesExpectedFeedback(
  67 |                 request.postData() ?? '',
  68 |                 'polski_submit_deactivation_feedback',
  69 |                 'missing_feature',
  70 |                 'Please add more merchant onboarding presets.',
  71 |             );
  72 |         });
  73 | 
  74 |         await modal.locator('#polski-submit-feedback').click({ force: true });
  75 |         await requestPromise;
  76 |         await page.waitForURL('**/wp-admin/admin.php?page=polski-reports&view=feedback');
  77 |         await expect(page.getByText('The setup could be clearer for first-time merchants.')).toBeVisible();
  78 |         await expect(page.getByText('Please add more merchant onboarding presets.')).toBeVisible();
  79 |     });
  80 | 
  81 |     test('submits sidebar feedback form for non-technical users', async ({ page }) => {
  82 |         await page.goto('/wp-admin/admin.php?page=polski');
  83 | 
  84 |         await page.getByLabel('Your name').fill('Mariusz');
  85 |         await page.getByLabel('Your email').fill('mariusz@example.com');
  86 |         await page.getByLabel('Topic').selectOption('general_feedback');
  87 |         await page.getByLabel('Message').fill('Please make the onboarding easier for first-time merchants.');
  88 |         await page.getByRole('button', { name: 'Send feedback' }).click();
  89 | 
  90 |         await page.waitForURL('**/wp-admin/admin.php?page=polski**');
  91 |         await expect(page.getByText('Thanks, your feedback has been saved.')).toBeVisible();
  92 | 
  93 |         await page.goto('/wp-admin/admin.php?page=polski-reports&view=feedback');
  94 |         await expect(page.getByText('Logi opinii z panelu')).toBeVisible();
  95 |         await expect(page.getByText('Please make the onboarding easier for first-time merchants.')).toBeVisible();
  96 |     });
  97 | });
  98 | 
```