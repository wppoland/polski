# WordPress.org Release

Free plugin release should be prepared from `polski/` only.

## Included

- runtime PHP code
- templates
- compiled `build/`
- `autoload.php`
- translations
- `readme.txt`

## Excluded

- tests
- docs
- `node_modules`
- source `resources/`
- local env files
- build tooling files
- `vendor/`

## Prepare trunk package

```bash
cd polski
bash scripts/prepare-wporg-release.sh
```

Default output:

```text
/tmp/polski-wporg-trunk
```

## Sync local SVN checkout

```bash
svn checkout https://plugins.svn.wordpress.org/polski /tmp/polski-svn
cd polski
bash scripts/sync-wporg-svn.sh /tmp/polski-wporg-trunk /tmp/polski-svn
```

## SVN layout

- `trunk/` - contents of the prepared package
- `tags/1.1.0/` - same contents for the release tag
- `assets/` - WordPress.org banner, icon and screenshots, if needed
