<?php

declare(strict_types=1);

namespace Polski\CRA\Enum;

defined('ABSPATH') || exit;

enum IncidentKind: string
{
    case ActivelyExploitedVulnerability = 'vulnerability';
    case SecurityIncident = 'incident';
    case NearMiss = 'near_miss';

    public function label(): string
    {
        return match ($this) {
            self::ActivelyExploitedVulnerability => __('Actively exploited vulnerability', 'polski'),
            self::SecurityIncident => __('Security incident', 'polski'),
            self::NearMiss => __('Near miss / weakness', 'polski'),
        };
    }

    /**
     * CRA Art. 14 defines differentiated reporting deadlines.
     * Reporting clock for actively-exploited vulnerabilities: 24h early warning,
     * 72h notification, 14 days final report. This enum returns the early
     * warning window used for the default deadline.
     */
    public function earlyWarningHours(): int
    {
        return match ($this) {
            self::ActivelyExploitedVulnerability => 24,
            self::SecurityIncident => 24,
            self::NearMiss => 72,
        };
    }
}
