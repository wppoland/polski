=== Polski for WooCommerce ===
Contributors: motylanogha
Tags: faktury, gpsr, omnibus, rodo, ksef
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 1.32.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce for Polish shops: VAT invoices, GPSR, Omnibus, GDPR, withdrawals, NIP, KSeF, unit prices and storefront modules.

== Description ==

**Polski for WooCommerce** is a free WooCommerce plugin for Polish online shops. It issues numbered VAT invoices, and helps you organise GPSR product data, the Omnibus lowest-price history, GDPR consents, the right of withdrawal, VAT ID (NIP) handling, unit prices, hooks for KSeF processes, DSA reports and storefront modules.

The plugin is modular. You can enable only the features a given shop needs, for example GPSR, Omnibus, cart consents, withdrawals, unit prices, food data, a wishlist, a product comparison or AJAX search.

Polski helps you configure the technical shop processes related to the Polish and EU market. It is not legal advice and does not guarantee regulatory compliance. Your shop configuration, terms, products and obligations always have to be verified for your specific business.

= Documentation and links =

* **Documentation** - [plogins.com/polski/docs/](https://plogins.com/polski/docs/)
* **Plugin page** - [plogins.com/polski/](https://plogins.com/polski/)
* **Source code** - [github.com/wppoland/polski](https://github.com/wppoland/polski)
* **Bug reports and feature requests** - [github.com/wppoland/polski/issues](https://github.com/wppoland/polski/issues)
* **Discussions and questions** - [github.com/wppoland/polski/discussions](https://github.com/wppoland/polski/discussions)
* **Polski PRO** - [plogins.com/polski-pro/](https://plogins.com/polski-pro/)

= Why Polski for WooCommerce? =

* **One plugin, many modules** - GPSR, Omnibus, GDPR, right of withdrawal, product data and storefront modules in one place.
* **Built for Polish shops** - features designed for WooCommerce stores selling in Poland or to Polish customers.
* **Free and open source** - the core product, checkout and storefront tools are available at no cost.
* **Modern code** - PHP 8.1+, a React admin panel, REST API and WP-CLI support.
* **WooCommerce blocks support** - compatible with both the classic and block-based cart and checkout.
* **HPOS compatible** - supports WooCommerce High-Performance Order Storage.

= Key modules =

* **GPSR product fields** - manufacturer, importer and EU responsible person, each with a postal address and the electronic contact art. 19 asks for, plus product identifiers, safety warnings and instructions, split into a responsibility group and a safety group, including CSV import and export.
* **Omnibus price history** - records and displays the lowest price from the last 30 days on discounted products.
* **Deposit scheme (system kaucyjny)** - adds the statutory deposit to beverages in covered packaging, untaxed and on its own checkout line.
* **GDPR consents and checkboxes** - configurable consents at checkout, registration and reviews, with a consent log.
* **Right of withdrawal and returns** - requests from the customer account, e-mail confirmations and a request log.
* **VAT ID (NIP) and KSeF hooks** - detection of orders with a VAT ID, a KSeF flag and hooks for invoicing integrations.
* **EU VAT ID check (VIES)** - confirms a customer's EU VAT number against the European Commission's register and records the consultation number on the order.
* **VAT margin scheme** - the art. 120 annotation on invoices for second-hand goods, works of art, collectors' items and antiques.
* **DSA reports** - a point of contact, an illegal-content report form and an admin panel.
* **Shop health monitor** - passive monitoring of frontend errors, checkout issues and sales anomalies.
* **Security incident log** - an internal log of incidents, outages, vulnerabilities and follow-up actions.
* **Product environmental fields** - a basis for green claims, certificates and expiry dates.
* **Verified purchase badge** - a badge on reviews from customers who bought the product.
* **AI transparency (AI Act art. 50)** - a clear "classified by AI" marker on return reasons processed by the optional AI classifier, filterable in the admin withdrawals list. The storefront disclosure of AI-generated product copy is a Polski PRO feature, since it labels copy from the Pro AI description generator.

= Checkout, consents and returns =

* **Consent checkboxes** - consents at order, registration and reviews, with the option to enable only selected fields.
* **Omnibus price history** - automatic recording and display of the lowest price from 30 days.
* **Right of withdrawal** - withdrawal/return forms and requests from the customer account.
* **Double e-mail confirmation** - e-mail address confirmation during customer registration.
* **Shop pages** - link the terms, privacy policy and withdrawal content into WooCommerce notices.
* **Dispute resolution** - an ODR information module for the shop's information pages.
* **Consent log** - logging of consents with date, context, IP address and content version.

= Product data and labelling =

* **Unit prices** - price per kg, litre, metre, piece or a custom unit.
* **Delivery time** - estimated delivery time on product pages and product lists.
* **Tax information** - gross/net messages and the VAT rate.
* **Price display** - configuration of how prices are presented in the shop.
* **Food data** - composition, nutrition values, allergens, origin, distributor and other fields for grocery shops.

= Storefront modules =

* **Wishlist** - save products for later.
* **Product comparison** - compare products side by side.
* **Waitlist** - back-in-stock notifications for products.
* **Quick view** - preview a product without opening the product page.
* **Gallery zoom** - enhanced product image zoom.
* **Product video** - add a video on the product page.
* **Product slider** - a carousel of products and collections.
* **Infinite scroll** - automatic loading of more products.
* **Product tab manager** - configure the tabs on the product page.
* **AJAX filters** - filter products without reloading the page.
* **AJAX search** - live product search, matching the title, the SKU, category names and the values of the global attributes you choose to index.
* **Product badges** - sale, new, featured and custom labels.
* **Promotional popups** - popup campaigns in the shop.

= Admin and developer tools =

* **React panel** - manage modules and settings.
* **REST API** - an API for settings, checkboxes, legal pages, withdrawals and search.
* **WP-CLI** - commands to manage selected features from the terminal.
* **CSV import and export** - bulk management of product data, including GPSR.
* **Shortcodes** - embed GPSR information, withdrawal forms, DSA reports and other elements.
* **Database migrations** - versioned and safe updates of data structures.
* **Integration hooks** - filters and actions for KSeF, invoicing and integrations with other plugins.
* **Audit scope** - DPA, DSA, KSeF readiness, environmental-claim control, verified reviews and security incidents.

= You may also like these plugins =

More free WooCommerce plugins from WPPoland:

* [Plogins Tiers](https://wordpress.org/plugins/plogins-tiers/) - quantity and volume pricing tiers with a server-rendered price table.
* [Plogins Waitlist](https://wordpress.org/plugins/plogins-waitlist/) - back-in-stock waitlist that emails shoppers the moment a product returns.
* [Sieve - Search & Filter](https://wordpress.org/plugins/sieve/) - fast AJAX product search and filtering for WooCommerce, with no jQuery.

Browse the full catalogue at [plogins.com/](https://plogins.com/) .

Reporting a security issue: email hello@wppoland.com, and under our [coordinated disclosure policy](https://wppoland.com/en/security-policy/) we confirm within two business days, assess within five, and patch a critical issue within seven days of confirming it.

== Polski PRO ==

Polski for WooCommerce covers the essential Polish-market compliance for free. **Polski PRO** is the full store suite:

* **Invoices and KSeF** - VAT invoices, corrections and receipts with PDF, plus e-invoicing to KSeF
* **Shipping integrations** - InPost, DPD, DHL and Poczta Polska: labels, tracking and pickup points
* **Order fulfilment** - Packed, Shipped and Delivered statuses, a tracking field and customer emails
* **Subscriptions** - recurring payments with renewals and one-click cancellation
* **Gift cards** - sell cards, generate codes and redeem balances in the cart
* **Affiliate program** - referral links, commission tracking and an affiliate dashboard
* **Multi-step checkout** - split checkout into address, delivery, payment and summary
* **Pre-orders, bundles and add-ons** - sell before availability and bundle products
* **Catalog mode and RFQ** - hide prices and collect quote requests

= What stays free, and what PRO adds =

The free edition is not a trial. Every module listed above works in full, with
nothing time-limited, no feature switched off after a while and no account to
create. PRO is a separate plugin that adds the parts a shop needs once it is
actually trading:

    Free                              PRO
    -----------------------------------------------------------------
    GPSR product data                 VAT invoices, corrections, PDF
    Omnibus price history             KSeF e-invoicing and JPK export
    GDPR consents and consent log     InPost, DPD, DHL, Poczta Polska
    Right of withdrawal, RMA          labels, tracking, pickup points
    VAT ID (NIP) validation           Fulfilment statuses and emails
    Unit prices, food data            Subscriptions and renewals
    Wishlist, compare, AJAX search    Gift cards and affiliate program
    77 modules, all switchable        Multi-step checkout, pre-orders,
                                      bundles, catalog mode and RFQ

Everything in the free edition stays free and open, and keeps working whether
or not you ever buy PRO. Polski PRO starts at 69 EUR per year, priced and
charged in EUR.

* **Polski PRO** - [plogins.com/polski-pro/](https://plogins.com/polski-pro/)
* **Compare editions and pricing** - [plogins.com/polski-pro/pricing/](https://plogins.com/polski-pro/pricing/)
* **PRO documentation** - [plogins.com/polski-pro/docs/](https://plogins.com/polski-pro/docs/)

== Installation ==

= Automatic installation =

1. In the WordPress dashboard go to **Plugins > Add New**.
2. Search for **Polski for WooCommerce**.
3. Click **Install**, then **Activate**.
4. Open the new **Polski** menu in the admin panel.

= Manual installation =

1. Download the plugin ZIP from WordPress.org.
2. In the WordPress dashboard go to **Plugins > Add New > Upload Plugin**.
3. Choose the ZIP file and click **Install Now**.
4. Click **Activate Plugin**.

== Getting Started ==

1. **Check the legal pages**: go to **Polski > Modules** and make sure the legal pages module is active. In its settings choose your terms, privacy policy and withdrawal page.
2. **Configure the checkboxes**: open the legal checkboxes module and enable the consents your shop requires.
3. **Check VAT rates**: make sure WooCommerce has the correct tax rates for your shop.
4. **Fill in unit prices**: for products sold by weight or volume, fill in the data in the **Polski** tab of the product editor.
5. **Enable Omnibus**: the module records price history and can display the lowest price from 30 days.
6. **Fill in GPSR**: for physical products add the manufacturer, importer and responsible person data and safety information.

== Configuration ==

Polski works in a modular way. You can enable only the features you need:

* **Product data**: GPSR, unit prices, delivery time, food data.
* **Checkout and consents**: checkboxes, right of withdrawal, legal pages.
* **Storefront**: wishlist, comparison, search, filters and badges.

Active modules with settings appear in the **Polski** menu or have a settings link on the modules page.

== Frequently Asked Questions ==

= Is Polski for WooCommerce free? =

Yes. Polski for WooCommerce is a free WooCommerce plugin for Polish online shops and is distributed as open source under GPLv2 or later.

= Which WooCommerce shop is Polski for? =

Polski is intended for WooCommerce stores selling in Poland or to Polish customers. It is especially useful when a shop needs modules for GPSR, Omnibus, GDPR, VAT ID (NIP), the right of withdrawal, KSeF and product data.

= Does Polski support GPSR in WooCommerce? =

Yes. Polski adds GPSR-related product fields, including manufacturer, importer and EU responsible person data, product identifiers, safety warnings and instructions. The data can be filled in the product editor and in bulk via CSV import or export.

= Can I show the manufacturer, importer and responsible person on the product page? =

Yes. The GPSR module can store and display the manufacturer, importer and EU responsible person data on the WooCommerce product page. Visibility depends on the module settings and the data filled in for the product.

= Does Polski support the Omnibus Directive and the lowest price from 30 days? =

Yes. The Omnibus module records price history and can display the lowest price from the last 30 days on discounted products. You can adjust the display settings in the module panel.

= Does Polski add GDPR consents in WooCommerce? =

Yes. The plugin lets you add configurable consent checkboxes at order, registration and reviews, and keep a consent log with date, context and technical audit information.

= Does Polski add checkboxes at checkout? =

Yes. Polski can add checkboxes for the terms, privacy policy, withdrawal information, consent for digital content, marketing consent, delivery notifications and a review reminder.

= Does Polski add a withdrawal or return form? =

Yes. Polski can add right-of-withdrawal handling from the customer account, with request confirmation, a request log and e-mail messages. This helps organise the returns process in WooCommerce.

= Does Polski support VAT ID (NIP) in WooCommerce? =

Yes. Polski includes features and hooks related to the Polish VAT ID (NIP), including detection of orders that may require invoice or KSeF handling. Field availability and behaviour depend on the enabled modules.

= Does Polski support KSeF in WooCommerce? =

Polski is not a complete system for sending invoices to KSeF, but it adds mechanisms ready for integrations: flagging orders by VAT ID, a KSeF status column and hooks for invoicing plugins and custom integrations.

= Does Polski issue invoices in WooCommerce? =

Polski provides data, flags and hooks useful for invoicing and KSeF, but it does not replace a full invoicing plugin or accounting system. For automatic invoicing, use a dedicated invoicing integration.

= Does Polski work with the WooCommerce block checkout? =

Yes. Polski supports the classic checkout as well as the block-based WooCommerce cart and checkout.

= Does Polski work with HPOS in WooCommerce? =

Yes. Polski declares compatibility with WooCommerce HPOS, that is High-Performance Order Storage / Custom Order Tables.

= Does Polski add unit prices in WooCommerce? =

Yes. The plugin lets you show unit prices, for example per kg, litre, metre, piece or a custom unit.

= Is Polski suitable for a WooCommerce grocery shop? =

Yes. Polski includes modules useful for grocery shops, including composition, nutrition values, allergens, origin, distributor and additional product labelling fields.

= Does Polski add a DSA report form? =

Yes. The plugin includes DSA tools, including point-of-contact settings, an illegal-content report form shortcode, a report-handling panel and e-mail notifications.

= Does Polski add a wishlist, comparison and quick view? =

Yes. The storefront modules include a wishlist, product comparison, quick view, back-in-stock notifications, AJAX filters, AJAX search and product badges.

= Can I enable only selected modules? =

Yes. Polski is modular, so you can enable only the features you need, for example GPSR, Omnibus, GDPR, returns, VAT ID, DSA or storefront modules.

= Does Polski support CSV import and export? =

Yes. Polski extends the WooCommerce CSV import and export with selected product data, including GPSR fields and other product information.

= Does Polski have shortcodes? =

Yes. The plugin provides shortcodes for selected modules, including GPSR information, withdrawal forms, DSA reports, complaint templates and shop messages.

= Does Polski guarantee legal compliance? =

No. Polski provides technical modules for WooCommerce, but it is not legal advice and does not guarantee that your shop is compliant. Your shop configuration, terms and obligations always have to be verified for your specific business.

= Is Polski ready for the Cyber Resilience Act? =

Polski follows security practices relevant to CRA readiness: updates are delivered through the official WordPress.org channel, vulnerabilities can be reported under a coordinated disclosure policy, the code uses standard WordPress security mechanisms, and external services are described in this readme. This is not a declaration of legal compliance.

= Where do I report bugs or feature requests? =

For day-to-day support use the WordPress.org forum. Technical bugs and feature requests can also be reported in the GitHub repository.

= Does the plugin have a simple feedback form? =

Yes. The admin panel includes a simple feedback form that stores messages locally in WordPress. Do not enter passwords, license keys or customer personal data there.

= What happens when the plugin is deactivated and uninstalled? =

Deactivating the plugin keeps your settings and stored data. Uninstalling removes the plugin files. Plugin data is removed only when you enable the "remove data on uninstall" setting.

== External Services ==

= GUS REGON API =

When the VAT ID (NIP) lookup module is enabled, the plugin can connect to the public GUS REGON registry to fetch company data based on the VAT ID entered by the user. The connection is made only after the lookup is deliberately triggered.

* Data sent: the VAT ID (NIP).
* Service address: [https://wyszukiwarkaregon.stat.gov.pl/](https://wyszukiwarkaregon.stat.gov.pl/)
* Terms of service: [https://api.stat.gov.pl/Home/RegulaminBIR](https://api.stat.gov.pl/Home/RegulaminBIR)
* Privacy policy: [https://bip.stat.gov.pl/](https://bip.stat.gov.pl/)

= VIES (European Commission) =

When the EU VAT ID check module is enabled, the plugin can connect to the European Commission's VIES service to confirm that a customer's EU VAT number is registered. The connection is made only when the check is deliberately triggered from the order screen.

* Data sent: the customer's EU VAT number and, if you configure one, your own VAT number, which is what makes it a qualified check and returns a consultation number.
* Service address: [https://ec.europa.eu/taxation_customs/vies/](https://ec.europa.eu/taxation_customs/vies/)
* Terms of use: [https://ec.europa.eu/taxation_customs/vies/#/help](https://ec.europa.eu/taxation_customs/vies/#/help)
* Privacy policy: [https://commission.europa.eu/privacy-policy-websites-managed-european-commission_en](https://commission.europa.eu/privacy-policy-websites-managed-european-commission_en)

= Google OAuth =

When the social login module is enabled and Google login is configured, a customer clicking the continue-with-Google button is redirected to Google for authentication. The plugin exchanges the authorization code for an access token and fetches the profile data needed to sign in or create an account.

* Data sent: the redirect address, client ID, authorization code and access token used to fetch the profile.
* Data received: the Google account ID, e-mail address and name.
* Service address: [https://accounts.google.com/](https://accounts.google.com/)
* Terms of service: [https://policies.google.com/terms](https://policies.google.com/terms)
* Privacy policy: [https://policies.google.com/privacy](https://policies.google.com/privacy)

= Facebook OAuth =

When the social login module is enabled and Facebook login is configured, a customer clicking the continue-with-Facebook button is redirected to Facebook for authentication. The plugin exchanges the authorization code for an access token and fetches the profile data needed to sign in or create an account.

* Data sent: the redirect address, application ID, authorization code and access token used to fetch the profile.
* Data received: the Facebook account ID, e-mail address and name.
* Service address: [https://www.facebook.com/](https://www.facebook.com/)
* Terms of service: [https://www.facebook.com/legal/terms](https://www.facebook.com/legal/terms)
* Privacy policy: [https://www.facebook.com/privacy/policy/](https://www.facebook.com/privacy/policy/)

= Google Tag Manager / Google Analytics =

When the DataLayer module is enabled and a GTM container ID or a GA4 measurement ID is configured, the plugin can load the Google Tag Manager or Google Analytics scripts in the shop and send ecommerce events according to the configuration.

* Data sent: page views and ecommerce event data, for example product IDs, product names, prices, cart actions, checkout events and order values, depending on the configuration.
* Service address: [https://www.googletagmanager.com/](https://www.googletagmanager.com/)
* Terms of service: [https://policies.google.com/terms](https://policies.google.com/terms)
* Privacy policy: [https://policies.google.com/privacy](https://policies.google.com/privacy)

Admin-panel feedback and deactivation-form information are stored locally in WordPress and are not sent to an external service.

== Screenshots ==

1. The module management panel with module toggles and settings.
2. GPSR product safety fields in the product editor.
3. GDPR consent checkboxes at checkout with the consent log.
4. Omnibus Directive - the lowest price from 30 days on a discounted product.
5. The right-of-withdrawal action in the customer account.
6. The DSA illegal-content report form.
7. AJAX search and product filters in the shop.
8. Wishlist, comparison and quick view on the product list.

== Translations ==

Polski for WooCommerce is fully translatable and ships the `polski.pot` template. Translations are delivered by WordPress.org language packs from translate.wordpress.org, which is where Polish, German and Spanish are being contributed; the package itself carries no compiled translation files.

== Changelog ==

Full release history is in changelog.txt in the plugin folder and on the plugin page at plogins.com. WordPress.org shows only the most recent releases.

= 1.32.0 =
* New: AJAX search can look inside the values of the global product attributes you choose. Pick them under the AJAX search module; leave them all unticked and attributes are skipped entirely. Requested on GitHub (#72).
* Fixed: "Search by SKU" and "Search by categories" were on the screen but no code read either one, so both did nothing whichever way you set them. They work now, and switching them off really excludes that data.
* Fixed: the search dropdown never used any of the extra matching at all. It ran its own product query, while the manufacturer, GTIN and ingredient matching only applied to the main search page, so the dropdown and the results page could disagree about the same term. Both now go through one place.
* New: electronic contact fields for the manufacturer, the EU responsible person and the importer, plus a postal address for the responsible person. GPSR art. 19(1)(a) asks for a postal and an electronic address, and there was nowhere to put the electronic one. Included in CSV import and export.
* Changed: the product data panel splits GPSR into "Product responsibility" (who is answerable) and "Product safety" (warnings, instructions, identifier), and the storefront output does the same. A group with nothing filled in is no longer printed as an empty heading. Requested on GitHub (#63).
* New: the `polski/gpsr/data` filter, so the values shown for a product can be supplied from elsewhere. Plogins Polski PRO uses it for reusable responsibility profiles.
* Fixed: the changelog on the WordPress.org plugin page was cut off. WordPress.org truncates a readme changelog over 5,000 words and only tells the plugin author by email after the import, so the page had quietly been dropping its oldest entries. readme.txt now carries the last twenty releases and the full history stays in changelog.txt inside the plugin folder.
* Release checks added for all three failure modes above: a changelog over the wp.org limit, and a product field that renders an input but is missing from the save map, so it accepts typing and discards it.

= 1.31.10 =
* Fixed: the Omnibus "calculated from" setting did nothing, and the window it was meant to control was the wrong one. The lowest price was always measured up to now, so a running sale price competed to be its own lowest price and the notice could just repeat the current price. Measured from the sale's start date, as the setting says and as the Directive intends, the notice shows the lowest price in the 30 days before the reduction instead.
* Note on upgrading: on a product whose sale has a start date, the figure in the notice can change, and it changes to the legally correct one. In the test case, a product reduced to 79 after selling at 250 and 300 showed "79" before and shows "250" after. Products with a sale price and no scheduled start are unaffected, because there is no date to anchor the window to; that is the common case.

= 1.31.9 =
* Fixed: eleven settings on the Modules screen did nothing. Wired up: unit price on product lists, Omnibus with tax, Omnibus regular price, the Omnibus "no price history" choice and its custom text, Omnibus visibility on the product page and on product lists, the GPSR display mode and section title, KSeF auto-detection by VAT ID, and the DSA contact name and phone, which art. 12 expects to be published and which were only ever used internally.
* Fixed: the Omnibus notice was printed on product lists whatever the screen said, and the screen said off. It is now honestly declared as on by default and can really be switched off. Nothing changes for an existing shop.
* Fixed: the Omnibus lowest price ignored tax entirely, so a shop entering prices without tax showed a net figure beside a gross selling price.
* Removed two settings that could not be delivered as written: "Related and featured products", which the renderer cannot distinguish from any other list, and "Track variations separately", which is what the code already does unconditionally.
* Changed: the default text for "no price history" no longer claims the price has not changed. No recorded history is not the same as no change; a fresh install has none either.
* The release check for settings nothing reads is now accurate. It used to let a key read by one module vouch for every module sharing the name, and treated the defaults file as a reader. Corrected, it found twenty-eight more, all interface text made configurable and never read back, now recorded as a list to shrink.

= 1.31.8 =
* Fixed: the digital-content waiver field was registered on block checkout even with the Legal checkboxes module switched off, so every order carried it and the confirmation page listed it under Additional information with the value "No". The service had no module check at all. It is now silent whenever the module is off. Reported by strid3rr on the support forum.
* Fixed: two switches governed that one field and they contradicted each other. "Digital content (waiver)" on the modules screen was read by nothing, while the field was actually driven by a setting on the withdrawal page that defaults to on, so turning the visible switch off changed nothing. Where no explicit choice was ever saved, the visible switch now decides, and its default of off means off. A shop that deliberately set the mode on the withdrawal page keeps what it configured: this is a declaration under art. 16(m), and dropping it from a working checkout would be worse than the noise.

= 1.31.7 =
* Fixed: the Omnibus "show in cart" setting did nothing. It had been on the settings screen since the module shipped, while the class meant to render it was an empty stub, so the lowest-price notice never appeared in any cart, classic or block. The notice now shows under the item in both, and under cross-sells and any other product listing built with blocks, which do not fire the classic loop hooks the module was relying on. Reported by strid3rr on the support forum.
* Added a release check that fails the build when the modules screen offers a setting no code reads. That is exactly how this bug survived, and the check found eleven more dead switches, which will be fixed or removed in turn.

= 1.31.6 =
* Corrected the module count on this page. It said 70 while the plugin ships 77, counted from the modules screen itself.
* Documentation for the three modules added in 1.31.3 to 1.31.5, the deposit scheme, the VIES check and the VAT margin scheme, is now on plogins.com in English, Polish, German and Spanish.

= 1.31.5 =
* Added: the VAT margin scheme (procedura marży), off by default. For second-hand goods, works of art, collectors' items and antiques taxed on the margin under art. 120 ustawy o VAT, an invoice must not show a VAT amount and must name the scheme, as art. 106e ust. 3 requires. Mark the scheme on a product and the invoice carries the wording; where every line on the order is under the scheme the VAT summary is omitted. An order that mixes margin goods with ordinary taxed goods is annotated with a warning instead, because the two cannot share one invoice and silently hiding the VAT would hide that from you. The module changes what the invoice says; it does not compute the margin or touch prices and tax classes.
* Added: the `polski/invoice/data` filter, so an add-on can adjust the invoice payload while the document is still being built.
* Fixed: the invoice total was summed from the VAT breakdown rather than from the lines. Every invoice issued so far was correct, because the breakdown always covered every line, but it would have printed a total due of zero on the first invoice with no VAT rows.

= 1.31.4 =
* Added: EU VAT ID check against VIES, off by default. The NIP module only ever covered Polish numbers, through the GUS register, so nothing in the plugin could say whether a VAT number issued by another member state was real, which is what an intra-EU sale at 0% VAT turns on. The order screen now has a check button, and the answer is stored on the order. Enter your own VAT number in the module settings to make it a qualified check: only then does VIES return a consultation number, which is the evidence that the check happened. A check recorded on an order always calls VIES fresh, never from cache, because a cached consultation number would document a consultation that never took place for that order.

= 1.31.3 =
* Added: the deposit scheme (system kaucyjny), off by default. Poland has charged a deposit on beverages in covered packaging since 1 October 2025, and the plugin had nothing for it. Mark a product's packaging (PET up to 3 l, a can up to 1 l, or reusable glass up to 1.5 l) and how many containers one item holds; the deposit is then added on top of the price as its own line at checkout and shown on the product page. The statutory amounts, 0.50 and 1.00, are the defaults and can be changed if the scheme's operator revises them. The line is added untaxed on purpose: the deposit sits outside the VAT base at the point of sale.

= 1.31.2 =
* Fixed: an order given one of the withdrawal statuses vanished from WooCommerce - Orders. The three statuses were 21 to 23 characters long and an order status is stored in a 20-character column, so each one was cut short on the way into the database and the order ended up carrying a status that is registered nowhere. It was never lost: it stayed in the database, opened normally from its own URL, and came back on the list as soon as a standard status was set. The statuses are now short enough to store, and updating rewrites the ones already saved. Reported by strid3rr on the support forum.
* Fixed: the withdrawal button and the complaint / return link appeared on the order confirmation screen, straight after checkout. WooCommerce 10.9 began rendering My Account order actions there as well; both links belong on My Account - Orders, which is where the documentation puts them. Reported by strid3rr on the support forum.

= 1.31.1 =
* Fixed: switching the NIP module off left the NIP field on checkout. Two services could add it, and the second one defaulted to on from a setting no screen writes, so the switch a merchant can actually see only ever worked in one direction. The field now has a single owner, the NIP module, on both the block and the classic checkout. Reported by czester on the support forum.

= 1.31.0 =
* Added: **VAT invoices, in the free plugin.** Switch the Invoices module on and an order screen gains an Issue invoice button. The invoice is numbered per year (FV/1/2026, restarting each January), shows both parties with their VAT IDs, lists every line with its own rate, and groups VAT by rate the way an accountant reads it. Shipping and fees are invoiced too.
* Added: customers get an Invoice link on their order in My Account. The document opens through a link carrying a token derived from the invoice itself, so it works from an email without a login and cannot be reached by guessing an id.
* Added: the invoice is laid out for A4 and prints cleanly. The free plugin deliberately does not bundle a PDF engine, because a usable one is tens of megabytes; every browser prints to PDF, and that is one click on the document.
* Note: an issued invoice is frozen. Editing the order afterwards does not rewrite it, which is the point of a document with a number on it. Issuing twice for one order returns the invoice already issued rather than burning a second number.

= 1.30.8 =
* Fixed: the NIP field never appeared on the block checkout. Polski boots on `init` priority 0, by which point WooCommerce has already fired `woocommerce_init`, so the services that registered their block checkout fields on that action registered them into an event that had already passed. Affected the NIP lookup module and the B2B checkout fields.
* Fixed: legal checkboxes could be switched on but never off. Two handlers were attached to the same admin-post action; the one that won only wrote back the fields present in the request, and an unticked checkbox is absent from a form submission. There is now a single save path, and it also stops stripping the link markup out of the Terms and Privacy labels.
* Fixed: the order button label had no effect on the block checkout. That button is rendered by React and its label resolved through WooCommerce's own checkout filter, so the PHP filter Polski used could never reach it. It now registers the supported `placeOrderButtonLabel` filter instead. The classic checkout is unchanged.
* Fixed: the NIP field was registered twice when both the NIP lookup module and the B2B checkout fields were on, which WooCommerce rejects. The lookup module now owns the field.

= 1.30.7 =
* Fixed: the Polski icon in the admin menu still sat low on a narrow screen and when the menu is collapsed to icons. WordPress shrinks that row from 34 to 30 pixels in both cases and the plugin was still centring the icon in the taller one. 1.30.3 fixed the ordinary sidebar; this fixes the other two.

= 1.30.6 =
* Fixed: a shop manager could open the withdrawal settings screen but not save it. The menu was available to anyone who manages WooCommerce, while saving was still restricted to a full administrator, so pressing Save ended in a permissions error. Both now use the same permission.
* Fixed: changelog.txt, the file that keeps the full release history inside the plugin, was missing the last four releases.

= 1.30.5 =
* Fixed: a percent sign in the guest form's intro text, added in 1.30.4, corrupted the sentence on the public page. Writing something as ordinary as "Zwracamy 100% ceny" produced "Zwracamy 100" followed by an invisible byte and the rest of the line, because the text was passed through a formatter that reads a percent as a formatting instruction. The intro now uses {company} and {days} and prints everything else exactly as typed, percent signs included. If you already saved an intro containing a percent, it will render correctly after this update with no change on your side.
* Fixed: the plugin reported its own version as 1.29.7 while being 1.30.x. That number is shown on the plugin's admin page and is used to version stylesheets and scripts, so an admin who had already loaded the old files kept them after updating, which is why the menu-icon fix in 1.30.3 may not have appeared for you. It is also the value the plugin compares against when deciding whether to run an upgrade routine. The release script had been silently failing to update it since 1.29.7.

= 1.30.4 =
* Added: the guest withdrawal form's wording is now editable under Withdrawal settings, in a Guest form wording section: the heading, the intro paragraph, the two field labels and the submit button. Asked for on the support forum. The texts on the My Account side were already editable and these were not, which was an inconsistency rather than a decision. Leave a field empty and the built-in wording is used exactly as before, so nothing changes for a shop that does not open the screen. The intro accepts %1$s for your company name and %2$d for the withdrawal period, and prints your text as written if you leave them out.

= 1.30.3 =
* Fixed: the Polski icon in the admin menu sat lower than every other icon in the sidebar, and jumped into place only while the pointer was over it. WordPress puts vertical padding on that element and the plugin's own styling cleared it only in the hover state, so the icon was misaligned for as long as you were not pointing at it.

= 1.30.2 =
* Declared compatibility with WooCommerce 11.0.

= 1.30.1 =
* The Polish catalogue is bundled again, on purpose. WordPress.org builds its language packs from the strings in the released package, so a release that changes source strings always lands before translate.wordpress.org has seen the new ones, and this release changes about 520 of them. Without the bundled copy a Polish shop would read an English interface until the translations were re-imported. WordPress still prefers the language pack wherever it has a string, so nothing overrides a translator.
* Fixed the PRO promo on the settings screen quoting a price in PLN. PRO is priced and charged in EUR, so an admin on a Polish site was shown a zloty amount and then billed in euro, and the zloty figure was a fixed conversion that drifted from the real charge as the rate moved. The promo now shows the euro price that is actually taken.
* Rebuilt the bundled language catalogues from translate.wordpress.org rather than from the copies in the repository. Those copies had fallen behind: Polish was missing 43 strings that translators had already done, and disagreed with them on another 52. Polish and Czech are now complete.
* Fixed the withdrawal information block printing its heading twice. It rendered a title of its own directly above the first statutory heading from Annex I to Directive 2011/83/EU, so "Right of withdrawal" appeared on two lines in a row. The two statutory headings are now the only headings, both at the same level as the model form's.

= 1.30.0 =
* The plugin's source strings are now English. About 520 of them were written in Polish, which is how the plugin started, but WordPress.org expects an English source: every other language on translate.wordpress.org was being translated out of Polish rather than out of the original. Nothing changes on a Polish shop, because each of those strings now carries its old Polish wording as the Polish translation. Shops running another language get translations made from English for the first time.
* Along the way, a handful of strings that said the same thing in two different ways were consolidated, so the same label no longer appears as two entries to translate.
