# Analytics subscribers — withdrawal funnel

The withdrawal module fires a single canonical analytics action
`polski/withdrawal/event` with a normalised payload covering every
funnel milestone (filed → confirmed → completed / rejected). Plug it
into your analytics stack with one of the snippets below; pick the
"event_payload" filter if you need to inject store-specific dimensions
before the event leaves PHP.

Payload shape:

```php
[
    'event'          => 'filed' | 'confirmed' | 'completed' | 'rejected',
    'declaration_id' => 'POL-WD-000017',
    'numeric_id'     => 17,
    'order_id'       => 123,
    'order_total'    => 240.00,
    'currency'       => 'PLN',
    'channel'        => 'online' | 'guest' | 'phone' | 'email' | 'letter' | 'in_store',
    'refund_amount'  => 75.0 | null,
    'timestamp'      => '2026-05-19T18:42:00+00:00',
]
```

## Google Analytics 4 (Measurement Protocol)

```php
<?php
add_action('polski/withdrawal/event', static function (array $payload): void {
    $measurementId = getenv('GA4_MEASUREMENT_ID');
    $apiSecret = getenv('GA4_API_SECRET');
    if (! $measurementId || ! $apiSecret) {
        return;
    }

    wp_remote_post(
        sprintf('https://www.google-analytics.com/mp/collect?measurement_id=%s&api_secret=%s', $measurementId, $apiSecret),
        [
            'timeout' => 3,
            'blocking' => false,
            'body' => wp_json_encode([
                'client_id' => 'polski-' . $payload['order_id'],
                'events' => [[
                    'name' => 'withdrawal_' . $payload['event'],
                    'params' => [
                        'currency' => $payload['currency'],
                        'value' => $payload['refund_amount'] ?? $payload['order_total'],
                        'transaction_id' => $payload['declaration_id'],
                        'channel' => $payload['channel'],
                    ],
                ]],
            ]),
            'headers' => ['Content-Type' => 'application/json'],
        ],
    );
}, 10, 1);
```

## Matomo (tracker API)

```php
<?php
add_action('polski/withdrawal/event', static function (array $payload): void {
    $url = (string) get_option('polski_matomo_url', '');
    $siteId = (int) get_option('polski_matomo_site_id', 0);
    if ($url === '' || $siteId <= 0) {
        return;
    }

    wp_remote_post(rtrim($url, '/') . '/matomo.php', [
        'timeout' => 3,
        'blocking' => false,
        'body' => [
            'idsite' => $siteId,
            'rec' => 1,
            'e_c' => 'withdrawal',
            'e_a' => $payload['event'],
            'e_n' => $payload['declaration_id'],
            'e_v' => $payload['refund_amount'] ?? $payload['order_total'],
            'cvar' => wp_json_encode([
                '1' => ['channel', $payload['channel']],
                '2' => ['currency', $payload['currency']],
            ]),
        ],
    ]);
}, 10, 1);
```

## Mixpanel

```php
<?php
add_action('polski/withdrawal/event', static function (array $payload): void {
    $token = (string) get_option('polski_mixpanel_token', '');
    if ($token === '') {
        return;
    }

    $event = [
        'event' => 'withdrawal_' . $payload['event'],
        'properties' => array_merge(
            ['token' => $token, 'time' => time(), 'distinct_id' => 'order-' . $payload['order_id']],
            $payload,
        ),
    ];

    wp_remote_post('https://api-eu.mixpanel.com/track', [
        'timeout' => 3,
        'blocking' => false,
        'body' => ['data' => base64_encode(wp_json_encode([$event]))],
    ]);
}, 10, 1);
```

## Frontend gtag (browser side)

If you collect analytics client-side instead of server-side, expose the
event payload through `wp_localize_script` and fire `gtag('event', …)`
from JS. Sample wrapper:

```php
<?php
// Server side: push events as data attributes onto a body class so JS picks them up.
add_action('polski/withdrawal/event', static function (array $payload): void {
    set_transient(
        'polski_analytics_event_' . get_current_user_id(),
        $payload,
        MINUTE_IN_SECONDS,
    );
}, 10, 1);

add_action('wp_footer', static function (): void {
    $payload = get_transient('polski_analytics_event_' . get_current_user_id());
    if (! is_array($payload)) {
        return;
    }
    delete_transient('polski_analytics_event_' . get_current_user_id());
    ?>
    <script>
        if (typeof gtag === 'function') {
            gtag('event', 'withdrawal_<?php echo esc_js($payload['event']); ?>', {
                currency: <?php echo wp_json_encode($payload['currency']); ?>,
                value: <?php echo (float) ($payload['refund_amount'] ?? $payload['order_total']); ?>,
                transaction_id: <?php echo wp_json_encode($payload['declaration_id']); ?>,
                channel: <?php echo wp_json_encode($payload['channel']); ?>
            });
        }
    </script>
    <?php
});
```

## Injecting store-specific dimensions

Run a filter before the event fans out:

```php
<?php
add_filter('polski/withdrawal/event_payload', static function (array $payload, string $event, $request): array {
    $payload['store_segment'] = get_option('polski_store_segment', 'b2c');
    $payload['locale'] = get_locale();
    return $payload;
}, 10, 3);
```

Subscribers see the augmented payload — no need to repeat the lookup.
