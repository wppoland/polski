# Withdrawal REST API

Every withdrawal operation is reachable through a REST endpoint or through
the WP 6.9+ Abilities API (whichever your client prefers). This document
focuses on REST - for the Abilities catalog see [abilities.md](abilities.md).

All examples assume:

```bash
export SITE=https://example.test
export NONCE="$(curl -s -b cookies.txt $SITE/?rest_route=/wp/v2/users/me \
    -H 'Content-Type: application/json' \
    --data '{"_locale":"user"}' | jq -r '.meta.rest_nonce')"
```

For machine-to-machine usage outside the browser, prefer
[Application Passwords](https://developer.wordpress.org/rest-api/reference/authentication/#application-passwords)
over cookie+nonce.

---

## FREE plugin endpoints

Base namespace: `polski/v1`.

### `GET /polski/v1/withdrawals`

List withdrawal requests. Admin only (`manage_woocommerce`).

```bash
curl -X GET \
    -H "X-WP-Nonce: $NONCE" \
    -b cookies.txt \
    "$SITE/wp-json/polski/v1/withdrawals?status=requested&per_page=20&page=1"
```

Response:

```json
[
    {
        "id": 17,
        "order_id": 123,
        "customer_id": 9,
        "status": "requested",
        "status_label": "Submitted",
        "reason": "Niewłaściwy rozmiar",
        "items": [{ "product_id": 88, "quantity": 1 }],
        "guest_email": null,
        "channel": "online",
        "requested_at": "2026-05-17T18:42:00+00:00",
        "confirmed_at": null,
        "completed_at": null,
        "rejected_at": null
    }
]
```

Query parameters:

| Param | Type | Default | Notes |
|---|---|---|---|
| `status` | string | - | `requested` / `confirmed` / `completed` / `rejected` |
| `per_page` | int | 20 | 1-100 |
| `page` | int | 1 | |

### `POST /polski/v1/withdrawals`

Create a withdrawal request for an order owned by the authenticated user.

```bash
curl -X POST \
    -H "X-WP-Nonce: $NONCE" \
    -H "Content-Type: application/json" \
    -b cookies.txt \
    -d '{"order_id": 123, "reason": "Niewłaściwy rozmiar"}' \
    "$SITE/wp-json/polski/v1/withdrawals"
```

Body shape:

| Field | Type | Required | Notes |
|---|---|---|---|
| `order_id` | int | yes | Must belong to the calling user |
| `reason` | string | no | Free text, sanitized server-side |

### `PUT /polski/v1/withdrawals/{id}`

Change the status of a withdrawal request. Admin only.

```bash
curl -X PUT \
    -H "X-WP-Nonce: $NONCE" \
    -H "Content-Type: application/json" \
    -b cookies.txt \
    -d '{"status": "confirmed"}' \
    "$SITE/wp-json/polski/v1/withdrawals/17"
```

Allowed status transitions:

```
requested → confirmed → completed
requested → rejected
```

---

## Pro plugin endpoints

Base namespace: `polski-pro/v1`. All require `manage_woocommerce`.

### Audit log

#### `GET /polski-pro/v1/withdrawals/audit`

Paginated audit log entries.

```bash
curl -X GET \
    -H "X-WP-Nonce: $NONCE" \
    -b cookies.txt \
    "$SITE/wp-json/polski-pro/v1/withdrawals/audit?from=2026-05-01&to=2026-05-31&action=completed&per_page=50&page=1"
```

Response:

```json
{
    "page": 1,
    "per_page": 50,
    "count": 12,
    "entries": [
        {
            "id": 412,
            "withdrawal_id": 17,
            "order_id": 123,
            "action": "completed",
            "actor_user_id": 1,
            "actor_login": "admin",
            "actor_role": "administrator",
            "ip_address": "203.0.113.1",
            "user_agent": "Mozilla/5.0 …",
            "payload_json": "{\"refund_amount\":120.00,\"refund_id\":456}",
            "created_at": "2026-05-17 19:02:11"
        }
    ]
}
```

Filters: `action`, `from`, `to` (ISO 8601), `withdrawal_id`, `order_id`.

#### `GET /polski-pro/v1/withdrawals/audit/export`

Streamed CSV of the same data (no pagination - applies filters and writes
all matching rows to the response).

```bash
curl -X GET \
    -H "X-WP-Nonce: $NONCE" \
    -b cookies.txt \
    -o audit-2026-05.csv \
    "$SITE/wp-json/polski-pro/v1/withdrawals/audit/export?from=2026-05-01&to=2026-05-31"
```

Response headers:

```
Content-Type: text/csv; charset=utf-8
Content-Disposition: attachment; filename=polski-withdrawal-audit-2026-05-17-190215.csv
```

### Reports

#### `GET /polski-pro/v1/withdrawals/reports/scorecards`

```bash
curl -X GET \
    -H "X-WP-Nonce: $NONCE" \
    -b cookies.txt \
    "$SITE/wp-json/polski-pro/v1/withdrawals/reports/scorecards?from=2026-05-01&to=2026-05-31"
```

```json
{
    "filed": 24,
    "completed": 18,
    "rejected": 2,
    "in_progress": 4,
    "average_processing_seconds": 86400,
    "refund_count": 18,
    "refund_volume": 2840.5,
    "refund_per_currency": {}
}
```

#### `GET /polski-pro/v1/withdrawals/reports/reasons`

```bash
curl -X GET \
    -H "X-WP-Nonce: $NONCE" \
    -b cookies.txt \
    "$SITE/wp-json/polski-pro/v1/withdrawals/reports/reasons?limit=10"
```

```json
{
    "items": [
        { "reason": "Niewłaściwy rozmiar", "count": 12 },
        { "reason": "Zmiana zdania", "count": 5 },
        { "reason": "Uszkodzony przy dostawie", "count": 3 }
    ]
}
```

#### `GET /polski-pro/v1/withdrawals/reports/channels`

```json
{
    "items": [
        { "channel": "online", "count": 18 },
        { "channel": "phone", "count": 4 },
        { "channel": "email", "count": 2 },
        { "channel": "guest", "count": 2 }
    ]
}
```

---

## Status codes

| Code | Meaning |
|---|---|
| `200` | Success |
| `400` | Bad request body (missing required field, invalid status transition) |
| `401` | Not authenticated (missing nonce / Application Password) |
| `403` | Authenticated but lacks `manage_woocommerce` (or not the order owner) |
| `404` | Withdrawal or order not found |
| `429` | Rate-limited (guest lookup only - 5 attempts / 15 min per e-mail+IP) |
| `500` | Database error during refund - payload returned `{"error": "…"}` |

---

## Programmatic use without REST: the Abilities API

If you target WP 6.9+, prefer the Abilities API - it auto-generates request
validation from the JSON Schema declared in PHP and integrates with the
Site Editor command palette and `@wordpress/abilities` JS package.

```bash
curl -X POST \
    -H "X-WP-Nonce: $NONCE" \
    -H "Content-Type: application/json" \
    -b cookies.txt \
    -d '{"input": {"order_id": 123, "reason": "Niewłaściwy rozmiar"}}' \
    "$SITE/wp-json/wp-abilities/v1/abilities/polski%2Fwithdrawal-create/execute"
```

```js
import { execute } from '@wordpress/abilities';
const { declaration_id, status } = await execute(
    'polski/withdrawal-create',
    { order_id: 123, reason: 'Niewłaściwy rozmiar' },
);
```

See [abilities.md](abilities.md) for the full list.
