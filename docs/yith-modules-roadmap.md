# YITH Modules Roadmap for Spolszczony

## Goal

Turn the most useful YITH plugin ideas into native `spolszczony` modules, without copying functionality that already exists in free or PRO.

The target is full functional coverage of the YITH plugin set selected for this project, but implemented as native `spolszczony` modules, shared services, or integrations.

## Parity rule

`Spolszczony` should expose the business capability of each relevant YITH plugin, even if the UX, settings layout, storage model or code structure is different.

That means:

- we aim for feature parity, not a 1:1 port of plugin code or admin screens,
- overlapping YITH features can be served by one stronger native module,
- existing `spolszczony` or `spolszczony-pro` features count as covered if they meet the same business need,
- each YITH plugin should have an explicit coverage status: `covered`, `planned`, `partial`, or `not planned`.

## Coverage matrix

| YITH plugin | Coverage target in Spolszczony | Status |
|--------|--------|--------|
| Request a Quote | Native quote workflow, quote-only products, B2B lead capture, admin inbox, privacy consent | covered |
| WooCommerce Catalog Mode | Native catalogue-only mode, hidden prices, role/customer rules, quote pairing | planned |
| WooCommerce Ajax Search | Native fast AJAX search, SKU support, result templates, mobile search UX | planned |
| WooCommerce Ajax Product Filter | Native layered filtering with attributes, taxonomies, stock, price, AJAX updates | planned |
| WooCommerce Wishlist | Native wishlist with guest/user support and account integration | planned |
| WooCommerce Compare | Native product compare table with attributes and differences | planned |
| WooCommerce Quick View | Native quick view modal with variation support | planned |
| Frequently Bought Together | Native bundle suggestions and add-all CTA | planned |
| WooCommerce Badge Management | Native badges with smart conditions and Polish merchandising defaults | planned |
| WooCommerce Brands Add-On | Native brand taxonomy and storefront rendering | planned |
| WooCommerce Tab Manager | Native extra tabs, conditional tabs, product/category level overrides | planned |
| WooCommerce Featured Video | Native featured video in gallery and PDP media area | planned |
| WooCommerce Product Gallery & Image Zoom | Native gallery enhancements and zoom support | planned |
| WooCommerce Product Slider Carousel | Native merchandising sliders and product collections | planned |
| WooCommerce Product Add-Ons | Native configurable product extras and price modifiers | planned |
| Pre-Order for WooCommerce | Native pre-order workflow with dates, messaging and stock rules | planned |
| WooCommerce Waitlist | Native back-in-stock and waiting list flow | planned |
| WooCommerce Subscription | Native recurring billing module or dedicated PRO extension | planned |
| WooCommerce Gift Cards | Native gift card purchase, balance and redemption flow | planned |
| WooCommerce Product Bundles | Native bundle builder and bundle pricing | planned |
| WooCommerce Affiliates | Native affiliate/referral layer only if product strategy confirms the need | partial |
| Infinite Scrolling | Native archive UX enhancement, optional and theme-sensitive | partial |
| WooCommerce Popup | Native promotional or lead popup system only if needed by growth stack | partial |
| WooCommerce Order & Shipment Tracking | Covered by shipping providers and tracking modules in PRO | covered |
| PayPal Express Checkout for WooCommerce | Covered by payment integrations, not as a dedicated standalone YITH port | covered |
| Custom Login | Out of core scope unless storefront account UX becomes a roadmap priority | not planned |
| Maintenance Mode | Out of e-commerce compliance scope | not planned |
| Proteo Toolkit / Proteo / Wonder / Slider for page builders | Theme or builder scope, not plugin-core roadmap | not planned |

## Current exclusions

The following YITH plugins should not become standalone modules right now because `spolszczony` already covers them fully or partially:

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
2. `Catalog Mode`
   Best paired with quote requests for hidden-price or dealer-only catalogues.
3. `Ajax Search`
   High impact on product discovery, should stay lightweight and cache-friendly.
4. `Ajax Product Filter`
   Strong fit for larger catalogues, but only after search because filters need good taxonomy and attribute coverage.

## Batch 2, conversion helpers

These features improve consideration and shortlist building without fighting existing compliance logic:

1. `Wishlist`
2. `Compare`
3. `Quick View`
4. `Frequently Bought Together`

## Batch 3, merchandising and richer PDP content

These are useful, but they should land after the commercial funnel is stable:

1. `Badge Management`
2. `Brands Add-On`
3. `Tab Manager`
4. `Featured Video`
5. `Product Gallery & Image Zoom`
6. `Product Slider Carousel`

## Batch 4, advanced commercial models

More complex modules with deeper stock, order and fulfilment implications:

1. `Product Add-Ons`
2. `Pre-Order`
3. `Waitlist`
4. `Subscription`
5. `Gift Cards`
6. `Product Bundles`

## Batch 5, optional growth modules

These should stay last because they are either lower fit or need business-specific strategy:

1. `WooCommerce Affiliates`
2. `Infinite Scrolling`
3. `Popup`

## Implementation notes

- Prefer native `spolszczony` settings, repositories, services and templates, not one-to-one plugin ports.
- Keep module JS progressive and minimal.
- Reuse legal pages, consent logging and admin shell where possible.
- Keep WooCommerce hooks server-side first, then add JS only for interaction.
- Every module should expose product-level overrides to avoid store-wide blunt toggles.
