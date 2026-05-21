# Polski for WooCommerce - Performance tuning

This document is a short guide for store operators (and developers) who want
to get the best performance out of the plugin on a live site. The plugin is
designed to be cheap on default WordPress, but a few server-side toggles
unlock additional headroom that matter on stores with large catalogs or
heavy traffic.

The numbers below come from the in-tree Lighthouse CI baseline, run against
the development wp-env on `/shop/` (12 products) and `/my-account/`. See
`lighthouserc.json` for the assertion thresholds; the nightly run lives in
`.github/workflows/a11y.yml`.

## Recommended stack

| Component | Recommendation |
|---|---|
| PHP | 8.1+ (8.3 recommended) - the plugin already declares this; matches WP Performance Lab guidance. |
| WordPress | 6.4 or higher, 7.0 to unlock the AI Client integration. |
| WooCommerce | 8.0+; HPOS (Custom Order Tables) enabled. The plugin declares compatibility in `polski.php`. |
| Persistent object cache | **Strongly recommended** for high-traffic stores. See section below. |
| PHP OPcache | Enabled with `validate_timestamps=0` in production. |
| Database | MySQL 5.7+ / MariaDB 10.3+; InnoDB on all tables. |

## Persistent object cache (the biggest single win)

WordPress ships with a non-persistent, in-process object cache by default,
which means every request starts with an empty cache. The plugin makes
several wp_cache_get / wp_cache_set calls (Omnibus per-product lowest
price, AnalyticsService payload, structured-data per-product) that would
otherwise translate into repeated DB queries on every page render.

Install a persistent backend - Redis or Memcached - and the
`polski_omnibus` cache group keeps lowest-price results across requests
for one hour, invalidated automatically whenever a product price is
saved. The same applies to other plugin caches.

Recommended providers:

* **Redis Object Cache** plugin by Till Krüss (free) - drop-in for any
  hoster with Redis available.
* **Memcached Object Cache** drop-in - shipped by many managed WordPress
  hosts (WP Engine, Kinsta, Pressable).
* Cloud platforms: AWS ElastiCache, GCP Memorystore, Upstash, Aiven.

Confirm a persistent backend is active in `wp-admin/site-health.php` under
`Object Cache`, or by running `wp cli has-command 'cache flush'` followed
by `wp cache flush` - on a non-persistent cache the second call is a
no-op and your monitoring should warn about that.

### Expected impact

In our test environment (no persistent cache, 12-product `/shop/` archive):

* Without batch-warming the Omnibus cache: 12 separate queries to
  `polski_price_history` per archive render.
* With batch-warming (`OmnibusService::warmCacheForArchive` hooked into
  `woocommerce_before_shop_loop`): 1 SQL query, then 12 cache hits.

On a store with a persistent cache + ~1 000 product catalog, the
batch-warm typically drops to one cache hit per archive page (warmed by
an earlier visitor or by the daily maintenance task), so the SQL query is
not run at all.

## Database indexes

The plugin ships with indexes appropriate for the queries it runs.
Migrations are auto-discovered and run on every version bump - see
`src/Migrator.php` and `src/Plugin.php::syncInstalledVersion`. Notable
indexes:

* `polski_price_history(product_id, recorded_at)` - covers the Omnibus
  lowest-price window scan.
* `polski_withdrawals(status)`, `(channel)`, `(guest_email)`,
  `(guest_token_hash)`, `(customer_id)` - covers operator-dashboard and
  guest-magic-link lookups.
* `polski_withdrawals(status, ai_category)` - added in Migration_2_3_1
  for the operator-dashboard combined filter so the query plan stays
  index-bound past ~10 000 declarations.
* `polski_consent_log(user_id)`, `(checkbox_id, context)` - covers
  consent log lookups.

If you have an existing install that pre-dates Migration_2_3_1, simply
deactivate + reactivate the plugin once (or run `wp polski migrate` if
you have WP-CLI configured), and the new compound index is applied
idempotently.

## Disable unused modules

The plugin's free distribution at `polski-mvp/` is a four-feature
compliance core and renders nothing beyond what is needed. The
development branch in `polski/` contains many storefront extras
(wishlist, compare, quick view, product slider, AJAX search/filters,
popups, ...). Every extra is gated by a module toggle (`ModulesPage` in
admin); modules that are off do not enqueue assets or run hooks. Turn
off anything you do not actually use.

## Core Web Vitals baseline (development wp-env)

| Page | Performance | LCP | FCP | CLS | TBT | TTFB |
|---|---|---|---|---|---|---|
| `/shop/` | 88 | 1.5 s | 1.3 s | 0 | 0 ms | ~470 ms |
| `/my-account/` | 96 | 1.2 s | 1.0 s | 0.001 | 0 ms | ~160 ms |
| `/odstapienie/` | (see lhci) | (see lhci) | (see lhci) | (see lhci) | (see lhci) | (see lhci) |

All three Core Web Vitals (LCP, CLS, INP/TBT) are green on every tested
page. Production numbers depend on hosting; the plugin itself does not
move them noticeably when modules are off.

## Reporting a regression

If a release moves any of the Lighthouse scores measurably (more than
five points down on Performance, or CLS above 0.1), open an issue with:

* The exact plugin version,
* The Lighthouse HTML report saved from the nightly run
  (`reports/lighthouse/`),
* The full set of modules you have enabled.

The maintainers track the baseline above and treat downward movement as
a release blocker.
