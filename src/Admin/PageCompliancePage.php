<?php

declare(strict_types=1);

namespace Polski\Admin;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Enum\LegalPageType;
use Polski\PageCompliance\Enum\Severity;
use Polski\PageCompliance\Model\CheckReport;
use Polski\PageCompliance\Model\CheckResult;
use Polski\PageCompliance\PageComplianceService;

/**
 * Admin subpage rendering the Privacy Policy and Regulamin compliance checklists.
 *
 * Registers as a submenu under the Polski top-level menu. The page is purely
 * informational: it lists mandatory and recommended elements, shows a
 * pass/fail for each, and links to the corresponding page editor.
 */
final class PageCompliancePage implements HasHooks
{
    private const SLUG = 'polski-page-compliance';

    public function __construct(
        private readonly PageComplianceService $service,
    ) {
    }

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('page_compliance')) {
            return;
        }

        add_action('admin_menu', [$this, 'registerPage'], 60);
    }

    public function registerPage(): void
    {
        add_submenu_page(
            'polski',
            __('Compliance checklist', 'polski'),
            __('Compliance checklist', 'polski'),
            'manage_woocommerce',
            self::SLUG,
            [$this, 'render'],
        );
    }

    public function render(): void
    {
        echo '<div class="wrap polski-page-compliance">';
        echo '<h1>' . esc_html__('Compliance checklist', 'polski') . '</h1>';
        echo '<p>' . esc_html__('Automated structural check of the Privacy Policy and Terms pages. The checker looks for keywords matching each legally expected element. A match does not guarantee legal compliance, but a missing element is a strong signal that the page is incomplete.', 'polski') . '</p>';

        echo '<p class="description"><strong>' . esc_html__('Disclaimer:', 'polski') . '</strong> '
            . esc_html__('This is a structural heuristic, not legal advice. Consult a lawyer for a binding assessment.', 'polski') . '</p>';

        foreach ([LegalPageType::Privacy, LegalPageType::Terms] as $type) {
            $report = $this->service->check($type);
            $this->renderReport($type, $report);
        }

        $this->renderCookieBannerSection();
        $this->renderAccessibilitySection();

        echo '</div>';
    }

    private function renderAccessibilitySection(): void
    {
        $report = $this->service->checkAccessibility();
        $homeUrl = (string) home_url('/');

        echo '<section style="margin-top:24px;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:4px">';

        printf(
            '<h2 style="margin-top:0">%s <span style="float:right;font-size:14px;color:%s">%s%%</span></h2>',
            esc_html__('Accessibility (WCAG) heuristics', 'polski'),
            esc_attr($this->scoreColor($report->score())),
            (int) $report->score(),
        );

        printf(
            '<p>%s <code>%s</code></p>',
            esc_html__('Scanning:', 'polski'),
            esc_html($homeUrl),
        );

        if ($report->contentLength === 0) {
            printf(
                '<p><em>%s</em></p>',
                esc_html__('Could not fetch the homepage HTML. Check your site is reachable from wp-admin.', 'polski'),
            );
            echo '</section>';
            return;
        }

        echo '<p class="description">' . esc_html__('Heuristic checks against the static homepage HTML. Use axe-core or Lighthouse for a rendered audit; this module only flags common regressions.', 'polski') . '</p>';

        echo '<table class="wp-list-table widefat striped" style="margin-top:8px"><thead><tr>';
        printf('<th style="width:42%%">%s</th>', esc_html__('Element', 'polski'));
        printf('<th style="width:14%%">%s</th>', esc_html__('Severity', 'polski'));
        printf('<th style="width:10%%">%s</th>', esc_html__('Status', 'polski'));
        printf('<th>%s</th>', esc_html__('Hint if missing', 'polski'));
        echo '</tr></thead><tbody>';

        foreach ($report->results as $result) {
            $this->renderRow($result);
        }

        echo '</tbody></table></section>';
    }

    private function renderCookieBannerSection(): void
    {
        $report = $this->service->checkCookieBanner();
        $homeUrl = (string) home_url('/');

        echo '<section style="margin-top:24px;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:4px">';

        printf(
            '<h2 style="margin-top:0">%s <span style="float:right;font-size:14px;color:%s">%s%%</span></h2>',
            esc_html__('Cookie banner (active consent)', 'polski'),
            esc_attr($this->scoreColor($report->score())),
            (int) $report->score(),
        );

        printf(
            '<p>%s <code>%s</code></p>',
            esc_html__('Scanning:', 'polski'),
            esc_html($homeUrl),
        );

        if ($report->contentLength === 0) {
            printf(
                '<p><em>%s</em></p>',
                esc_html__('Could not fetch the homepage HTML. Check your site is reachable from wp-admin.', 'polski'),
            );
            echo '</section>';
            return;
        }

        echo '<p class="description">' . esc_html__('Heuristic scan of the homepage HTML. JS-rendered banners may not be visible here — use this as a starting point, not a verdict.', 'polski') . '</p>';

        echo '<table class="wp-list-table widefat striped" style="margin-top:8px"><thead><tr>';
        printf('<th style="width:42%%">%s</th>', esc_html__('Element', 'polski'));
        printf('<th style="width:14%%">%s</th>', esc_html__('Severity', 'polski'));
        printf('<th style="width:10%%">%s</th>', esc_html__('Status', 'polski'));
        printf('<th>%s</th>', esc_html__('Hint if missing', 'polski'));
        echo '</tr></thead><tbody>';

        foreach ($report->results as $result) {
            $this->renderRow($result);
        }

        echo '</tbody></table></section>';
    }

    private function renderReport(LegalPageType $type, CheckReport $report): void
    {
        $editUrl = $report->pageId !== null
            ? (string) get_edit_post_link($report->pageId)
            : (string) admin_url('options-general.php?page=polski&tab=legal');

        echo '<section style="margin-top:24px;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:4px">';

        printf(
            '<h2 style="margin-top:0">%s <span style="float:right;font-size:14px;color:%s">%s%%</span></h2>',
            esc_html($type->label()),
            esc_attr($this->scoreColor($report->score())),
            (int) $report->score(),
        );

        if ($report->pageId === null) {
            printf(
                '<p><em>%s</em></p>',
                esc_html__('No page is configured for this section. Set it under Polski > Legal pages.', 'polski'),
            );
            echo '</section>';
            return;
        }

        printf(
            '<p><a href="%s" class="button button-secondary">%s</a> &nbsp; <span>%s</span></p>',
            esc_url($editUrl),
            esc_html__('Edit page', 'polski'),
            esc_html(sprintf(
                /* translators: %d: content length in characters */
                __('Content length: %d chars', 'polski'),
                $report->contentLength,
            )),
        );

        echo '<table class="wp-list-table widefat striped" style="margin-top:8px"><thead><tr>';
        printf('<th style="width:42%%">%s</th>', esc_html__('Element', 'polski'));
        printf('<th style="width:14%%">%s</th>', esc_html__('Severity', 'polski'));
        printf('<th style="width:10%%">%s</th>', esc_html__('Status', 'polski'));
        printf('<th>%s</th>', esc_html__('Hint if missing', 'polski'));
        echo '</tr></thead><tbody>';

        foreach ($report->results as $result) {
            $this->renderRow($result);
        }

        echo '</tbody></table></section>';
    }

    private function renderRow(CheckResult $result): void
    {
        $statusLabel = $result->passed
            ? '<span style="color:#1a7f37">&#10003; ' . esc_html__('OK', 'polski') . '</span>'
            : '<span style="color:#d63638">&#10007; ' . esc_html__('Missing', 'polski') . '</span>';

        echo '<tr>';
        printf('<td><strong>%s</strong></td>', esc_html($result->label));
        printf(
            '<td><span style="color:%s">%s</span></td>',
            esc_attr($result->severity->color()),
            esc_html($result->severity->label()),
        );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from literal markup + esc_html__.
        printf('<td>%s</td>', $statusLabel);
        printf('<td>%s</td>', $result->passed ? '&mdash;' : esc_html($result->hint));
        echo '</tr>';
    }

    private function scoreColor(int $score): string
    {
        return match (true) {
            $score >= 90 => '#1a7f37',
            $score >= 70 => '#dba617',
            default => '#d63638',
        };
    }
}
