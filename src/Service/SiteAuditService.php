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
        echo '<h1>' . esc_html__('Audyt zgodności sklepu', 'polski') . '</h1>';
        echo '<p>' . esc_html__('Automatyczna weryfikacja najczęstszych problemów polskich sklepów WooCommerce.', 'polski') . '</p>';

        echo '<div style="display:flex;gap:20px;margin:20px 0;">';
        printf(
            '<div style="padding:15px 25px;background:#46b450;color:#fff;border-radius:4px;"><strong>%d</strong> %s</div>',
            $passed,
            esc_html__('OK', 'polski'),
        );
        printf(
            '<div style="padding:15px 25px;background:#f0ad4e;color:#fff;border-radius:4px;"><strong>%d</strong> %s</div>',
            $warnings,
            esc_html__('Ostrzeżenia', 'polski'),
        );
        printf(
            '<div style="padding:15px 25px;background:#dc3232;color:#fff;border-radius:4px;"><strong>%d</strong> %s</div>',
            $failures,
            esc_html__('Problemy', 'polski'),
        );
        echo '</div>';

        echo '<table class="widefat fixed striped"><thead><tr>';
        echo '<th style="width:40px;">' . esc_html__('Status', 'polski') . '</th>';
        echo '<th>' . esc_html__('Sprawdzenie', 'polski') . '</th>';
        echo '<th>' . esc_html__('Szczegóły', 'polski') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($results as $check) {
            $icon = match ($check['status']) {
                self::STATUS_PASS => '<span style="color:#46b450;">&#10003;</span>',
                self::STATUS_WARNING => '<span style="color:#f0ad4e;">&#9888;</span>',
                self::STATUS_FAIL => '<span style="color:#dc3232;">&#10007;</span>',
            };
            echo '<tr>';
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
        ];
    }

    /**
     * Check that WooCommerce terms page and privacy policy page are configured.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkLegalPages(): array
    {
        $label = __('Strony prawne (regulamin, polityka prywatności)', 'polski');

        $termsPageId = (int) get_option('woocommerce_terms_page_id', 0);
        $privacyPageId = (int) get_option('wp_page_for_privacy_policy', 0);

        $missingPages = [];

        if ($termsPageId <= 0 || get_post_status($termsPageId) !== 'publish') {
            $missingPages[] = __('regulamin', 'polski');
        }

        if ($privacyPageId <= 0 || get_post_status($privacyPageId) !== 'publish') {
            $missingPages[] = __('polityka prywatności', 'polski');
        }

        if (count($missingPages) === 0) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('Strony regulaminu i polityki prywatności są ustawione i opublikowane.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_FAIL,
            'label'  => $label,
            'detail' => sprintf(
                /* translators: %s: comma-separated list of missing pages */
                __('Brakujące strony: %s. Ustaw je w WooCommerce > Ustawienia > Zaawansowane.', 'polski'),
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
        $label = __('Treść polityki prywatności', 'polski');
        $privacyPageId = (int) get_option('wp_page_for_privacy_policy', 0);

        if ($privacyPageId <= 0) {
            return [
                'status' => self::STATUS_FAIL,
                'label'  => $label,
                'detail' => __('Strona polityki prywatności nie jest ustawiona.', 'polski'),
            ];
        }

        $privacyPage = get_post($privacyPageId);

        if (! $privacyPage || $privacyPage->post_status !== 'publish') {
            return [
                'status' => self::STATUS_FAIL,
                'label'  => $label,
                'detail' => __('Strona polityki prywatności nie jest opublikowana.', 'polski'),
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
                    __('Polityka prywatności ma tylko %d znaków - może być zbyt krótka, aby spełnić wymogi RODO Art. 13.', 'polski'),
                    $contentLength,
                ),
            ];
        }

        return [
            'status' => self::STATUS_PASS,
            'label'  => $label,
            'detail' => sprintf(
                /* translators: %d: character count */
                __('Polityka prywatności zawiera %d znaków.', 'polski'),
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
        $label = __('Dane identyfikacyjne firmy', 'polski');
        $generalSettings = get_option('polski_general', []);

        if (! is_array($generalSettings)) {
            $generalSettings = [];
        }

        $requiredFields = [
            'company_name'  => __('nazwa firmy', 'polski'),
            'company_nip'   => __('NIP', 'polski'),
            'company_address' => __('adres', 'polski'),
            'company_email' => __('email kontaktowy', 'polski'),
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
                'detail' => __('Wszystkie podstawowe dane firmy są uzupełnione.', 'polski'),
            ];
        }

        $severity = count($missingFields) >= 3 ? self::STATUS_FAIL : self::STATUS_WARNING;

        return [
            'status' => $severity,
            'label'  => $label,
            'detail' => sprintf(
                /* translators: %s: comma-separated list of missing fields */
                __('Brakujące dane: %s. Uzupełnij w Polski > Ustawienia.', 'polski'),
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
        $label = __('Pre-zaznaczone checkboxy (dark pattern)', 'polski');
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
                'detail' => __('Żadne checkboxy nie są domyślnie zaznaczone.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_FAIL,
            'label'  => $label,
            'detail' => sprintf(
                /* translators: %d: number of pre-checked checkboxes */
                __('Wykryto %d pre-zaznaczonych checkboxów. UOKiK uznaje to za ciemny wzorzec.', 'polski'),
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
        $label = __('Tekst przycisku zamówienia', 'polski');
        $checkoutSettings = get_option('polski_checkout', []);

        if (! is_array($checkoutSettings)) {
            $checkoutSettings = [];
        }

        $buttonText = $checkoutSettings['order_button_text'] ?? '';
        $buttonTextLower = mb_strtolower((string) $buttonText);

        $requiredPhrases = ['oplacam', 'platnosc', 'zaplac', 'obowiazkiem zaplaty', 'obowiazkiem zapłaty'];

        foreach ($requiredPhrases as $phrase) {
            if (mb_strpos($buttonTextLower, $phrase) !== false) {
                return [
                    'status' => self::STATUS_PASS,
                    'label'  => $label,
                    'detail' => sprintf(
                        /* translators: %s: the button text */
                        __('Przycisk: "%s" - zawiera informacje o obowiązku zapłaty.', 'polski'),
                        $buttonText,
                    ),
                ];
            }
        }

        if (empty($buttonText)) {
            return [
                'status' => self::STATUS_WARNING,
                'label'  => $label,
                'detail' => __('Tekst przycisku nie jest skonfigurowany. Ustawienie domyślne WooCommerce może nie zawierać wymaganej informacji o płatności.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_WARNING,
            'label'  => $label,
            'detail' => sprintf(
                /* translators: %s: the button text */
                __('Przycisk: "%s" - może nie zawierać wymaganej informacji o obowiązku zapłaty (np. "Zamawiam z obowiązkiem zapłaty").', 'polski'),
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
        $label = __('Moduł Omnibus (najniższa cena)', 'polski');

        if (ModulesPage::isModuleEnabled('omnibus')) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('Moduł Omnibus jest włączony - najniższa cena z 30 dni będzie wyświetlana przy promocjach.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_FAIL,
            'label'  => $label,
            'detail' => __('Moduł Omnibus jest wyłączony. Dyrektywa Omnibus wymaga wyświetlania najniższej ceny z 30 dni przy promocjach.', 'polski'),
        ];
    }

    /**
     * Check whether GDPR legal checkboxes are enabled.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkGDPRCheckboxes(): array
    {
        $label = __('Checkboxy prawne (RODO)', 'polski');

        if (ModulesPage::isModuleEnabled('legal_checkboxes')) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('Moduł checkboxów prawnych jest włączony.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_FAIL,
            'label'  => $label,
            'detail' => __('Moduł checkboxów prawnych jest wyłączony. Sklep może nie zbierać wymaganych zgód RODO.', 'polski'),
        ];
    }

    /**
     * Check whether the withdrawal (right of return) module is enabled.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkWithdrawalModule(): array
    {
        $label = __('Prawo odstąpienia od umowy', 'polski');

        if (ModulesPage::isModuleEnabled('withdrawal')) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('Moduł prawa odstąpienia jest włączony.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_WARNING,
            'label'  => $label,
            'detail' => __('Moduł prawa odstąpienia jest wyłączony. Konsumenci mają 14-dniowe prawo odstąpienia od umowy.', 'polski'),
        ];
    }

    /**
     * Check whether the GPSR module is enabled.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkGPSRModule(): array
    {
        $label = __('Moduł GPSR (bezpieczeństwo produktów)', 'polski');

        if (ModulesPage::isModuleEnabled('gpsr')) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('Moduł GPSR jest włączony - dane producenta i importera mogą być wyświetlane.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_WARNING,
            'label'  => $label,
            'detail' => __('Moduł GPSR jest wyłączony. Rozporządzenie (UE) 2023/988 wymaga podania danych producenta/importera.', 'polski'),
        ];
    }

    /**
     * Check whether verified review badges are enabled.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkVerifiedReviewModule(): array
    {
        $label = __('Zweryfikowane opinie', 'polski');

        if (ModulesPage::isModuleEnabled('verified_review')) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('Moduł zweryfikowanych opinii jest włączony - sklep może oznaczać recenzje od realnych kupujących.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_WARNING,
            'label'  => $label,
            'detail' => __('Moduł zweryfikowanych opinii jest wyłączony. To utrudnia odróżnienie opinii od realnych klientów.', 'polski'),
        ];
    }

    /**
     * Check whether the DSA report flow is enabled.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkDSAModule(): array
    {
        $label = __('DSA - zgłaszanie nielegalnych treści', 'polski');

        if (ModulesPage::isModuleEnabled('dsa_toolkit')) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('Moduł DSA jest włączony - formularz zgłoszeń i panel raportów są dostępne.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_WARNING,
            'label'  => $label,
            'detail' => __('Moduł DSA jest wyłączony. Marketplace lub sklep z treściami sponsorowanymi powinien mieć procedurę notice-and-action.', 'polski'),
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
                'detail' => __('Moduł KSeF-ready jest włączony — zamówienia z NIP mogą być oznaczane i przekazywane do integracji fakturowych.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_WARNING,
            'label'  => $label,
            'detail' => __('Moduł KSeF-ready jest wyłączony. Sklep B2B może potrzebować automatycznego oznaczania zamówień wymagających e-faktury.', 'polski'),
        ];
    }

    /**
     * Check whether anti-greenwashing product fields are enabled.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkAntiGreenwashingModule(): array
    {
        $label = __('Twierdzenia ekologiczne (anti-greenwashing)', 'polski');

        if (ModulesPage::isModuleEnabled('anti_greenwashing')) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('Moduł anti-greenwashing jest włączony — produkty mogą przechowywać podstawę twierdzeń ekologicznych i linki do certyfikatów.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_WARNING,
            'label'  => $label,
            'detail' => __('Moduł anti-greenwashing jest wyłączony. Przy deklaracjach ekologicznych warto przechowywać źródło i podstawę twierdzeń.', 'polski'),
        ];
    }

    /**
     * Check DPA registry status against tracked services.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkDPARegistry(): array
    {
        $label = __('Rejestr DPA (umowy powierzenia)', 'polski');

        if (! ModulesPage::isModuleEnabled('dpa_tracker')) {
            return [
                'status' => self::STATUS_WARNING,
                'label'  => $label,
                'detail' => __('Moduł rejestru DPA jest wyłączony. Przy hostingu, płatnościach i narzędziach zewnętrznych warto monitorować status umów powierzenia.', 'polski'),
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
                'detail' => __('Moduł DPA jest włączony, ale rejestr jest pusty. Oznacz przynajmniej hosting, płatności i inne usługi przetwarzające dane.', 'polski'),
            ];
        }

        if ($coveredServices === 0) {
            return [
                'status' => self::STATUS_FAIL,
                'label'  => $label,
                'detail' => __('Rejestr DPA nie ma oznaczonej żadnej aktywnej umowy powierzenia.', 'polski'),
            ];
        }

        if ($coveredServices < $trackedServices) {
            return [
                'status' => self::STATUS_WARNING,
                'label'  => $label,
                'detail' => sprintf(
                    /* translators: 1: number of services with DPA, 2: number of tracked services */
                    __('Tylko %1$d z %2$d wykrytych usług ma oznaczoną umowę powierzenia.', 'polski'),
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
                __('Rejestr DPA obejmuje %d usług i wszystkie mają oznaczony status umowy powierzenia.', 'polski'),
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
        $label = __('Rejestr incydentów bezpieczeństwa', 'polski');

        if (! ModulesPage::isModuleEnabled('security_incidents')) {
            return [
                'status' => self::STATUS_WARNING,
                'label'  => $label,
                'detail' => __('Moduł incydentów bezpieczeństwa jest wyłączony. Warto utrzymywać prosty rejestr incydentów pod CRA i wewnętrzne audyty.', 'polski'),
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
                'detail' => __('Moduł incydentów jest włączony, ale nie ma ustawionego adresu kontaktowego security.', 'polski'),
            ];
        }

        if ($incidents === []) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('Moduł incydentów jest włączony i ma ustawiony kontakt security. Rejestr jest obecnie pusty.', 'polski'),
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
                    __('W rejestrze są %d otwarte incydenty bezpieczeństwa wymagające dalszej obsługi.', 'polski'),
                    $openCount,
                )
                : __('Rejestr incydentów jest aktywny i nie ma otwartych spraw.', 'polski'),
        ];
    }

    /**
     * Check whether the site is served over SSL/TLS.
     *
     * @return array{status: string, label: string, detail: string}
     */
    private function checkSSL(): array
    {
        $label = __('Szyfrowanie SSL/TLS', 'polski');

        if (is_ssl()) {
            return [
                'status' => self::STATUS_PASS,
                'label'  => $label,
                'detail' => __('Strona jest serwowana przez HTTPS.', 'polski'),
            ];
        }

        return [
            'status' => self::STATUS_FAIL,
            'label'  => $label,
            'detail' => __('Strona nie używa HTTPS. RODO wymaga odpowiednich środków technicznych ochrony danych - SSL jest minimum.', 'polski'),
        ];
    }

    public function handleAuditAjax(): void
    {
        check_ajax_referer('polski_site_audit');

        if (! current_user_can('manage_woocommerce')) {
            wp_die(-1);
        }

        wp_send_json_success($this->runAudit());
    }
}
