<?php

declare(strict_types=1);

namespace Polski\PageCompliance;

use Polski\Contract\HasHooks;
use Polski\Enum\LegalPageType;
use Polski\PageCompliance\Model\CheckReport;
use Polski\PageCompliance\Model\CheckResult;
use Polski\PageCompliance\Model\CheckRule;
use Polski\PageCompliance\Rules\AccessibilityRules;
use Polski\PageCompliance\Rules\CookieBannerRules;
use Polski\PageCompliance\Rules\PrivacyPolicyRules;
use Polski\PageCompliance\Rules\RegulaminRules;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Runs structural compliance checks against the shop's legal pages.
 *
 * Input: a LegalPageType (privacy or terms). The service looks up the
 * configured WP page (via `polski_{type}_page_id` option), strips HTML and
 * diacritics, and evaluates the applicable rule set.
 */
final class PageComplianceService implements HasHooks
{
    private const COOKIE_SCAN_TRANSIENT = 'polski_cookie_scan';
    private const COOKIE_SCAN_TTL = HOUR_IN_SECONDS;

    public function registerHooks(): void
    {
        // No runtime hooks; service is invoked by REST and admin page.
    }

    /**
     * Fetch the given URL and evaluate cookie-banner compliance rules
     * against the (normalized) HTML. Result is cached in a transient for
     * one hour to avoid repeated HTTP requests.
     *
     * @param string|null $url Target URL; defaults to site home_url().
     */
    public function checkCookieBanner(?string $url = null): CheckReport
    {
        $target = $url !== null && $url !== '' ? esc_url_raw($url) : (string) home_url('/');
        $html = $this->fetchUrl($target);
        $normalized = $this->normalize($html);

        $rules = CookieBannerRules::all();
        $inverse = CookieBannerRules::inverseRuleIds();
        $results = [];

        foreach ($rules as $rule) {
            $baseResult = $this->evaluate($rule, $normalized);

            if (in_array($rule->id, $inverse, true)) {
                $results[] = new \Polski\PageCompliance\Model\CheckResult(
                    ruleId: $baseResult->ruleId,
                    label: $baseResult->label,
                    severity: $baseResult->severity,
                    passed: ! $baseResult->passed,
                    hint: $baseResult->hint,
                );
                continue;
            }

            $results[] = $baseResult;
        }

        return new CheckReport(
            pageType: 'cookie_banner',
            pageId: null,
            contentLength: mb_strlen($html),
            results: $results,
        );
    }

    public function clearCookieScanCache(): void
    {
        delete_transient(self::COOKIE_SCAN_TRANSIENT);
    }

    /**
     * Run heuristic WCAG checks against the static homepage HTML.
     *
     * The HTML is NOT normalised for this check because attribute-level
     * matches (lang="pl", role="search", autoplay) are case-insensitive
     * but need to stay inside tags, not inside body text.
     */
    public function checkAccessibility(?string $url = null): CheckReport
    {
        $target = $url !== null && $url !== '' ? esc_url_raw($url) : (string) home_url('/');
        $html = $this->fetchUrl($target);

        if ($html === '') {
            return new CheckReport(
                pageType: 'accessibility',
                pageId: null,
                contentLength: 0,
                results: [],
            );
        }

        $lower = strtolower($html);

        $rules = AccessibilityRules::all();
        $inverse = AccessibilityRules::inverseRuleIds();
        $results = [];

        foreach ($rules as $rule) {
            // Special-case: missing_alt_heuristic fails when there's any
            // <img ... src ...> without an alt attribute.
            if ($rule->id === 'missing_alt_heuristic') {
                $results[] = new \Polski\PageCompliance\Model\CheckResult(
                    ruleId: $rule->id,
                    label: $rule->label,
                    severity: $rule->severity,
                    passed: ! $this->hasImagesWithoutAlt($html),
                    hint: $rule->hint,
                );
                continue;
            }

            $baseResult = $this->evaluate($rule, $lower);

            if (in_array($rule->id, $inverse, true)) {
                $results[] = new \Polski\PageCompliance\Model\CheckResult(
                    ruleId: $baseResult->ruleId,
                    label: $baseResult->label,
                    severity: $baseResult->severity,
                    passed: ! $baseResult->passed,
                    hint: $baseResult->hint,
                );
                continue;
            }

            $results[] = $baseResult;
        }

        return new CheckReport(
            pageType: 'accessibility',
            pageId: null,
            contentLength: mb_strlen($html),
            results: $results,
        );
    }

    private function hasImagesWithoutAlt(string $html): bool
    {
        if (! preg_match_all('#<img\b([^>]*)>#i', $html, $matches)) {
            return false;
        }

        foreach ($matches[1] as $attrs) {
            if (! preg_match('#\balt\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', (string) $attrs)) {
                return true;
            }
        }

        return false;
    }

    private function fetchUrl(string $url): string
    {
        $cached = get_transient(self::COOKIE_SCAN_TRANSIENT . '_' . md5($url));

        if (is_string($cached)) {
            return $cached;
        }

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'redirection' => 3,
            'user-agent' => 'polski-page-compliance/1.0',
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            return '';
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code < 200 || $code >= 400) {
            return '';
        }

        $body = (string) wp_remote_retrieve_body($response);

        set_transient(self::COOKIE_SCAN_TRANSIENT . '_' . md5($url), $body, self::COOKIE_SCAN_TTL);

        return $body;
    }

    public function check(LegalPageType $type): CheckReport
    {
        $pageId = $this->resolvePageId($type);
        $content = $pageId > 0 ? $this->loadContent($pageId) : '';
        $normalized = $this->normalize($content);

        $rules = $this->rulesFor($type);
        $results = array_map(
            fn (CheckRule $rule): CheckResult => $this->evaluate($rule, $normalized),
            $rules,
        );

        return new CheckReport(
            pageType: $type->value,
            pageId: $pageId > 0 ? $pageId : null,
            contentLength: mb_strlen($content),
            results: array_values($results),
        );
    }

    /**
     * @return list<CheckRule>
     */
    public function rulesFor(LegalPageType $type): array
    {
        return match ($type) {
            LegalPageType::Privacy => PrivacyPolicyRules::all(),
            LegalPageType::Terms => RegulaminRules::all(),
            default => [],
        };
    }

    public function evaluate(CheckRule $rule, string $normalizedContent): CheckResult
    {
        if ($rule->minLength > 0 && mb_strlen($normalizedContent) < $rule->minLength) {
            return new CheckResult(
                ruleId: $rule->id,
                label: $rule->label,
                severity: $rule->severity,
                passed: false,
                hint: $rule->hint,
            );
        }

        $passed = false;

        foreach ($rule->patterns as $pattern) {
            if ($pattern !== '' && str_contains($normalizedContent, $pattern)) {
                $passed = true;
                break;
            }
        }

        return new CheckResult(
            ruleId: $rule->id,
            label: $rule->label,
            severity: $rule->severity,
            passed: $passed,
            hint: $rule->hint,
        );
    }

    /**
     * Lowercase + strip diacritics + strip HTML, so pattern matching can
     * operate on ASCII substrings without worrying about ą/ć/ł/ś etc.
     */
    public function normalize(string $content): string
    {
        $stripped = wp_strip_all_tags($content);
        $lower = mb_strtolower($stripped);

        return strtr(
            $lower,
            [
                'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
                'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
            ],
        );
    }

    private function resolvePageId(LegalPageType $type): int
    {
        $optionKey = $type->optionKey();

        return (int) get_option($optionKey, 0);
    }

    private function loadContent(int $pageId): string
    {
        $post = get_post($pageId);

        if (! $post instanceof WP_Post) {
            return '';
        }

        return (string) $post->post_content;
    }
}
