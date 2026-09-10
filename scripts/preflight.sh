#!/usr/bin/env bash
#
# Release preflight for Polski. Run this GREEN before any wp.org SVN release.
# It is the local mirror of the CI gate (.github/workflows/ci.yml) plus a real
# WordPress Plugin Check on the built plugin.
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

echo "==> 1/14  boot timing and duplicated admin_post handlers"
php tests/boot-timing-check.php

echo "==> 2/14  every settings key has a reader"
php tests/settings-are-read-check.php

echo "==> 3/14  every module id has a card that can switch it on"
php tests/module-ids-are-reachable-check.php

echo "==> 4/14  readme.txt Changelog under the wp.org truncation limit"
php tests/readme-changelog-length-check.php

echo "==> 5/14  product meta fields are rendered and saved"
php tests/product-meta-render-save-check.php

echo "==> 6/14  public (nopriv) handlers are guarded by their module"
php tests/nopriv-handlers-are-guarded-check.php

echo "==> 7/14  taxonomies follow their module toggles"
php tests/taxonomies-follow-module-toggles-check.php

echo "==> 8/14  withdrawal lookup texts are translatable"
php tests/withdrawal-lookup-texts-check.php

echo "==> 9/14  structured data: salt is converted, no private keys leak"
php tests/schema-structured-data-check.php

echo "==> 10/14  phpcs"
vendor/bin/phpcs

echo "==> 11/14  phpstan (memory 2G)"
php -d memory_limit=2G vendor/bin/phpstan analyse -c phpstan.neon.dist --no-progress

echo "==> 12/14  runtime fatal smoke (wp-env)"
npx wp-env start >/dev/null 2>&1 || true
# PRO would gate the admin behind a Freemius license screen; the smoke exercises
# the FREE plugin's code directly, so deactivate PRO for a deterministic run.
npx wp-env run cli wp plugin deactivate polski-pro >/dev/null 2>&1 || true
npx wp-env run cli wp eval-file wp-content/plugins/polski/scripts/smoke-fatal-check.php

echo "==> 13/14  WordPress Plugin Check"
bash scripts/plugin-check.sh

echo "==> 14/14  package contents and header/readme agreement"
bash scripts/prepare-wporg-release.sh /tmp/polski-preflight-package >/dev/null
bash scripts/assert-package-clean.sh /tmp/polski-preflight-package
rm -rf /tmp/polski-preflight-package

echo ""
echo "✅ PREFLIGHT PASSED, safe to release to wp.org."
