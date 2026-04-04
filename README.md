<p align="center">
  <img src="docs/wporg-assets/icon-256x256.png" alt="Polski for WooCommerce" width="128" height="128">
</p>

<h1 align="center">Polski for WooCommerce 🇵🇱</h1>

<p align="center">
  <strong>Narzędzia wspomagające dostosowanie sklepu WooCommerce do polskich wymagań.</strong><br>
  GPSR · Omnibus · GDPR · prawo odstąpienia · ceny jednostkowe · moduły sklepowe
</p>

<p align="center">
  <a href="https://wordpress.org"><img src="https://img.shields.io/badge/WordPress-6.4%2B-21759b?logo=wordpress&logoColor=white" alt="WordPress 6.4+"></a>
  <a href="https://woocommerce.com"><img src="https://img.shields.io/badge/WooCommerce-8.0%2B-7f54b3?logo=woocommerce&logoColor=white" alt="WooCommerce 8.0+"></a>
  <a href="https://php.net"><img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.1+"></a>
  <a href="https://www.gnu.org/licenses/gpl-2.0.html"><img src="https://img.shields.io/badge/Licencja-GPLv2-green" alt="GPLv2"></a>
</p>

<p align="center">
  <a href="https://wppoland.com/pl/polski/">📖 Dokumentacja</a> · <a href="https://github.com/wppoland/polski/discussions">💬 Dyskusje</a> · <a href="https://github.com/wppoland/polski/issues">🐛 Zgłoś błąd</a>
</p>

---

Polski for WooCommerce helps adapt a WooCommerce shop to Polish market requirements while adding selected storefront features. The free plugin focuses on product information, checkout tools, and lightweight merchandising.

**Author:** [WPPoland](https://wppoland.com)

---

## Features

### Price Display & Omnibus Directive
- **Unit prices (cena jednostkowa)** - per-unit pricing as required by Polish consumer law
- **Omnibus Directive compliance** - automatic 30-day lowest price tracking and display on sale products
- **Tax display (brutto/netto)** - configurable VAT notice with rate display
- **Small business exemption** - zwolnienie podmiotowe (art. 113 ust. 1 ustawy o VAT) notice
- **Shipping costs notice** - configurable shipping cost link on product pages
- **Price labels** - customizable price suffixes and sale labels

### Checkout Compliance
- **Order button text** - "Order with an obligation to pay" wording required by Polish law
- **Legal checkboxes** - configurable consent checkboxes for checkout, registration, reviews, and pay-for-order, with editable labels, descriptions, and validation copy
- **Consent logging** - GDPR-compliant consent audit trail with IP, user agent, and timestamps
- **Terms & conditions** - mandatory acceptance with link to legal pages
- **Privacy policy checkbox** - GDPR consent at checkout

### Storefront Features
- **AJAX search** - lightweight live product search with SKU support and a shortcode or block surface
- **AJAX filters** - archive filtering by categories, brands, price, stock, sale and attributes
- **Wishlist** - guest and customer wishlists with account integration
- **Compare** - guest and customer compare lists with a table view
- **Quick View** - lightweight product modal for archive browsing
- **Badge Management** - manual and automatic badges for promotions, newness, low stock and bestsellers
- **Tab Manager** - extra product tabs with product-level and global storefront content
- **Featured Video** - embedded YouTube, Vimeo or MP4 video in the product media area
- **Gallery & Zoom** - lightweight hover zoom and product image lightbox
- **Product Slider Carousel** - merchandising slider for related, sale, upsell and featured products
- **Waitlist** - back-in-stock signup for out-of-stock products
- **Infinite Scroll** - lightweight archive pagination replacement with button or automatic product loading
- **Popup** - lightweight promotional popup with page targeting and frequency control

### Consumer Rights & Withdrawal
- **14-day withdrawal right** - online withdrawal form, with configurable form copy, account button, status labels and notices
- **Withdrawal request action** - customer-initiated withdrawal flow from order history with a confirmation step
- **Withdrawal confirmation emails** - automated email flow for withdrawal requests, with configurable subject, heading and body copy
- **Withdrawal exemptions** - per-product exemption for digital goods, perishables, etc.
- **Withdrawal tracking** - admin dashboard for managing withdrawal requests
- **Security incidents** - CRA-oriented incident log for vulnerabilities, data breaches, payment failures, third-party outages, and internal follow-up with CSV export

### Legal Pages
- **Auto-generate legal pages** - Regulamin, Polityka prywatnosci, Polityka zwrotow, Reklamacje
- **Legal page attachments** - attach legal pages to order emails (plain text or PDF)
- **Dispute resolution notice** - EU ODR platform information in footer

### Product Information
- **Delivery times** - per-product and per-variation delivery time display with default fallback
- **Manufacturer information** - manufacturer name and details (GPSR compliance)
- **Brands** - separate merchandising taxonomy for brands, independent from manufacturer/GPSR data, with configurable label visibility, separators and brand archive links
- **Safety documents** - product safety instructions and attachments
- **Power supply information** - energy consumption data for appliances
- **Defect description** - transparency notices for defective/refurbished products
- **GTIN/EAN** - barcode support for product identification

### Food Products
- **Nutrients table** - nutritional values display (per 100g/100ml), with configurable captions, column labels and reference unit
- **Allergen information** - allergen warnings and declarations, with configurable label and visibility
- **Ingredients list** - full ingredients display, with configurable label and visibility
- **Nutri-Score** - health rating badge (A-E)
- **Net filling quantity** - package content weight/volume
- **Alcohol content** - percentage display for beverages
- **Place of origin** - country/region of origin, with configurable label and visibility
- **Food distributor** - responsible distributor information, with configurable label and visibility

### Emails
- **Order confirmation with legal attachments** - terms, privacy, withdrawal policy
- **Withdrawal confirmation email** - automated response to withdrawal requests
- **Double opt-in (DOI)** - email verification for customer registration
- **Double opt-in (DOI)** - email verification for customer registration, with configurable login errors, activation notices and email copy
- **Custom email templates** - cancelled order, paid order, processing order emails

### Checkout & Integrations
- **Checkout toolkit integration** - compatibility layer for selected checkout-field, cookies, and product-data plugins

### Tax & VAT
- **Brutto/netto toggle** - store-wide price display mode
- **VAT rate display** - "w tym 23% VAT" notices
- **Small business (ZP)** - art. 113 exemption notice
- **OSS support** - One Stop Shop compliance for EU cross-border sales
- **Split tax calculation** - separate shipping and fee tax handling

### Shortcodes and Blocks
- `[polski_withdrawal_form]` - withdrawal request form
- `[polski_ajax_search]` - AJAX product search form with configurable labels, field output and REST-backed suggestions
- `[polski_ajax_filters]` - AJAX product filters form with editable field labels, reset behaviour and GET fallback
- `[polski_product_slider]` - merchandising slider for related, upsell, sale or featured products
- `[polski_wishlist]` - wishlist view
- `[polski_compare]` - compare table view
- `[polski_payment_methods]` - payment method information
- `[polski_complaints]` - dispute resolution notice
- `[polski_unit_price]` - product unit price
- Gutenberg blocks for AJAX search, AJAX filters, and product sliders
- Elementor widgets for search, filters, and product sliders
- `[polski_delivery_time]` - product delivery time
- `[polski_tax_notice]` - VAT information
- `[polski_shipping_notice]` - shipping cost notice
- `[polski_omnibus_price]` - lowest 30-day price
- `[polski_manufacturer]` - manufacturer info
- `[polski_safety_info]` - GPSR safety information
- `[polski_nutrients]` - nutrition facts table

### Admin & UX
- **React-based settings panel** - modern admin UI with live preview
- **Compliance dashboard** - one-click compliance check showing green/red status, with configurable admin notices, status labels and onboarding checklist copy
- **Expanded site audit** - central compliance review for legal pages, dark patterns, DPA registry, DSA, KSeF-ready, anti-greenwashing, and security incidents
- **Admin notes** - contextual guidance, update notifications, compatibility alerts, with configurable onboarding note copy
- **Incident logging** - lightweight store-side security incident register with status tracking and CSV export for internal reviews
- **Product meta box** - "Polski" tab in product editor with all compliance fields
- **Bulk edit support** - edit Polski fields across multiple products
- **CSV import/export** - Polski fields in WooCommerce product CSV

### Compatibility
- **Omnibus** - native price history tracking and display
- **Verified reviews** - purchase badge works for logged-in customers and guest purchases matched by email
- **Checkout and consent** - compatible with common checkout and cookie consent plugins
- **Page builders** - Elementor widgets for storefront search, filters, slider and product-info output
- **Caching** - compatible with common WordPress cache plugins

### Technical
- **WooCommerce HPOS** - native Custom Order Tables support
- **WooCommerce Blocks** - Cart and Checkout Blocks integration via Store API
- **Gutenberg blocks** - native dynamic blocks for AJAX search, AJAX filters and product slider
- **REST API** - full CRUD API for settings, checkboxes, withdrawals, delivery times
- **WP-CLI commands** - database migrations and maintenance via CLI
- **Smoke test script** - run `wp eval-file scripts/smoke-tests.php --allow-root` inside `wp-env` to verify wizard completion plus core free compliance and storefront flows
- **WordPress 6.7-safe bootstrap** - plugin translations are loaded on `init`, avoiding early text-domain notices in plugin runtime
- **Template overrides** - all templates overridable from theme (`yourtheme/polski/`)

---

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 6.4+ |
| WooCommerce | 8.0+ |
| PHP | 8.1+ |

## Installation

### From WordPress Dashboard
1. Go to **Plugins > Add New**.
2. Search for **Polski for WooCommerce**.
3. Click **Install Now** and then **Activate**.
4. Navigate to the new **Polski** menu item in your sidebar.

### Manual Installation
1. Download the plugin ZIP file.
2. In your WordPress admin, go to **Plugins > Add New > Upload Plugin**.
3. Choose the ZIP file and click **Install Now**.
4. Click **Activate Plugin**.

## Getting Started

Follow these steps to configure the plugin for a Polish store. Always consult a qualified lawyer for your specific situation:

1. **Verify Legal Pages**: Go to **Polski > Modules** and ensure **Legal Pages** is active. Go to its settings and select your Terms, Privacy Policy, and Withdrawal pages.
2. **Configure Checkboxes**: Go to **Polski > Modules > Legal Checkboxes** (ensure it's active) and enable the required checkboxes for checkout (Terms, Privacy, Withdrawal).
3. **Set VAT Rates**: Ensure you have correct Polish VAT rates (23%, 8%, 5%, 0%) configured in **WooCommerce > Settings > Tax**.
4. **Unit Prices**: For products sold by weight or volume, enter the unit pricing data in the **Polski** tab within the product editor.
5. **Omnibus**: The plugin automatically tracks the lowest price from the last 30 days once a product goes on sale. No manual setup is required, but you can customize the display in **Polski > Modules > Omnibus**.
6. **GPSR (2024)**: If you sell physical goods, fill in the Manufacturer and Responsible Person details in the **Polski** tab of your products to comply with the General Product Safety Regulation.

## Configuration

The plugin is modular. You can enable or disable features based on your needs:

* **Compliance**: GDPR, Omnibus, GPSR, DSA, Withdrawal flow, and security incident logging.
* **Shopmarks**: Unit prices and delivery times.
* **Storefront**: Wishlist, Compare, Search, Filters, and Badges.

Each active module with configuration options will appear as a sub-menu under **Polski** or have a "Settings" link on the Modules page.

## Development

```bash
# Clone the repository
git clone git@github.com:wppoland/polski.git
cd polski

# Install dependencies
composer install
npm install

# Start development environment
npm run env:start
npm run env:cli -- wp plugin list

# Build assets
npm run build

# Run tests
composer test
npm run test:e2e

# Static analysis
composer analyse
```

### Local smoke checks

For module work, keep a lightweight runtime check in the same pass as the code change:

1. Start `wp-env`.
2. Verify plugin activation with `npm run env:cli -- wp plugin list`.
3. Check migrations with `npm run env:cli -- wp db query "SELECT version FROM wp_polski_migrations ORDER BY version;"`.
4. Open the affected storefront URL with `curl` and look for PHP fatals before treating the module as done.
5. For customer-facing flows, submit at least one real request in local `wp-env` and confirm DB persistence when the feature stores data.

## Support

- Use the WordPress.org support forum for release support and setup questions.
- Use [GitHub Discussions](https://github.com/wppoland/polski/discussions) for product ideas and implementation questions.
- Use [GitHub Issues](https://github.com/wppoland/polski/issues) only for reproducible bugs and concrete development tasks.

## Plugin Ecosystem
Polski for WooCommerce works as a compliance hub for Polish WooCommerce stores and stays compatible with common checkout, consent and cache plugins.

## Architecture

- **PHP 8.1+** - enums, typed properties, readonly, union types, named arguments
- **DI Container** - lightweight dependency injection (~150 lines, no framework)
- **Service Layer** - business logic in service classes, data access via repositories
- **Hook Subscribers** - all WordPress hooks registered via `HasHooks` interface
- **Shopmark System** - configurable display elements with priority ordering
- **Integration Manager** - auto-detects third-party plugins at boot
- **Template Loader** - theme-overridable templates (`yourtheme/polski/`)
- **React Admin SPA** - modern settings panel built on `@wordpress/components`
- **Gutenberg Blocks** - server-side rendered blocks with `block.json` for selected storefront modules
- **REST API** - full CRUD under `polski/v1/` namespace

## License

GPLv2 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
