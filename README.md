# Spolszczony

**Polish e-commerce legal compliance for WooCommerce**

[![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue)](https://wordpress.org)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-purple)](https://woocommerce.com)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4)](https://php.net)
[![License](https://img.shields.io/badge/License-GPLv2-green)](https://www.gnu.org/licenses/gpl-2.0.html)

Spolszczony is a comprehensive compliance plugin that makes your WooCommerce store fully compliant with Polish and EU e-commerce law. It serves as a single hub consolidating all legal, tax, and display requirements for the Polish market.

**Author:** [WP Poland](https://wppoland.com)

---

## Features (FREE)

### Price Display & Omnibus Directive
- **Unit prices (cena jednostkowa)** — per-unit pricing as required by Polish consumer law
- **Omnibus Directive compliance** — automatic 30-day lowest price tracking and display on sale products
- **Tax display (brutto/netto)** — configurable VAT notice with rate display
- **Small business exemption** — zwolnienie podmiotowe (art. 113 ust. 1 ustawy o VAT) notice
- **Shipping costs notice** — configurable shipping cost link on product pages
- **Price labels** — customizable price suffixes and sale labels

### Checkout Compliance
- **Order button text** — "Zamawiam z obowiazkiem zaplaty" as required by Polish law
- **Legal checkboxes** — configurable consent checkboxes for checkout, registration, reviews, and pay-for-order
- **Consent logging** — GDPR-compliant consent audit trail with IP, user agent, and timestamps
- **Terms & conditions** — mandatory acceptance with link to legal pages
- **Privacy policy checkbox** — GDPR consent at checkout
- **Contract helper** — delayed payment flow with order confirmation before payment

### Consumer Rights & Withdrawal
- **14-day withdrawal right** — online withdrawal form (prawo odstapienia od umowy)
- **EU withdrawal button** — one-click withdrawal request from order history (EU Directive 2023/2673)
- **Withdrawal confirmation emails** — automated email flow for withdrawal requests
- **Withdrawal exemptions** — per-product exemption for digital goods, perishables, etc.
- **Withdrawal tracking** — admin dashboard for managing withdrawal requests

### Legal Pages
- **Auto-generate legal pages** — Regulamin, Polityka prywatnosci, Polityka zwrotow, Reklamacje
- **Legal page attachments** — attach legal pages to order emails (plain text or PDF)
- **Dispute resolution notice** — EU ODR platform information in footer

### Product Information
- **Delivery times** — per-product and per-variation delivery time display with default fallback
- **Manufacturer information** — manufacturer name and details (GPSR compliance)
- **Safety documents** — product safety instructions and attachments
- **Power supply information** — energy consumption data for appliances
- **Defect description** — transparency notices for defective/refurbished products
- **GTIN/EAN** — barcode support for product identification

### Food Products
- **Nutrients table** — nutritional values display (per 100g/100ml)
- **Allergen information** — allergen warnings and declarations
- **Ingredients list** — full ingredients display
- **Nutri-Score** — health rating badge (A-E)
- **Net filling quantity** — package content weight/volume
- **Alcohol content** — percentage display for beverages
- **Place of origin** — country/region of origin
- **Food distributor** — responsible distributor information

### Emails
- **Order confirmation with legal attachments** — terms, privacy, withdrawal policy
- **Withdrawal confirmation email** — automated response to withdrawal requests
- **Double opt-in (DOI)** — email verification for customer registration
- **Custom email templates** — cancelled order, paid order, processing order emails

### Payment Gateways
- **Invoice payment (Przelew/Faktura)** — bank transfer payment with legal compliance
- **Payment gateway enhancements** — legal notices and compliance for all gateways

### Tax & VAT
- **Brutto/netto toggle** — store-wide price display mode
- **VAT rate display** — "w tym 23% VAT" notices
- **Small business (ZP)** — art. 113 exemption notice
- **OSS support** — One Stop Shop compliance for EU cross-border sales
- **Split tax calculation** — separate shipping and fee tax handling

### Shortcodes
- `[spolszczony_withdrawal_form]` — withdrawal request form
- `[spolszczony_payment_methods]` — payment method information
- `[spolszczony_complaints]` — dispute resolution notice
- `[spolszczony_unit_price]` — product unit price
- `[spolszczony_delivery_time]` — product delivery time
- `[spolszczony_tax_notice]` — VAT information
- `[spolszczony_shipping_notice]` — shipping cost notice
- `[spolszczony_omnibus_price]` — lowest 30-day price
- `[spolszczony_manufacturer]` — manufacturer info
- `[spolszczony_safety_info]` — GPSR safety information
- `[spolszczony_nutrients]` — nutrition facts table

### Admin & UX
- **React-based settings panel** — modern admin UI with live preview
- **Setup wizard** — guided first-run configuration with smart Polish defaults
- **Compliance dashboard** — one-click compliance check showing green/red status
- **Admin notes** — contextual guidance, update notifications, compatibility alerts
- **Product meta box** — "Spolszczony" tab in product editor with all compliance fields
- **Bulk edit support** — edit Spolszczony fields across multiple products
- **CSV import/export** — Spolszczony fields in WooCommerce product CSV

### Plugin Integrations
- **Omnibus plugins** — WC Price History (kkarpieszuk) and Omnibus (iworks) with built-in fallback
- **WP Desk** — Flexible Checkout Fields, Flexible Cookies, GPSR for WooCommerce
- **Payment gateways** — Przelewy24, PayU, Tpay, BLIK, Autopay
- **Consent** — Simple Consent Mode (iworks)
- **Page builders** — Elementor widgets for all product display elements
- **Multilingual** — WPML and Polylang compatibility
- **Caching** — W3 Total Cache, WP Super Cache, LiteSpeed cache flush
- **Typography** — recommends Sierotki (iworks) for Polish orphan characters

### Technical
- **WooCommerce HPOS** — native Custom Order Tables support
- **WooCommerce Blocks** — Cart and Checkout Blocks integration via Store API
- **Gutenberg blocks** — native blocks for all product display elements
- **REST API** — full CRUD API for settings, checkboxes, withdrawals, delivery times
- **WP-CLI commands** — database migrations and maintenance via CLI
- **Template overrides** — all templates overridable from theme (`yourtheme/spolszczony/`)

---

## PRO Features

### PDF Invoices (Polish Tax Law)
- **Faktura VAT** — compliant with Polish tax regulations
- **Faktura korygujaca** — correction/cancellation invoices
- **Paragon** — receipt generation
- **Invoice numbering** — configurable sequential numbering (FV/2026/04/001)
- **PDF generation** — customizable templates with visual editor
- **Bulk operations** — bulk invoice generation and printing
- **Packing slips** — per-shipment packing slip generation
- **Email delivery** — automatic invoice attachment to order emails

### KSeF (Krajowy System e-Faktur)
- **API integration** — submit invoices to the Polish National e-Invoice System
- **Digital signatures** — qualified electronic signature support
- **Async queue** — Action Scheduler-based submission with retry logic
- **Status dashboard** — monitor KSeF submission status, accepted/rejected invoices
- **Batch submission** — bulk submit pending invoices

### NIP Validation
- **Polish NIP checksum** — local validation before API call
- **GUS/REGON API** — verify NIP against the Polish government database
- **VIES validation** — EU VAT ID verification for B2B
- **Auto-fill company data** — populate company name and address from NIP
- **Validation cache** — reduce API calls with smart caching
- **Checkout integration** — real-time NIP validation in checkout

### Multi-Step Checkout
- **Modern React-based UI** — stepped checkout flow with progress indicator
- **Address step** — billing and shipping with NIP/company fields
- **Shipping step** — carrier selection with pickup point picker
- **Payment step** — payment method selection
- **Review step** — order summary with legal confirmations
- **Mobile-first** — responsive design optimized for mobile

### Shipping Integrations
- **InPost (Paczkomaty)** — label generation, pickup point map picker, tracking
- **DPD** — domestic and international shipping labels
- **DHL** — label generation and tracking
- **Poczta Polska** — Polish national postal service integration
- **Orlen Paczka** — Orlen pickup point network
- **Pickup point picker** — interactive map widget for carrier point selection

### Accounting Integrations
- **wFirma** — invoice sync, customer sync, payment status
- **Fakturownia** — automatic invoice export
- **iFirma** — invoice and expense sync

### Legal Text Generator
- **Regulamin (Terms)** — auto-generate compliant terms and conditions
- **Polityka prywatnosci** — privacy policy generator
- **Polityka zwrotow** — return/withdrawal policy generator
- **Template variables** — dynamic company data insertion

### Advanced Consent Management
- **GDPR audit trail** — full consent history with export capability
- **Consent versioning** — track which version of terms was accepted
- **Data export** — GDPR-compliant personal data export
- **Consent withdrawal** — customer self-service consent management

### Fiscal Printer Integration
- **Paragon fiskalny** — direct printing to fiscal devices
- **Supported devices** — Posnet, Elzab, Novitus drivers

---

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 6.4+ |
| WooCommerce | 8.0+ |
| PHP | 8.1+ |

## Installation

1. Upload `spolszczony` to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu
3. Navigate to **Spolszczony** in the admin menu
4. Follow the setup wizard to configure your store

## Development

```bash
# Clone the repository
git clone git@github.com:wppoland/spolszczony.git
cd spolszczony

# Install dependencies
composer install
npm install

# Start development environment
npm run env:start

# Build assets
npm run build

# Run tests
composer test
npm run test:e2e

# Static analysis
composer analyse
```

## Plugin Ecosystem

Spolszczony works as a **compliance hub** that integrates with the best Polish WordPress plugins:

| Plugin | Author | Integration |
|--------|--------|-------------|
| [WC Price History](https://wordpress.org/plugins/wc-price-history/) | kkarpieszuk | Omnibus directive |
| [Omnibus](https://pl.wordpress.org/plugins/omnibus/) | iworks | Omnibus directive |
| [Sierotki](https://pl.wordpress.org/plugins/sierotki/) | iworks | Polish typography |
| [Simple Consent Mode](https://pl.wordpress.org/plugins/simple-consent-mode/) | iworks | GTM consent |
| [Flexible Checkout Fields](https://wordpress.org/plugins/flexible-checkout-fields/) | WP Desk | Checkout customization |
| [Flexible Cookies](https://pl.wordpress.org/plugins/flexible-cookies/) | WP Desk | Cookie consent |
| [GPSR for WooCommerce](https://wordpress.org/plugins/gpsr-for-woocommerce/) | WP Desk | Product safety |

## Architecture

- **PHP 8.1+** — enums, typed properties, readonly, union types, named arguments
- **DI Container** — lightweight dependency injection (~150 lines, no framework)
- **Service Layer** — business logic in service classes, data access via repositories
- **Hook Subscribers** — all WordPress hooks registered via `HasHooks` interface
- **Shopmark System** — configurable display elements with priority ordering
- **Integration Manager** — auto-detects third-party plugins at boot
- **Template Loader** — theme-overridable templates (`yourtheme/spolszczony/`)
- **React Admin SPA** — modern settings panel built on `@wordpress/components`
- **Gutenberg Blocks** — server-side rendered blocks with `block.json`
- **REST API** — full CRUD under `spolszczony/v1/` namespace

## License

GPLv2 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
