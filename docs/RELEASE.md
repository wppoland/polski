# Release & WordPress.org Publishing Guide

## Prerequisites

- Node.js 18+ (for building JS/CSS assets)
- PHP 8.1+ with `msgfmt` (for compiling translations)
- SVN client (for WordPress.org deployment)

## 1. Build the ZIP for WordPress.org submission

```bash
# From the plugin root directory
cd /path/to/polski

# Build JS/CSS assets (if changed)
npm install
npm run build

# Build the distribution ZIP
bash bin/build-zip.sh
```

This produces `polski.zip` (~ 900 KB) with:
- Production PHP code (`src/`, `config/`, `templates/`)
- Built assets (`build/`, `assets/`)
- All translations (`.po`, `.mo`, `.json`)
- `readme.txt`, `polski.php`, `uninstall.php`

Excluded automatically via `.distignore`: tests, node_modules, .git, resources, vendor, dev configs.

## 2. Verify with Plugin Check

Before submitting, run the official WordPress Plugin Check:

```bash
# In wp-env
npm run env:start
npm run env:cli -- wp plugin install plugin-check --activate
npm run env:cli -- wp plugin check polski --format=table
```

Expected: only warnings (global variable prefixes in templates, DB query patterns). No blockers.

## 3. First-time submission

1. Go to https://wordpress.org/plugins/developers/add/
2. Upload `polski.zip`
3. Wait for manual review (1-10 business days)
4. You'll receive SVN credentials and plugin slug

## 4. SVN deployment (after approval)

### Initial checkout

```bash
svn checkout https://plugins.svn.wordpress.org/polski /tmp/polski-svn
```

### Prepare release files

```bash
# Build clean distribution
bash bin/build-zip.sh

# Or use the prepare script
bash scripts/prepare-wporg-release.sh
```

### Sync to SVN and publish

```bash
# Sync trunk and create version tag
bash scripts/sync-wporg-svn.sh /tmp/polski-wporg-trunk /tmp/polski-svn

# Add WordPress.org listing assets (first time only)
# Place in /tmp/polski-svn/assets/:
#   banner-772x250.png    (772x250px - plugin page header)
#   banner-1544x500.png   (1544x500px - retina header)
#   icon-128x128.png      (128x128px - search results icon)
#   icon-256x256.png      (256x256px - retina icon)
#   screenshot-1.png      (any size - listed in readme.txt)

# Review and commit
cd /tmp/polski-svn
svn status
svn add --force trunk tags assets --auto-props --parents
svn commit -m "Release 1.3.0"
```

## 5. Version bump checklist

When releasing a new version, update these files:

| File | Field |
|------|-------|
| `polski.php` | `Version: X.Y.Z` |
| `readme.txt` | `Stable tag: X.Y.Z` |
| `readme.txt` | `Tested up to: X.Y` (match current WP version) |
| `src/Plugin.php` or constants | `VERSION` constant |
| `readme.txt` | Add changelog entry under `== Changelog ==` |

## 6. Translation workflow

Translations live in `languages/`. To add/update:

```bash
# Regenerate POT from source (in wp-env)
npm run env:cli -- wp i18n make-pot wp-content/plugins/polski \
  wp-content/plugins/polski/languages/polski.pot --domain=polski

# Update PO files with new strings
# Then compile MO files
for f in languages/*.po; do
  msgfmt "$f" -o "${f%.po}.mo"
done

# Generate JSON files for JS translations
npm run env:cli -- wp i18n make-json languages/ --no-purge
```
