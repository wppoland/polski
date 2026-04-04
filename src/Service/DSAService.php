<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;
use Polski\Util\TemplateLoader;

final class DSAService implements HasHooks
{
    public function __construct(
        private readonly TemplateLoader $templateLoader,
    ) {}

    public function registerHooks(): void
    {
        add_action('admin_post_nopriv_polski_dsa_report', [$this, 'handleReportSubmission']);
        add_action('admin_post_polski_dsa_report', [$this, 'handleReportSubmission']);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('dsa_toolkit');
    }

    /**
     * Render the DSA report form for use by shortcodes or templates.
     */
    public function renderReportForm(): string
    {
        if (! $this->isEnabled()) {
            return '';
        }

        ob_start();
        $this->templateLoader->include('forms/dsa-report', [
            'settings' => $this->getSettings(),
        ]);
        return (string) ob_get_clean();
    }

    /**
     * Handle frontend DSA report form submission.
     */
    public function handleReportSubmission(): void
    {
        if (
            ! isset($_POST['_polski_dsa_nonce'])
            || ! wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['_polski_dsa_nonce'])),
                'polski_dsa_report',
            )
        ) {
            wp_die(esc_html__('Invalid security token.', 'polski'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'polski_dsa_reports';

        $data = [
            'reporter_name'  => sanitize_text_field(wp_unslash($_POST['reporter_name'] ?? '')),
            'reporter_email' => sanitize_email(wp_unslash($_POST['reporter_email'] ?? '')),
            'content_url'    => esc_url_raw(wp_unslash($_POST['content_url'] ?? '')),
            'reason'         => sanitize_text_field(wp_unslash($_POST['reason'] ?? '')),
            'description'    => sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
            'status'         => 'new',
            'created_at'     => current_time('mysql'),
        ];

        $wpdb->insert($table, $data);
        $reportId = (int) $wpdb->insert_id;

        do_action('polski/dsa/report_created', $reportId, $data);

        $settings = $this->getSettings();
        $contactEmail = $settings['contact_email'] ?? '';

        if (! empty($contactEmail) && is_email($contactEmail)) {
            wp_mail(
                $contactEmail,
                sprintf(
                    /* translators: %d: report ID */
                    __('[Polski DSA] New report #%d', 'polski'),
                    $reportId,
                ),
                sprintf(
                    "New DSA report:\n\nReporter: %s (%s)\nURL: %s\nReason: %s\nDescription: %s",
                    $data['reporter_name'],
                    $data['reporter_email'],
                    $data['content_url'],
                    $data['reason'],
                    $data['description'],
                ),
            );
        }

        $redirectUrl = isset($_POST['_wp_http_referer'])
            ? sanitize_text_field(wp_unslash($_POST['_wp_http_referer']))
            : home_url();

        wp_safe_redirect(add_query_arg('polski_dsa_sent', '1', $redirectUrl));
        exit;
    }

    /**
     * Render admin reports page listing all DSA reports.
     */
    public function renderReportsPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to manage DSA reports.', 'polski'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'polski_dsa_reports';

        // Handle status update.
        if (isset($_POST['report_id'], $_POST['new_status'], $_POST['_polski_dsa_admin_nonce'])) {
            if (wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['_polski_dsa_admin_nonce'])),
                'polski_dsa_admin',
            )) {
                $newStatus = sanitize_key(wp_unslash($_POST['new_status']));

                if (! in_array($newStatus, ['new', 'resolved'], true)) {
                    wp_die(esc_html__('Invalid DSA report status.', 'polski'));
                }

                $wpdb->update(
                    $table,
                    [
                        'status'      => $newStatus,
                        'resolved_at' => current_time('mysql'),
                    ],
                    ['id' => absint($_POST['report_id'])],
                );
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $reports = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", 100),
        );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('DSA Reports', 'polski') . '</h1>';

        if (empty($reports)) {
            echo '<p>' . esc_html__('No reports found.', 'polski') . '</p>';
        } else {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>#</th>';
            echo '<th>' . esc_html__('Date', 'polski') . '</th>';
            echo '<th>' . esc_html__('Reporter', 'polski') . '</th>';
            echo '<th>' . esc_html__('URL', 'polski') . '</th>';
            echo '<th>' . esc_html__('Reason', 'polski') . '</th>';
            echo '<th>' . esc_html__('Status', 'polski') . '</th>';
            echo '<th>' . esc_html__('Action', 'polski') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($reports as $report) {
                echo '<tr>';
                echo '<td>' . (int) $report->id . '</td>';
                echo '<td>' . esc_html($report->created_at) . '</td>';
                echo '<td>' . esc_html($report->reporter_name) . '<br><small>' . esc_html($report->reporter_email) . '</small></td>';
                echo '<td><a href="' . esc_url($report->content_url) . '" target="_blank" rel="noopener noreferrer">'
                    . esc_html(mb_substr($report->content_url, 0, 50)) . '</a></td>';
                echo '<td>' . esc_html($report->reason) . '</td>';
                echo '<td><span class="polski-dsa-status-' . esc_attr($report->status) . '">'
                    . esc_html($report->status) . '</span></td>';
                echo '<td>';

                if ($report->status === 'new') {
                    echo '<form method="post" style="display:inline;">';
                    wp_nonce_field('polski_dsa_admin', '_polski_dsa_admin_nonce');
                    echo '<input type="hidden" name="report_id" value="' . (int) $report->id . '">';
                    echo '<input type="hidden" name="new_status" value="resolved">';
                    echo '<button type="submit" class="button button-small">'
                        . esc_html__('Mark as resolved', 'polski') . '</button>';
                    echo '</form>';
                }

                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        echo '</div>';
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        $settings = get_option('polski_dsa', []);
        return is_array($settings) ? $settings : [];
    }
}
