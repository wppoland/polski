<?php

declare(strict_types=1);

namespace Polski\PageCompliance\Rules;

use Polski\PageCompliance\Enum\Severity;
use Polski\PageCompliance\Model\CheckRule;

defined('ABSPATH') || exit;

/**
 * Heuristic WCAG 2.1 (Level AA) checks that can be evaluated against the
 * static HTML of the homepage. These rules cover common regressions only;
 * a full audit still requires a rendering browser (axe-core, Lighthouse).
 *
 * Inverse rules are listed in `inverseRuleIds()` and flip the match logic
 * (pattern present == failure).
 */
final class AccessibilityRules
{
    /**
     * @return list<CheckRule>
     */
    public static function all(): array
    {
        return [
            new CheckRule(
                id: 'html_lang',
                label: __('Document language declared (html lang)', 'polski'),
                severity: Severity::Required,
                patterns: [
                    '<html lang="pl',
                    '<html lang=\'pl',
                    '<html lang="en',
                    '<html lang=\'en',
                    '<html lang="de',
                    '<html lang=\'de',
                    'lang="pl-pl',
                    'lang="en-us',
                ],
                hint: __('Add a `lang` attribute to the <html> element (WCAG 3.1.1). WordPress usually emits this via the language_attributes() call.', 'polski'),
            ),
            new CheckRule(
                id: 'skip_link',
                label: __('Skip-to-content link present', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'skip-link',
                    'skip to content',
                    'przejdz do tresci',
                    'skip-to-content',
                    'screen-reader-text',
                ],
                hint: __('Provide a skip-to-content link as the first focusable element (WCAG 2.4.1).', 'polski'),
            ),
            new CheckRule(
                id: 'single_h1',
                label: __('Primary heading (h1) present', 'polski'),
                severity: Severity::Required,
                patterns: [
                    '<h1',
                ],
                hint: __('Homepage should expose exactly one <h1> describing the page (WCAG 1.3.1 / 2.4.6).', 'polski'),
            ),
            new CheckRule(
                id: 'viewport_meta',
                label: __('Responsive viewport declared', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'name="viewport"',
                    'name=\'viewport\'',
                ],
                hint: __('Include <meta name="viewport"> with width=device-width so the page scales on mobile (WCAG 1.4.10 Reflow).', 'polski'),
            ),
            new CheckRule(
                id: 'search_landmark',
                label: __('Search form uses role="search" or <search>', 'polski'),
                severity: Severity::Recommended,
                patterns: [
                    'role="search"',
                    'role=\'search\'',
                    '<search',
                ],
                hint: __('If your header exposes a search form, wrap it in <search> or add role="search" so assistive tech can jump to it.', 'polski'),
            ),
            new CheckRule(
                id: 'main_landmark',
                label: __('Main landmark defined', 'polski'),
                severity: Severity::Required,
                patterns: [
                    '<main',
                    'role="main"',
                    'role=\'main\'',
                    'id="main',
                    'id=\'main',
                ],
                hint: __('The primary content should be wrapped in <main> or role="main" (WCAG 1.3.1).', 'polski'),
            ),
            new CheckRule(
                id: 'focus_outline',
                label: __('Focus outline not suppressed globally', 'polski'),
                severity: Severity::Recommended,
                patterns: [
                    'outline:0',
                    'outline: 0',
                    'outline:none',
                    'outline: none',
                ],
                hint: __('Global `outline: none` on :focus breaks keyboard visibility (WCAG 2.4.7). Replace with a visible focus-visible ring.', 'polski'),
            ),
            new CheckRule(
                id: 'autoplay_video',
                label: __('No autoplay video with sound', 'polski'),
                severity: Severity::Recommended,
                patterns: [
                    'autoplay',
                    'data-autoplay="1"',
                ],
                hint: __('Avoid autoplay (WCAG 1.4.2 Audio Control) or ensure the video is muted and stops within 5 seconds.', 'polski'),
            ),
            new CheckRule(
                id: 'missing_alt_heuristic',
                label: __('Images missing alt attribute (heuristic)', 'polski'),
                severity: Severity::Required,
                patterns: [
                    '<img src',
                ],
                hint: __('The homepage contains <img> tags that did not have an `alt=` attribute in the sampled markup (WCAG 1.1.1).', 'polski'),
            ),
        ];
    }

    /**
     * Rules where the pattern presence indicates a FAILURE.
     *
     * @return list<string>
     */
    public static function inverseRuleIds(): array
    {
        return ['focus_outline', 'autoplay_video', 'missing_alt_heuristic'];
    }
}
