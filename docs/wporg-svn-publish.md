# WordPress.org SVN Publish

Use the prepared package from:

```text
/tmp/polski-wporg-trunk
```

## First checkout

```bash
svn checkout https://plugins.svn.wordpress.org/polski /tmp/polski-svn
```

## Update trunk

```bash
rsync -a --delete /tmp/polski-wporg-trunk/ /tmp/polski-svn/trunk/
```

## Tag release

```bash
rm -rf /tmp/polski-svn/tags/1.1.0
cp -R /tmp/polski-svn/trunk /tmp/polski-svn/tags/1.1.0
```

## Optional assets

Add plugin directory assets to:

```text
/tmp/polski-svn/assets/
```

Suggested names:

- `icon-128x128.png`
- `icon-256x256.png`
- `banner-772x250.png`
- `banner-1544x500.png`
- `screenshot-1.png`

## Commit

```bash
cd /tmp/polski-svn
svn status
svn add --force trunk tags assets --auto-props --parents
svn commit -m "Release 1.1.0"
```
