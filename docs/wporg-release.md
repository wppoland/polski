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

## SVN layout

- `trunk/` - contents of the prepared package
- `tags/1.1.0/` - same contents for the release tag
- `assets/` - WordPress.org banner, icon and screenshots, if needed
