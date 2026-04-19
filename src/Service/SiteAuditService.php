<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * Site compliance audit - checks the shop for common regulatory issues.
 *
 * Runs automated checks on:
 * - Missing legal pages (regulamin, privacy policy, withdrawal)
 * - Pre-checked consent checkboxes (dark pattern)
 * - Missing business identification (NIP, address, contact)
 * - Privacy policy completeness (RODO Art. 13 elements)
 * - DPA status with third-party services
 * - DSA, KSeF-ready, anti-greenwashing, and CRA-related module coverage
 *
 * @author wppoland.com
 */
final class SiteAuditService implements HasHooks
{
    private const STATUS_PASS = 'pass';
    private const STATUS_WARNING = 'warning';
    private const STATUS_FAIL = 'fail';

    public function registerHooks(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        add_action('wp_ajax_polski_run_audit', [$this, 'handleAuditAjax']);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('site_audit');
    }

    public function renderAuditPage(): void
    {
        $results = $this->runAudit();
        $passed = count(array_filter($results, static fn(array $r): bool => $r['status'] === self::STATUS_PASS));
        $warnings = count(array_filter($results, static fn(array $r): bool => $r['status'] === self::STATUS_WARNING));
        $failures = count(array_filter($results, static fn(array $r): bool => $r['status'] === self::STATUS_FAIL));

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Shop compliance audit', 'polski') . '</h1>';
        echo '<p>' . esc_html__('Automatic verification of common issues for Polish WooCommerce shops.', 'polski') . '</p>';

        echo '<div style="display:flex;gap:20px;margin:20px 0;">';
        printf(
            '<div style="padding:15px 25px;background:#46b450;color:#fff;border-radius:4px;"><strong>%d</strong> %s</div>',
            (int) $passed,
            esc_html__('Passed', 'polski'),
        );
        printf(
            '<div style="padding:15px 25px;background:#f0ad4e;color:#fff;border-radius:4px;"><strong>%d</strong> %s</div>',
            (int) $warnings,
            esc_html__('Warnings', 'polski'),
        );
        printf(
            '<div style="padding:15px 25px;background:#dc3232;color:#fff;border-radius:4px;"><strong>%d</strong> %s</div>',
            (int) $failures,
            esc_html__('Issues', 'polski'),
        );
        echo '</div>';

        echo '<table class="widefat fixed striped"><thead><tr>';
        echo '<th style="width:40px;">' . esc_html__('Status', 'polski') . '</th>';
        echo '<th>' . esc_html__('Check', 'polski') . '</th>';
        echo '<th>' . esc_html__('Details', 'polski') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($results as $check) {
            $icon = match ($check['status']) {
                self::STATUS_PASS => '<span style="color:#46b450;">&#10003;</span>',
                self::STATUS_WARNING => '<span style="color:#f0ad4e;">&#9888;</span>',
                self::STATUS_FAIL => '<span style="color:#dc3232;">&#10007;</span>',
                default => '<span style="color:#999;">?</span>',
            };
            echo '<tr>';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $icon contains static HTML entities
            echo '<td>' . $icon . '</td>';
            echo '<td><strong>' . esc_html($check['label']) . '</strong></td>';
            echo '<td>' . esc_html($check['detail']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * Run all audit checks.
     *
     * @return list<array{status: string, label: string, detail: string}>
     */
    public function runAudit(): array
    {
        return [
            $this->checkLegalPages(),
            $this->checkPrivacyPolicy(),
            $this->checkBusinessIdentification(),
            $this->checkPreCheckedBoxes(),
            $this->checkOrderButtonText(),
            $this->checkOmnibusEnabled(),
            $this->checkGDPRCheckboxes(),
            $this->checkWithdrawalModule(),
            $this->checkGPSRModule(),
            $this->checkVerifiedReviewModule(),
            $this->checkDSAModule(),
            $this->checkKSeFReadyModule(),
            $this->checkAntiGreenwashingModule(),
            $this->checkDPARegistry(),
            $this->checkSecurityIncidents(),
            $this->checkSSL(),
            // Dark pattern enhancements.
            $this->checkForcedAccountCreation(),
            $this->checkStaleSaleDates(),
            $this->checkMisleadingFromPrice(),
            $this->checkFakeLowStockThreshold(),
        ];
    }

    /**
     * Forced account creation is a dark pattern per EU Directive 2023/2673
     * unless the store has a legitimate B2B reason to require an account.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkForcedAccountCreation(): array
    {
        $label = __('Forced account creation (dark pattern)', 'polski');

        $guestCheckout = get_option('woocommerce_enable_guest_checkout', 'yes');
        $mustLogin = get_option('woocommerce_enable_checkout_login_reminder', 'no');

        if ($guestCheckout === 'yes') {
            return [
                'status' => self::STATUS_PASS,
                'label' => $label,
                'detail' => __('Guest checkout is enabled. Customers are not forced to create an account.', 'polski'),
            ];
        }

        if ($mustLogin === 'yes') {
            return [
                'status' => self::STATUS_FAIL,
                'label' => $label,
                'detail' => __('Guest checkout is disabled AND login is required — customers are forced to create an account. EU Directive 2023/2673 considers this a dark pattern unless justified (e.g. B2B). Enable guest checkout under WooCommerce > Settings > Accounts.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_WARNING,
            'label' => $label,
            'detail' => __('Guest checkout is disabled. If this is not a B2B-only store, enable guest checkout under WooCommerce > Settings > Accounts.', 'polski'),
        ];
    }

    /**
     * Flag products whose sale date ended in the past but are still listed
     * as on-sale (fake flash-sale countdown). Scans the 100 most recent
     * published products; a conservative sample for the admin audit.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkStaleSaleDates(): array
    {
        $label = __('Stale or fake sale countdown (dark pattern)', 'polski');

        if (! function_exists('wc_get_products')) {
            return [
                'status' => self::STATUS_WARNING,
                'label' => $label,
                'detail' => __('WooCommerce not active.', 'polski'),
            ];
        }

        $productIds = wc_get_products([
            'status' => 'publish',
            'limit' => 100,
            'return' => 'ids',
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);

        if (! is_array($productIds) || $productIds === []) {
            return [
                'status' => self::STATUS_PASS,
                'label' => $label,
                'detail' => __('No products to scan.', 'polski'),
            ];
        }

        $now = time();
        $stale = 0;

        foreach ($productIds as $productId) {
            $product = wc_get_product((int) $productId);

            if (! $product) {
                continue;
            }

            $salesTo = $product->get_date_on_sale_to('edit');

            if ($salesTo === null) {
                continue;
            }

            $ts = is_numeric($salesTo) ? (int) $salesTo : strtotime((string) $salesTo);

            if ($ts > 0 && $ts < $now - DAY_IN_SECONDS && $product->is_on_sale()) {
                ++$stale;
            }
        }

        if ($stale === 0) {
            return [
                'status' => self::STATUS_PASS,
                'label' => $label,
                'detail' => __('No products with expired sale dates are still on sale.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_WARNING,
            'label' => $label,
            'detail' => sprintf(
                /* translators: %d: number of products */
                __('%d product(s) have a sale-to date in the past but still appear on sale. Ensure your countdown or promo widgets do not silently reset after expiry.', 'polski'),
                $stale,
            ),
        ];
    }

    /**
     * Variable products showing a "from" price where the minimum is less
     * than 50 percent of the maximum can mislead buyers into expecting the
     * lower price. Flag as warning for manual review.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkMisleadingFromPrice(): array
    {
        $label = __('Misleading "from" price on variable products', 'polski');

        if (! function_exists('wc_get_products')) {
            return [
                'status' => self::STATUS_WARNING,
                'label' => $label,
                'detail' => __('WooCommerce not active.', 'polski'),
            ];
        }

        $productIds = wc_get_products([
            'status' => 'publish',
            'type' => 'variable',
            'limit' => 100,
            'return' => 'ids',
        ]);

        if (! is_array($productIds) || $productIds === []) {
            return [
                'status' => self::STATUS_PASS,
                'label' => $label,
                'detail' => __('No variable products to scan.', 'polski'),
            ];
        }

        $suspects = 0;

        foreach ($productIds as $productId) {
            $product = wc_get_product((int) $productId);

            if (! $product || $product->get_type() !== 'variable') {
                continue;
            }

            $min = (float) $product->get_variation_price('min', true);
            $max = (float) $product->get_variation_price('max', true);

            if ($min <= 0 || $max <= 0) {
                continue;
            }

            if ($min < $max * 0.5) {
                ++$suspects;
            }
        }

        if ($suspects === 0) {
            return [
                'status' => self::STATUS_PASS,
                'label' => $label,
                'detail' => __('All variable products have a reasonable min/max price spread.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_WARNING,
            'label' => $label,
            'detail' => sprintf(
                /* translators: %d: number of products */
                __('%d variable product(s) show a "from" price where the cheapest variant is less than 50%% of the most expensive. Consider showing a price range or clarifying the variant context to avoid misleading buyers.', 'polski'),
                $suspects,
            ),
        ];
    }

    /**
     * WooCommerce low-stock messaging ("Only X left") becomes a false
     * urgency signal when the stock threshold is set unreasonably high.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkFakeLowStockThreshold(): array
    {
        $label = __('False urgency via low-stock threshold (dark pattern)', 'polski');

        $threshold = (int) get_option('woocommerce_notify_low_stock_amount', 2);

        if ($threshold <= 5) {
            return [
                'status' => self::STATUS_PASS,
                'label' => $label,
                'detail' => sprintf(
                    /* translators: %d: threshold value */
                    __('Low-stock threshold is %d, within a reasonable range.', 'polski'),
                    $threshold,
                ),
            ];
        }

        return [
            'status' => self::STATUS_WARNING,
            'label' => $label,
            'detail' => sprintf(
                /* translators: %d: threshold value */
                __('Low-stock threshold is %d, which is high. A threshold above 5 can make "Only X left" appear on plentiful stock and create artificial urgency. Consider lowering it under WooCommerce > Settings > Products > Inventory.', 'polski'),
                $threshold,
            ),
        ];
    }

    /**
     * Check that WooCommerce terms page and privacy policy page are configured.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkLegalPages(): array
    {
        $label = __('Legal pages (Terms, Privacy Policy)', 'polski');

        $termsPageId = (int) get_option('woocommerce_terms_page_id', 0);
        $privacyPageId = (int) get_option('wp_page_for_privacy_policy', 0);

        $missingPages = [];

        if ($termsPageId <= 0 || get_post_status($termsPageId) !== 'publish') {
            $missingPages[] = __('Terms', 'polski');
        }

        if ($privacyPageId <= 0 || get_post_status($privacyPageId) !== 'publish') {
            $missingPages[] = __('Privacy Policy', 'polski');
        }

        if (count($missingPages) === 0) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('Terms and Privacy Policy pages are set and published.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_FAIL,
            'label'  => $label,
            'detail' => sprintf(
                /* translators: %s: comma-separated list of missing pages */
                __('Missing pages: %s. Set them in WooCommerce > Settings > Advanced.', 'polski'),
                implode(', ', $missingPages),
            ),
        ];
    }

    /**
     * Check that a privacy policy page exists and has content.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkPrivacyPolicy(): array
    {
        $label = __('Privacy Policy content', 'polski');
        $privacyPageId = (int) get_option('wp_page_for_privacy_policy', 0);

        if ($privacyPageId <= 0) {
            return [
                'status' => self::STATUS_FAIL,
                'label'  => $label,
                'detail' => __('Privacy Policy page is not set.', 'polski'),
            ];
        }

        $privacyPage = get_post($privacyPageId);

        if (! $privacyPage || $privacyPage->post_status !== 'publish') {
            return [
                'status' => self::STATUS_FAIL,
                'label'  => $label,
                'detail' => __('Privacy Policy page is not published.', 'polski'),
            ];
        }

        $contentLength = mb_strlen(wp_strip_all_tags($privacyPage->post_content));
        $minimumContentLength = 500;

        if ($contentLength < $minimumContentLength) {
            return [
                'status' => self::STATUS_WARNING,
                'label'  => $label,
                'detail' => sprintf(
                    /* translators: %d: character count */
                    __('Privacy Policy has only %d characters - it may be too short to comply with GDPR Art. 13.', 'polski'),
                    $contentLength,
                ),
            ];
        }

        return [
            'status' => self::STATUS_PASS,
            'label'  => $label,
            'detail' => sprintf(
                /* translators: %d: character count */
                __('Privacy Policy contains %d characters.', 'polski'),
                $contentLength,
            ),
        ];
    }

    /**
     * Check that basic business identification is configured (company name, NIP, address, email).
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkBusinessIdentification(): array
    {
        $label = __('Business identification data', 'polski');
        $generalSettings = get_option('polski_general', []);

        if (! is_array($generalSettings)) {
            $generalSettings = [];
        }

        $requiredFields = [
            'company_name'  => __('Company name', 'polski'),
            'company_nip'   => __('VAT ID (NIP)', 'polski'),
            'company_address' => __('Address', 'polski'),
            'company_email' => __('Contact email', 'polski'),
        ];

        $missingFields = [];

        foreach ($requiredFields as $fieldKey => $fieldLabel) {
            $fieldValue = $generalSettings[$fieldKey] ?? '';

            if (empty(trim((string) $fieldValue))) {
                $missingFields[] = $fieldLabel;
            }
        }

        if (count($missingFields) === 0) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('All basic company data is completed.', 'polski'),
            ];
        }

        $severity = count($missingFields) >= 3 ? self::STATUS_FAIL : self::STATUS_WARNING;

        return [
            'status' => $severity,
            'label'  => $label,
            'detail' => sprintf(
                /* translators: %s: comma-separated list of missing fields */
                __('Missing data: %s. Fill in Polski > Settings.', 'polski'),
                implode(', ', $missingFields),
            ),
        ];
    }

    /**
     * Check that no checkout checkboxes are pre-checked by default (dark pattern).
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkPreCheckedBoxes(): array
    {
        $label = __('Pre-checked checkboxes (dark pattern)', 'polski');
        $checkoutSettings = get_option('polski_checkout', []);

        if (! is_array($checkoutSettings)) {
            $checkoutSettings = [];
        }

        $checkboxKeys = [
            'terms_checkbox',
            'privacy_checkbox',
            'withdrawal_checkbox',
            'digital_waiver_checkbox',
            'parcel_delivery_checkbox',
            'review_reminder_checkbox',
            'marketing_checkbox',
        ];

        $preCheckedBoxes = [];

        foreach ($checkboxKeys as $checkboxKey) {
            $checkedKey = $checkboxKey . '_checked';

            if (! empty($checkoutSettings[$checkedKey])) {
                $preCheckedBoxes[] = $checkboxKey;
            }
        }

        if (count($preCheckedBoxes) === 0) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('No checkboxes are checked by default.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_FAIL,
            'label'  => $label,
            'detail' => sprintf(
                /* translators: %d: number of pre-checked checkboxes */
                __('Detected %d pre-checked checkboxes. Authorities consider this a dark pattern.', 'polski'),
                count($preCheckedBoxes),
            ),
        ];
    }

    /**
     * Check that the order button text contains recommended wording about payment obligation.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkOrderButtonText(): array
    {
        $label = __('Order button text', 'polski');
        $checkoutSettings = get_option('polski_checkout', []);

        if (! is_array($checkoutSettings)) {
            $checkoutSettings = [];
        }

        $buttonText = $checkoutSettings['order_button_text'] ?? '';
        $buttonTextLower = mb_strtolower((string) $buttonText);

        $requiredPhrases = ['pay', 'payment', 'oplacam', 'platnosc', 'zaplac', 'obowiazkiem zaplaty'];

        foreach ($requiredPhrases as $phrase) {
            if (mb_strpos($buttonTextLower, $phrase) !== false) {
                return [
                    'status' => self::STATUS_PASS,
                    'label'  => $label,
                    'detail' => sprintf(
                        /* translators: %s: the button text */
                        __('Button: "%s" - contains info about payment obligation.', 'polski'),
                        $buttonText,
                    ),
                ];
            }
        }

        if (empty($buttonText)) {
            return [
                'status' => self::STATUS_WARNING,
                'label'  => $label,
                'detail' => __('Button text is not configured. Default WooCommerce setting might lack required payment info.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_WARNING,
            'label'  => $label,
            'detail' => sprintf(
                /* translators: %s: the button text */
                __('Button: "%s" - may not contain required payment info (e.g. "Order with obligation to pay").', 'polski'),
                $buttonText,
            ),
        ];
    }

    /**
     * Check whether the Omnibus directive module is enabled.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkOmnibusEnabled(): array
    {
        $label = __('Omnibus module (lowest price)', 'polski');

        if (ModulesPage::isModuleEnabled('omnibus')) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('Omnibus module is enabled - lowest price from 30 days will be shown on sales.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_FAIL,
            'label'  => $label,
            'detail' => __('Omnibus module is disabled. Omnibus Directive requires showing lowest price from 30 days on sales.', 'polski'),
        ];
    }

    /**
     * Check whether GDPR legal checkboxes are enabled.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkGDPRCheckboxes(): array
    {
        $label = __('Legal checkboxes (GDPR)', 'polski');

        if (ModulesPage::isModuleEnabled('legal_checkboxes')) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('Legal checkboxes module is enabled.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_FAIL,
            'label'  => $label,
            'detail' => __('Legal checkboxes module is disabled. The shop might not collect required GDPR consents.', 'polski'),
        ];
    }

    /**
     * Check whether the withdrawal (right of return) module is enabled.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkWithdrawalModule(): array
    {
        $label = __('Right of withdrawal', 'polski');

        if (ModulesPage::isModuleEnabled('withdrawal')) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('Withdrawal module is enabled.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_WARNING,
            'label'  => $label,
            'detail' => __('Withdrawal module is disabled. Consumers have a 14-day right of withdrawal.', 'polski'),
        ];
    }

    /**
     * Check whether the GPSR module is enabled.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkGPSRModule(): array
    {
        $label = __('GPSR module (product safety)', 'polski');

        if (ModulesPage::isModuleEnabled('gpsr')) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('GPSR module is enabled - manufacturer and importer data can be displayed.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_WARNING,
            'label'  => $label,
            'detail' => __('GPSR module is disabled. Regulation (EU) 2023/988 requires providing manufacturer/importer data.', 'polski'),
        ];
    }

    /**
     * Check whether verified review badges are enabled.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkVerifiedReviewModule(): array
    {
        $label = __('Verified reviews', 'polski');

        if (ModulesPage::isModuleEnabled('verified_review')) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('Verified reviews module is enabled - the shop can mark reviews from actual buyers.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_WARNING,
            'label'  => $label,
            'detail' => __('Verified reviews module is disabled. It makes it harder to distinguish reviews from actual customers.', 'polski'),
        ];
    }

    /**
     * Check whether the DSA report flow is enabled.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkDSAModule(): array
    {
        $label = __('DSA - reporting illegal content', 'polski');

        if (ModulesPage::isModuleEnabled('dsa_toolkit')) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('DSA module is enabled - report form and dashboard are available.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_WARNING,
            'label'  => $label,
            'detail' => __('DSA module is disabled. Marketplaces or shops with sponsored content should have a notice-and-action procedure.', 'polski'),
        ];
    }

    /**
     * Check whether KSeF-ready helpers are enabled.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkKSeFReadyModule(): array
    {
        $label = __('KSeF-ready', 'polski');

        if (ModulesPage::isModuleEnabled('ksef_ready')) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('KSeF-ready module is enabled - orders with VAT ID can be marked for invoice integration.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_WARNING,
            'label'  => $label,
            'detail' => __('KSeF-ready module is disabled. B2B shops might need automatic marking of orders requiring e-invoices.', 'polski'),
        ];
    }

    /**
     * Check whether anti-greenwashing product fields are enabled.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkAntiGreenwashingModule(): array
    {
        $label = __('Environmental claims (anti-greenwashing)', 'polski');

        if (ModulesPage::isModuleEnabled('anti_greenwashing')) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('Anti-greenwashing module is enabled - products can store claims basis and certificate links.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_WARNING,
            'label'  => $label,
            'detail' => __('Anti-greenwashing module is disabled. It is good practice to store sources for environmental claims.', 'polski'),
        ];
    }

    /**
     * Check DPA registry status against tracked services.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkDPARegistry(): array
    {
        $label = __('DPA registry (data processing agreements)', 'polski');

        if (! ModulesPage::isModuleEnabled('dpa_tracker')) {
            return [
                'status' => self::STATUS_WARNING,
                'label'  => $label,
                'detail' => __('DPA registry module is disabled. It is good practice to monitor DPA status for hosting, payments, etc.', 'polski'),
            ];
        }

        $registry = get_option('polski_dpa_registry', []);
        $registry = is_array($registry) ? $registry : [];

        $trackedServices = 0;
        $coveredServices = 0;

        foreach ($registry as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $trackedServices++;

            if (! empty($entry['has_dpa'])) {
                $coveredServices++;
            }
        }

        if ($trackedServices === 0) {
            return [
                'status' => self::STATUS_WARNING,
                'label'  => $label,
                'detail' => __('DPA module is enabled, but the registry is empty. Mark at least hosting, payments, and other data processing services.', 'polski'),
            ];
        }

        if ($coveredServices === 0) {
            return [
                'status' => self::STATUS_FAIL,
                'label'  => $label,
                'detail' => __('DPA registry has no active data processing agreements marked.', 'polski'),
            ];
        }

        if ($coveredServices < $trackedServices) {
            return [
                'status' => self::STATUS_WARNING,
                'label'  => $label,
                'detail' => sprintf(
                    /* translators: 1: number of services with DPA, 2: number of tracked services */
                    __('Only %1$d of %2$d tracked services have a data processing agreement marked.', 'polski'),
                    $coveredServices,
                    $trackedServices,
                ),
            ];
        }

        return [
            'status' => self::STATUS_PASS,
            'label'  => $label,
            'detail' => sprintf(
                /* translators: %d: number of tracked services */
                __('DPA registry covers %d services and all have an agreement status marked.', 'polski'),
                $trackedServices,
            ),
        ];
    }

    /**
     * Check whether CRA-oriented incident logging is enabled and configured.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkSecurityIncidents(): array
    {
        $label = __('Security incident registry', 'polski');

        if (! ModulesPage::isModuleEnabled('security_incidents')) {
            return [
                'status' => self::STATUS_WARNING,
                'label'  => $label,
                'detail' => __('Security incident module is disabled. It is good practice to maintain a simple incident registry for compliance.', 'polski'),
            ];
        }

        $settings = get_option('polski_security', []);
        $settings = is_array($settings) ? $settings : [];
        $contactEmail = sanitize_email((string) ($settings['incident_contact_email'] ?? ''));
        $incidents = get_option('polski_security_incidents', []);
        $incidents = is_array($incidents) ? $incidents : [];

        if ($contactEmail === '') {
            return [
                'status' => self::STATUS_WARNING,
                'label'  => $label,
                'detail' => __('Incident module is enabled, but no security contact email is set.', 'polski'),
            ];
        }

        if ($incidents === []) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('Incident module is enabled and security contact is set. The registry is currently empty.', 'polski'),
            ];
        }

        $openStatuses = ['open', 'investigating', 'monitoring'];
        $openCount = 0;

        foreach ($incidents as $incident) {
            if (is_array($incident) && in_array((string) ($incident['status'] ?? 'open'), $openStatuses, true)) {
                $openCount++;
            }
        }

        return [
            'status' => $openCount > 0 ? self::STATUS_WARNING : self::STATUS_PASS,
            'label'  => $label,
            'detail' => $openCount > 0
                ? sprintf(
                    /* translators: %d: number of open incidents */
                    __('There are %d open security incidents requiring further action in the registry.', 'polski'),
                    $openCount,
                )
                : __('The incident registry is active and has no open cases.', 'polski'),
        ];
    }

    /**
     * Check whether the site is served over SSL/TLS.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkSSL(): array
    {
        $label = __('SSL/TLS encryption', 'polski');

        if (is_ssl()) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('The site is served over HTTPS.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_FAIL,
            'label'  => $label,
            'detail' => __('The site does not use HTTPS. GDPR requires appropriate technical measures - SSL is the minimum.', 'polski'),
        ];
    }

    public function handleAuditAjax(): void
    {
        check_ajax_referer('polski_site_audit');

        if (! current_user_can('manage_woocommerce')) {
            wp_die('0');
        }

        wp_send_json_success($this->runAudit());
    }
}
