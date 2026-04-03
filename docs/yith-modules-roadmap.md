# YITH Modules Roadmap for Polski

## Goal

Turn the most useful YITH plugin ideas into native `polski` modules, without copying functionality that already exists in free or PRO.

The target is full functional coverage of the YITH plugin set selected for this project, but implemented as native `polski` modules, shared services, or integrations.

## Parity rule

`Polski` should expose the business capability of each relevant YITH plugin, even if the UX, settings layout, storage model or code structure is different.

That means:

- we aim for feature parity, not a 1:1 port of plugin code or admin screens,
- overlapping YITH features can be served by one stronger native module,
- existing `polski` or `polski-pro` features count as covered if they meet the same business need,
- each YITH plugin should have an explicit coverage status: `covered`, `planned`, `partial`, or `not planned`.

## Coverage matrix

| YITH plugin | Coverage target in Polski | Status |
|--------|--------|--------|
| Request a Quote | Native quote workflow, quote-only products, B2B lead capture, admin inbox, privacy consent and configurable form labels or validation copy | covered |
| WooCommerce Catalog Mode | Native catalogue-only mode, hidden prices, role/customer rules, quote pairing and configurable restriction, notice and CTA behaviour | covered |
| WooCommerce Ajax Search | Native fast AJAX search, SKU support, configurable result labels, optional submit button, REST runtime and mobile search UX | covered |
| WooCommerce Ajax Product Filter | Native layered filtering with attributes, taxonomies, stock, price, sale, AJAX updates and editable field labels or title visibility | covered |
| WooCommerce Wishlist | Native wishlist with guest/user support, account integration and configurable account layout, card visibility and messages | covered |
| WooCommerce Compare | Native product compare table with guest/user storage, differences, My Account view, configurable headings, intro, row labels, messages and header actions | covered |
| WooCommerce Quick View | Native quick view modal with variation support, gallery, add to cart, configurable visible sections, modal label visibility and close behaviour | covered |
| Frequently Bought Together | Native bundle suggestions, product-level bundles, configurable title, price visibility, selection behaviour, empty state and add-all-to-cart CTA | covered |
| WooCommerce Badge Management | Native badges with smart conditions, manual overrides and configurable shape, casing, badge styles and limits for Polish merchandising defaults | covered |
| WooCommerce Brands Add-On | Native brand taxonomy and storefront rendering with configurable label visibility, separators and archive links | covered |
| WooCommerce Tab Manager | Native extra tabs with global and product-level content blocks, plus toggles and configurable tab priorities | covered |
| WooCommerce Featured Video | Native featured video in gallery and PDP media area with configurable heading and intro visibility | covered |
| WooCommerce Product Gallery & Image Zoom | Native gallery enhancements, hover zoom and lightbox support with configurable lightbox labels and backdrop behaviour | covered |
| WooCommerce Product Slider Carousel | Native merchandising sliders and product collections with configurable section visibility, intro, empty state, card fields and view-all CTA | covered |
| WooCommerce Product Add-Ons | Native configurable product extras such as engraving, gift wrap, installation, extended warranty, service packages or B2B option surcharges, with validation and price modifiers | covered |
| Pre-Order for WooCommerce | Native pre-order workflow with configurable dates, messaging, notice title, notice visibility and stock rules | covered |
| WooCommerce Waitlist | Native back-in-stock and waiting list flow with configurable form title or intro and notification copy | covered |
| WooCommerce Subscription | Native recurring billing with account area, reminders, renewal orders and configurable reminder or renewal copy, title visibility, cycle wording and account columns | covered |
| WooCommerce Gift Cards | Native gift card purchase, balance, codes, redemption flow and configurable validation, title visibility and account columns | covered |
| WooCommerce Product Bundles | Native bundle builder, package discounts, grouped cart logic and configurable labels, notices and quantity output | covered |
| WooCommerce Affiliates | Native affiliate/referral layer with tokens, order attribution, partner dashboard and configurable dashboard visibility, stats and table structure | covered |
| Infinite Scrolling | Native archive UX enhancement with button and auto-load modes, editable status messages and optional auto-mode button | covered |
| WooCommerce Popup | Native promotional or lead popup system with frequency control, configurable heading visibility, close control and fallback CTA target | covered |
| WooCommerce Order & Shipment Tracking | Covered by shipping providers and tracking modules in PRO | covered |
| PayPal Express Checkout for WooCommerce | Covered by payment integrations, not as a dedicated standalone YITH port | covered |
| Custom Login | Out of core scope unless storefront account UX becomes a roadmap priority | not planned |
| Maintenance Mode | Out of e-commerce compliance scope | not planned |
| Proteo Toolkit / Proteo / Wonder / Slider for page builders | Theme or builder scope, not plugin-core roadmap | not planned |

## Current exclusions

The following YITH plugins should not become standalone modules right now because `polski` already covers them fully or partially:

- `YITH WooCommerce Order & Shipment Tracking` - overlaps with `shipping_providers` in PRO.
- `YITH PayPal Express Checkout for WooCommerce` - overlaps with payment gateway integrations.
- `YITH Custom Login` - low strategic value for this product and outside the Polish-commerce core.
- `YITH Maintenance Mode` - generic site tooling, not market-specific.
- `YITH Proteo Toolkit`, `YITH Proteo`, `YITH Wonder`, `YITH Slider for page builders` - theme or builder scope, not plugin core.

## Batch 1, B2B revenue and product discovery

Implement first because these features directly help Polish stores close manual deals, especially B2B, custom pricing and wholesale:

1. `Request a Quote`
   Native module implemented in this iteration.
   Polish extension: company, phone, NIP, postcode, consent logging, product-level quote-only mode.
   Runtime note: verified in local `wp-env` on 3 April 2026, including storefront render and successful quote request persistence.
   Current config depth: field labels, textarea placeholder, login-required copy, price placeholder, close label and validation or save-error messages.
2. `Catalog Mode`
   Implemented as a native B2B catalogue mode with audience targeting, hidden prices, disabled cart and quote/login/custom CTA.
   Runtime note: verified in local `wp-env` on 3 April 2026 together with quote CTA pairing on a quote-only product.
   Current config depth: hidden-price text, notice visibility, restriction copy, CTA mode and dedicated login CTA label.
3. `Ajax Search`
   Implemented as a lightweight live product search with SKU and category matching, plus WooCommerce search form replacement.
   Current config depth: field labels, result labels, no-results text, view-all CTA and optional submit button visibility.
4. `Ajax Product Filter`
   Implemented as progressive AJAX archive filters with categories, brands, price, stock, sale and attribute filtering.
   Current config depth: field labels, reset CTA, title visibility and GET fallback behaviour.

## Batch 2, conversion helpers

These features improve consideration and shortlist building without fighting existing compliance logic:

1. `Wishlist`
   Implemented with guest and user storage, AJAX add/remove, My Account integration and shortcode support.
   Current config depth: account title, intro text, product image or name visibility, grid size and runtime messages.
2. `Compare`
   Implemented with guest and user storage, AJAX add/remove, compare table, difference highlighting, My Account integration and shortcode support.
   Current config depth: table intro, row labels, table headings, header actions and operational messages.
3. `Quick View`
   Implemented as a lightweight AJAX modal with gallery, pricing, stock, delivery time, brand, manufacturer and add-to-cart support, including variable products.
   Current config depth: button label, modal label visibility, close button visibility, close label, loading text, AJAX error text, SKU label and view-product CTA.
4. `Frequently Bought Together`
   Implemented as a native cross-sell bundle section with product-level configuration, checkbox selection, total price and one-click add-all flow.
   Current config depth: title visibility, product price visibility, empty state rendering, total label and CTA copy.

## Batch 3, merchandising and richer PDP content

These are useful, but they should land after the commercial funnel is stable:

1. `Badge Management`
   Implemented with automatic and manual badges for sale, newness, low stock and bestseller signals.
   Current config depth: badge shape, casing, manual and secondary badge styles, thresholds and per-view limits.
2. `Brands Add-On`
   Implemented as a separate merchandising taxonomy, distinct from compliance-oriented manufacturer data.
   Current config depth: label visibility, brand separator and optional linking to brand archive pages.
3. `Tab Manager`
   Implemented with extra product tabs and optional global tabs for shipping and returns content.
   Current config depth: per-tab enablement for the built-in global tabs and configurable priorities for product/global tabs.
4. `Featured Video`
   Implemented with YouTube, Vimeo and MP4 embeds in the PDP media area.
   Current config depth: section title, intro text, placement and optional heading or intro visibility.
5. `Product Gallery & Image Zoom`
   Implemented with lightweight hover zoom and click lightbox for the native WooCommerce gallery.
   Current config depth: zoom scale, lightbox toggle, dialog label, close label and backdrop-close behaviour.
6. `Product Slider Carousel`
   Implemented as a scroll-snap slider for related, upsell, sale and featured products.
   Current config depth: section visibility, intro text, empty state, product image or name visibility and editable view-all CTA text or target.

## Batch 4, advanced commercial models

More complex modules with deeper stock, order and fulfilment implications:

1. `Product Add-Ons`
   Implemented with configurable fields per product, cart price modifiers and order item metadata.
   Typical use cases: grawer, pakowanie na prezent, rozszerzona gwarancja, montaż, wniesienie, pakiet serwisowy lub dopłata za wariant usługi.
   Current config depth: checkbox, select, text and textarea fields, per-field descriptions, placeholders, max length, optional or required state and module-level UI labels.
2. `Pre-Order`
   Implemented with product-level enablement, release date messaging and custom pre-order CTA.
   Current config depth: notice title, notice visibility, date format, mixed-cart rule and CTA copy.
3. `Waitlist`
   Implemented with email signup, waitlist storage and automatic back-in-stock notifications.
   Current config depth: form title, intro visibility, field labels, placeholders, validation or access messages, success text and email subject or body copy.
4. `Subscription`
   Implemented with product-level subscription config, My Account management, renewal reminders and renewal order generation.
   Current config depth: storefront labels, section visibility, cycle wording, period labels, account title visibility, account table headings or columns, status labels, action labels, success notices and email subjects.
5. `Gift Cards`
   Implemented with gift card products, code generation, recipient email delivery, balance tracking and redemption in cart/checkout.
   Current config depth: product form labels, title visibility, redeem form labels, account title visibility, account table headings or columns, status labels, fee labels and success or error notices.
6. `Product Bundles`
   Implemented with product-level bundle composition, optional items, package discount and grouped cart/order metadata.
   Current config depth: selection and success notices, bundle total label, included-item label and quantity output formatting.

## Batch 5, optional growth modules

These should stay last because they are either lower fit or need business-specific strategy:

1. `WooCommerce Affiliates`
   Implemented as a lightweight referral layer with partner links, order attribution, commission stats and My Account dashboard.
   Current config depth: dashboard title or intro visibility, referral link visibility, stats visibility, table visibility, table headings or columns, account labels and referral attribution settings.
2. `Infinite Scrolling`
   Implemented as a native archive enhancement with button and auto-load modes.
   Current config depth: button label, loading, error and end messages, status visibility and optional button rendering in auto mode.
3. `Popup`
   Current config depth: title, content, CTA, heading visibility, close button visibility, close label, page targeting, fallback CTA target, delay and frequency control.

## Implementation notes

- Prefer native `polski` settings, repositories, services and templates, not one-to-one plugin ports.
- Keep module JS progressive and minimal.
- Reuse legal pages, consent logging and admin shell where possible.
- Keep WooCommerce hooks server-side first, then add JS only for interaction.
- Every module should expose product-level overrides to avoid store-wide blunt toggles.
