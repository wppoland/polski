=== Polski for WooCommerce ===
Contributors: wppoland
Donate link: https://wppoland.com/donate
Tags: woocommerce, polish, gdpr, omnibus, gpsr
Requires at least: 6.4
Tested up to: 6.9
Stable tag: 1.4.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adapts WooCommerce to Polish market requirements: GPSR, Omnibus, GDPR, DSA, KSeF-ready, unit prices, withdrawal forms, and storefront tools.

== Description ==

**Polski for WooCommerce** adapts your WooCommerce store to Polish market conditions and current EU regulations. It handles everything Polish shops need -- from product information and checkout adjustments to storefront tools -- in one package.

= Why Polski for WooCommerce? =

* **All-in-one for Polish WooCommerce** - GPSR, Omnibus, GDPR, DSA, withdrawal requests, anti-greenwashing fields, and KSeF-ready hooks in one plugin
* **Built for Polish market** - Adapts WooCommerce to Polish business and regulatory requirements
* **Free and open source** - Product information, checkout adjustments, and storefront modules included
* **Modern codebase** - PHP 8.1+, React admin panel, REST API, WP-CLI support
* **Block-ready** - Full WooCommerce Blocks checkout and cart compatibility
* **HPOS compatible** - Works with WooCommerce High-Performance Order Storage

= Polish Market 2026 (new) =

* **GPSR - General Product Safety Regulation** - Manufacturer, importer, EU responsible person, product identifiers, safety warnings and instructions. Bulk CSV import/export, status column in product list, dedicated product page section
* **Withdrawal request flow** - My Account withdrawal action with confirmation page, automatic request creation, email confirmation and audit trail (EU Directive 2023/2673, mandatory from 19 June 2026)
* **DSA Toolkit** - Digital Services Act: contact point settings, illegal content report form via shortcode [polski_dsa_report], admin report management page with status tracking, email notifications
* **KSeF-ready** - Automatic detection of orders requiring KSeF invoicing based on NIP, action hooks for invoice plugin integration, KSeF status column in orders list
* **Security incidents** - CRA-oriented incident log for vulnerabilities, breaches, payment failures, third-party outages, and internal follow-up with CSV export
* **Anti-greenwashing fields** - Product-level eco claim basis, certificate link, and expiry date fields for the EU greenwashing directive (September 2026)
* **Verified purchase badge** - Green badge on product reviews from customers who actually purchased the product

= Checkout and Consent =

* **GDPR consent checkboxes** - Configurable consent checkboxes at checkout, registration, and reviews with full audit trail
* **Omnibus Directive** - Automatic 30-day lowest price display on sale products (EU Directive 2019/2161)
* **Right of withdrawal** - Complete withdrawal/return form with automated processing and email confirmations
* **Double opt-in** - Email verification for customer registration (GDPR best practice)
* **Store pages** - Attach terms, privacy policy, and revocation content to WooCommerce emails
* **Dispute resolution** - ODR platform notice for your imprint/terms page
* **Consent audit trail** - Logging of customer consents with timestamps, IP, and context

= Product Display (Shopmarks) =

* **Unit prices** - Display price per kg, liter, meter, or any custom unit (required by Polish trade law)
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
* **Polish food labeling** - Meets Polish food information requirements

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
5. **Omnibus**: The plugin automatically tracks the lowest price from the last 30 days once a product goes on sale. No manual setup is required, but you can customize the display in **Polski > Modules > Omnibus**.
6. **GPSR (2024)**: If you sell physical goods, fill in the Manufacturer and Responsible Person details in the **Polski** tab of your products to comply with the General Product Safety Regulation.

== Configuration ==

The plugin is modular. You can enable or disable features based on your needs:

* **Compliance**: GDPR, Omnibus, GPSR, DSA, and Withdrawal flows.
* **Shopmarks**: Unit prices and delivery times.
* **Storefront**: Wishlist, Compare, Search, Filters, and Badges.

Each active module with configuration options will appear as a sub-menu under **Polski** or have a "Settings" link on the Modules page.

== Frequently Asked Questions ==

= Is Polski for WooCommerce free? =

Yes. Polski for WooCommerce is free and open source under GPLv2 or later.

= Does Polski support GPSR (General Product Safety Regulation)? =

Yes. Polski provides 8 dedicated product fields for GPSR data (manufacturer, importer, EU responsible person, product identifiers, safety warnings, instructions), bulk CSV import/export, a status column in the product list, and automatic display on product pages.

= Does it support customer withdrawal requests (EU Directive 2023/2673)? =

Yes. Polski adds a withdrawal action directly in My Account > Orders for eligible orders. The customer opens a confirmation page, submits the request, then receives confirmation and the request is logged in the audit trail.

= Is Polski ready for KSeF? =

Polski automatically detects orders requiring KSeF invoicing based on NIP in billing data and provides action hooks (polski/ksef/invoice_ready, polski/ksef/is_required) for invoice plugin integration. A KSeF status column appears in the orders list.

= Does Polski support GDPR for Polish shops? =

Yes. Polski provides configurable consent checkboxes with a full audit trail, double opt-in registration, and data privacy controls - all designed specifically for Polish GDPR requirements (RODO).

= Does it support the Omnibus Directive (EU Directive 2019/2161)? =

Yes. Polski automatically tracks and displays the lowest price from the last 30 days on sale products, as required by the Omnibus Directive implementation in Polish law.

= Does Polski work with WooCommerce Blocks checkout? =

Yes. Polski fully supports both the classic and block-based checkout and cart.

= Does Polski work with HPOS (High-Performance Order Storage)? =

Yes. Polski declares full compatibility with WooCommerce HPOS (Custom Order Tables).

= Where can I report bugs or suggest features? =

Please use the WordPress.org support forum for release support, [GitHub Discussions](https://github.com/wppoland/polski/discussions) for questions, and [GitHub Issues](https://github.com/wppoland/polski/issues) for reproducible bugs.

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

No other external services are used. Deactivation feedback and all other data are stored locally in WordPress.

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

= 1.4.0 =
* Added "From price" display for variable products (shows "from XX PLN" instead of price range)
* Added minimum order value and quantity rules with cart validation
* Added automated review request emails after order completion
* Added opt-out support for review request emails
* Improved localization: all __() fallback strings now use English source language

= 1.3.0 =
* Added GPSR module: 8 product fields, CSV bulk import/export, status column in product list, product page display
* Added customer withdrawal request flow in My Account (EU Directive 2023/2673)
* Added DSA Toolkit: report form shortcode, admin reports page, email notifications
* Added KSeF-ready module: NIP-based auto-detection, integration hooks, order list column
* Added Security incidents module: CRA-oriented incident log with status tracking and CSV export
* Added verified purchase badge for product reviews
* Expanded Site Audit with DPA, DSA, KSeF-ready, anti-greenwashing, verified review, and security incident checks
* Added anti-greenwashing product fields (eco claim basis, certificate, expiry)
* Added dynamic per-module settings pages in WordPress admin menu
* Fixed GPSR rendering on WooCommerce Blocks single product pages
* Streamlined free version for WordPress.org submission
* 5 language packs: Polish, German, Czech, Slovak, Ukrainian

= 1.1.0 =
* Added storefront modules (compare, quick view, badges, tabs, video, zoom, slider, infinite scroll, popups)
* Added configurable admin and email copy
* Added customer flows for withdrawal and waitlist
* Improved WooCommerce Blocks checkout support

= 1.0.0 =
* Initial release
* GDPR checkboxes, Omnibus Directive, withdrawal forms
* Shopmarks: unit prices, delivery times, tax notices
* Wishlist, waitlist features
* Food product information fields
* REST API, WP-CLI, CSV import/export
* Full Polish translation

== Disclaimer ==

THIS PLUGIN IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.

WPPoland (wppoland.com) shall not be liable for any direct, indirect, incidental, special, consequential or exemplary damages, including but not limited to damages for loss of profits, goodwill, data, or other intangible losses, resulting from the use or inability to use this plugin.

This plugin provides technical tools and templates for WooCommerce stores. It does not constitute legal advice. Review all generated texts and settings before using them in production. Always test in a development or staging environment before deploying to a live store.

WPPoland bears no responsibility for any legal, financial, regulatory, or other consequences arising from the use of this plugin. By installing and activating this plugin, you acknowledge that you do so entirely at your own risk.

== Upgrade Notice ==

= 1.3.0 =
Major update: GPSR, customer withdrawal requests, DSA toolkit, KSeF-ready hooks, verified purchase badges, anti-greenwashing fields, 5 language packs.
