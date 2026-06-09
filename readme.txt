=== Polski for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, gdpr, omnibus, gpsr, ksef
Requires at least: 6.4
Tested up to: 7.0
Stable tag: 1.22.4
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

= 1.22.4 =
* New: BDO number module. Enter your BDO registration number (Baza Danych o Odpadach) and display it anywhere with the [polski_bdo] shortcode or the BDO number block, for example in the footer. The business identification block can also include the BDO number.

= 1.22.3 =
* Admin: consolidated the five separate settings pages into a single tabbed Settings screen, and moved Withdrawals, Consent records, CRA incidents, SBOM, Complaint template and RODO training documents into a new Reports & Tools hub for a cleaner, shorter menu.
* Admin: sub-grouped the module list by area and added rich help tooltips to every module (what it does and what happens when you enable it); action links are muted while a module is off.
* Fixed: the Polski admin menu icon is now vertically centered with its label.
* Translations: resynced and recompiled all bundled locale catalogs after the settings and menu reorganization.
* Docs: updated the documentation and plugin page links to plogins.com.

= 1.22.2 =
* Translations: translated all interface strings added in 1.21.0 and 1.22.0 (Consent Manager, Tag Manager, AI Bridge, Custom Integrations, Safe Fonts, and Custom Triggers) into every bundled locale (Polish, German, Czech, Slovak, Ukrainian, Lithuanian, Belarusian, Simplified Chinese). Recompiled the binary and JavaScript translation files.

= 1.22.1 =
* Fixed (privacy): the shop business-identification ability and the withdrawal annex documents no longer fall back to the site administrator email when no public contact email is configured. They now return an empty email instead, so the administrator address is never exposed through publicly readable output.

= 1.22.0 =
* New: Consent Manager - a native cookie-consent banner with categories (necessary, preferences, analytics, marketing), Google Consent Mode v2 (signals default to denied and update when the visitor chooses), consent-gated script loading, and a records-of-consent log with CSV export. Module is off by default.
* New: unified Tag Manager - load common marketing and analytics tags (such as Meta Pixel, TikTok, Microsoft Ads, Clarity, LinkedIn, Pinterest, X, Matomo, Plausible, PostHog, Hotjar, Inspectlet, Crazy Egg, Simple Analytics) by entering your own IDs. Every tag is loaded only after the matching consent category is granted. Module is off by default.
* New: Custom Integrations - add your own header or footer snippets, each assigned to a consent category so it runs only after that category is granted.
* New: Safe Fonts - help reduce layout shift and defer Google Fonts, with an option to hold the fonts stylesheet until the chosen consent category is granted.
* New: Custom Triggers - push custom data-layer events on page or click conditions, optionally gated by consent.
* All new modules are off by default and integrate with the Consent Manager.

= 1.21.0 =
* New: AI Bridge - exposes read-only store information to AI assistants and agents through the WordPress Abilities API (WordPress 6.9 or newer). Abilities cover product facts, Omnibus price history, GPSR product-safety data, products still missing GPSR data, compliance status and overall store health. Every ability is read-only and protected by a WooCommerce capability check; nothing in your store is changed automatically.
* New: AI product summary - generate a short, plain-language product summary on demand from the product editor. Uses your site's configured AI provider when one is available (WordPress AI Client) and simply does nothing when none is configured.
* New: GPSR safety-text draft helper - generate a draft product-safety text that you review and edit before saving. Drafts only; nothing is published automatically.
* Compatibility: the AI features degrade gracefully when no AI provider is configured, and are forward-compatible with the WordPress AI Client. No third-party AI keys are stored by the plugin.

= 1.20.1 =
* Fixed: the admin screens now load their scripts reliably. The bundled admin and frontend scripts are emitted as classic browser scripts (with their WordPress script dependencies declared) instead of ES modules, so the React-based admin renders correctly across setups.
* Translations: refreshed all bundled locales (Polish, German, Czech, Slovak, Ukrainian, Lithuanian, Belarusian, Simplified Chinese) so every interface string is up to date.

= 1.20.0 =
* New module: Promotions / dynamic pricing (basic). Optional, off by default. Two automatic cart discounts you configure in the module settings: a bulk discount (a percentage off a product line once its quantity reaches a threshold) and a cart discount (a percentage off when the cart subtotal reaches a threshold, applied as a cart fee). Recomputed idempotently from the regular price, safe across WooCommerce's repeated total calculations.

= 1.19.0 =
* New module: Returns and complaints (RMA). Optional, off by default. Customers can open a complaint (reklamacja) or return (zwrot) request for an eligible order from My Account; the request is stored, confirmed by email to the customer and the shop, and managed in a new admin queue (WooCommerce > Polski > Returns & complaints) with status changes (submitted, in progress, resolved, rejected). Mirrors the withdrawal request flow and reuses the consent/order infrastructure. Configurable eligibility window and notification email. Provides tools and templates, not legal advice.
* Storefront module orchestration: unified loader so wishlist, quick-view and other buttons rendered after page load (infinite scroll, AJAX filters, quick-view modal) keep working, with accessibility and performance improvements.

= 1.18.0 =
* Visual identity refresh across the admin: a new Polski brand with self-hosted Schibsted Grotesk and Hanken Grotesk webfonts (font-display swap), a monogram menu icon and a wordmark dashboard header. Admin styling loads only on the plugin's own screens, never on the storefront, to protect Core Web Vitals.
* Storefront structured data: the plugin now augments WooCommerce's own Product and Offer JSON-LD (no duplicate graph) with a priceValidUntil value and, on sale products, the truthful Omnibus lowest 30-day price as a MinimumPrice specification for better rich results and machine readability.
* Expanded localization catalogues for Polish, German, Czech, Slovak, Ukrainian, Simplified Chinese, Belarusian, and Lithuanian, with ongoing translation maintenance.
* Typography cleanup: replaced long dashes with hyphens across interface strings and all translation catalogs for consistent rendering.

= 1.17.0 =
* Store health monitor: new optional module (off by default) for continuous, passive monitoring of store operations. Three sensors run every 5 minutes via WP-Cron: front-end fatal errors (`shutdown` handler, storefront only), the checkout failure rate (observes `woocommerce_checkout_order_processed`, the Store API equivalent, and `woocommerce_order_status_failed` over a rolling 2-hour window), and a sales anomaly check (previous full hour vs the typical order count for the same weekday/hour over the past 8 weeks, evaluated at most once per hour). No synthetic orders are ever placed. Alerts go out by email and optional JSON webhook (Slack/Discord-compatible) with a configurable cooldown; a hard outage also records an entry in the security incident log when that module is enabled. Health dashboard under Reports & Tools with a manual "Run check now", an admin notice when status is not OK, and a read-only REST endpoint `GET /polski/v1/store-health`. Block checkout is covered through the Store API hook. Settings: alert email/webhook, failure-rate threshold and minimum sample, sales anomaly threshold, and alert cooldown.

= 1.15.0 =
* B2B fields: optional "Potrzebuję faktury VAT" toggle, separate from the existing "Buying as a company" checkbox. Polish e-commerce convention treats invoice-need as orthogonal to company-vs-consumer (paragon vs faktura), so the field is its own opt-in. Saves to `_polski_needs_invoice` order meta. Wired through both classic checkout and the WC 8.6+ additional-fields API. New setting `polski_b2b.show_needs_invoice_toggle` (default off).
* Compare: sticky bottom drawer (`polski_compare.show_sticky_bar`, default off) showing thumbnails of compared products with a "Porównaj (N)" CTA and a clear-all button. Hidden on the compare page itself; auto-suppressed when the list is empty. Mobile-responsive CSS.
* Compare: new shortcode `[polski_compare_count]` for the header counter, with `template`, `class`, and `hide_when_empty` attributes. Renders an anchor to the compare page with a `data-polski-compare-count` attribute that the existing AJAX layer can update live.
* AJAX filters: named presets. `[polski_filters preset="b2b"]` shortcode argument loads overrides from the new `polski_filter_presets` option (`[name => array<setting, value>]`); the inner array is merged over the global filter settings before render. Archives can map to a preset via the `polski/filters/archive_preset` filter. Per-preset runtime tweaks via `polski/filters/preset` (preset, name).

= 1.14.1 =
* B2B fields: full IBAN validation. `B2BCheckoutService::isPlausibleIban()` now performs the ISO 13616 mod-97 checksum and a country-code length lookup (PL=28, DE=22, GB=22, FR=27, IT=27, plus 25 more EU/CH/GB markets). Replaces the previous structural-only sanity check.
* DSA: per-IP rate limiting on the report submission handler. Default 5 reports per hour per IP; window and limit are filterable via `polski/dsa/rate_limit_window_seconds` and `polski/dsa/rate_limit_max_attempts`. Source IP is filterable via `polski/dsa/rate_limit_ip` for sites behind a reverse proxy.
* Code quality: tighter Plugin Check compliance in pre-existing modules. CRA `IncidentRepository` now uses `%i` placeholders instead of interpolated `{$table}` queries; `FilterService` documents the read-only GET-based filter context with a scoped `phpcs:disable`/`enable` block instead of leaving a Recommended warning open; `CRAIncidentsPage` adds `wp_unslash()` + `sanitize_key()` before passing `$_POST['kind']` and `$_POST['severity']` to `IncidentKind::tryFrom()` and `Severity::tryFrom()`; `templates/forms/ajax-filters.php` sanitises single-string `$_GET[$key]` reads.

= 1.14.0 =
* B2B checkout fields: Block-checkout support via `woocommerce_register_additional_checkout_field` (WC 8.6+). NIP, REGON, and IBAN now appear in both classic and Block checkouts from a single registration. Values written by the WC additional-fields API are mirrored to legacy `_billing_nip`, `_billing_regon`, `_billing_iban` order meta on save (`woocommerce_set_additional_field_value`) so the existing KSeF and invoice modules pick them up unchanged. The classic-only `woocommerce_billing_fields` path is automatically skipped when the modern API is available, preventing duplicate billing rows. Stores on WC < 8.6 continue to use the classic-only path with the company toggle.
* DSA module: per-product report widget. Optional collapsible "Zgłoś nielegalne treści (DSA)" section on single product pages with the report form prefilled with the product permalink and human-readable name. The form posts to the existing `polski_dsa_report` admin-post handler, so reports flow into the same admin queue as shortcode submissions. Configurable position (after product summary or in product meta block). New filter `polski/dsa/product_widget_enabled`. Defaults `polski_dsa.product_widget_enabled` (off) and `polski_dsa.product_widget_position` (`after_summary`).
* DSA module: defaults populated for `polski_dsa` (`contact_email`, `form_title`, `form_intro`, plus the new widget keys) so admins see seeded values on first activation instead of empty strings.

= 1.13.0 =
* New module: B2B checkout fields. Adds an optional "Buying as a company" toggle plus NIP, REGON, and IBAN fields to WooCommerce classic checkout, with conditional show/hide tied to the toggle. NIP is validated on submit using the official Polish checksum algorithm and saved to standard `_billing_nip` meta so existing KSeF and invoice integrations can pick it up without changes. REGON accepts 9- or 14-digit numbers; IBAN passes a structural sanity check (country prefix + 13-32 alphanumeric body, length 15-34). Settings group `polski_b2b` (`enabled`, `show_company_toggle`, `nip`, `regon`, `iban`). New static utility `Polski\Util\NipValidator` (`isValid`, `normalize`, `format`). If another integration registers a NIP field, Polski skips its own NIP registration to avoid a duplicate field.

= 1.12.0 =
* AI Feed: `/llms.txt` manifest at the site root following the open standard at https://llmstxt.org. AI agents that look for the well-known file at `/llms.txt` now get a Markdown index of the site - title, description, legal pages with `?output_format=md` links, the WooCommerce shop page, and the top product categories. Filters: `polski/ai_feed/llms_txt_enabled`, `polski/ai_feed/llms_txt_sections`, `polski/ai_feed/llms_txt_category_limit`. Setting `polski_ai_feed.llms_txt_enabled` (default `true`).

= 1.11.0 =
* New module: AI Feed. Serves singular posts, pages, and WooCommerce products as Markdown via content negotiation so AI agents and LLM crawlers can ingest store content without scraping HTML. Triggered by `Accept: text/markdown` header or `?output_format=md` query argument. Adds `<link rel="alternate" type="text/markdown">` to single views for discovery and a "View AI Version" row action on the Posts, Pages, and Products list screens.
* AI Feed: product Markdown enriched with Polish-market data - SKU, GTIN/EAN, gross/regular/sale price, currency, tax class, Omnibus lowest price (last 30 days), delivery time, stock quantity and availability, weight, dimensions, brand, manufacturer, GPSR responsible person, and product categories. Front matter exposes the same fields as YAML for structured ingestion.
* AI Feed: filters `polski/ai_feed/enabled`, `polski/ai_feed/post_types`, `polski/ai_feed/post_markdown`, `polski/ai_feed/product_markdown`, `polski/ai_feed/product_facts`, `polski/ai_feed/password_required`. Settings group `polski_ai_feed` (`enabled`, `post_types`). Default post types: `post`, `page`, `product`.

= 1.10.0 =
* New module: OSS observer. Tracks the EU intra-community €10,000 B2C delivery threshold by integrating with the standalone One Stop Shop plugin. One-click install + activation directly from the module row. WooCommerce admin note prompts install when the observer is toggled on without the external plugin present. Exposes the filter `polski_tax_oss_enabled` so custom tax integrations can branch tax logic on OSS state.
* Modules page: redesigned as a WP list-table (Name / Enabled / Description / Edit) with MoSCoW-prioritised grouping - Legal & Compliance, Tax & Pricing, Checkout & Orders, Content & Trust, Advanced & Tools. Pencil icon opens a dedicated settings subpage per bucket (`admin.php?page=polski-group-<bucket>#polski-module-<id>`) registered dynamically for every module with settings, enabled or not.
* Setup wizard: rewritten as a 5-step guided flow (Company > Legal > Tax & OSS > Checkout > Finish). Each step uses toggle rows with inline description panels; optional steps have Skip Step + Continue; OSS toggle on the Tax step triggers One Stop Shop plugin install on Finish.
* Dashboard: "Relaunch setup wizard" button for merchants who want to rerun the guided setup after completion.

= 1.9.1 =
* Compliance checklist: Accessibility (WCAG) section - 9 heuristic rules scanned against the static homepage HTML (html lang, skip link, h1, viewport meta, main landmark, search role, focus outline, autoplay sound, missing img alt). REST: `GET /polski/v1/compliance/accessibility`.
* Compliance checklist: Cookie banner now includes a push-notification prompt detector - flags `Notification.requestPermission`, `PushManager.subscribe` and common third-party push SDKs triggered without user interaction.
* New module: RODO training documentation generator. Admin page `Polski > RODO training docs` downloads three printable HTML templates (training logbook, principles summary, data-breach response playbook). Pre-branded with shop data from the setup wizard.

= 1.9.0 =
* New module: Complaint template generator. Ready-to-print complaint form (formularz reklamacyjny) auto-populated with seller data. Admin page `Polski > Complaint template` with preview + download as standalone HTML. `[polski_complaint_template]` shortcode to embed on customer pages.
* New module: Copyright / license notice helpers. `[polski_copyright]` shortcode + `polski/copyright` block with year, owner and optional license. `[polski_image_credit]` shortcode for per-image credits with source link and license.

= 1.8.2 =
* New module: Business identification. Renders the shop's business data (name, address, NIP, REGON, email, phone) as a `[polski_business_info]` shortcode and a dynamic Gutenberg block `polski/business-info`. Reads values set in the setup wizard (`polski_general` option). Block and inline formats with configurable separator.

= 1.8.1 =
* New module: SBOM generator. Emits a CycloneDX 1.4 JSON document listing PHP (composer.lock) and JS (package-lock.json) dependencies plus plugin metadata. Admin page `Polski > SBOM` with one-click download. Content-Type `application/vnd.cyclonedx+json` - ready for Dependency-Track / Trivy.

= 1.8.0 =
* New module: CRA incident reporting. Records actively-exploited vulnerabilities and security incidents with a CRA Article 14 early-warning deadline (24h for incidents/exploits, 72h for near misses). Admin page `Polski > CRA incidents` to record, dispatch (webhook + email) and mark resolved. JSON export per ENISA SRP draft schema. Hourly cron checks for deadlines approaching. Action hooks `polski_cra_incident_recorded` and `polski_cra_incident_deadline_approaching`. Migration 2.1.0 creates `polski_cra_incidents`.

= 1.7.2 =
* Site audit: four new dark-pattern checks. Forced account creation (EU Directive 2023/2673), stale/fake sale countdowns (products still on-sale after date_to passed), misleading "from" price on variable products with >50% min/max spread, false urgency via oversized low-stock threshold (>5).

= 1.7.1 =
* Compliance checklist: added Cookie banner (active consent) section. Scans the homepage HTML with a 1h transient cache and reports 9 rules (banner presence, Accept, Reject with equal prominence, granular settings, Analytics/Marketing categories, privacy-policy link, withdrawal hint, implied-consent phrase trap).
* REST API: `GET /polski/v1/compliance/cookie-banner?url=` returns the cookie-banner checklist as JSON.
* 5 new unit tests.

= 1.7.0 =
* New module: Compliance checklist for Privacy Policy (RODO Art. 13) and Regulamin (Ustawa o swiadczeniu uslug / Ustawa o prawach konsumenta). Structural heuristic scanner with 17 + 15 rules, severity levels (Required/Recommended/Optional), and a WP-admin checklist page showing score and per-element pass/fail.
* REST API: `GET /polski/v1/compliance/page/{privacy|terms}` returns the full checklist as JSON.
* Admin: new submenu Polski > Compliance checklist.
* 12 unit tests covering normalization (HTML + diacritic strip), rule evaluation, score math and default rule sets.

= 1.6.3 =
* Fixed: Added per-line `phpcs:ignore` annotations with justifications on all `$wpdb` custom-table calls (repositories, Migrator, DSAService, uninstall.php)
* Fixed: Added `phpcs:ignore` annotations on `meta_key` / `meta_value` / `meta_query` / `tax_query` lookups in service classes (ExpertReview, DoubleOptIn, SocialLogin, ReviewRequest, Faq)
* Fixed: Added `phpcs:ignore` on WooCommerce email header/footer `do_action()` invocations in email templates
* Result: Plugin Check now reports 0 errors and 0 warnings on the built release package

= 1.6.2 =
* Fixed: Softened plugin description and FAQ wording to avoid implying legal compliance or legal guarantees
* Fixed: Documented Google OAuth, Facebook OAuth, and Google Tag Manager / Google Analytics in External Services
* Fixed: Removed broken GitHub support links from the admin sidebar and deactivation modal
* Fixed: Hardened remaining `$_GET` and `$_POST` handling in admin and storefront flows
* Fixed: Replaced internal `wp_redirect()` calls with `wp_safe_redirect()` where the target stays on-site
* Fixed: Removed HEREDOC usage from review request emails for better Plugin Check compatibility
* Fixed: Replaced all remaining inline `<script>` tags (DataLayer events, JSON-LD schema) with `wp_print_inline_script_tag()` / `wp_print_script_tag()`
* Fixed: Refactored repository queries to use `$wpdb->prepare()` with `%i` table-name placeholder (eliminates table-name interpolation suppressions)
* Fixed: Replaced raw `echo $html` in Elementor widgets with `wp_kses_post()`
* Fixed: Prefixed all template variables with `polski_` to satisfy WordPress.org naming conventions
* Fixed: Removed dozens of phpcs:ignore suppressions in favour of real fixes

= 1.6.1 =
* Fixed: Moved inline admin CSS and JS to enqueued asset files (wp_enqueue_style / wp_enqueue_script)
* Fixed: Sanitized $_GET inputs in the AJAX product filters template
* Fixed: Removed manual load_plugin_textdomain() call (WordPress.org auto-loads translations since WP 4.6)
* Fixed: Hardened nonce verification by sanitizing $_POST values before passing to wp_verify_nonce()
* Fixed: Added missing wp_unslash() and capability check in expert review and product meta save flows
* Fixed: Updated readme.txt Contributors username and removed broken donate / GitHub repository links

= 1.6.0 =
* Added Social Login module (Google + Facebook OAuth2 with auto-registration)
* Added Product Authors taxonomy for bookstores and publishers
* Added Expert Reviews custom post type with ratings and Schema.org markup
* Added Order Export module (CSV with 30+ configurable fields)
* Added FAQ module with categories, accordion shortcode, and Schema.org FAQPage
* Enhanced Custom Checkout Fields with 5 conditional logic types (field value, shipping, payment, category, cart total)

= 1.5.0 =
* Added Auto Restore Stock module - automatically restores product stock on order cancellation, refund or failure
* Added AJAX Add to Cart module - add products to cart without page reload, including variable products
* Added Custom Checkout Fields module - add, modify and reorder checkout fields with multiple field types and validation
* New "Stock & Cart" and "Checkout" module groups in the admin panel

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

This plugin provides technical tools and templates for WooCommerce stores. It does not constitute legal advice and does not guarantee compliance. Review all generated texts and settings before using them in production. Always test in a development or staging environment before deploying to a live store.

WPPoland bears no responsibility for any legal, financial, regulatory, or other consequences arising from the use of this plugin. By installing and activating this plugin, you acknowledge that you do so entirely at your own risk.

== Upgrade Notice ==

= 1.20.1 =
Fixes the admin screens not loading their scripts on some setups, and refreshes all bundled translations.

= 1.6.3 =
Plugin Check cleanup: annotated custom-table queries with justifications. No functional changes.

= 1.6.2 =
WordPress.org review hardening update: safer input handling, expanded external service disclosure, and revised readme wording.
