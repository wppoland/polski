# WordPress.org Assets

Prepared assets:

- `icon-128x128.png`
- `icon-256x256.png`
- `banner-772x250.png`
- `banner-1544x500.png`

These files are ready to be copied into the WordPress.org SVN `assets/` directory.

## Included screenshots

The release already includes the following screenshot set:

1. `screenshot-1-modules-dashboard.png`
   - Modules dashboard
   - Highlight compliance groups and enabled defaults

2. `screenshot-2-gpsr-product-editor.png`
   - Product editor
   - Show GPSR manufacturer, importer, warnings, and instructions

3. `screenshot-3-checkout-checkboxes.png`
   - Checkout
   - Show legal checkboxes and order button wording

4. `screenshot-4-omnibus-lowest-price.png`
   - Product page or archive
   - Show lowest price from 30 days

5. `screenshot-5-withdrawal-request.png`
   - My Account > Orders
   - Show withdrawal action or confirmation screen

6. `screenshot-6-dsa-report-form.png`
   - Frontend DSA form
   - Show legal report fields

7. `screenshot-7-storefront-search-filters.png`
   - Shop page
   - Show AJAX search and AJAX filters

8. `screenshot-8-wishlist-compare-quick-view.png`
   - Shop archive cards
   - Show wishlist, compare, and quick view buttons

9. `screenshot-9-ksef-orders-column.png`
   - WooCommerce > Orders (HPOS list)
   - Show the KSeF column: dash, amber dot, green dot

## Screenshot rules

- Use PNG, not JPG
- Use Polish UI copy on screenshots
- Add short callouts or arrows only where they clarify the feature
- Keep the background clean and avoid browser chrome if possible
- Do not show any non-public UI

## SVN asset structure

WordPress.org matches caption N to `assets/screenshot-N.png`, so each file is
copied without its descriptive suffix (`screenshot-9-ksef-orders-column.png`
becomes `assets/screenshot-9.png`).

```text
assets/
  banner-772x250.png
  banner-1544x500.png
  icon-128x128.png
  icon-256x256.png
  screenshot-1.png
  screenshot-2.png
  screenshot-3.png
  screenshot-4.png
  screenshot-5.png
  screenshot-6.png
  screenshot-7.png
  screenshot-8.png
  screenshot-9.png
```

## Final publish check

- Verify `readme.txt` screenshot captions match the final filenames and order
- Verify the plugin slug is `polski-for-woocommerce`
- Verify the main plugin file is `polski.php`
- Build the release package again before SVN sync
- Add only static PNG assets to WordPress.org
