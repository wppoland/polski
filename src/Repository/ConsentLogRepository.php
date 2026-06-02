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
     * Record a Consent Manager banner decision: one row per category with the
     * granted/denied flag and the wording/version hash the visitor agreed to.
     *
     * @param array<string, bool> $categoryStates Map of category key => granted.
     */
    public function logCookieConsent(
        array $categoryStates,
        string $consentVersion,
        ?int $userId = null,
        ?string $sessionId = null,
    ): void {
        $ip = $this->getClientIp();
        $ua = $this->getUserAgent();
        $now = current_time('mysql', true);

        foreach ($categoryStates as $category => $granted) {
            $this->wpdb->insert(
                $this->tableName(),
                [
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'checkbox_id' => 'cookie_' . $category,
                    'context' => CheckboxContext::CookieBanner->value,
                    'consented' => $granted ? 1 : 0,
                    'ip_address' => $ip,
                    'user_agent' => $ua,
                    'consent_version' => $consentVersion,
                    'created_at' => $now,
                ],
                ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'],
            );
        }
    }

    /**
     * List Consent Manager banner records (newest first).
     *
     * @return list<ConsentRecord>
     */
    public function findCookieConsents(int $limit = 100, int $offset = 0): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE context = %s ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d',
                $this->tableName(),
                CheckboxContext::CookieBanner->value,
                $limit,
                max(0, $offset),
            ),
        );

        $list = is_array($rows) ? array_values($rows) : [];

        return array_map(
            static fn (\stdClass $row) => ConsentRecord::fromRow($row),
            $list,
        );
    }

    /**
     * Count Consent Manager banner records.
     */
    public function countCookieConsents(): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE context = %s',
                $this->tableName(),
                CheckboxContext::CookieBanner->value,
            ),
        );
    }

    /**
     * Find consent records for a specific user.
     *
     * @return list<ConsentRecord>
     */
    public function findByUser(int $userId, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d',
                $this->tableName(),
                $userId,
                $limit,
                max(0, $offset),
            ),
        );

        $list = is_array($rows) ? $rows : [];

        return array_map(
            static fn (\stdClass $row) => ConsentRecord::fromRow($row),
            $list,
        );
    }

    /**
     * Find consent records for a specific order (by session or user).
     *
     * @return list<ConsentRecord>
     */
    public function findBySession(string $sessionId): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE session_id = %s ORDER BY created_at ASC',
                $this->tableName(),
                $sessionId,
            ),
        );

        $list = is_array($rows) ? $rows : [];

        return array_map(
            static fn (\stdClass $row) => ConsentRecord::fromRow($row),
            $list,
        );
    }

    /**
     * Get consent statistics for the dashboard.
     *
     * @return array<string, mixed>
     */
    public function getStats(int $days = 30): array
    {
        global $wpdb;

        $table = $this->tableName();
        $sinceTs = strtotime("-{$days} days");
        $since = gmdate('Y-m-d H:i:s', $sinceTs !== false ? $sinceTs : time());

        // Total consent records.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $totalRecords = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE created_at >= %s',
                $table,
                $since,
            ),
        );

        // Consented vs declined.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $consentedCount = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE consented = 1 AND created_at >= %s',
                $table,
                $since,
            ),
        );

        // Consents per checkbox.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $perCheckbox = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT checkbox_id,
                        SUM(consented = 1) AS accepted,
                        SUM(consented = 0) AS declined,
                        COUNT(*) AS total
                 FROM %i
                 WHERE created_at >= %s
                 GROUP BY checkbox_id
                 ORDER BY total DESC',
                $table,
                $since,
            ),
        );

        // Daily consent trend (last N days).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $dailyTrend = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT DATE(created_at) AS date,
                        COUNT(*) AS total,
                        SUM(consented = 1) AS accepted
                 FROM %i
                 WHERE created_at >= %s
                 GROUP BY DATE(created_at)
                 ORDER BY date ASC',
                $table,
                $since,
            ),
        );

        // Consents by context.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, prepared statement below.
        $byContext = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT context, COUNT(*) AS total, SUM(consented = 1) AS accepted
                 FROM %i
                 WHERE created_at >= %s
                 GROUP BY context',
                $table,
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
            ], is_array($perCheckbox) ? $perCheckbox : []),
            'daily_trend' => array_map(static fn (object $row) => [
                'date' => $row->date,
                'total' => (int) $row->total,
                'accepted' => (int) $row->accepted,
            ], is_array($dailyTrend) ? $dailyTrend : []),
            'by_context' => array_map(static fn (object $row) => [
                'context' => $row->context,
                'total' => (int) $row->total,
                'accepted' => (int) $row->accepted,
            ], is_array($byContext) ? $byContext : []),
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
        $ip = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field((string) wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';

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
        $ua = isset($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field((string) wp_unslash($_SERVER['HTTP_USER_AGENT']))
            : '';
        return $ua !== '' ? mb_substr($ua, 0, 500) : null;
    }
}
