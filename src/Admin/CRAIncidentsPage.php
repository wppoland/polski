<?php

declare(strict_types=1);

namespace Polski\Admin;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use Polski\Contract\HasHooks;
use Polski\CRA\Enum\IncidentKind;
use Polski\CRA\Enum\IncidentStatus;
use Polski\CRA\Enum\Severity;
use Polski\CRA\IncidentService;
use Polski\CRA\Model\Incident;
use Polski\CRA\Repository\IncidentRepository;

/**
 * Admin UI for registering CRA Article 14 security incidents, tracking
 * early-warning deadlines and exporting the record in a JSON shape
 * aligned with ENISA's draft Single Reporting Platform schema.
 */
final class CRAIncidentsPage implements HasHooks
{
    private const SLUG = 'polski-cra-incidents';
    private const NONCE_NEW = 'polski_cra_incident_new';
    private const NONCE_ACTION = 'polski_cra_incident_action';

    public function __construct(
        private readonly IncidentService $service,
        private readonly IncidentRepository $repository,
    ) {
    }

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('cra_readiness')) {
            return;
        }

        add_action('admin_menu', [$this, 'registerPage'], 70);
        add_action('admin_post_polski_cra_incident_save', [$this, 'handleSave']);
        add_action('admin_post_polski_cra_incident_action', [$this, 'handleAction']);
    }

    public function registerPage(): void
    {
        add_submenu_page(
            'polski',
            __('CRA incidents', 'polski'),
            __('CRA incidents', 'polski'),
            'manage_woocommerce',
            self::SLUG,
            [$this, 'render'],
        );
    }

    public function render(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET navigation.
        $view = isset($_GET['view']) ? sanitize_key(wp_unslash((string) $_GET['view'])) : 'list';

        echo '<div class="wrap polski-cra-incidents">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('CRA incidents', 'polski') . '</h1>';
        printf(
            ' <a href="%s" class="page-title-action">%s</a>',
            esc_url(admin_url('admin.php?page=' . self::SLUG . '&view=new')),
            esc_html__('Record incident', 'polski'),
        );
        echo '<hr class="wp-header-end">';

        $this->renderNotice();

        if ($view === 'new') {
            $this->renderForm();
        } else {
            $this->renderList();
        }

        echo '</div>';
    }

    private function renderNotice(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice display.
        $notice = isset($_GET['polski_notice']) ? sanitize_key(wp_unslash((string) $_GET['polski_notice'])) : '';

        if ($notice === '') {
            return;
        }

        $messages = [
            'saved' => __('Incident recorded.', 'polski'),
            'notified' => __('Incident notification dispatched.', 'polski'),
            'resolved' => __('Incident marked resolved.', 'polski'),
            'deleted' => __('Incident deleted.', 'polski'),
            'error' => __('Something went wrong.', 'polski'),
        ];

        $class = $notice === 'error' ? 'notice-error' : 'notice-success';
        $text = $messages[$notice] ?? '';

        if ($text !== '') {
            printf('<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr($class), esc_html($text));
        }
    }

    private function renderList(): void
    {
        echo '<p>' . esc_html__('Record actively-exploited vulnerabilities and security incidents. The checker tracks the CRA Article 14 24-hour early-warning deadline and exports a structured JSON submission.', 'polski') . '</p>';
        echo '<p class="description">' . esc_html__('Configure the webhook URL and notification email under Polski > Settings > CRA incidents.', 'polski') . '</p>';

        $incidents = $this->repository->all(200);

        if ($incidents === []) {
            echo '<p><em>' . esc_html__('No incidents recorded yet.', 'polski') . '</em></p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
        printf('<th>%s</th>', esc_html__('Title', 'polski'));
        printf('<th>%s</th>', esc_html__('Severity', 'polski'));
        printf('<th>%s</th>', esc_html__('Status', 'polski'));
        printf('<th>%s</th>', esc_html__('Deadline', 'polski'));
        printf('<th>%s</th>', esc_html__('Actions', 'polski'));
        echo '</tr></thead><tbody>';

        $now = new DateTimeImmutable('now');
        foreach ($incidents as $incident) {
            $this->renderRow($incident, $now);
        }

        echo '</tbody></table>';
    }

    private function renderRow(Incident $incident, DateTimeImmutable $now): void
    {
        $hoursLeft = $incident->hoursUntilDeadline($now);
        $overdue = $incident->isOverdue($now);

        echo '<tr>';
        printf('<td><strong>%s</strong><br><small>%s</small></td>', esc_html($incident->title), esc_html($incident->kind->label()));
        printf(
            '<td><span style="color:%s">%s</span></td>',
            esc_attr($incident->severity->color()),
            esc_html($incident->severity->label()),
        );
        printf('<td>%s</td>', esc_html($incident->status->label()));

        if ($incident->notifiedAt !== null) {
            printf(
                '<td><span style="color:#1a7f37">%s</span></td>',
                esc_html(sprintf(
                    /* translators: %s: notification timestamp */
                    __('Notified %s', 'polski'),
                    $incident->notifiedAt->format('Y-m-d H:i'),
                )),
            );
        } elseif ($overdue) {
            printf(
                '<td><span style="color:#d63638"><strong>%s</strong></span></td>',
                esc_html(sprintf(
                    /* translators: %d: hours */
                    __('Overdue by %dh', 'polski'),
                    abs($hoursLeft),
                )),
            );
        } else {
            printf(
                '<td><span>%s</span></td>',
                esc_html(sprintf(
                    /* translators: %d: hours */
                    __('%dh remaining', 'polski'),
                    $hoursLeft,
                )),
            );
        }

        echo '<td>';
        $this->renderRowActions($incident);
        echo '</td></tr>';
    }

    private function renderRowActions(Incident $incident): void
    {
        $nonce = wp_create_nonce(self::NONCE_ACTION);
        $base = admin_url('admin-post.php');

        $exportUrl = add_query_arg([
            'action' => 'polski_cra_incident_action',
            'incident_action' => 'export',
            'incident_id' => $incident->id,
            '_wpnonce' => $nonce,
        ], $base);

        $notifyUrl = add_query_arg([
            'action' => 'polski_cra_incident_action',
            'incident_action' => 'notify',
            'incident_id' => $incident->id,
            '_wpnonce' => $nonce,
        ], $base);

        $resolveUrl = add_query_arg([
            'action' => 'polski_cra_incident_action',
            'incident_action' => 'resolve',
            'incident_id' => $incident->id,
            '_wpnonce' => $nonce,
        ], $base);

        $deleteUrl = add_query_arg([
            'action' => 'polski_cra_incident_action',
            'incident_action' => 'delete',
            'incident_id' => $incident->id,
            '_wpnonce' => $nonce,
        ], $base);

        $links = [
            sprintf('<a href="%s">%s</a>', esc_url($exportUrl), esc_html__('Export JSON', 'polski')),
        ];

        if ($incident->status === IncidentStatus::Open) {
            $links[] = sprintf('<a href="%s">%s</a>', esc_url($notifyUrl), esc_html__('Dispatch notification', 'polski'));
        }
        if ($incident->status !== IncidentStatus::Resolved) {
            $links[] = sprintf('<a href="%s">%s</a>', esc_url($resolveUrl), esc_html__('Mark resolved', 'polski'));
        }

        $confirmAttr = sprintf(' onclick="return confirm(%s)"', esc_attr(wp_json_encode(__('Delete incident?', 'polski'))));
        $links[] = sprintf('<a href="%s"%s>%s</a>', esc_url($deleteUrl), $confirmAttr, esc_html__('Delete', 'polski'));

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Parts pre-built with esc_url/esc_html.
        echo implode(' | ', $links);
    }

    private function renderForm(): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::NONCE_NEW);
        echo '<input type="hidden" name="action" value="polski_cra_incident_save">';

        echo '<table class="form-table" role="presentation"><tbody>';
        $this->textRow(__('Title', 'polski'), 'title', '', true);
        $this->textRow(__('Affected component', 'polski'), 'affected_component', '');
        $this->textRow(__('Affected versions', 'polski'), 'affected_versions', '');
        $this->textRow(__('Reporter', 'polski'), 'reporter', '');
        $this->textRow(__('External reference ID', 'polski'), 'reference_id', '');

        $this->selectRow(__('Kind', 'polski'), 'kind', IncidentKind::ActivelyExploitedVulnerability->value, [
            IncidentKind::ActivelyExploitedVulnerability->value => IncidentKind::ActivelyExploitedVulnerability->label(),
            IncidentKind::SecurityIncident->value => IncidentKind::SecurityIncident->label(),
            IncidentKind::NearMiss->value => IncidentKind::NearMiss->label(),
        ]);

        $this->selectRow(__('Severity', 'polski'), 'severity', Severity::High->value, [
            Severity::Critical->value => Severity::Critical->label(),
            Severity::High->value => Severity::High->label(),
            Severity::Medium->value => Severity::Medium->label(),
            Severity::Low->value => Severity::Low->label(),
        ]);

        echo '<tr><th scope="row"><label for="polski-summary">' . esc_html__('Summary', 'polski') . '</label></th><td>';
        echo '<textarea id="polski-summary" name="summary" rows="6" class="large-text" required></textarea>';
        echo '</td></tr>';

        echo '</tbody></table>';

        submit_button(__('Record incident', 'polski'));
        echo '</form>';
    }

    public function handleSave(): void
    {
        check_admin_referer(self::NONCE_NEW);

        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'polski'));
        }

        $kind = IncidentKind::tryFrom(sanitize_key(wp_unslash((string) ($_POST['kind'] ?? ''))))
            ?? IncidentKind::ActivelyExploitedVulnerability;
        $severity = Severity::tryFrom(sanitize_key(wp_unslash((string) ($_POST['severity'] ?? '')))) ?? Severity::Medium;

        $title = sanitize_text_field(wp_unslash((string) ($_POST['title'] ?? '')));
        $summary = sanitize_textarea_field(wp_unslash((string) ($_POST['summary'] ?? '')));

        if ($title === '' || $summary === '') {
            $this->redirectTo('list', 'error');
        }

        $this->service->record(
            kind: $kind,
            severity: $severity,
            title: $title,
            summary: $summary,
            affectedComponent: sanitize_text_field(wp_unslash((string) ($_POST['affected_component'] ?? ''))),
            affectedVersions: sanitize_text_field(wp_unslash((string) ($_POST['affected_versions'] ?? ''))),
            reporter: sanitize_text_field(wp_unslash((string) ($_POST['reporter'] ?? ''))),
            referenceId: sanitize_text_field(wp_unslash((string) ($_POST['reference_id'] ?? ''))),
        );

        $this->redirectTo('list', 'saved');
    }

    public function handleAction(): void
    {
        check_admin_referer(self::NONCE_ACTION);

        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'polski'));
        }

        $action = sanitize_key((string) ($_GET['incident_action'] ?? ''));
        $id = isset($_GET['incident_id']) ? (int) $_GET['incident_id'] : 0;

        if ($id <= 0) {
            $this->redirectTo('list', 'error');
        }

        switch ($action) {
            case 'export':
                $this->export($id);
                return;
            case 'notify':
                $this->service->dispatchNotification($id);
                $this->redirectTo('list', 'notified');
                return;
            case 'resolve':
                $this->service->markResolved($id);
                $this->redirectTo('list', 'resolved');
                return;
            case 'delete':
                $this->repository->delete($id);
                $this->redirectTo('list', 'deleted');
                return;
        }

        $this->redirectTo('list', 'error');
    }

    private function export(int $id): void
    {
        $incident = $this->repository->find($id);

        if ($incident === null) {
            $this->redirectTo('list', 'error');
        }

        $filename = sprintf('cra-incident-%d-%s.json', $id, gmdate('Ymd-His'));

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON payload under Content-Type application/json.
        echo wp_json_encode($incident->toExportArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function textRow(string $label, string $name, string $value, bool $required = false): void
    {
        printf(
            '<tr><th scope="row"><label for="polski-%1$s">%2$s</label></th><td><input type="text" id="polski-%1$s" name="%1$s" value="%3$s" class="regular-text"%4$s></td></tr>',
            esc_attr($name),
            esc_html($label),
            esc_attr($value),
            $required ? ' required' : '',
        );
    }

    /**
     * @param array<string, string> $options
     */
    private function selectRow(string $label, string $name, string $value, array $options): void
    {
        printf(
            '<tr><th scope="row"><label for="polski-%1$s">%2$s</label></th><td><select id="polski-%1$s" name="%1$s">',
            esc_attr($name),
            esc_html($label),
        );

        foreach ($options as $key => $optLabel) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($key),
                $key === $value ? ' selected' : '',
                esc_html($optLabel),
            );
        }

        echo '</select></td></tr>';
    }

    private function redirectTo(string $view, string $notice): void
    {
        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG . '&view=' . $view . '&polski_notice=' . $notice));
        exit;
    }
}
