<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * Surfaces withdrawal-flow configuration health to the WordPress
 * Tools › Site Health screen. Catches the three configuration
 * mistakes that come up most often after upgrading to 1.16.0:
 *
 *  - guest lookup page not configured (Art. 11a requires accessible
 *    in-shop functionality for guests too)
 *  - trigger statuses empty (the 14-day clock would never start)
 *  - Annex locale auto-detect but site_locale not in the supported set
 *
 * Each test returns either a "good" or "recommended" status - never
 * "critical", because the plugin still works without these (just less
 * compliant / harder to discover). The dashboard tile uses the
 * critical/recommended split for the headline number.
 */
final class WithdrawalSiteHealthService implements HasHooks
{
    private const SUPPORTED_ANNEX_LOCALES = ['pl', 'de', 'at', 'fr', 'nl', 'it', 'es', 'eu'];

    public function registerHooks(): void
    {
        add_filter('site_status_tests', [$this, 'registerTests']);
    }

    /**
     * @param array{direct: array<string, mixed>, async: array<string, mixed>} $tests
     * @return array{direct: array<string, mixed>, async: array<string, mixed>}
     */
    public function registerTests(array $tests): array
    {
        $tests['direct']['polski_withdrawal_lookup_page'] = [
            'label' => __('Withdrawal: guest lookup page', 'polski'),
            'test' => [$this, 'testLookupPage'],
        ];
        $tests['direct']['polski_withdrawal_trigger_statuses'] = [
            'label' => __('Withdrawal: trigger statuses', 'polski'),
            'test' => [$this, 'testTriggerStatuses'],
        ];
        $tests['direct']['polski_withdrawal_annex_locale'] = [
            'label' => __('Withdrawal: Annex locale', 'polski'),
            'test' => [$this, 'testAnnexLocale'],
        ];

        return $tests;
    }

    /**
     * @return array<string, mixed>
     */
    public function testLookupPage(): array
    {
        $settings = get_option('polski_withdrawal', []);
        $settings = is_array($settings) ? $settings : [];
        $pageId = (int) ($settings['lookup_page_id'] ?? 0);

        if ($pageId > 0 && get_post_status($pageId) === 'publish') {
            return $this->resultGood(
                'polski_withdrawal_lookup_page',
                __('Guest withdrawal lookup page configured.', 'polski'),
                __('Guests can file an Art. 11a withdrawal declaration without logging in. Required from 19 June 2026 by Directive 2023/2673.', 'polski'),
            );
        }

        $createUrl = admin_url('admin.php?page=polski-withdrawal-settings');

        return $this->resultRecommended(
            'polski_withdrawal_lookup_page',
            __('No public withdrawal lookup page configured.', 'polski'),
            sprintf(
                /* translators: %s = settings page URL */
                __('Art. 11a of Directive 2023/2673 (in force from 19 June 2026) requires that every distance-selling shop provides a working withdrawal feature inside the shop, including for guests. Open <a href="%s">Polski › Withdrawal settings</a> and pick a page containing the [polski_withdrawal_lookup] shortcode (or run the setup wizard\'s "Publish /odstapienie/ on Finish" step).', 'polski'),
                esc_url($createUrl),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function testTriggerStatuses(): array
    {
        $settings = get_option('polski_withdrawal', []);
        $settings = is_array($settings) ? $settings : [];
        $statuses = (array) ($settings['trigger_statuses'] ?? []);

        if ($statuses !== []) {
            return $this->resultGood(
                'polski_withdrawal_trigger_statuses',
                __('Trigger statuses configured.', 'polski'),
                sprintf(
                    /* translators: %s = comma-separated status slugs */
                    __('The 14-day withdrawal clock starts when an order enters: %s.', 'polski'),
                    esc_html(implode(', ', array_map('strval', $statuses))),
                ),
            );
        }

        $settingsUrl = admin_url('admin.php?page=polski-withdrawal-settings');

        return $this->resultRecommended(
            'polski_withdrawal_trigger_statuses',
            __('No trigger statuses configured for the withdrawal clock.', 'polski'),
            sprintf(
                /* translators: %s = settings page URL */
                __('Without a trigger status, the 14-day withdrawal countdown never starts and no order is eligible. Open <a href="%s">Polski › Withdrawal settings</a> and tick at least one status (commonly "Completed" or "Delivered").', 'polski'),
                esc_url($settingsUrl),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function testAnnexLocale(): array
    {
        $settings = get_option('polski_withdrawal', []);
        $settings = is_array($settings) ? $settings : [];
        $configured = (string) ($settings['annex_locale'] ?? '');

        if ($configured !== '') {
            return $this->resultGood(
                'polski_withdrawal_annex_locale',
                __('Annex I(B) locale explicitly set.', 'polski'),
                sprintf(
                    /* translators: %s = locale slug */
                    __('Annex I(B) is generated in: %s.', 'polski'),
                    esc_html($configured),
                ),
            );
        }

        $siteLocale = strtolower((string) get_locale());
        $matched = false;
        foreach (self::SUPPORTED_ANNEX_LOCALES as $loc) {
            if (str_starts_with($siteLocale, $loc) || $siteLocale === $loc . '_' . strtoupper($loc)) {
                $matched = true;
                break;
            }
        }

        if ($matched) {
            return $this->resultGood(
                'polski_withdrawal_annex_locale',
                __('Annex I(B) auto-locale matches site language.', 'polski'),
                __('Annex I(B) generator auto-detects the site locale. No action needed.', 'polski'),
            );
        }

        return $this->resultRecommended(
            'polski_withdrawal_annex_locale',
            __('Annex I(B) auto-locale may fall back to EN.', 'polski'),
            sprintf(
                /* translators: %s = site locale */
                __('Site locale is "%s" but no matching Annex I(B) translation ships with the Pro plugin. Either pick an explicit locale in Polski › Withdrawal settings (PL, DE, AT, FR, NL, IT, ES) or accept the generic EN fallback.', 'polski'),
                esc_html($siteLocale),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resultGood(string $testId, string $label, string $description): array
    {
        return [
            'label' => $label,
            'status' => 'good',
            'badge' => ['label' => __('Polski', 'polski'), 'color' => 'green'],
            'description' => sprintf('<p>%s</p>', esc_html($description)),
            'test' => $testId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resultRecommended(string $testId, string $label, string $descriptionHtml): array
    {
        return [
            'label' => $label,
            'status' => 'recommended',
            'badge' => ['label' => __('Polski', 'polski'), 'color' => 'orange'],
            'description' => sprintf('<p>%s</p>', wp_kses_post($descriptionHtml)),
            'test' => $testId,
        ];
    }
}
