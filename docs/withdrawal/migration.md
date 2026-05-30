# Upgrading to Polski 1.16.0 (withdrawal module)

This document walks shop owners through what happens when their site
upgrades from Polski 1.9.x or earlier to 1.16.0 - the release that ships
the full Art. 11a withdrawal flow.

If you are on a fresh install you can ignore everything below: the
[setup wizard](../../resources/js/admin/pages/SetupWizard.tsx) configures
everything in a few clicks.

## TL;DR

Five things change. All are additive - your existing checkout, terms
page and order flow keep working.

1. New database tables (`polski_withdrawals`, `polski_withdrawal_items`)
   are created on plugin activation via the migration runner.
2. Three new order statuses (`wc-withdrawal-requested`, `-partial`,
   `-completed`) are registered.
3. Three new Gutenberg blocks and three shortcodes become available.
4. A `Polski › Withdrawals` admin menu plus a `Polski › Withdrawal
   settings` page appear.
5. 16 new abilities are registered under `wp-abilities/v1/` on WP 6.9+.

## Before you upgrade

Run these checks (5 min):

```bash
# Back up first - the migration is idempotent but better safe than sorry.
wp db export polski-pre-1.16.sql

# Inventory your custom code touching withdrawal-related options or
# meta keys; renames are documented below.
grep -rn "polski_withdrawal_exempt\|polski/withdrawal" wp-content/themes wp-content/mu-plugins
```

## After you upgrade

### 1. The migration runs automatically

On the first admin page load after the upgrade, `Polski\Migrator` runs
Migration_2_2_0 which:

- Adds 13 columns to `polski_withdrawals` (`channel`, `guest_email`,
  `guest_token_hash`, `guest_token_expires_at`, `clock_started_at`,
  `refund_id`, `refund_amount`, `pdf_attachment_id`, `language_code`,
  `created_by_user_id`, `registered_by_user_id`, `rejected_at`,
  `rejected_reason`).
- Creates the `polski_withdrawal_items` table (normalised line items
  per declaration).
- Adds 4 indexes (`idx_channel`, `idx_guest_email`, `idx_guest_token`,
  `idx_customer`).

Verify with:

```bash
wp db query "DESCRIBE $(wp db prefix --quiet)polski_withdrawals"
wp db query "DESCRIBE $(wp db prefix --quiet)polski_withdrawal_items"
```

### 2. Configure the withdrawal-flow options

Open `Polski › Withdrawal settings` and review:

| Setting | Default | What to consider |
|---|---|---|
| Withdrawal period (days) | 14 | EU standard. Increase only if you offer a longer window. |
| Trigger statuses | `completed` | Add `delivered` / `shipped` if your fulfillment workflow uses those. The 14-day clock starts when an order enters any of these. |
| Guest lookup page | (empty) | Required for the guest flow. Either run the setup wizard's "Publish /odstapienie/ on Finish" toggle, or manually create a page with `[polski_withdrawal_lookup]` and pick it here. |
| My Account endpoint slug | `polski-withdrawals` | Flush permalinks after changing. |
| Digital consent mode | `optional` | If you sell mostly downloadable products, `required` is safer for you. |
| Pro: download verification | off | Pro only. Restores withdrawal eligibility if no file was downloaded. |
| Annex locale | auto | Pro only. Force a specific language for the Annex I(B) form. |

### 3. Re-publish the guest lookup page (if you skipped the wizard)

```php
// Quick CLI:
wp post create --post_type=page --post_status=publish \
    --post_title='Odstąpienie od umowy' --post_name=odstapienie \
    --post_content='[polski_withdrawal_lookup]'

# Then:
wp option patch update polski_withdrawal lookup_page_id <NEW_PAGE_ID>
```

Or use Gutenberg directly - the block `polski/withdrawal-lookup` is in
the inserter under "Widgets".

### 4. Configure exempt products and categories

The `_polski_withdrawal_exempt` post meta from earlier releases is
still respected. Two new meta keys layer on top:

- `_polski_withdrawal_exempt_reason` - value from
  `Polski\Enum\WithdrawalExemptionReason` (`art38_3` through `art38_13`,
  or `custom`).
- `_polski_withdrawal_exempt_reason_custom` - free text shown when
  reason is `custom`.

At the term (`product_cat`) level the keys are:

- `polski_withdrawal_exempt` (`'yes'` or empty)
- `polski_withdrawal_exempt_reason`
- `polski_withdrawal_exempt_reason_custom`

Bulk-enable a category from CLI:

```bash
TERM_ID=$(wp term list product_cat --name='Kable cięte z metra' --field=term_id)
wp term meta update $TERM_ID polski_withdrawal_exempt yes
wp term meta update $TERM_ID polski_withdrawal_exempt_reason art38_3
```

### 5. Migrate custom code

If your theme or a custom mu-plugin used any of these, here is what
changed:

| Pre-1.16 | Post-1.16 |
|---|---|
| `do_action('polski_withdrawal_submitted', $order_id)` | `do_action('polski/withdrawal/requested', WithdrawalRequest $r)` |
| `apply_filters('polski_withdrawal_days', 14)` | `apply_filters('polski/withdrawal/period_days', 14)` |
| Direct read of `polski_withdrawals.items_json` | Read from `polski_withdrawal_items` (normalised); the JSON column stays populated for backwards compat |
| `polski_settings['withdrawal_days']` option | `polski_withdrawal['period_days']` option |

The old hooks/options are no longer fired/read. If you cannot migrate
yet, pin to Polski 1.15.x until you can.

## Rollback

If the upgrade misbehaves, roll back the plugin code:

```bash
wp plugin update polski --version=1.15.0
```

The schema changes from Migration_2_2_0 are **not** reverted by code
downgrade - the extra columns simply sit unused. Restore the database
backup taken before the upgrade if you need a clean slate.

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Guest form returns "Order not found" for valid orders | `billing_email` mismatch | Confirm the e-mail on the order matches what the customer is typing (case-insensitive but exact otherwise). |
| Magic-link e-mail never arrives | wp-cron not running / SMTP misconfig | Send a test e-mail via `wp eval 'wp_mail(...)'`. The lookup form returns the same masked notice regardless - by design (anti-enumeration). |
| Two-step form shows "wszystkie pozycje wyłączone" | Every product/category is marked exempt | Audit term meta `polski_withdrawal_exempt` on each category. |
| Customer can withdraw past the 14-day window | Trigger statuses misconfigured | Check that `wc-completed` (or your chosen status) is set in `polski_withdrawal.trigger_statuses` and the order actually entered that status (look for `_polski_withdrawal_clock_start` order meta). |
| Subscription cancelled by withdrawal but you didn't want that | Pro `SubscriptionsCompat` always cancels on complete | Add `add_filter('polski_pro/subscriptions/treat_as_exempt', '__return_true');` to opt out. |

## Going further

- [Withdrawal architecture overview](README.md)
- [Abilities API map](abilities.md)
- [REST API reference](rest-api.md)
- [Settings reference](settings.md)
- [Manual a11y testing protocol](manual-testing.md)
