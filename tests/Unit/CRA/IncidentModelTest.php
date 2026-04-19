<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\CRA;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Polski\CRA\Enum\IncidentKind;
use Polski\CRA\Enum\IncidentStatus;
use Polski\CRA\Enum\Severity;
use Polski\CRA\Model\Incident;

final class IncidentModelTest extends TestCase
{
    public function testEarlyWarningHoursDefaultTo24ForActiveExploit(): void
    {
        $this->assertSame(24, IncidentKind::ActivelyExploitedVulnerability->earlyWarningHours());
        $this->assertSame(72, IncidentKind::NearMiss->earlyWarningHours());
    }

    public function testHoursUntilDeadlineNegativeWhenOverdue(): void
    {
        $incident = $this->buildIncident(
            discoveredAt: new DateTimeImmutable('-48 hours'),
            deadlineAt: new DateTimeImmutable('-24 hours'),
            notifiedAt: null,
        );

        $this->assertTrue($incident->isOverdue(new DateTimeImmutable('now')));
        $this->assertLessThan(0, $incident->hoursUntilDeadline(new DateTimeImmutable('now')));
    }

    public function testNotNowOverdueWhenAlreadyNotified(): void
    {
        $incident = $this->buildIncident(
            discoveredAt: new DateTimeImmutable('-48 hours'),
            deadlineAt: new DateTimeImmutable('-24 hours'),
            notifiedAt: new DateTimeImmutable('-23 hours'),
        );

        $this->assertFalse($incident->isOverdue(new DateTimeImmutable('now')));
    }

    public function testExportArrayShape(): void
    {
        $incident = $this->buildIncident();

        $export = $incident->toExportArray();

        $this->assertSame('polski.cra_incident', $export['schema']);
        $this->assertSame(1, $export['schema_version']);
        $this->assertArrayHasKey('reference_id', $export);
        $this->assertArrayHasKey('timeline', $export);
        $this->assertArrayHasKey('affected', $export);
        $this->assertSame('Sample vuln', $export['title']);
        $this->assertSame('high', $export['severity']);
    }

    public function testFromRowReconstructsIncident(): void
    {
        $row = [
            'id' => '7',
            'kind' => 'vulnerability',
            'severity' => 'critical',
            'status' => 'open',
            'title' => 'CVE-2026-1234',
            'summary' => 'Auth bypass',
            'affected_component' => 'plugin',
            'affected_versions' => '<= 1.2.3',
            'discovered_at' => '2026-04-19 10:00:00',
            'deadline_at' => '2026-04-20 10:00:00',
            'notified_at' => null,
            'resolved_at' => null,
            'reporter' => 'security@example.com',
            'reference_id' => 'EXT-42',
            'payload_json' => wp_json_encode(['cve' => 'CVE-2026-1234']),
            'created_at' => '2026-04-19 10:00:00',
            'updated_at' => '2026-04-19 10:00:00',
        ];

        $incident = Incident::fromRow($row);

        $this->assertSame(7, $incident->id);
        $this->assertSame(Severity::Critical, $incident->severity);
        $this->assertSame(IncidentKind::ActivelyExploitedVulnerability, $incident->kind);
        $this->assertSame(IncidentStatus::Open, $incident->status);
        $this->assertSame(['cve' => 'CVE-2026-1234'], $incident->payload);
    }

    public function testToDbRowIsRoundTrippable(): void
    {
        $incident = $this->buildIncident();

        $row = $incident->toDbRow();
        $row['id'] = '3';
        $row['created_at'] = '2026-04-19 10:00:00';
        $row['updated_at'] = '2026-04-19 10:00:00';

        $back = Incident::fromRow($row);

        $this->assertSame($incident->title, $back->title);
        $this->assertSame($incident->severity, $back->severity);
        $this->assertSame($incident->kind, $back->kind);
        $this->assertSame($incident->status, $back->status);
    }

    private function buildIncident(
        ?DateTimeImmutable $discoveredAt = null,
        ?DateTimeImmutable $deadlineAt = null,
        ?DateTimeImmutable $notifiedAt = null,
    ): Incident {
        $discoveredAt = $discoveredAt ?? new DateTimeImmutable('-1 hour');
        $deadlineAt = $deadlineAt ?? $discoveredAt->modify('+24 hours');

        return new Incident(
            id: 1,
            kind: IncidentKind::ActivelyExploitedVulnerability,
            severity: Severity::High,
            status: IncidentStatus::Open,
            title: 'Sample vuln',
            summary: 'Short description of the vulnerability.',
            affectedComponent: 'polski',
            affectedVersions: '>=1.6.0 <=1.7.1',
            discoveredAt: $discoveredAt,
            deadlineAt: $deadlineAt,
            notifiedAt: $notifiedAt,
            resolvedAt: null,
            reporter: 'security@example.com',
            referenceId: 'CVE-TEST',
            payload: ['note' => 'redacted'],
        );
    }
}
