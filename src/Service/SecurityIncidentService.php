<?php

declare(strict_types=1);

namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * Lightweight incident log for store-side security and CRA readiness work.
 */
final class SecurityIncidentService implements HasHooks
{
    private const PAGE_SLUG = 'polski-security-incidents';
    private const SETTINGS_OPTION = 'polski_security';
    private const INCIDENTS_OPTION = 'polski_security_incidents';
    private const CAPABILITY = 'manage_woocommerce';

    public function registerHooks(): void
    {
        add_action('admin_post_polski_save_security_incident', [$this, 'handleSaveIncident']);
        add_action('admin_post_polski_update_security_incident', [$this, 'handleUpdateIncident']);
        add_action('admin_post_polski_export_security_incidents', [$this, 'handleExportCsv']);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('security_incidents');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getIncidents(): array
    {
        $incidents = get_option(self::INCIDENTS_OPTION, []);

        if (! is_array($incidents)) {
            return [];
        }

        usort($incidents, static function (array $left, array $right): int {
            return strcmp((string) ($right['reported_at'] ?? ''), (string) ($left['reported_at'] ?? ''));
        });

        return array_values($incidents);
    }

    public function countOpenIncidents(): int
    {
        $openStatuses = ['open', 'investigating', 'monitoring'];
        $count = 0;

        foreach ($this->getIncidents() as $incident) {
            if (in_array((string) ($incident['status'] ?? 'open'), $openStatuses, true)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createIncident(array $data): string
    {
        $incidents = $this->getIncidents();
        $incident = $this->sanitizeIncident($data);
        $incidents[] = $incident;
        update_option(self::INCIDENTS_OPTION, $incidents);

        return (string) $incident['id'];
    }

    public function renderPage(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'polski'));
        }

        $settings = get_option(self::SETTINGS_OPTION, []);
        $settings = is_array($settings) ? $settings : [];
        $incidents = $this->getIncidents();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Security incidents', 'polski') . '</h1>';
        echo '<p>' . esc_html__('Track security incidents, product vulnerabilities, data breaches, and hosting or payment issues in one place. This helps with internal audit trails and CRA readiness work.', 'polski') . '</p>';

        echo '<div style="display:grid;grid-template-columns:minmax(360px,420px) 1fr;gap:24px;align-items:start;">';

        echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:20px;">';
        echo '<h2 style="margin-top:0;">' . esc_html__('Add incident', 'polski') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('polski_save_security_incident', '_polski_security_nonce');
        echo '<input type="hidden" name="action" value="polski_save_security_incident" />';

        $this->renderField(__('Reported at', 'polski'), '<input type="datetime-local" name="reported_at" value="' . esc_attr(gmdate('Y-m-d\TH:i')) . '" class="regular-text" />');
        $this->renderField(__('Type', 'polski'), $this->renderSelect('type', [
            'vulnerability' => __('Vulnerability', 'polski'),
            'data_breach' => __('Data breach', 'polski'),
            'payment_issue' => __('Payment issue', 'polski'),
            'availability' => __('Availability issue', 'polski'),
            'third_party' => __('Third-party incident', 'polski'),
            'other' => __('Other', 'polski'),
        ]));
        $this->renderField(__('Severity', 'polski'), $this->renderSelect('severity', [
            'low' => __('Low', 'polski'),
            'medium' => __('Medium', 'polski'),
            'high' => __('High', 'polski'),
            'critical' => __('Critical', 'polski'),
        ], 'medium'));
        $this->renderField(__('Title', 'polski'), '<input type="text" name="title" value="" class="regular-text" required />');
        $this->renderField(__('Affected area', 'polski'), '<input type="text" name="affected_area" value="" class="regular-text" placeholder="' . esc_attr__('Checkout, hosting, orders, API, plugin update...', 'polski') . '" />');
        $this->renderField(__('Mitigation', 'polski'), '<textarea name="mitigation" rows="3" class="large-text"></textarea>');
        $this->renderField(__('Notes', 'polski'), '<textarea name="notes" rows="5" class="large-text"></textarea>');
        $this->renderField(__('Reported by', 'polski'), '<input type="text" name="reporter_name" value="" class="regular-text" placeholder="' . esc_attr((string) ($settings['default_reporter_name'] ?? '')) . '" />');
        $this->renderField(__('Reporter email', 'polski'), '<input type="email" name="reporter_email" value="" class="regular-text" placeholder="' . esc_attr((string) ($settings['incident_contact_email'] ?? '')) . '" />');
        $this->renderField(__('Notifications', 'polski'), '<label><input type="checkbox" name="notified_hosting" value="1" /> ' . esc_html__('Hosting provider notified', 'polski') . '</label><br><label><input type="checkbox" name="notified_authority" value="1" /> ' . esc_html__('Authority or regulator notified', 'polski') . '</label>');

        submit_button(__('Save incident', 'polski'));
        echo '</form>';
        echo '</div>';

        echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:20px;">';
        echo '<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">';
        echo '<div>';
        echo '<h2 style="margin:0;">' . esc_html__('Incident log', 'polski') . '</h2>';
        echo '<p style="margin:6px 0 0;color:#666;">' . esc_html(sprintf(
            /* translators: %d: number of currently open incidents */
            __('Currently open incidents: %d', 'polski'),
            $this->countOpenIncidents(),
        )) . '</p>';
        echo '</div>';
        echo '<a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=polski_export_security_incidents'), 'polski_export_security_incidents', '_polski_security_export_nonce')) . '">' . esc_html__('Export CSV', 'polski') . '</a>';
        echo '</div>';

        if ($incidents === []) {
            echo '<p style="margin:20px 0 0;">' . esc_html__('No incidents logged yet.', 'polski') . '</p>';
        } else {
            echo '<table class="widefat striped" style="margin-top:16px;">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Reported at', 'polski') . '</th>';
            echo '<th>' . esc_html__('Title', 'polski') . '</th>';
            echo '<th>' . esc_html__('Type', 'polski') . '</th>';
            echo '<th>' . esc_html__('Severity', 'polski') . '</th>';
            echo '<th>' . esc_html__('Status', 'polski') . '</th>';
            echo '<th>' . esc_html__('Notifications', 'polski') . '</th>';
            echo '<th>' . esc_html__('Action', 'polski') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($incidents as $incident) {
                echo '<tr>';
                echo '<td>' . esc_html($this->formatReportedAt((string) ($incident['reported_at'] ?? ''))) . '</td>';
                echo '<td><strong>' . esc_html((string) ($incident['title'] ?? '')) . '</strong><br><span style="color:#666;">' . esc_html((string) ($incident['affected_area'] ?? '')) . '</span></td>';
                echo '<td>' . esc_html($this->labelForType((string) ($incident['type'] ?? 'other'))) . '</td>';
                echo '<td>' . esc_html($this->labelForSeverity((string) ($incident['severity'] ?? 'medium'))) . '</td>';
                echo '<td>';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;gap:8px;align-items:center;">';
                wp_nonce_field('polski_update_security_incident', '_polski_security_update_nonce');
                echo '<input type="hidden" name="action" value="polski_update_security_incident" />';
                echo '<input type="hidden" name="incident_id" value="' . esc_attr((string) ($incident['id'] ?? '')) . '" />';
                echo $this->renderSelect('status', [
                    'open' => __('Open', 'polski'),
                    'investigating' => __('Investigating', 'polski'),
                    'monitoring' => __('Monitoring', 'polski'),
                    'resolved' => __('Resolved', 'polski'),
                ], (string) ($incident['status'] ?? 'open'));
                echo '<button type="submit" class="button button-small">' . esc_html__('Update', 'polski') . '</button>';
                echo '</form>';
                echo '</td>';
                echo '<td>';
                echo ((bool) ($incident['notified_hosting'] ?? false)) ? esc_html__('Hosting', 'polski') : '';
                echo ((bool) ($incident['notified_hosting'] ?? false) && (bool) ($incident['notified_authority'] ?? false)) ? '<br>' : '';
                echo ((bool) ($incident['notified_authority'] ?? false)) ? esc_html__('Authority', 'polski') : '';
                echo '</td>';
                echo '<td><details><summary>' . esc_html__('Details', 'polski') . '</summary><div style="margin-top:8px;max-width:360px;white-space:pre-wrap;">' . esc_html((string) ($incident['notes'] ?? '')) . ($incident['mitigation'] ? "\n\n" . __('Mitigation:', 'polski') . ' ' . (string) $incident['mitigation'] : '') . '</div></details></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    public function handleSaveIncident(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'polski'));
        }

        check_admin_referer('polski_save_security_incident', '_polski_security_nonce');

        $this->createIncident($_POST);

        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG . '&saved=1'));
        exit;
    }

    public function handleUpdateIncident(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'polski'));
        }

        check_admin_referer('polski_update_security_incident', '_polski_security_update_nonce');

        $incidentId = sanitize_text_field((string) ($_POST['incident_id'] ?? ''));
        $status = sanitize_key((string) ($_POST['status'] ?? 'open'));
        $allowedStatuses = ['open', 'investigating', 'monitoring', 'resolved'];

        if ($incidentId === '' || ! in_array($status, $allowedStatuses, true)) {
            wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG . '&updated=0'));
            exit;
        }

        $incidents = $this->getIncidents();

        foreach ($incidents as &$incident) {
            if (($incident['id'] ?? '') !== $incidentId) {
                continue;
            }

            $incident['status'] = $status;
            $incident['updated_at'] = current_time('mysql', true);
        }

        update_option(self::INCIDENTS_OPTION, $incidents);

        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG . '&updated=1'));
        exit;
    }

    public function handleExportCsv(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'polski'));
        }

        check_admin_referer('polski_export_security_incidents', '_polski_security_export_nonce');

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=polski-security-incidents.csv');

        $output = fopen('php://output', 'wb');

        if (! is_resource($output)) {
            exit;
        }

        fputcsv($output, [
            'id',
            'reported_at',
            'type',
            'severity',
            'status',
            'title',
            'affected_area',
            'mitigation',
            'notes',
            'reporter_name',
            'reporter_email',
            'notified_hosting',
            'notified_authority',
            'created_at',
            'updated_at',
        ]);

        foreach ($this->getIncidents() as $incident) {
            fputcsv($output, [
                (string) ($incident['id'] ?? ''),
                (string) ($incident['reported_at'] ?? ''),
                (string) ($incident['type'] ?? ''),
                (string) ($incident['severity'] ?? ''),
                (string) ($incident['status'] ?? ''),
                (string) ($incident['title'] ?? ''),
                (string) ($incident['affected_area'] ?? ''),
                (string) ($incident['mitigation'] ?? ''),
                (string) ($incident['notes'] ?? ''),
                (string) ($incident['reporter_name'] ?? ''),
                (string) ($incident['reporter_email'] ?? ''),
                ((bool) ($incident['notified_hosting'] ?? false)) ? 'yes' : 'no',
                ((bool) ($incident['notified_authority'] ?? false)) ? 'yes' : 'no',
                (string) ($incident['created_at'] ?? ''),
                (string) ($incident['updated_at'] ?? ''),
            ]);
        }

        fclose($output);
        exit;
    }

    private function renderField(string $label, string $controlHtml): void
    {
        echo '<p style="margin:0 0 14px;">';
        echo '<label style="display:block;font-weight:600;margin-bottom:6px;">' . esc_html($label) . '</label>';
        echo $controlHtml;
        echo '</p>';
    }

    /**
     * @param array<string, string> $options
     */
    private function renderSelect(string $name, array $options, string $selected = ''): string
    {
        $html = '<select name="' . esc_attr($name) . '">';

        foreach ($options as $value => $label) {
            $html .= '<option value="' . esc_attr($value) . '" ' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
        }

        $html .= '</select>';

        return $html;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function sanitizeIncident(array $input): array
    {
        $allowedTypes = ['vulnerability', 'data_breach', 'payment_issue', 'availability', 'third_party', 'other'];
        $allowedSeverities = ['low', 'medium', 'high', 'critical'];
        $allowedStatuses = ['open', 'investigating', 'monitoring', 'resolved'];
        $reportedAtRaw = sanitize_text_field((string) ($input['reported_at'] ?? ''));
        $reportedAt = $reportedAtRaw !== '' ? str_replace('T', ' ', $reportedAtRaw) . ':00' : current_time('mysql', true);

        $type = sanitize_key((string) ($input['type'] ?? 'other'));
        $severity = sanitize_key((string) ($input['severity'] ?? 'medium'));
        $status = sanitize_key((string) ($input['status'] ?? 'open'));

        if (! in_array($type, $allowedTypes, true)) {
            $type = 'other';
        }

        if (! in_array($severity, $allowedSeverities, true)) {
            $severity = 'medium';
        }

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'open';
        }

        return [
            'id' => wp_generate_uuid4(),
            'reported_at' => $reportedAt,
            'type' => $type,
            'severity' => $severity,
            'status' => $status,
            'title' => sanitize_text_field((string) ($input['title'] ?? '')),
            'affected_area' => sanitize_text_field((string) ($input['affected_area'] ?? '')),
            'mitigation' => sanitize_textarea_field((string) ($input['mitigation'] ?? '')),
            'notes' => sanitize_textarea_field((string) ($input['notes'] ?? '')),
            'reporter_name' => sanitize_text_field((string) ($input['reporter_name'] ?? '')),
            'reporter_email' => sanitize_email((string) ($input['reporter_email'] ?? '')),
            'notified_hosting' => ! empty($input['notified_hosting']),
            'notified_authority' => ! empty($input['notified_authority']),
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ];
    }

    private function formatReportedAt(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);

        return $timestamp ? wp_date('Y-m-d H:i', $timestamp) : $value;
    }

    private function labelForType(string $value): string
    {
        return match ($value) {
            'vulnerability' => __('Vulnerability', 'polski'),
            'data_breach' => __('Data breach', 'polski'),
            'payment_issue' => __('Payment issue', 'polski'),
            'availability' => __('Availability issue', 'polski'),
            'third_party' => __('Third-party incident', 'polski'),
            default => __('Other', 'polski'),
        };
    }

    private function labelForSeverity(string $value): string
    {
        return match ($value) {
            'low' => __('Low', 'polski'),
            'medium' => __('Medium', 'polski'),
            'high' => __('High', 'polski'),
            'critical' => __('Critical', 'polski'),
            default => __('Medium', 'polski'),
        };
    }
}
