import { chromium } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const projectRoot = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const outputDir = path.join(projectRoot, 'docs', 'wporg-assets');
const baseUrl = 'http://localhost:8888';

function runWpCli(args) {
    return execFileSync('npm', ['run', 'env:cli', '--', ...args], {
        cwd: projectRoot,
        encoding: 'utf8',
    });
}

function extractJson(raw) {
    const start = raw.indexOf('{');
    const end = raw.lastIndexOf('}');
    if (start === -1 || end === -1 || end <= start) {
        throw new Error(`Could not extract JSON from output:\n${raw}`);
    }

    return JSON.parse(raw.slice(start, end + 1));
}

async function login(page, login, password) {
    await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'networkidle' });
    await page.fill('#user_login', login);
    await page.fill('#user_pass', password);
    await page.click('#wp-submit');
    await page.waitForLoadState('networkidle');
}

async function ensureDir(dir) {
    await fs.promises.mkdir(dir, { recursive: true });
}

async function captureLocator(locator, filePath) {
    await locator.scrollIntoViewIfNeeded();
    await locator.screenshot({ path: filePath });
}

async function firstVisibleLocator(page, selectors) {
    for (const selector of selectors) {
        const locator = page.locator(selector).first();

        if (await locator.count() === 0) {
            continue;
        }

        if (await locator.isVisible().catch(() => false)) {
            return locator;
        }
    }

    throw new Error(`None of the selectors matched a visible element: ${selectors.join(', ')}`);
}

async function main() {
    await ensureDir(outputDir);

    runWpCli(['wp', 'plugin', 'activate', 'polski']);
    const fixtureOutput = runWpCli([
        '--env-cwd=wp-content/plugins/polski',
        'wp',
        'eval-file',
        'tests/E2E/fixtures/wporg-screenshots-bootstrap.php',
    ]);
    const fixture = extractJson(fixtureOutput);

    const browser = await chromium.launch({ headless: true });

    const adminContext = await browser.newContext({
        baseURL: baseUrl,
        viewport: { width: 1440, height: 1400 },
    });
    const adminPage = await adminContext.newPage();
    await login(adminPage, fixture.admin.login, fixture.admin.password);

    console.log('Capturing screenshot 1: modules dashboard');
    await adminPage.goto('/wp-admin/admin.php?page=polski&tab=modules', { waitUntil: 'networkidle' });
    await captureLocator(adminPage.locator('.wrap').first(), path.join(outputDir, 'screenshot-1-modules-dashboard.png'));

    console.log('Capturing screenshot 2: GPSR product editor');
    await adminPage.goto(`/wp-admin/post.php?post=${fixture.products.sale}&action=edit`, { waitUntil: 'networkidle' });
    await adminPage.click('a[href="#polski_product_data"]');
    await adminPage.waitForSelector('#polski_product_data', { state: 'visible' });
    await adminPage.locator('text=GPSR').first().scrollIntoViewIfNeeded();
    await captureLocator(adminPage.locator('#polski_product_data'), path.join(outputDir, 'screenshot-2-gpsr-product-editor.png'));

    await adminContext.close();

    const guestContext = await browser.newContext({
        baseURL: baseUrl,
        viewport: { width: 1440, height: 1400 },
    });
    const guestPage = await guestContext.newPage();

    console.log('Capturing screenshot 3: checkout');
    await guestPage.goto(`/?add-to-cart=${fixture.products.sale}`, { waitUntil: 'networkidle' });
    await guestPage.goto(fixture.urls.checkout, { waitUntil: 'networkidle' });
    const checkoutLocator = guestPage.locator('.wc-block-checkout, form.checkout').first();
    await checkoutLocator.waitFor({ state: 'visible' });
    await captureLocator(checkoutLocator, path.join(outputDir, 'screenshot-3-checkout-checkboxes.png'));

    console.log('Capturing screenshot 4: Omnibus');
    await guestPage.goto(fixture.urls.sale_product, { waitUntil: 'networkidle' });
    const productDetails = await firstVisibleLocator(guestPage, [
        '.summary.entry-summary',
        '.wp-block-columns.alignwide',
        '.wp-block-woocommerce-product-price',
    ]);
    await captureLocator(productDetails, path.join(outputDir, 'screenshot-4-omnibus-lowest-price.png'));

    console.log('Capturing screenshot 6: DSA form');
    await guestPage.goto(fixture.urls.dsa, { waitUntil: 'networkidle' });
    const dsaForm = await firstVisibleLocator(guestPage, [
        'form[action*="polski_dsa_report"]',
        'form:has-text("URL")',
        '.polski-dsa-report-form',
    ]);
    await captureLocator(dsaForm, path.join(outputDir, 'screenshot-6-dsa-report-form.png'));

    console.log('Capturing screenshot 7: AJAX search');
    await guestPage.goto(fixture.urls.search, { waitUntil: 'networkidle' });
    await guestPage.fill('[data-polski-ajax-search-input]', 'Alpha');
    await guestPage.waitForTimeout(800);
    await guestPage.screenshot({
        path: path.join(outputDir, 'screenshot-7-storefront-search-filters.png'),
        fullPage: false,
    });

    console.log('Capturing screenshot 8: storefront actions');
    await guestPage.goto(`${fixture.urls.shop}?polski_filter_brand=wporg-brand-beta`, { waitUntil: 'networkidle' });
    const firstWishlist = guestPage.locator('[data-polski-wishlist-button]').first();
    const firstCompare = guestPage.locator('[data-polski-compare-button]').first();
    const quickView = guestPage.locator('[data-polski-quick-view]').first();
    await firstWishlist.click();
    await firstCompare.click();
    await quickView.click();
    await guestPage.waitForSelector('[data-polski-quick-view-modal]', { state: 'visible' });
    await captureLocator(guestPage.locator('body'), path.join(outputDir, 'screenshot-8-wishlist-compare-quick-view.png'));
    await guestContext.close();

    const customerContext = await browser.newContext({
        baseURL: baseUrl,
        viewport: { width: 1440, height: 1400 },
    });
    const customerPage = await customerContext.newPage();
    await login(customerPage, fixture.customer.login, fixture.customer.password);

    console.log('Capturing screenshot 5: withdrawal request');
    await customerPage.goto(fixture.urls.myaccount_orders, { waitUntil: 'networkidle' });
    const withdrawalContent = await firstVisibleLocator(customerPage, [
        '.woocommerce-MyAccount-content',
        'main .entry-content',
        'main',
    ]);
    await captureLocator(withdrawalContent, path.join(outputDir, 'screenshot-5-withdrawal-request.png'));
    await customerContext.close();

    await browser.close();
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
