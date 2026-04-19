<?php

declare(strict_types=1);

namespace Polski\CRA;

use DateTimeImmutable;
use Polski\Contract\HasHooks;
use Polski\CRA\Enum\IncidentKind;
use Polski\CRA\Enum\IncidentStatus;
use Polski\CRA\Enum\Severity;
use Polski\CRA\Model\Incident;
use Polski\CRA\Repository\IncidentRepository;

defined('ABSPATH') || exit;

/**
 * CRA Article 14 incident-handling workflow: register, track deadlines,
 * dispatch early-warning notifications, export as JSON for submission
 * to the ENISA Single Reporting Platform (shape is forward-compatible
 * with the draft schema; endpoint URL is configurable).
 */
final class IncidentService implements HasHooks
{
    public const OPTION_WEBHOOK = 'polski_cra_incident_webhook';
    public const OPTION_NOTIFY_EMAIL = 'polski_cra_incident_email';
    public const CRON_HOOK = 'polski_cra_incident_deadline_check';

    public function __construct(
        private readonly IncidentRepository $repository,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('init', [$this, 'ensureCron']);
        add_action(self::CRON_HOOK, [$this, 'runDeadlineCheck']);
    }

    public function ensureCron(): void
    {
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function record(
        IncidentKind $kind,
        Severity $severity,
        string $title,
        string $summary,
        string $affectedComponent,
        string $affectedVersions,
        array $payload = [],
        ?DateTimeImmutable $discoveredAt = null,
        string $reporter = '',
        string $referenceId = '',
    ): Incident {
        $discoveredAt = $discoveredAt ?? new DateTimeImmutable('now');
        $deadlineAt = $discoveredAt->modify('+' . $kind->earlyWarningHours() . ' hours');

        $incident = new Incident(
            id: null,
            kind: $kind,
            severity: $severity,
            status: IncidentStatus::Open,
            title: $title,
            summary: $summary,
            affectedComponent: $affectedComponent,
            affectedVersions: $affectedVersions,
            discoveredAt: $discoveredAt,
            deadlineAt: $deadlineAt,
            notifiedAt: null,
            resolvedAt: null,
            reporter: $reporter,
            referenceId: $referenceId,
            payload: $payload,
        );

        $id = $this->repository->save($incident);

        /**
         * Fires immediately after an incident is recorded, before any
         * automated notification. Useful for logging/integrations.
         */
        do_action('polski_cra_incident_recorded', $id, $incident);

        return $this->repository->find($id) ?? $incident;
    }

    public function markNotified(int $id): ?Incident
    {
        $incident = $this->repository->find($id);

        if ($incident === null) {
            return null;
        }

        $updated = new Incident(
            id: $incident->id,
            kind: $incident->kind,
            severity: $incident->severity,
            status: IncidentStatus::Notified,
            title: $incident->title,
            summary: $incident->summary,
            affectedComponent: $incident->affectedComponent,
            affectedVersions: $incident->affectedVersions,
            discoveredAt: $incident->discoveredAt,
            deadlineAt: $incident->deadlineAt,
            notifiedAt: new DateTimeImmutable('now'),
            resolvedAt: $incident->resolvedAt,
            reporter: $incident->reporter,
            referenceId: $incident->referenceId,
            payload: $incident->payload,
        );

        $this->repository->save($updated);

        return $this->repository->find($id);
    }

    public function markResolved(int $id): ?Incident
    {
        $incident = $this->repository->find($id);

        if ($incident === null) {
            return null;
        }

        $updated = new Incident(
            id: $incident->id,
            kind: $incident->kind,
            severity: $incident->severity,
            status: IncidentStatus::Resolved,
            title: $incident->title,
            summary: $incident->summary,
            affectedComponent: $incident->affectedComponent,
            affectedVersions: $incident->affectedVersions,
            discoveredAt: $incident->discoveredAt,
            deadlineAt: $incident->deadlineAt,
            notifiedAt: $incident->notifiedAt,
            resolvedAt: new DateTimeImmutable('now'),
            reporter: $incident->reporter,
            referenceId: $incident->referenceId,
            payload: $incident->payload,
        );

        $this->repository->save($updated);

        return $this->repository->find($id);
    }

    /**
     * Dispatch an early-warning notification:
     *  - to a configured webhook URL (POST JSON body), and/or
     *  - to a configured DPO / legal contact email (human summary).
     *
     * The dispatcher is idempotent-ish: marking as notified is only done
     * after at least one channel succeeds.
     *
     * @return array{webhook_ok: bool, email_ok: bool}
     */
    public function dispatchNotification(int $id): array
    {
        $incident = $this->repository->find($id);

        if ($incident === null) {
            return ['webhook_ok' => false, 'email_ok' => false];
        }

        $webhookOk = $this->sendWebhook($incident);
        $emailOk = $this->sendEmail($incident);

        if ($webhookOk || $emailOk) {
            $this->markNotified($id);
        }

        return ['webhook_ok' => $webhookOk, 'email_ok' => $emailOk];
    }

    public function runDeadlineCheck(): void
    {
        $now = new DateTimeImmutable('now');

        foreach ($this->repository->all(500) as $incident) {
            if ($incident->notifiedAt !== null || $incident->status !== IncidentStatus::Open) {
                continue;
            }

            if ($incident->hoursUntilDeadline($now) <= 2) {
                /**
                 * Fires when an incident is nearing its reporting deadline
                 * (<=2h remaining) and has not been notified yet.
                 */
                do_action('polski_cra_incident_deadline_approaching', $incident);
            }
        }
    }

    private function sendWebhook(Incident $incident): bool
    {
        $url = (string) get_option(self::OPTION_WEBHOOK, '');

        if ($url === '' || ! wp_http_validate_url($url)) {
            return false;
        }

        $response = wp_remote_post($url, [
            'timeout' => 10,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => (string) wp_json_encode($incident->toExportArray()),
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        return $code >= 200 && $code < 300;
    }

    private function sendEmail(Incident $incident): bool
    {
        $to = (string) get_option(self::OPTION_NOTIFY_EMAIL, '');

        if ($to === '' || ! is_email($to)) {
            return false;
        }

        $subject = sprintf(
            /* translators: 1: severity, 2: component */
            __('[CRA early warning] %1$s - %2$s', 'polski'),
            strtoupper($incident->severity->value),
            $incident->affectedComponent !== '' ? $incident->affectedComponent : get_bloginfo('name'),
        );

        $body = implode("\n", [
            __('CRA Article 14 early-warning notification.', 'polski'),
            '',
            /* translators: %s: incident title */
            sprintf(__('Title: %s', 'polski'), $incident->title),
            /* translators: %s: incident kind label */
            sprintf(__('Kind: %s', 'polski'), $incident->kind->label()),
            /* translators: %s: incident severity label */
            sprintf(__('Severity: %s', 'polski'), $incident->severity->label()),
            sprintf(
                /* translators: 1: affected component name, 2: affected version range */
                __('Affected: %1$s %2$s', 'polski'),
                $incident->affectedComponent,
                $incident->affectedVersions,
            ),
            /* translators: %s: ISO 8601 datetime when the incident was discovered */
            sprintf(__('Discovered: %s', 'polski'), $incident->discoveredAt->format(DATE_ATOM)),
            /* translators: %s: ISO 8601 datetime by which the report must be filed */
            sprintf(__('Deadline: %s', 'polski'), $incident->deadlineAt->format(DATE_ATOM)),
            '',
            __('Summary:', 'polski'),
            $incident->summary,
        ]);

        return (bool) wp_mail($to, $subject, $body);
    }
}
