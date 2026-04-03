# WordPress.org SVN Publish

Prepared package:

```text
/tmp/polski-wporg-trunk
```

## Release flow

```bash
svn checkout https://plugins.svn.wordpress.org/polski /tmp/polski-svn
bash scripts/prepare-wporg-release.sh
bash scripts/sync-wporg-svn.sh /tmp/polski-wporg-trunk /tmp/polski-svn
cd /tmp/polski-svn
svn status
svn add --force trunk tags assets --auto-props --parents
svn commit -m "Release 1.1.0"
```

## Optional assets

Place directory assets in:

```text
/tmp/polski-svn/assets/
```

Suggested filenames:

- `icon-128x128.png`
- `icon-256x256.png`
- `banner-772x250.png`
- `banner-1544x500.png`
- `screenshot-1.png`
