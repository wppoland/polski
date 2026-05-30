<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * Reports unexpected withdrawal-flow failures to whatever the storefront
 * has plugged in - Sentry, Datadog, internal Slack hook, anything - by
 * firing a single canonical filter and action.
 *
 * The service is opt-in: a storefront subscribes to `polski/withdrawal/error`
 * (action) or filters `polski/withdrawal/error_report` to mutate the payload
 * before it goes upstream. Without subscribers this service is a no-op.
 *
 * Three failure paths are wired up:
 *
 *  - Magic-link e-mail dispatch (`wp_mail` returned false)
 *  - Withdrawal request persistence (repository insert returned 0)
 *  - PDF generation (Pro hook - fired from polski-pro's PDF service)
 *
 * Each report includes:
 *  - `code` (machine-readable, namespaced under `polski/withdrawal/`)
 *  - `message` (human-readable, English by convention for log aggregators)
 *  - `context` (associative array: order_id, declaration_id, channel, ip, …)
 *  - `timestamp` (ISO 8601 UTC)
 *  - `plugin_version`
 *  - `wp_version`
 *
 * Storefronts subscribe like this:
 *
 * ```php
 * add_action('polski/withdrawal/error', function (array $report): void {
 *     \Sentry\captureMessage($report['message'], \Sentry\Severity::error(), [
 *         'extra' => $report['context'],
 *         'tags' => ['module' => 'polski-withdrawal', 'code' => $report['code']],
 *     ]);
 * });
 * ```
 */
final class WithdrawalErrorTelemetry implements HasHooks
{
    public function registerHooks(): void
    {
        add_action('polski/withdrawal/mail_failed', [$this, 'reportMailFailure'], 10, 3);
        add_action('polski/withdrawal/persist_failed', [$this, 'reportPersistFailure'], 10, 2);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function reportMailFailure(string $email, string $orderNumber, array $context = []): void
    {
        $this->report(
            'polski/withdrawal/mail_failed',
            'Magic-link e-mail dispatch to the consumer failed.',
            array_merge([
                'email' => $this->maskEmail($email),
                'order_number' => $orderNumber,
            ], $context),
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function reportPersistFailure(int $orderId, array $context = []): void
    {
        $this->report(
            'polski/withdrawal/persist_failed',
            'Withdrawal request could not be written to the database.',
            array_merge(['order_id' => $orderId], $context),
        );
    }

    /**
     * Generic entry point - any module can call this directly for ad-hoc
     * reports. Pro's PDF service uses it for `polski/withdrawal/pdf_failed`.
     *
     * @param array<string, mixed> $context
     */
    public function report(string $code, string $message, array $context = []): void
    {
        $report = [
            'code' => $code,
            'message' => $message,
            'context' => $context,
            'timestamp' => gmdate('c'),
            'plugin_version' => defined('Polski\\VERSION') ? \Polski\VERSION : '0.0.0',
            'wp_version' => function_exists('get_bloginfo') ? (string) get_bloginfo('version') : 'unknown',
        ];

        /**
         * Filter the error report before it is fanned out to subscribers.
         * Use this to strip PII, add custom tags, or rewrite the message.
         *
         * @param array{
         *     code: string,
         *     message: string,
         *     context: array<string, mixed>,
         *     timestamp: string,
         *     plugin_version: string,
         *     wp_version: string,
         * } $report
         */
        $report = (array) apply_filters('polski/withdrawal/error_report', $report);

        /**
         * Dispatch the (potentially mutated) report to subscribers - Sentry,
         * Datadog, custom Slack hook, etc. No-op when nothing subscribes.
         *
         * @param array<string, mixed> $report
         */
        do_action('polski/withdrawal/error', $report);
    }

    /**
     * Replace local-part with three chars + "…" to keep logs PII-light while
     * still distinguishing reports per address (helpful for repro).
     */
    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return $email;
        }
        [$local, $domain] = explode('@', $email, 2);
        $masked = mb_substr($local, 0, 3) . '***';

        return $masked . '@' . $domain;
    }
}
