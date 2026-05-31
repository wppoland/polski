<?php

declare(strict_types=1);

namespace Polski\CRA\Model;

use DateTimeImmutable;
use Polski\CRA\Enum\IncidentKind;
use Polski\CRA\Enum\IncidentStatus;
use Polski\CRA\Enum\Severity;

defined('ABSPATH') || exit;

/**
 * Aggregate for a single CRA-reportable incident or vulnerability record.
 */
final class Incident
{
    /**
     * @param array<string, mixed> $payload Arbitrary structured details
     *     (affected endpoints, mitigation, CVEs) captured alongside the
     *     incident and shipped verbatim in the export.
     */
    public function __construct(
        public readonly ?int $id,
        public readonly IncidentKind $kind,
        public readonly Severity $severity,
        public readonly IncidentStatus $status,
        public readonly string $title,
        public readonly string $summary,
        public readonly string $affectedComponent,
        public readonly string $affectedVersions,
        public readonly DateTimeImmutable $discoveredAt,
        public readonly DateTimeImmutable $deadlineAt,
        public readonly ?DateTimeImmutable $notifiedAt,
        public readonly ?DateTimeImmutable $resolvedAt,
        public readonly string $reporter,
        public readonly string $referenceId,
        public readonly array $payload,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
    }

    public function hoursUntilDeadline(?DateTimeImmutable $now = null): int
    {
        $now = $now ?? new DateTimeImmutable('now');
        $diff = $this->deadlineAt->getTimestamp() - $now->getTimestamp();

        return (int) floor($diff / 3600);
    }

    public function isOverdue(?DateTimeImmutable $now = null): bool
    {
        if ($this->notifiedAt !== null) {
            return false;
        }

        return $this->hoursUntilDeadline($now) < 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $payload = json_decode((string) ($row['payload_json'] ?? '[]'), true);

        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            kind: IncidentKind::from((string) ($row['kind'] ?? IncidentKind::ActivelyExploitedVulnerability->value)),
            severity: Severity::from((string) ($row['severity'] ?? Severity::Medium->value)),
            status: IncidentStatus::from((string) ($row['status'] ?? IncidentStatus::Open->value)),
            title: (string) ($row['title'] ?? ''),
            summary: (string) ($row['summary'] ?? ''),
            affectedComponent: (string) ($row['affected_component'] ?? ''),
            affectedVersions: (string) ($row['affected_versions'] ?? ''),
            discoveredAt: new DateTimeImmutable((string) ($row['discovered_at'] ?? 'now')),
            deadlineAt: new DateTimeImmutable((string) ($row['deadline_at'] ?? 'now')),
            notifiedAt: ! empty($row['notified_at']) ? new DateTimeImmutable((string) $row['notified_at']) : null,
            resolvedAt: ! empty($row['resolved_at']) ? new DateTimeImmutable((string) $row['resolved_at']) : null,
            reporter: (string) ($row['reporter'] ?? ''),
            referenceId: (string) ($row['reference_id'] ?? ''),
            payload: is_array($payload) ? $payload : [],
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
        );
    }

    /**
     * Export the incident in a JSON shape aligned with ENISA's draft
     * Single Reporting Platform schema. The concrete schema is still in
     * flux; the shape here is pragmatic and forward-compatible.
     *
     * @return array<string, mixed>
     */
    public function toExportArray(): array
    {
        return [
            'schema' => 'polski.cra_incident',
            'schema_version' => 1,
            'reference_id' => $this->referenceId !== '' ? $this->referenceId : sprintf('POLSKI-CRA-%d', (int) $this->id),
            'kind' => $this->kind->value,
            'severity' => $this->severity->value,
            'status' => $this->status->value,
            'title' => $this->title,
            'summary' => $this->summary,
            'affected' => [
                'component' => $this->affectedComponent,
                'versions' => $this->affectedVersions,
            ],
            'timeline' => [
                'discovered_at' => $this->discoveredAt->format(DATE_ATOM),
                'deadline_at' => $this->deadlineAt->format(DATE_ATOM),
                'notified_at' => $this->notifiedAt?->format(DATE_ATOM),
                'resolved_at' => $this->resolvedAt?->format(DATE_ATOM),
            ],
            'reporter' => $this->reporter,
            'payload' => $this->payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDbRow(): array
    {
        return [
            'kind' => $this->kind->value,
            'severity' => $this->severity->value,
            'status' => $this->status->value,
            'title' => $this->title,
            'summary' => $this->summary,
            'affected_component' => $this->affectedComponent,
            'affected_versions' => $this->affectedVersions,
            'discovered_at' => $this->discoveredAt->format('Y-m-d H:i:s'),
            'deadline_at' => $this->deadlineAt->format('Y-m-d H:i:s'),
            'notified_at' => $this->notifiedAt?->format('Y-m-d H:i:s'),
            'resolved_at' => $this->resolvedAt?->format('Y-m-d H:i:s'),
            'reporter' => $this->reporter,
            'reference_id' => $this->referenceId,
            'payload_json' => wp_json_encode($this->payload),
        ];
    }
}
