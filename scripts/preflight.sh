#!/usr/bin/env bash
#
# Release preflight for Polski. Run this GREEN before any wp.org SVN release.
# This is the ONLY gate. .github/workflows/ci.yml is disabled on the repo and
# last ran on 2026-07-06, red; nothing a commit does starts it. A check that is
# not a step below does not run before a release.
#
# Order matters: cheap static checks first, then the runtime fatal smoke (the
# step that catches TypeErrors phpstan misses - e.g. the 1.22.4
# woocommerce_order_query / paginated-orders fatal), then Plugin Check.
#
# Usage:  bash scripts/preflight.sh
# Exits non-zero on the first failure, so a release script can gate on it:
#   bash scripts/preflight.sh && bash scripts/sync-wporg-svn.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "==> 1/17  boot timing and duplicated admin_post handlers"
php tests/boot-timing-check.php

echo "==> 2/17  every settings key has a reader"
php tests/settings-are-read-check.php

echo "==> 3/17  every module id has a card that can switch it on"
php tests/module-ids-are-reachable-check.php

echo "==> 4/17  readme.txt Changelog under the wp.org truncation limit"
php tests/readme-changelog-length-check.php

echo "==> 5/17  product meta fields are rendered and saved"
php tests/product-meta-render-save-check.php

echo "==> 6/17  public (nopriv) handlers are guarded by their module"
php tests/nopriv-handlers-are-guarded-check.php

echo "==> 7/17  taxonomies follow their module toggles"
php tests/taxonomies-follow-module-toggles-check.php

echo "==> 8/17  withdrawal lookup texts are translatable"
php tests/withdrawal-lookup-texts-check.php

echo "==> 9/17  structured data: salt is converted, no private keys leak"
php tests/schema-structured-data-check.php

echo "==> 10/17  products are queried by 'include', never by 'post__in'"
php tests/product-query-args-check.php

# The two stock-export tests in this class are what actually caught the 1.37.5
# data loss: they walk the export over 450 products, which is more than one
# batch, and a hydration that does not really ask by ID fails them. Only this
# file runs, not the suite: three OmnibusBatchLoaderTest failures predate this
# gate (they are red at 6af29ca, before any of it was written), and a step that
# is known red is a step nobody reads.
echo "==> 11/17  unit tests: batched exports, cart label split, legal page rules"
vendor/bin/phpunit tests/Unit/Service/UnboundedQueryBatchingTest.php
vendor/bin/phpunit tests/Unit/Service/OmnibusServiceTest.php
vendor/bin/phpunit tests/Unit/PageCompliance

echo "==> 12/17  phpcs"
vendor/bin/phpcs

echo "==> 13/17  phpstan (memory 2G)"
php -d memory_limit=2G vendor/bin/phpstan analyse -c phpstan.neon.dist --no-progress

echo "==> 14/17  runtime fatal smoke (wp-env)"
npx wp-env start >/dev/null 2>&1 || true
# PRO would gate the admin behind a Freemius license screen; the smoke exercises
# the FREE plugin's code directly, so deactivate PRO for a deterministic run.
npx wp-env run cli wp plugin deactivate polski-pro >/dev/null 2>&1 || true
npx wp-env run cli wp eval-file wp-content/plugins/polski/scripts/smoke-fatal-check.php

# Two halves of one misunderstanding, both invisible to static analysis.
# 1.37.9 shipped two NIP inputs on My Account > Addresses (the classic billing
# field plus the additional-fields copy WooCommerce renders there itself), and
# three services dropped their CLASSIC registration whenever the additional-
# fields API existed, which emptied the shortcode checkout from WC 8.6 on.
echo "==> 15/17  address form has no duplicates, classic checkout keeps its fields"
for module in nip_lookup b2b_checkout custom_checkout_fields withdrawal; do
  npx wp-env run cli wp option patch insert polski_modules "${module}" 1 >/dev/null
done
npx wp-env run cli --env-cwd=wp-content/plugins/polski wp eval-file tests/address-fields-no-duplicates-check.php

echo "==> 16/17  WordPress Plugin Check"
bash scripts/plugin-check.sh

echo "==> 17/17  package contents and header/readme agreement"
bash scripts/prepare-wporg-release.sh /tmp/polski-preflight-package >/dev/null
bash scripts/assert-package-clean.sh /tmp/polski-preflight-package
rm -rf /tmp/polski-preflight-package

echo ""
echo "✅ PREFLIGHT PASSED, safe to release to wp.org."
