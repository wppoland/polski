import { test, expect, type Page } from '@playwright/test';

const ADMIN_USER = 'admin';
const ADMIN_PASS = 'password';

async function loginToAdmin(page: Page): Promise<void> {
    await page.goto('/wp-admin/');
    await page.waitForLoadState('domcontentloaded');

    if (page.url().includes('/wp-login.php') || await page.locator('#user_login').count() > 0) {
        await page.locator('#user_login').fill(ADMIN_USER);
        await page.locator('#user_pass').fill(ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');
    }

    await expect(page).toHaveURL(/\/wp-admin\//);
}

async function setPostSubmitHref(page: Page, selector: string, href: string): Promise<void> {
    await page.locator(selector).evaluate((link: HTMLAnchorElement, nextHref: string) => {
        link.href = nextHref;
    }, href);
}

function requestMatchesExpectedFeedback(
    postData: string,
    expectedAction: string,
    expectedReason: string,
    expectedFeature: string,
): boolean {
    const params = new URLSearchParams(postData);

    return params.get('action') === expectedAction
        && params.get('reason') === expectedReason
        && params.get('requested_feature') === expectedFeature;
}

test.describe('Deactivation feedback', () => {
    test.setTimeout(60000);

    test.beforeEach(async ({ page }) => {
        await loginToAdmin(page);
        await page.goto('/wp-admin/plugins.php');
    });

    test('captures free plugin deactivation feedback and shows it in reports', async ({ page }) => {
        const deactivateSelector = 'tr[data-plugin="polski/polski.php"] .deactivate a, tr[data-plugin="polski/polski.php"] a.deactivate';

        await setPostSubmitHref(page, deactivateSelector, '/wp-admin/admin.php?page=polski&tab=reports&view=feedback');
        await page.click(deactivateSelector);

        const modal = page.locator('#polski-deactivation-modal');
        await expect(modal).toBeVisible();
        await expect(modal.getByText('Before you deactivate Polski')).toBeVisible();

        await modal.locator('input[value="missing_feature"]').check();
        await modal.locator('#polski-feedback-improvement').fill('The setup could be clearer for first-time merchants.');
        await modal.locator('#polski-feedback-feature').fill('Please add more merchant onboarding presets.');

        const requestPromise = page.waitForRequest((request) => {
            if (!request.url().includes('/wp-admin/admin-ajax.php') || request.method() !== 'POST') {
                return false;
            }

            return requestMatchesExpectedFeedback(
                request.postData() ?? '',
                'polski_submit_deactivation_feedback',
                'missing_feature',
                'Please add more merchant onboarding presets.',
            );
        });

        // jQuery .click() handler: avoid Playwright viewport issues on tall modals.
        await page.evaluate(() => {
            document.getElementById('polski-submit-feedback')?.click();
        });
        await requestPromise;
        await page.waitForURL(/admin\.php\?page=polski&tab=reports&view=feedback/);
        await expect(page.getByText('The setup could be clearer for first-time merchants.').first()).toBeVisible();
        await expect(page.getByText('Please add more merchant onboarding presets.').first()).toBeVisible();
    });

    test('submits sidebar feedback form for non-technical users', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=polski');

        // Form lives inside a closed <details>; open it so fields are visible to the user agent.
        await page.locator('#polski-feedback-form-dashboard').evaluate((form) => {
            form.closest('details')?.setAttribute('open', '');
        });

        await page.locator('#polski-feedback-form-dashboard-name').fill('Mariusz');
        await page.locator('#polski-feedback-form-dashboard-email').fill('mariusz@example.com');
        await page.locator('#polski-feedback-form-dashboard-topic').selectOption('general_feedback');
        await page.locator('#polski-feedback-form-dashboard-message').fill(
            'Please make the onboarding easier for first-time merchants.',
        );
        await page.locator('#polski-feedback-form-dashboard').getByRole('button', { name: /^Send feedback$/i }).click();

        await page.waitForURL('**/wp-admin/admin.php?page=polski**');
        await expect(page.getByText('Thanks, your feedback has been saved.')).toBeVisible();

        await page.goto('/wp-admin/admin.php?page=polski&tab=reports&view=feedback');
        await expect(page.getByText('Feedback Logs')).toBeVisible();
        await expect(page.getByText('Please make the onboarding easier for first-time merchants.').first()).toBeVisible();
    });
});
