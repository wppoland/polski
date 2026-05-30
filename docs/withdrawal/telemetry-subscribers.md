# Telemetry subscribers - copy-pasteable snippets

The withdrawal module fires a single canonical action
`polski/withdrawal/error` carrying a normalised report payload. Drop any
of the snippets below into a `mu-plugin` or a small site-specific plugin
to forward those events to your stack of choice. They all run
out-of-process from the customer flow - a failing subscriber will not
break the consumer's submission.

The payload shape is:

```php
[
    'code'           => 'polski/withdrawal/mail_failed',  // or persist_failed, pdf_failed, …
    'message'        => 'Magic-link e-mail dispatch to the consumer failed.',
    'context'        => ['email' => 'cus***@example.test', 'order_id' => 123, ...],
    'timestamp'      => '2026-05-19T12:34:56+00:00',
    'plugin_version' => '1.16.0',
    'wp_version'     => '6.9.4',
]
```

## Slack

Create an incoming webhook at <https://api.slack.com/apps>, paste the
URL below.

```php
<?php
add_action('polski/withdrawal/error', static function (array $report): void {
    $webhook = 'https://hooks.slack.com/services/REPLACE_WITH_YOUR_WEBHOOK_PATH';

    $contextLines = [];
    foreach ($report['context'] ?? [] as $k => $v) {
        $contextLines[] = '*' . $k . ':* `' . (is_scalar($v) ? (string) $v : wp_json_encode($v)) . '`';
    }

    $payload = [
        'attachments' => [[
            'color' => $report['code'] === 'polski/withdrawal/persist_failed' ? '#e11d48' : '#f59e0b',
            'title' => $report['message'],
            'text' => '`' . $report['code'] . '` · plugin ' . $report['plugin_version'] . ' · WP ' . $report['wp_version']
                . ($contextLines === [] ? '' : "\n" . implode("\n", $contextLines)),
            'ts' => strtotime($report['timestamp']),
        ]],
    ];

    wp_remote_post($webhook, [
        'timeout' => 3,
        'blocking' => false,
        'body' => wp_json_encode($payload),
        'headers' => ['Content-Type' => 'application/json'],
    ]);
}, 10, 1);
```

## Discord

```php
<?php
add_action('polski/withdrawal/error', static function (array $report): void {
    $webhook = 'https://discord.com/api/webhooks/0000000000/XXXXXX';

    $description = '```' . $report['code'] . "```\n" . $report['message'];
    foreach ($report['context'] ?? [] as $k => $v) {
        $description .= "\n**" . $k . ':** `' . (is_scalar($v) ? (string) $v : wp_json_encode($v)) . '`';
    }

    wp_remote_post($webhook, [
        'timeout' => 3,
        'blocking' => false,
        'body' => wp_json_encode([
            'username' => 'Polski Withdrawal',
            'embeds' => [[
                'title' => '[' . $report['plugin_version'] . '] withdrawal alert',
                'description' => $description,
                'color' => 15158332,
            ]],
        ]),
        'headers' => ['Content-Type' => 'application/json'],
    ]);
}, 10, 1);
```

## Sentry (sentry-sdk-for-php)

```php
<?php
add_action('polski/withdrawal/error', static function (array $report): void {
    if (! class_exists(\Sentry\State\Hub::class)) {
        return;
    }

    \Sentry\withScope(static function (\Sentry\State\Scope $scope) use ($report): void {
        $scope->setTags([
            'module' => 'polski-withdrawal',
            'code' => $report['code'],
            'plugin_version' => $report['plugin_version'],
            'wp_version' => $report['wp_version'],
        ]);
        $scope->setExtras($report['context'] ?? []);
        \Sentry\captureMessage($report['message'], \Sentry\Severity::error());
    });
}, 10, 1);
```

## Datadog Logs

```php
<?php
add_action('polski/withdrawal/error', static function (array $report): void {
    $apiKey = getenv('DATADOG_API_KEY');
    if (! is_string($apiKey) || $apiKey === '') {
        return;
    }

    wp_remote_post('https://http-intake.logs.datadoghq.eu/api/v2/logs', [
        'timeout' => 3,
        'blocking' => false,
        'body' => wp_json_encode([[
            'ddsource' => 'polski-withdrawal',
            'service' => 'polski',
            'ddtags' => 'code:' . $report['code'] . ',plugin_version:' . $report['plugin_version'],
            'hostname' => parse_url(home_url(), PHP_URL_HOST),
            'message' => $report['message'],
            'context' => $report['context'] ?? [],
        ]]),
        'headers' => [
            'Content-Type' => 'application/json',
            'DD-API-KEY' => $apiKey,
        ],
    ]);
}, 10, 1);
```

## E-mail (no third-party service)

```php
<?php
add_action('polski/withdrawal/error', static function (array $report): void {
    $body  = $report['message'] . "\n\n";
    $body .= 'Code: ' . $report['code'] . "\n";
    $body .= 'Time: ' . $report['timestamp'] . "\n";
    $body .= 'Plugin: ' . $report['plugin_version'] . "\n";
    $body .= 'WordPress: ' . $report['wp_version'] . "\n\n";
    foreach ($report['context'] ?? [] as $k => $v) {
        $body .= $k . ': ' . (is_scalar($v) ? (string) $v : wp_json_encode($v)) . "\n";
    }

    wp_mail(
        (string) get_option('admin_email'),
        '[Polski] withdrawal alert: ' . $report['code'],
        $body,
    );
}, 10, 1);
```

## PII reduction

The plugin already masks e-mail addresses in error payloads (first 3
characters + `***@domain`). If you ship to a third-party log aggregator
and need stronger guarantees, filter the payload before it leaves:

```php
<?php
add_filter('polski/withdrawal/error_report', static function (array $report): array {
    // Drop the masked e-mail entirely, keep order_id + flow tag.
    if (isset($report['context']['email'])) {
        unset($report['context']['email']);
    }
    return $report;
});
```

Filters always run before the action - your subscriber sees the
already-redacted payload. Multiple filters and subscribers can coexist.
