import { expect, test, type Page } from '@playwright/test';

const PRODUCT_ALPHA = 'Polski E2E Alpha Drill';
const PRODUCT_BETA = 'Polski E2E Beta Saw';

function productCard(page: Page, productName: string) {
    return page.locator('li.product').filter({
        has: page.getByRole('link', { name: productName }).first(),
    }).first();
}

test.describe('Storefront merchandising modules', () => {
    test('AJAX search endpoint returns matching products', async ({ request }) => {
        const response = await request.get('/?rest_route=/polski/v1/search&q=Alpha&per_page=10');
        expect(response.ok()).toBeTruthy();

        const payload = await response.json();
        const match = payload.results.find((result: { name?: string; sku?: string }) => result.sku === 'E2E-ALPHA-001');

        expect(match?.name).toBe(PRODUCT_ALPHA);
    });

    test('AJAX search returns matching products on the shortcode page', async ({ page }) => {
        await page.goto('/?pagename=polski-e2e-search', { waitUntil: 'networkidle' });
        await expect(page.locator('[data-polski-ajax-search-input]')).toBeVisible();
        await expect(page.locator('[data-polski-ajax-search-results]')).toBeHidden();
    });

    test('filter query narrows archive results by brand', async ({ page }) => {
        await page.goto('/?post_type=product&polski_filter_brand=e2e-brand-beta', { waitUntil: 'networkidle' });
        await expect(page.locator('a[href*="product=polski-e2e-beta-saw"]').first()).toBeVisible();
        await expect(page.locator('a[href*="product=polski-e2e-alpha-drill"]')).toHaveCount(0);
    });

    test('wishlist button toggles on product cards', async ({ page }) => {
        await page.goto('/?post_type=product', { waitUntil: 'networkidle' });

        const card = productCard(page, PRODUCT_ALPHA);
        const button = card.locator('[data-polski-wishlist-button]');

        await expect(button).toBeVisible();
        await expect(button).toContainText('Add to Wishlist');

        await button.click();

        await expect(button).toHaveClass(/is-active/);
        await expect(button).toContainText('Remove from Wishlist');
    });

    test('compare flow adds a product and opens the compare table', async ({ page }) => {
        await page.goto('/?post_type=product', { waitUntil: 'networkidle' });

        const card = productCard(page, PRODUCT_ALPHA);
        const button = card.locator('[data-polski-compare-button]');
        const compareLink = card.locator('.polski-compare-link');

        await expect(button).toBeVisible();
        await button.click();

        await expect(button).toHaveClass(/is-active/);
        await compareLink.click();

        await expect(page).toHaveURL(/polski_compare=1/);
        await expect(page.locator('.polski-compare-table')).toBeVisible();
        await expect(page.locator('.polski-compare-table')).toContainText(PRODUCT_ALPHA);
    });

    test('quick view opens a modal with product details', async ({ page }) => {
        await page.goto('/?post_type=product', { waitUntil: 'networkidle' });

        const card = productCard(page, PRODUCT_BETA);
        const trigger = card.locator('[data-polski-quick-view]');
        const modal = page.locator('[data-polski-quick-view-modal]');
        const content = page.locator('[data-polski-quick-view-content]');

        await expect(trigger).toBeVisible();
        await trigger.click();

        await expect(modal).toBeVisible();
        await expect(content).toContainText(PRODUCT_BETA);
        await expect(content).toContainText('E2E-BETA-002');
    });
});
