<?php

declare(strict_types=1);

namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * Generate printable RODO (GDPR) training documentation for shop employees.
 *
 * Produces three bundled HTML documents:
 *   - Training logbook (table: employee, role, topics, date, signature)
 *   - Key RODO principles summary
 *   - Personal data breach response playbook (24h internal + 72h UODO timing)
 *
 * All documents are rendered with a print-friendly stylesheet. Shop data
 * is pulled from `polski_general` so the templates arrive pre-branded.
 */
final class RodoTrainingDocsService implements HasHooks
{
    private const SLUG = 'polski-rodo-training';
    private const NONCE = 'polski_rodo_training_download';

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('rodo_training_docs')) {
            return;
        }

        add_action('admin_menu', [$this, 'registerPage'], 95);
        add_action('admin_post_polski_rodo_training_download', [$this, 'handleDownload']);
    }

    public function registerPage(): void
    {
        add_submenu_page(
            'polski',
            __('RODO training docs', 'polski'),
            __('RODO training docs', 'polski'),
            'manage_woocommerce',
            self::SLUG,
            [$this, 'render'],
        );
    }

    public function render(): void
    {
        echo '<div class="wrap"><h1>' . esc_html__('RODO / GDPR training documentation', 'polski') . '</h1>';
        echo '<p>' . esc_html__('Ready-to-print templates for onboarding new shop employees. Pre-branded with your shop\'s business data.', 'polski') . '</p>';
        echo '<p><em>' . esc_html__('Disclaimer: generic starter templates - adapt to your actual data-processing workflows.', 'polski') . '</em></p>';

        foreach ($this->documents() as $key => $doc) {
            printf('<h2>%s</h2>', esc_html($doc['title']));
            echo '<p>' . esc_html($doc['description']) . '</p>';

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field(self::NONCE);
            echo '<input type="hidden" name="action" value="polski_rodo_training_download">';
            printf('<input type="hidden" name="doc" value="%s">', esc_attr($key));
            submit_button(__('Download HTML', 'polski'), 'secondary', 'submit', false);
            echo '</form>';
        }

        echo '</div>';
    }

    public function handleDownload(): void
    {
        check_admin_referer(self::NONCE);

        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'polski'));
        }

        $key = sanitize_key((string) ($_POST['doc'] ?? ''));
        $docs = $this->documents();

        if (! isset($docs[$key])) {
            wp_die(esc_html__('Unknown document.', 'polski'));
        }

        $html = $this->wrapStandalone($docs[$key]['title'], $docs[$key]['body']());
        $filename = sprintf('polski-rodo-%s-%s.html', $key, gmdate('Ymd'));

        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Standalone HTML; content prepared with esc_* helpers.
        echo $html;
        exit;
    }

    /**
     * @return array<string, array{title: string, description: string, body: callable}>
     */
    private function documents(): array
    {
        return [
            'logbook' => [
                'title' => __('Training logbook', 'polski'),
                'description' => __('Table to record employee training sessions: who, role, topics, date and signature.', 'polski'),
                'body' => fn (): string => $this->logbookBody(),
            ],
            'principles' => [
                'title' => __('RODO principles summary', 'polski'),
                'description' => __('One-page summary of the 7 RODO principles and the 8 data subject rights.', 'polski'),
                'body' => fn (): string => $this->principlesBody(),
            ],
            'breach_playbook' => [
                'title' => __('Data breach response playbook', 'polski'),
                'description' => __('Step-by-step checklist with the 72-hour UODO notification deadline and internal escalation.', 'polski'),
                'body' => fn (): string => $this->breachBody(),
            ],
        ];
    }

    private function wrapStandalone(string $title, string $body): string
    {
        return '<!doctype html>'
            . '<html lang="pl"><head><meta charset="utf-8"><title>' . esc_html($title) . '</title>'
            . '<style>'
            . 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:820px;margin:40px auto;padding:0 20px;color:#111;line-height:1.55}'
            . 'h1{font-size:24px;margin-bottom:8px}h2{font-size:18px;margin-top:24px}h3{font-size:14px;margin-top:16px}'
            . 'table{width:100%;border-collapse:collapse;margin-top:12px}th,td{border:1px solid #999;padding:8px;text-align:left;vertical-align:top}'
            . 'ol,ul{padding-left:24px}.header{color:#666;font-size:12px;margin-bottom:24px}'
            . '@media print{body{margin:0}}'
            . '</style></head><body>'
            . $this->documentHeader($title)
            . $body
            . '</body></html>';
    }

    private function documentHeader(string $title): string
    {
        $general = get_option('polski_general', []);
        $name = is_array($general) ? (string) ($general['company_name'] ?? '') : '';
        $nip = is_array($general) ? (string) ($general['company_nip'] ?? '') : '';

        $seller = '';
        if ($name !== '') {
            $seller .= esc_html($name);
        }
        if ($nip !== '') {
            $seller .= ($seller !== '' ? ' - ' : '') . esc_html__('NIP:', 'polski') . ' ' . esc_html($nip);
        }

        return sprintf(
            '<div class="header">%s</div><h1>%s</h1>',
            $seller,
            esc_html($title),
        );
    }

    private function logbookBody(): string
    {
        $html = '<p>' . esc_html__('Use one row per training session. The trainee signs to confirm attendance and understanding.', 'polski') . '</p>';
        $html .= '<table>';
        $html .= '<thead><tr>';
        foreach ([
            __('Date', 'polski'),
            __('Employee', 'polski'),
            __('Role', 'polski'),
            __('Topics covered', 'polski'),
            __('Trainer', 'polski'),
            __('Signature', 'polski'),
        ] as $col) {
            $html .= '<th>' . esc_html($col) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        for ($i = 0; $i < 10; $i++) {
            $html .= '<tr>' . str_repeat('<td style="height:28px"></td>', 6) . '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private function principlesBody(): string
    {
        $principles = [
            __('Lawfulness, fairness and transparency', 'polski'),
            __('Purpose limitation', 'polski'),
            __('Data minimisation', 'polski'),
            __('Accuracy', 'polski'),
            __('Storage limitation', 'polski'),
            __('Integrity and confidentiality (security)', 'polski'),
            __('Accountability', 'polski'),
        ];

        $rights = [
            __('Right of access (Art. 15)', 'polski'),
            __('Right to rectification (Art. 16)', 'polski'),
            __('Right to erasure (Art. 17)', 'polski'),
            __('Right to restriction of processing (Art. 18)', 'polski'),
            __('Right to notification about rectification or erasure (Art. 19)', 'polski'),
            __('Right to data portability (Art. 20)', 'polski'),
            __('Right to object (Art. 21)', 'polski'),
            __('Right not to be subject to automated decision-making (Art. 22)', 'polski'),
        ];

        $html = '<h2>' . esc_html__('Seven principles of processing (Art. 5)', 'polski') . '</h2>';
        $html .= '<ol>';
        foreach ($principles as $p) {
            $html .= '<li>' . esc_html($p) . '</li>';
        }
        $html .= '</ol>';

        $html .= '<h2>' . esc_html__('Eight data subject rights (Chapter III)', 'polski') . '</h2>';
        $html .= '<ol>';
        foreach ($rights as $r) {
            $html .= '<li>' . esc_html($r) . '</li>';
        }
        $html .= '</ol>';

        $html .= '<h2>' . esc_html__('Operational reminders', 'polski') . '</h2>';
        $html .= '<ul>';
        $html .= '<li>' . esc_html__('Never email spreadsheets of personal data - use encrypted channels.', 'polski') . '</li>';
        $html .= '<li>' . esc_html__('Verify the requester before acting on access or erasure requests.', 'polski') . '</li>';
        $html .= '<li>' . esc_html__('Log every disclosure to third parties (processors, authorities).', 'polski') . '</li>';
        $html .= '<li>' . esc_html__('Report every suspected data breach to the DPO/manager within 24 hours internally.', 'polski') . '</li>';
        $html .= '</ul>';

        return $html;
    }

    private function breachBody(): string
    {
        $steps = [
            __('Discovery - record timestamp, discoverer, affected systems.', 'polski'),
            __('Containment - isolate affected accounts/systems within 1 hour.', 'polski'),
            __('Internal notification - DPO and management within 24 hours.', 'polski'),
            __('Assessment - document data categories, subjects affected, likely impact.', 'polski'),
            __('UODO notification - required within 72 hours when risk to subjects is not unlikely.', 'polski'),
            __('Subject notification - required "without undue delay" when risk is high.', 'polski'),
            __('Remediation - patch, rotate credentials, review logs.', 'polski'),
            __('Post-mortem - document lessons learned and update training.', 'polski'),
        ];

        $html = '<h2>' . esc_html__('Step-by-step breach response', 'polski') . '</h2>';
        $html .= '<ol>';
        foreach ($steps as $step) {
            $html .= '<li>' . esc_html($step) . '</li>';
        }
        $html .= '</ol>';

        $html .= '<h2>' . esc_html__('Breach log template', 'polski') . '</h2>';
        $html .= '<table>';
        $html .= '<tr><th style="width:30%">' . esc_html__('Field', 'polski') . '</th><th>' . esc_html__('Value', 'polski') . '</th></tr>';

        foreach ([
            __('Incident ID', 'polski'),
            __('Detected at (UTC)', 'polski'),
            __('Detected by', 'polski'),
            __('Affected systems', 'polski'),
            __('Affected data categories', 'polski'),
            __('Approximate number of subjects', 'polski'),
            __('Likely impact', 'polski'),
            __('Containment actions', 'polski'),
            __('UODO notified at', 'polski'),
            __('Subjects notified at', 'polski'),
            __('Status', 'polski'),
        ] as $field) {
            $html .= '<tr><td>' . esc_html($field) . '</td><td style="height:32px"></td></tr>';
        }

        $html .= '</table>';

        $html .= '<p><strong>' . esc_html__('UODO notification channel:', 'polski') . '</strong> <a href="https://uodo.gov.pl" target="_blank" rel="noopener">uodo.gov.pl</a></p>';

        return $html;
    }
}
