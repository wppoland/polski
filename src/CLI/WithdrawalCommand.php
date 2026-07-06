<?php

declare(strict_types=1);
namespace Polski\CLI;

defined('ABSPATH') || exit;

use Polski\Enum\WithdrawalStatus;
use Polski\Repository\WithdrawalRepository;
use Polski\Service\WithdrawalService;

/**
 * WP-CLI commands for the consumer right of withdrawal flow.
 *
 * ## EXAMPLES
 *
 *     wp polski withdrawal list
 *     wp polski withdrawal list --status=requested --format=json
 *     wp polski withdrawal show 17
 *     wp polski withdrawal confirm 17
 *     wp polski withdrawal reject 17 --reason="Too late"
 *     wp polski withdrawal complete 17
 *     wp polski withdrawal export-csv --status=completed > export.csv
 */
class WithdrawalCommand
{
    /**
     * List withdrawal requests.
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : Filter by status. Accepts: requested, confirmed, completed, rejected.
     *
     * [--limit=<limit>]
     * : Maximum number of rows. Default: 50.
     *
     * [--offset=<offset>]
     * : Pagination offset. Default: 0.
     *
     * [--format=<format>]
     * : Output format. Accepts: table, csv, json, count, ids.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - count
     *   - ids
     * ---
     *
     * ## EXAMPLES
     *
     *     wp polski withdrawal list
     *     wp polski withdrawal list --status=requested --limit=200
     *     wp polski withdrawal list --format=ids
     *
     * @param list<string>         $args
     * @param array<string, mixed> $assocArgs
     */
    public function list(array $args, array $assocArgs): void
    {
        $status = isset($assocArgs['status'])
            ? WithdrawalStatus::tryFrom((string) $assocArgs['status'])
            : null;

        $limit = isset($assocArgs['limit']) ? max(1, (int) $assocArgs['limit']) : 50;
        $offset = isset($assocArgs['offset']) ? max(0, (int) $assocArgs['offset']) : 0;
        $format = (string) ($assocArgs['format'] ?? 'table');

        $rows = $this->repository()->findAll($limit, $offset, $status);

        if ($format === 'count') {
            \WP_CLI::log((string) count($rows));
            return;
        }

        if ($format === 'ids') {
            \WP_CLI::log(implode(' ', array_map(static fn ($r) => (string) $r->id, $rows)));
            return;
        }

        $serialised = array_map(
            static fn ($r) => [
                'id' => $r->id,
                'order_id' => $r->orderId,
                'status' => $r->status->value,
                'channel' => $r->channel,
                'guest_email' => $r->guestEmail ?? '',
                'requested_at' => $r->requestedAt->format('Y-m-d H:i'),
                'reason' => mb_substr((string) ($r->reason ?? ''), 0, 60),
            ],
            $rows,
        );

        \WP_CLI\Utils\format_items(
            $format,
            $serialised,
            ['id', 'order_id', 'status', 'channel', 'guest_email', 'requested_at', 'reason'],
        );
    }

    /**
     * Show one withdrawal request in detail.
     *
     * ## OPTIONS
     *
     * <id>
     * : Declaration id.
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     wp polski withdrawal show 17
     *     wp polski withdrawal show 17 --format=json
     *
     * @param list<string>         $args
     * @param array<string, mixed> $assocArgs
     */
    public function show(array $args, array $assocArgs): void
    {
        $id = (int) ($args[0] ?? 0);
        if ($id <= 0) {
            \WP_CLI::error('Provide a numeric declaration id.');
        }

        $request = $this->repository()->findById($id);
        if ($request === null) {
            \WP_CLI::error(sprintf('Withdrawal #%d not found.', $id));
        }

        $row = $request->toArray();
        $format = (string) ($assocArgs['format'] ?? 'table');

        if ($format === 'json') {
            \WP_CLI::log((string) wp_json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return;
        }

        $flat = [];
        foreach ($row as $key => $value) {
            $flat[] = [
                'field' => $key,
                'value' => is_scalar($value) || $value === null
                    ? (string) ($value ?? '')
                    : (string) wp_json_encode($value),
            ];
        }
        \WP_CLI\Utils\format_items('table', $flat, ['field', 'value']);
    }

    /**
     * Confirm a withdrawal request.
     *
     * ## OPTIONS
     *
     * <id>
     * : Declaration id.
     *
     * ## EXAMPLES
     *
     *     wp polski withdrawal confirm 17
     *
     * @param list<string>         $args
     * @param array<string, mixed> $assocArgs
     */
    public function confirm(array $args, array $assocArgs): void
    {
        unset($assocArgs);
        $id = (int) ($args[0] ?? 0);
        if ($id <= 0) {
            \WP_CLI::error('Provide a numeric declaration id.');
        }

        if ($this->withdrawalService()->confirm($id)) {
            \WP_CLI::success(sprintf('Withdrawal #%d confirmed.', $id));
            return;
        }

        \WP_CLI::error(sprintf('Could not confirm withdrawal #%d (not found or wrong status).', $id));
    }

    /**
     * Reject a withdrawal request.
     *
     * ## OPTIONS
     *
     * <id>
     * : Declaration id.
     *
     * [--reason=<reason>]
     * : Free-text rejection reason persisted to the audit trail.
     *
     * ## EXAMPLES
     *
     *     wp polski withdrawal reject 17
     *     wp polski withdrawal reject 17 --reason="Past 14-day window"
     *
     * @param list<string>         $args
     * @param array<string, mixed> $assocArgs
     */
    public function reject(array $args, array $assocArgs): void
    {
        $id = (int) ($args[0] ?? 0);
        if ($id <= 0) {
            \WP_CLI::error('Provide a numeric declaration id.');
        }

        $reason = isset($assocArgs['reason']) ? (string) $assocArgs['reason'] : null;

        if ($this->withdrawalService()->reject($id, $reason)) {
            \WP_CLI::success(sprintf('Withdrawal #%d rejected.', $id));
            return;
        }

        \WP_CLI::error(sprintf('Could not reject withdrawal #%d.', $id));
    }

    /**
     * Mark a confirmed withdrawal as completed.
     *
     * ## OPTIONS
     *
     * <id>
     * : Declaration id.
     *
     * ## EXAMPLES
     *
     *     wp polski withdrawal complete 17
     *
     * @param list<string>         $args
     * @param array<string, mixed> $assocArgs
     */
    public function complete(array $args, array $assocArgs): void
    {
        unset($assocArgs);
        $id = (int) ($args[0] ?? 0);
        if ($id <= 0) {
            \WP_CLI::error('Provide a numeric declaration id.');
        }

        if ($this->withdrawalService()->complete($id)) {
            \WP_CLI::success(sprintf('Withdrawal #%d completed.', $id));
            return;
        }

        \WP_CLI::error(sprintf('Could not complete withdrawal #%d (not in confirmed state?).', $id));
    }

    /**
     * Stream withdrawal records as CSV to stdout.
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : Filter by status (requested / confirmed / completed / rejected).
     *
     * [--limit=<limit>]
     * : Maximum rows. Default: all (10000 cap as safety).
     *
     * ## EXAMPLES
     *
     *     wp polski withdrawal export-csv > export.csv
     *     wp polski withdrawal export-csv --status=completed --limit=500
     *
     * @subcommand export-csv
     *
     * @param list<string>         $args
     * @param array<string, mixed> $assocArgs
     */
    public function exportCsv(array $args, array $assocArgs): void
    {
        unset($args);
        $status = isset($assocArgs['status'])
            ? WithdrawalStatus::tryFrom((string) $assocArgs['status'])
            : null;
        $limit = isset($assocArgs['limit']) ? max(1, (int) $assocArgs['limit']) : 10000;

        $rows = $this->repository()->findAll($limit, 0, $status);

        $out = fopen('php://stdout', 'wb');
        if ($out === false) {
            \WP_CLI::error('Could not open stdout for CSV write.');
        }

        fputcsv($out, [
            'id', 'order_id', 'customer_id', 'channel', 'status', 'guest_email',
            'reason', 'requested_at', 'confirmed_at', 'completed_at', 'rejected_at',
            'refund_id', 'refund_amount', 'language_code',
        ]);

        foreach ($rows as $r) {
            fputcsv($out, [
                (string) $r->id,
                (string) $r->orderId,
                (string) ($r->customerId ?? ''),
                $r->channel,
                $r->status->value,
                (string) ($r->guestEmail ?? ''),
                (string) ($r->reason ?? ''),
                $r->requestedAt->format('c'),
                $r->confirmedAt?->format('c') ?? '',
                $r->completedAt?->format('c') ?? '',
                $r->rejectedAt?->format('c') ?? '',
                (string) ($r->refundId ?? ''),
                (string) ($r->refundAmount ?? ''),
                $r->languageCode,
            ]);
        }

        fclose($out); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- streaming CSV to a CLI output stream, WP_Filesystem does not apply
    }

    /**
     * Register the WP-CLI command under `wp polski withdrawal`.
     */
    public static function register(): void
    {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('polski withdrawal', self::class);
        }
    }

    private function repository(): WithdrawalRepository
    {
        return \Polski\Plugin::instance()->container()->get(WithdrawalRepository::class);
    }

    private function withdrawalService(): WithdrawalService
    {
        return \Polski\Plugin::instance()->container()->get(WithdrawalService::class);
    }
}
