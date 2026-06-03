# Next steps / orchestration handoff

Last updated: 2026-06-03. This file is the continuation plan so work can resume
on another machine. It is in `.distignore`, so it never ships.

## Where things stand

- **FREE** (`github.com/wppoland/polski`, branch `main`): all the work below is
  committed and pushed, but **NOT released to wp.org**. Live wp.org = **1.22.2**.
  The accumulated, unreleased changes are the next release (**1.23.0**).
- **PRO** (`polski-pro`, branch `feat/pro-governed-abilities`, pushed): governed
  abilities + the ability-category-slug fix. License-gated; not released. Launch
  is blocked on Lemon Squeezy store approval.
- **Docs** (`polski-docs`, `main`, pushed) and **landing** (`wppoland-new`,
  `master`, pushed): updated for 1.21/1.22. Still need a pass for the 1.23.0
  admin menu/UX changes.
- `wp-env` runs **WordPress 7.0** (`.wp-env.json`).

## Done this session (unreleased, on `main`)

1. **Admin menu decluttered.** Feature/report pages (Withdrawals, Consent
   records, CRA incidents, SBOM, Complaint template, RODO training) are now
   registered hidden (empty parent) and surfaced as cards in the **Reports &
   Tools** hub instead of flat submenu items. They stay routable by URL.
2. **Settings consolidated.** The five per-bucket settings submenu pages are now
   one **"Settings"** menu item with a tab per bucket
   (`?page=polski-settings&bucket=<key>`). Final submenu: Dashboard, Modules,
   Reports & Tools, Settings, Pro license, Compliance checklist.
3. **Bucketing bug fixed (root cause).** Module `group` used to be a translated
   `__()` string, but `remapGroup()` matches English keys, so in non-English
   locales almost everything fell through to `advanced_tools` (the "mess"). Now
   `group` is a **stable English key**; display is translated via
   `getGroupDisplayLabel()`. Buckets are correct again and the modules list
   renders finer sub-group headers. NOTE: do **not** wrap `'group'` back in
   `__()` or bucketing/settings links break in PL.
4. **Module help tooltips.** `ModulesPage::getModuleTooltips()` holds a per-module
   "what it is + what happens when enabled" string for all 71 modules, shown in
   the row help (?) tooltip. Translated into all 8 locales.
5. **Off-module links muted.** Module action links (e.g. "Manage FAQ") are shown
   muted with an "Enable this module to use this" hint while the module is off,
   instead of 404ing. (This was the reported FAQ issue: the module was simply
   off.)
6. **i18n.** Translated genuine English-copy strings (where `msgstr == msgid`).
   **OSS** help link now points to `polski.wppoland.com/prices/oss-observer`
   (not the third-party page).

## Next actions (in order)

1. **Release 1.23.0 to wp.org.**
   - Bump `polski.php` `Version:` header + `const VERSION`, and `readme.txt`
     `Stable tag:`; add a changelog entry.
   - `npm run build`.
   - Gates: `vendor/bin/phpcs --standard=phpcs.xml.dist <changed>`;
     `php -d memory_limit=2G vendor/bin/phpstan analyse`; **Plugin Check on the
     clean package** (`bash scripts/prepare-wporg-release.sh`, then install
     `/tmp/polski-wporg-trunk` into wp-env under a throwaway slug and
     `wp plugin check <slug> --severity=error`).
   - Publish: `bash scripts/sync-wporg-svn.sh`, `svn add`, then
     `svn commit` with creds from `~/.claude/secrets/wporg.env`
     (`WPORG_SVN_PASSWORD`, NOT in the repo). See `docs/wporg-svn-publish.md`.
2. **Verify the settings save round-trip** end-to-end in wp-env: open
   Settings, change a field on a tab, save, confirm it persists and redirects
   back to the same tab (`&saved=1&bucket=<key>`). Wiring is in place
   (`group_slug` is preserved and the redirect/edit links target
   `polski-settings`), but a full submit was not exercised.
3. **Docs + landing for 1.23.0.** Update `polski-docs` and `wppoland-new` to
   describe the reorganised admin (Reports & Tools hub, tabbed Settings). Per
   project rule: docs + landing per feature.
4. **PRO launch** (blocked on Lemon Squeezy store approval). When approved:
   live-mode webhook, buyer-key delivery, unhide the PRO upsell in FREE,
   R2 auto-update. The destructive `OrderStatusTransition` ability stays
   `autoExecuteEnabled() = false` until an explicit human sign-off.
5. **i18n maintenance** (ongoing): after any string change, re-run the
   English-copy pass (see `scripts/i18n/README.md`). The 11 remaining PL
   "untranslated" entries are Polish-source hub-card strings that display
   correctly via the msgid fallback; leave them.
6. **Optional / skipped:** WP-Full-Picture "Reactions" and "Analytics
   Dashboards" were intentionally not ported (low value / heavy).
7. **Recurring scout** (`trig_01HvFvB6bjN2ZbU67kWotU6Z`): weekly WP/WC/API
   update check that opens draft PRs; nothing to do unless a PR lands.

## Orchestration notes (how to reproduce)

- **wp-env**: `npm run env:start`; admin at `http://localhost:8888/wp-admin`
  (`admin` / `password`); currently WP 7.0.
- **Gates** on every PHP change: phpcs (`phpcs.xml.dist`), phpstan (2 GB mem),
  and WordPress Plugin Check on the **clean package** (dev files like
  `.DS_Store`, `node_modules`, `tests` are excluded by `.distignore`; check the
  package, not the working tree).
- **i18n fan-out pattern** (used for tooltips + copy fixes): regenerate POT
  (`wp i18n make-pot`), detect untranslated entries (empty `msgstr` or
  `msgstr == msgid`) per locale with `scripts/i18n/po-analyze.php`, run one
  subagent per locale to translate only the genuine natural-language gaps
  (skip brand names, URLs, codes, and strings already in the target language),
  rebuild a compendium with `scripts/i18n/build-po.php`, merge with
  `msgcat --use-first <trans> <locale> -o <merged>`, then `wp i18n make-mo` +
  `wp i18n make-json`.
- **Module tooltips** live in `ModulesPage::getModuleTooltips()`; render via
  `getModuleHelpTooltip()`. Keep tooltips in `__()` so they stay translatable.

## Project rules (do not break)

- Branding is **WPPoland** (never "WP Poland").
- **No competitor names** anywhere shipped (code, docs, changelogs, commits).
- **No legal-compliance guarantees**: say "provides tools, not legal advice".
- **No em-dash** characters anywhere in the project.
- Update docs + landing per feature; bump the version per feature.
