=== Polski for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, gdpr, omnibus, gpsr, ksef
Requires at least: 6.4
Tested up to: 7.0
Stable tag: 1.23.1
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce for Polish stores: GPSR, Omnibus 30-day price, GDPR consent, withdrawal forms, KSeF hooks, unit prices and storefront modules.

== Description ==

**Polski for WooCommerce** is a free WooCommerce plugin for Polish stores. It helps with GPSR product safety information, Omnibus 30-day lowest price history, GDPR consent, withdrawal forms, unit prices, KSeF-ready invoicing hooks, DSA reporting, and storefront modules.

Built for Polish online stores, dropshippers, and agencies, it keeps the most common Polish and EU requirements in one place and lets you turn each module on or off as you need it.

This plugin helps you configure store workflows related to Polish and EU market practices. It does not provide legal advice or guarantee compliance. Always review your setup for your own business, products, and obligations.

= Documentation and useful links =

* **Documentation** - https://plogins.com/pl/polski/docs/
* **Plugin page (Polish)** - https://plogins.com/pl/polski/
* **Plugin page (English)** - https://plogins.com/polski/
* **Source code (GitHub)** - https://github.com/wppoland/polski
* **Report issues or request features** - https://github.com/wppoland/polski/issues
* **Discuss ideas and questions** - https://github.com/wppoland/polski/discussions

= Why Polski for WooCommerce? =

* **Broad module set** - GPSR-related product fields, Omnibus price history, consent checkboxes, withdrawal requests, product data, and storefront modules in one plugin
* **Built for Polish stores** - Focused on WooCommerce setups that sell in Poland
* **Free and open source** - Core product, checkout, and storefront tools included
* **Modern codebase** - PHP 8.1+, React admin panel, REST API, WP-CLI support
* **Block-ready** - Full WooCommerce Blocks checkout and cart compatibility
* **HPOS compatible** - Works with WooCommerce High-Performance Order Storage

= Recent Tools Added =

* **Store health monitor** - Passive background monitoring of front-end fatal errors, the checkout failure rate, and sales anomalies, with email and webhook alerts and a status dashboard
* **GPSR-related product fields** - Manufacturer, importer, EU responsible person, product identifiers, safety warnings, and instructions with bulk CSV import or export
* **Withdrawal request flow** - My Account withdrawal action with confirmation page, request logging, email confirmation, and audit trail
* **DSA report tools** - Contact point settings, illegal content report form via shortcode [polski_dsa_report], admin report management page, and email notifications
* **KSeF integration hooks** - NIP-based order flagging, action hooks for invoice plugin integration, and order list status column
* **Security incidents** - Incident log for vulnerabilities, breaches, payment failures, third-party outages, and internal follow-up with CSV export
* **Product sustainability fields** - Eco claim basis, certificate link, and expiry date fields
* **Verified purchase badge** - Green badge on product reviews from customers who actually purchased the product

= Checkout and Consent =

* **Consent checkboxes** - Configurable consent checkboxes at checkout, registration, and reviews with full audit trail
* **Omnibus price history** - Automatic 30-day lowest price display on sale products
* **Right of withdrawal** - Withdrawal and return request flow with email confirmations
* **Double opt-in** - Email verification for customer registration (GDPR best practice)
* **Store pages** - Attach terms, privacy policy, and revocation content to WooCommerce emails
* **Dispute resolution** - ODR platform notice for your imprint/terms page
* **Consent audit trail** - Logging of customer consents with timestamps, IP, and context

= Product Display (Shopmarks) =

* **Unit prices** - Display price per kg, litre, metre, or any custom unit
* **Delivery times** - Show estimated delivery times on product pages and listings
* **Tax notices** - Display gross/net price information and VAT rate
* **Price display** - Customize how prices appear across your entire shop

= Storefront Features =

* **Wishlist** - Save favorite products for later
* **Product compare** - Side-by-side product comparison
* **Waitlist** - Back-in-stock email notifications
* **Quick view** - Lightbox product preview from listings
* **Gallery zoom** - Enhanced product image zoom
* **Featured video** - Display product videos on the product page
* **Product slider** - Carousel display for product collections
* **Infinite scroll** - Load more products automatically on archive pages
* **Product tab manager** - Customize product page tabs
* **AJAX product filters** - Dynamic product filtering without page reload
* **AJAX search** - Live product search
* **Product badges** - Sale, new, featured, and custom badges
* **Promotional popups** - Targeted popup campaigns

= Food and Grocery =

* **Food product information** - Ingredients, nutrition facts, and allergen declarations
* **Optional food labelling fields** - Configure origin, distributor, alcohol, and nutrition display where needed

= Admin and Developer Tools =

* **Modern React admin panel** - Module management with per-module settings pages
* **REST API** - Full API for settings, checkboxes, legal pages, withdrawals, and search
* **WP-CLI commands** - Manage Polski from the command line
* **CSV import/export** - Bulk product data management including GPSR and green claim fields
* **Shortcodes** - Embed notices, withdrawal forms, GPSR info, DSA report form, and more
* **Database migrations** - Safe, versioned schema updates
* **Integration hooks** - KSeF action hooks, filters, and compatibility with popular plugins
* **Expanded audit scope** - Includes DPA registry, DSA, KSeF-ready, anti-greenwashing, verified reviews, and security incident coverage
* **Incident logging** - Record store-side security incidents and export them for internal reviews

== Installation ==

= Automatic Installation =

1. Go to **Plugins > Add New** in your WordPress admin.
2. Search for **Polski for WooCommerce**.
3. Click **Install Now** and then **Activate**.
4. Navigate to the new **Polski** menu item in your sidebar.

= Manual Installation =

1. Download the plugin ZIP file from the WordPress.org repository.
2. In your WordPress admin, go to **Plugins > Add New > Upload Plugin**.
3. Choose the ZIP file and click **Install Now**.
4. Click **Activate Plugin**.

== Getting Started ==

Follow these steps to configure the plugin for a Polish store. Always consult a qualified lawyer for your specific situation:

1. **Verify Legal Pages**: Go to **Polski > Modules** and ensure **Legal Pages** is active. Go to its settings and select your Terms, Privacy Policy, and Withdrawal pages.
2. **Configure Checkboxes**: Go to **Polski > Modules > Legal Checkboxes** (ensure it's active) and enable the required checkboxes for checkout (Terms, Privacy, Withdrawal).
3. **Set VAT Rates**: Ensure you have correct Polish VAT rates (23%, 8%, 5%, 0%) configured in **WooCommerce > Settings > Tax**.
4. **Unit Prices**: For products sold by weight or volume, enter the unit pricing data in the **Polski** tab within the product editor.
5. **Omnibus**: The plugin tracks the lowest price from the last 30 days once a product goes on sale. Review the output and adjust the display in **Polski > Modules > Omnibus**.
6. **GPSR**: If you sell physical goods, fill in the Manufacturer and Responsible Person details in the **Polski** tab of your products and review which information should appear on the product page.

== Configuration ==

The plugin is modular. You can enable or disable features based on your needs:

* **Product information**: GPSR fields, unit prices, delivery times, and food data.
* **Checkout and consent**: consent checkboxes, withdrawal flows, and legal page tools.
* **Storefront**: Wishlist, Compare, Search, Filters, and Badges.

Each active module with configuration options will appear as a sub-menu under **Polski** or have a "Settings" link on the Modules page.

== Frequently Asked Questions ==

= Is Polski for WooCommerce free? =

Yes. Polski for WooCommerce is free and open source under GPLv2 or later.

= Does Polski support GPSR for WooCommerce products? =

Yes. Polski includes 8 dedicated product fields for GPSR-related data, bulk CSV import or export, a status column in the product list, and product page display tools. You should review which fields and presentation are appropriate for your own products and obligations.

= Does it support withdrawal forms for WooCommerce orders? =

Yes. Polski adds a withdrawal action directly in My Account > Orders for eligible orders. The customer opens a confirmation page, submits the request, then receives confirmation and the request is logged in the audit trail.

= Is Polski ready for KSeF workflows in WooCommerce? =

Polski can flag orders that may require KSeF invoicing based on NIP in billing data and provides action hooks (`polski/ksef/invoice_ready`, `polski/ksef/is_required`) for invoice plugin integration. A KSeF status column appears in the orders list.

= Does Polski support GDPR consent workflows for Polish WooCommerce shops? =

Yes. Polski includes configurable consent checkboxes, consent logging, double opt-in registration, and related data-handling tools that can support GDPR workflows. Review the configuration for your own store and obligations.

= Does it support Omnibus price history for WooCommerce sale products? =

Yes. Polski tracks and displays the lowest price from the last 30 days on sale products. Review the output and pricing workflow for your own store before relying on it in production.

= Does Polski work with WooCommerce Blocks checkout? =

Yes. Polski fully supports both the classic and block-based checkout and cart.

= Does Polski work with HPOS (High-Performance Order Storage)? =

Yes. Polski declares full compatibility with WooCommerce HPOS (Custom Order Tables).

= Where can I report bugs or suggest features? =

Please use the WordPress.org support forum for support and feature suggestions.

= Is there a simple feedback form for non-technical users? =

Yes. The plugin admin includes a simple feedback form that stores messages locally in WordPress. Do not include passwords, licence keys, or customer personal data in that form.

= What is the difference between deactivation and uninstall? =

Deactivating Polski keeps your settings and stored data. Uninstalling removes the plugin files. Plugin data is deleted only if the remove-data-on-uninstall setting is enabled.

== External Services ==

= GUS REGON API (Polish Central Statistical Office) =

When the NIP Lookup module is enabled, this plugin connects to the GUS REGON public registry to retrieve company data based on the NIP (tax ID) entered by the user. This connection is made only when the user explicitly triggers a lookup.

* Data sent: NIP number
* Service URL: [https://wyszukiwarkaregon.stat.gov.pl/](https://wyszukiwarkaregon.stat.gov.pl/)
* Service terms: [https://api.stat.gov.pl/Home/RegulaminBIR](https://api.stat.gov.pl/Home/RegulaminBIR)
* Service privacy policy: [https://bip.stat.gov.pl/](https://bip.stat.gov.pl/)

= Google OAuth =

When the Social Login module is enabled and Google login is configured, customers who click **Continue with Google** are redirected to Google for authentication. The plugin exchanges the authorization code for an access token and retrieves profile data so the customer can sign in or create an account.

* Data sent: redirect URI, client ID, authorization code, and access token for profile retrieval
* Data received: Google account ID, email address, full name, first name, and last name
* Service URL: [https://accounts.google.com/](https://accounts.google.com/)
* Service terms: [https://policies.google.com/terms](https://policies.google.com/terms)
* Service privacy policy: [https://policies.google.com/privacy](https://policies.google.com/privacy)

= Facebook OAuth =

When the Social Login module is enabled and Facebook login is configured, customers who click **Continue with Facebook** are redirected to Facebook for authentication. The plugin exchanges the authorization code for an access token and retrieves profile data so the customer can sign in or create an account.

* Data sent: redirect URI, app ID, authorization code, and access token for profile retrieval
* Data received: Facebook account ID, email address, full name, first name, and last name
* Service URL: [https://www.facebook.com/](https://www.facebook.com/)
* Service terms: [https://www.facebook.com/legal/terms](https://www.facebook.com/legal/terms)
* Service privacy policy: [https://www.facebook.com/privacy/policy/](https://www.facebook.com/privacy/policy/)

= Google Tag Manager / Google Analytics =

When the DataLayer module is enabled and a GTM container ID or GA4 measurement ID is configured, the plugin loads Google Tag Manager or Google Analytics scripts on the storefront and pushes ecommerce events based on visitor activity.

* Data sent: page views and ecommerce event data such as product IDs, product names, prices, cart actions, checkout events, and order totals, depending on your configuration
* Service URL: [https://www.googletagmanager.com/](https://www.googletagmanager.com/)
* Service terms: [https://policies.google.com/terms](https://policies.google.com/terms)
* Service privacy policy: [https://policies.google.com/privacy](https://policies.google.com/privacy)

Admin feedback and deactivation feedback are stored locally in WordPress and are not sent to an external service.

== Screenshots ==

1. Module management dashboard with toggles and per-module settings
2. GPSR product safety fields in the product editor
3. GDPR consent checkboxes at checkout with audit trail
4. Omnibus Directive - 30-day lowest price on sale products
5. Withdrawal request action in My Account > Orders
6. DSA illegal content report form (shortcode)
7. AJAX search and filters on the storefront
8. Wishlist, compare, and quick view on product listings

== Changelog ==

= 1.23.1 =
* Translations: completed the bundled translations for German, Czech, Slovak, Ukrainian, Lithuanian, Belarusian and Simplified Chinese - all eight locales are now fully translated, including the new Setup wizard and module search/sort strings.

= 1.23.0 =
* New: Setup wizard. A new Setup tab proposes ready-made scenarios (Polish legal baseline, Food & grocery, Digital goods, B2B & wholesale, Fashion, Conversion boost) and enables the matching modules in one click. Non-destructive: it only switches modules on, never off.

= 1.22.7 =
* Fix: the Polski admin menu icon no longer shifts on hover / when active (removed stray padding).

= 1.22.6 =
* New: the Modules admin screen now has an instant search (matching both module name and description) and sorting (grouped, name A–Z, or enabled first). No page reload.

= 1.22.5 =
* Fix: resolved a fatal error (TypeError) that could occur on order queries, including the WooCommerce admin Orders screen, when the withdrawal query helper received a paginated result object instead of an array. The helper now handles both shapes safely.

= 1.22.4 =
* New: BDO number module. Enter your BDO registration number (Baza Danych o Odpadach) and display it anywhere with the [polski_bdo] shortcode or the BDO number block, for example in the footer. The business identification block can also include the BDO number.

= 1.22.3 =
* Admin: consolidated the five separate settings pages into a single tabbed Settings screen, and moved Withdrawals, Consent records, CRA incidents, SBOM, Complaint template and RODO training documents into a new Reports & Tools hub for a cleaner, shorter menu.
* Admin: sub-grouped the module list by area and added rich help tooltips to every module (what it does and what happens when you enable it); action links are muted while a module is off.
* Fixed: the Polski admin menu icon is now vertically centered with its label.
* Translations: resynced and recompiled all bundled locale catalogs after the settings and menu reorganization.
* Docs: updated the documentation and plugin page links to plogins.com.

For older releases, see [changelog.txt](https://plugins.svn.wordpress.org/polski/trunk/changelog.txt).

== Upgrade Notice ==

= 1.20.1 =
Fixes the admin screens not loading their scripts on some setups, and refreshes all bundled translations.

= 1.6.3 =
Plugin Check cleanup: annotated custom-table queries with justifications. No functional changes.

= 1.6.2 =
WordPress.org review hardening update: safer input handling, expanded external service disclosure, and revised readme wording.
