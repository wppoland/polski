<?php

declare(strict_types=1);
namespace Polski\Repository;

defined('ABSPATH') || exit;

use Polski\Enum\CheckboxContext;
use Polski\Model\ConsentRecord;
use wpdb;

/**
 * Data access for the consent log table (GDPR audit trail).
 */
final class ConsentLogRepository
{
    public function __construct(
        private readonly wpdb $wpdb,
    ) {
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . 'polski_consent_log';
    }

    /**
     * Log a consent action (checkbox accepted/declined).
     */
    public function log(
        string $checkboxId,
        CheckboxContext $context,
        bool $consented,
        ?int $userId = null,
        ?string $sessionId = null,
    ): int {
        $this->wpdb->insert(
            $this->tableName(),
            [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'checkbox_id' => $checkboxId,
                'context' => $context->value,
                'consented' => $consented ? 1 : 0,
                'ip_address' => $this->getClientIp(),
                'user_agent' => $this->getUserAgent(),
                'created_at' => current_time('mysql', true),
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s'],
        );

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Log multiple checkbox consents at once (e.g., all checkout checkboxes).
     *
     * @param array<string, bool> $checkboxStates Map of checkbox_id => consented.
     */
    public function logBatch(
        array $checkboxStates,
        CheckboxContext $context,
        ?int $userId = null,
        ?string $sessionId = null,
    ): void {
        $ip = $this->getClientIp();
        $ua = $this->getUserAgent();
        $now = current_time('mysql', true);

        foreach ($checkboxStates as $checkboxId => $consented) {
            $this->wpdb->insert(
                $this->tableName(),
                [
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'checkbox_id' => $checkboxId,
                    'context' => $context->value,
                    'consented' => $consented ? 1 : 0,
                    'ip_address' => $ip,
                    'user_agent' => $ua,
                    'created_at' => $now,
                ],
                ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s'],
            );
        }
    }

    /**
     * Find consent records for a specific user.
     *
     * @return list<ConsentRecord>
     */
    public function findByUser(int $userId, int $limit = 50): array
    {
        $table = $this->tableName();

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
                $userId,
                $limit,
            ),
        );

        return array_map(
            static fn (object $row) => ConsentRecord::fromRow($row),
            $rows,
        );
    }

    /**
     * Find consent records for a specific order (by session or user).
     *
     * @return list<ConsentRecord>
     */
    public function findBySession(string $sessionId): array
    {
        $table = $this->tableName();

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE session_id = %s ORDER BY created_at ASC",
                $sessionId,
            ),
        );

        return array_map(
            static fn (object $row) => ConsentRecord::fromRow($row),
            $rows,
        );
    }

    /**
     * Get consent statistics for the dashboard.
     *
     * @return array<string, mixed>
     */
    public function getStats(int $days = 30): array
    {
        $table = $this->tableName();
        $since = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Total consent records.
        $totalRecords = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
                $since,
            ),
        );

        // Consented vs declined.
        $consentedCount = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE consented = 1 AND created_at >= %s",
                $since,
            ),
        );

        // Consents per checkbox.
        $perCheckbox = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT checkbox_id,
                        SUM(consented = 1) AS accepted,
                        SUM(consented = 0) AS declined,
                        COUNT(*) AS total
                 FROM {$table}
                 WHERE created_at >= %s
                 GROUP BY checkbox_id
                 ORDER BY total DESC",
                $since,
            ),
        );

        // Daily consent trend (last N days).
        $dailyTrend = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT DATE(created_at) AS date,
                        COUNT(*) AS total,
                        SUM(consented = 1) AS accepted
                 FROM {$table}
                 WHERE created_at >= %s
                 GROUP BY DATE(created_at)
                 ORDER BY date ASC",
                $since,
            ),
        );

        // Consents by context.
        $byContext = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT context, COUNT(*) AS total, SUM(consented = 1) AS accepted
                 FROM {$table}
                 WHERE created_at >= %s
                 GROUP BY context",
                $since,
            ),
        );

        return [
            'period_days' => $days,
            'total_records' => $totalRecords,
            'consented' => $consentedCount,
            'declined' => $totalRecords - $consentedCount,
            'consent_rate' => $totalRecords > 0 ? round(($consentedCount / $totalRecords) * 100, 1) : 0,
            'per_checkbox' => array_map(static fn (object $row) => [
                'checkbox_id' => $row->checkbox_id,
                'accepted' => (int) $row->accepted,
                'declined' => (int) $row->declined,
                'total' => (int) $row->total,
                'rate' => (int) $row->total > 0 ? round(((int) $row->accepted / (int) $row->total) * 100, 1) : 0,
            ], $perCheckbox),
            'daily_trend' => array_map(static fn (object $row) => [
                'date' => $row->date,
                'total' => (int) $row->total,
                'accepted' => (int) $row->accepted,
            ], $dailyTrend),
            'by_context' => array_map(static fn (object $row) => [
                'context' => $row->context,
                'total' => (int) $row->total,
                'accepted' => (int) $row->accepted,
            ], $byContext),
        ];
    }

    /**
     * Delete all consent records for a user (GDPR data erasure).
     */
    public function deleteByUser(int $userId): int
    {
        return (int) $this->wpdb->delete(
            $this->tableName(),
            ['user_id' => $userId],
            ['%d'],
        );
    }

    /**
     * Get anonymized client IP (GDPR compliant - last octet zeroed).
     */
    private function getClientIp(): ?string
    {
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));

        if ($ip === '') {
            return null;
        }

        // Anonymize: zero last octet for IPv4, last 80 bits for IPv6.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return preg_replace('/:[^:]+:[^:]+:[^:]+:[^:]+:[^:]+$/', ':0:0:0:0:0', $ip);
        }

        return null;
    }

    private function getUserAgent(): ?string
    {
        $ua = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? ''));
        return $ua !== '' ? mb_substr($ua, 0, 500) : null;
    }
}
