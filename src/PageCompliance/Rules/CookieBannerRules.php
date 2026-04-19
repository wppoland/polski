<?php

declare(strict_types=1);

namespace Polski\PageCompliance\Rules;

use Polski\PageCompliance\Enum\Severity;
use Polski\PageCompliance\Model\CheckRule;

defined('ABSPATH') || exit;

/**
 * Rules that look for cookie-banner and active-consent signals in a page's
 * static HTML. The checker scans the homepage HTML for well-known banner
 * class/id fragments and for button labels that indicate the user can
 * accept, reject and configure cookies.
 *
 * This is a heuristic: JS-rendered banners may not be visible in the initial
 * HTML. In that case rule results act as a prompt rather than a verdict.
 */
final class CookieBannerRules
{
    /**
     * @return list<CheckRule>
     */
    public static function all(): array
    {
        return [
            new CheckRule(
                id: 'banner_present',
                label: __('Cookie banner present in HTML', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'cookie',
                    'ciasteczka',
                    'cookies',
                ],
                hint: __('No cookie-related markup was detected on the homepage. Install a banner or ensure it renders on initial load (not only after client-side JS).', 'polski'),
            ),
            new CheckRule(
                id: 'accept_button',
                label: __('Accept button label', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'akceptuje',
                    'akceptuje wszystkie',
                    'accept',
                    'accept all',
                    'zgadzam sie',
                    'zezwalaj',
                    'agree',
                    'allow all',
                ],
                hint: __('Expose a clearly labelled Accept / Akceptuje button in the banner.', 'polski'),
            ),
            new CheckRule(
                id: 'reject_button',
                label: __('Reject button with equal prominence', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'odrzuc',
                    'odrzucam',
                    'odrzuc wszystkie',
                    'nie zgadzam sie',
                    'reject',
                    'reject all',
                    'decline',
                    'tylko niezbedne',
                    'only necessary',
                ],
                hint: __('Active consent requires an equally visible Reject option next to Accept. A greyed-out or hidden button is not compliant.', 'polski'),
            ),
            new CheckRule(
                id: 'settings_link',
                label: __('Granular settings / preferences', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'ustawienia cookies',
                    'ustawienia ciasteczek',
                    'preferencje',
                    'dostosuj',
                    'cookie settings',
                    'manage preferences',
                    'customise',
                    'customize',
                ],
                hint: __('Provide a Settings / Preferences link so users can consent to individual categories (analytics, marketing) independently.', 'polski'),
            ),
            new CheckRule(
                id: 'category_analytics',
                label: __('Analytics category mentioned', 'polski'),
                severity: Severity::Recommended,
                patterns: [
                    'analityczne',
                    'analityka',
                    'statystyki',
                    'analytics',
                    'statistics',
                ],
                hint: __('Explicitly name the Analytics/Statistics category so the user knows what the toggle covers.', 'polski'),
            ),
            new CheckRule(
                id: 'category_marketing',
                label: __('Marketing / advertising category mentioned', 'polski'),
                severity: Severity::Recommended,
                patterns: [
                    'marketingowe',
                    'marketing',
                    'reklamowe',
                    'reklama',
                    'advertising',
                    'targeting',
                ],
                hint: __('Name the Marketing/Advertising category separately so opt-in is granular.', 'polski'),
            ),
            new CheckRule(
                id: 'privacy_link',
                label: __('Link to privacy policy in banner area', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'polityka prywatnosci',
                    'polityce prywatnosci',
                    'polityki prywatnosci',
                    'privacy policy',
                ],
                hint: __('Link the banner to the full privacy policy so users can read the details before consenting.', 'polski'),
            ),
            new CheckRule(
                id: 'withdrawal_hint',
                label: __('Consent withdrawal mechanism hinted', 'polski'),
                severity: Severity::Recommended,
                patterns: [
                    'mozesz zmienic',
                    'zmien ustawienia',
                    'change your preferences',
                    'withdraw consent',
                    'cofniecia zgody',
                    'w dowolnym momencie',
                    'any time',
                ],
                hint: __('Mention that consent can be withdrawn or changed at any time from the banner or a dedicated link.', 'polski'),
            ),
            new CheckRule(
                id: 'no_forced_scroll_accept',
                label: __('Banner does not rely on scrolling to imply consent', 'polski'),
                severity: Severity::Recommended,
                patterns: [
                    'klikajac',
                    'kontynuowanie',
                    'by continuing',
                    'by scrolling',
                    'korzystanie z',
                ],
                hint: __('Phrases like "By continuing to use the site you agree" are considered implied consent and fail active-consent rules. Remove them and require an explicit click.', 'polski'),
            ),
            new CheckRule(
                id: 'no_autoprompt_push',
                label: __('No auto-prompt for push notifications on first load', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'notification.requestpermission',
                    'pushmanager.subscribe',
                    'serviceworker.register',
                    'onesignalinit',
                    'onesignal.init',
                    'webpushr',
                    'pushengage',
                    'pushnami',
                ],
                hint: __('Triggering Notification.requestPermission() without prior user interaction is a deceptive pattern (EDPB 03/2022) and is also blocked by modern browsers. Gate push-subscribe prompts behind an explicit user click.', 'polski'),
            ),
        ];
    }

    /**
     * Rules whose *presence* in the scanned HTML indicates a compliance problem.
     * These flip the logic: if the pattern is found, the rule FAILS.
     *
     * @return list<string>
     */
    public static function inverseRuleIds(): array
    {
        return ['no_forced_scroll_accept', 'no_autoprompt_push'];
    }
}
