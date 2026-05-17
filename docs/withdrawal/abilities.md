# Abilities API map

20 abilities registered across 5 categories. Available on WordPress 6.9+ via
`/wp-json/wp-abilities/v1/abilities` and the `@wordpress/abilities` JS package.
On older WordPress versions the plugin functions normally — abilities just
aren't exposed.

## Categories

| ID | Source |
|---|---|
| `polski/withdrawal` | FREE — withdrawal flow |
| `polski/legal` | FREE — generated legal documents |
| `polski/compliance` | FREE — compliance audits |
| `polski/shop` | FREE — shop identification |
| `polski-pro/withdrawal` | PRO — refund / pdf / audit / reports |

## FREE abilities (16)

### `polski/withdrawal` category

| ID | Input | Output | Permission |
|---|---|---|---|
| `polski/withdrawal-is-eligible` | `{order_id}` | `{eligible, deadline}` | logged-in or shop manager |
| `polski/withdrawal-get-remaining-items` | `{order_id}` | `{items[]}` | logged-in or shop manager |
| `polski/withdrawal-get-deadline` | `{order_id}` | `{deadline, days_left}` | logged-in or shop manager |
| `polski/withdrawal-create` | `{order_id, reason?, items?}` | `{declaration_id, status}` | order owner or shop manager |
| `polski/withdrawal-confirm` | `{declaration_id}` | `{ok}` | shop manager |
| `polski/withdrawal-complete` | `{declaration_id}` | `{ok}` | shop manager |
| `polski/withdrawal-reject` | `{declaration_id, reason?}` | `{ok}` | shop manager |
| `polski/withdrawal-list` | `{status?, limit?, offset?}` | `{items[]}` | shop manager |
| `polski/withdrawal-get-exemption-reason` | `{product_id}` | `{exempt, reason}` | public |

### `polski/legal` category

| ID | Input | Output | Permission |
|---|---|---|---|
| `polski/annex-generate-info` | — | `{html}` | public |
| `polski/annex-generate-form` | — | `{html}` | public |

### `polski/compliance` category

| ID | Input | Output | Permission |
|---|---|---|---|
| `polski/compliance-check-privacy` | — | CheckReport | options manager |
| `polski/compliance-check-terms` | — | CheckReport | options manager |
| `polski/compliance-check-cookie-banner` | `{url?}` | CheckReport | options manager |

### `polski/shop` category

| ID | Input | Output | Permission |
|---|---|---|---|
| `polski/shop-get-business-info` | — | `{name, address, nip, regon, email, phone}` | public |
| `polski/shop-legal-pages-status` | — | `{pages: {type: bool}}` | options manager |

## PRO abilities (4)

### `polski-pro/withdrawal` category

| ID | Input | Output | Permission |
|---|---|---|---|
| `polski-pro/withdrawal-process-refund` | `{declaration_id}` | `{refund_id}` | shop manager |
| `polski-pro/withdrawal-generate-pdf` | `{declaration_id}` | `{filepath}` | shop manager |
| `polski-pro/withdrawal-audit-list` | `{action?, from?, to?, withdrawal_id?, order_id?, limit?}` | `{entries[]}` | shop manager |
| `polski-pro/withdrawal-report-scorecards` | `{from?, to?}` | KPI scorecards | shop manager |

## Calling an ability

```bash
curl -X POST \
  -H 'Content-Type: application/json' \
  -H 'X-WP-Nonce: <nonce>' \
  -d '{"input":{"order_id":123}}' \
  https://example.com/wp-json/wp-abilities/v1/abilities/polski%2Fwithdrawal-is-eligible/execute
```

```js
import { execute } from '@wordpress/abilities';
const { eligible, deadline } = await execute( 'polski/withdrawal-is-eligible', { order_id: 123 } );
```
