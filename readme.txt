=== Polski for WooCommerce ===
Contributors: motylanogha
Tags: faktury, jpk, ksef, gpsr, zwroty
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 1.41.3
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce for Polish shops: VAT invoices, JPK_FA, KSeF, NIP lookup, GPSR, Omnibus, withdrawals, returns and unit prices.

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
* **Product markings** - per-product flags that print the statutory notice on the product page: medical device, and goods sold to adults only such as alcohol or tobacco.
* **Deposit scheme (system kaucyjny)** - adds the statutory deposit to beverages in covered packaging, untaxed and on its own checkout line.
* **GDPR consents and checkboxes** - configurable consents at checkout, registration and reviews, with a consent log.
* **Right of withdrawal and returns** - requests from the customer account, e-mail confirmations and a request log.
* **VAT ID (NIP) and KSeF hooks** - detection of orders with a VAT ID, a KSeF flag and hooks for invoicing integrations.
* **EU VAT ID check (VIES)** - confirms a customer's EU VAT number against the European Commission's register and records the consultation number on the order. With the NIP module on, checkout and My Account accept a VAT number from any EU country, entered with its country code.
* **VAT margin scheme** - the art. 120 annotation on invoices for second-hand goods, works of art, collectors' items and antiques.
* **GTU markings** - one of the thirteen JPK_V7 goods and services groups per product, printed against its own line on the invoice.
* **DSA reports** - a point of contact, an illegal-content report form and an admin panel.
* **Shop health monitor** - passive monitoring of frontend errors, checkout issues and sales anomalies.
* **Security incident log** - an internal log of incidents, outages, vulnerabilities and follow-up actions.
* **Product environmental fields** - a basis for green claims, certificates and expiry dates.
* **Verified purchase badge** - a badge on reviews from customers who bought the product.
* **AI transparency (AI Act art. 50)** - a clear "classified by AI" marker on return reasons processed by the optional AI classifier, filterable in the admin withdrawals list. The storefront disclosure of AI-generated product copy is a Polski PRO feature, since it labels copy from the Pro AI description generator.

= Checkout, consents and returns =

* **Consent checkboxes** (beta on the block checkout) - consents at order, registration and reviews, with the option to enable only selected fields. On the block checkout the labels show without links and the conditional boxes are not shown.
* **Omnibus price history** - automatic recording and display of the lowest price from 30 days.
* **Right of withdrawal** - withdrawal/return forms and requests from the customer account.
* **Double e-mail confirmation** (beta) - e-mail address confirmation during customer registration. An account created at checkout stays logged in for that session.
* **Shop pages** - link the terms, privacy policy and withdrawal content into WooCommerce notices.
* **Dispute resolution** - a notice about out-of-court complaint and redress options (consumer ombudsman, Trade Inspection) on the cart and checkout pages and as a shortcode.
* **Consent log** - logging of consents with date, context, IP address and content version.

= Product data and labelling =

* **Unit prices** - price per kg, litre, metre, piece or a custom unit.
* **Delivery time** - estimated delivery time on product pages and product lists.
* **Tax information** - the VAT rate notice and the small business exemption (Art. 113).
* **Price display** - configuration of how prices are presented in the shop.
* **Food data** - composition, nutrition values, allergens, origin, distributor and other fields for grocery shops.

= Storefront modules =

* **Wishlist** - save products for later.
* **Product comparison** - compare products side by side.
* **Waitlist** - back-in-stock notifications for simple products and for each variation of a variable product.
* **Quick view** - preview a product without opening the product page.
* **Gallery zoom** - enhanced product image zoom.
* **Product video** - add a video on the product page.
* **Product slider** - a carousel of products and collections.
* **Infinite scroll** - automatic loading of more products.
* **Product tab manager** - configure the tabs on the product page.
* **AJAX filters** - filter products without reloading the page.
* **AJAX search** - live product search in a search box placed with a shortcode or block, matching the title, the SKU, category names and the values of the global attributes you choose to index.
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
or not you ever buy PRO. Polski PRO starts at 99 EUR per year, priced and
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

= 1.41.3 =
* Security (low): the [polski_withdrawal_form order_id=N] shortcode showed any order's number, date and withdrawal eligibility to whoever opened the page, so anyone able to place a shortcode (a contributor, or a comment or form that renders shortcodes) could read other customers' orders by number. It now shows the order only to its owner or a shop manager, and everyone else gets the same answer as for an order that does not exist. The product shortcodes (unit price, GPSR, Omnibus price and the rest) also rendered draft, private and password-protected products when given product=N; they now render only what the visitor could already see.

= 1.41.2 =
* Fixed: the withdrawal form lost its "Step 1. Choose the items to withdraw from" heading as soon as the settings were saved once. The settings default said "Order items", so saving wrote that over the template's heading; both defaults now match the form.
* Fixed: the KSeF badge on the orders list stayed amber for an invoice KSeF had accepted. Polski PRO records an accepted invoice as "accepted", and the badge only turned green for "sent"; it is green for both now.

= 1.41.1 =
* Fixed: the Elementor widgets (unit price, Omnibus price, tax, delivery time, GPSR, food, AJAX search, filters, product slider) never appeared in Elementor. The class that registers them was defined but had not been booted since April 2026. They now appear in the "Polski for WooCommerce" category and render through the same code as their shortcodes.
* Fixed: with Google for WooCommerce active, Polski now adds the product's GTIN (when Google for WooCommerce has none), manufacturer as brand, and unit pricing to the Google product. This integration had also not been booted since April 2026, and when switched back on it would have stopped every product sync with a type error; it is corrected to the filter's real arguments and to the attribute names Google expects.
* Changed: the CartFlows compatibility class is booted again and only acts when the Legal checkboxes module is on.
* Fixed: the daily maintenance job (Omnibus history pruning, double opt-in cleanup, scheduled review requests) was scheduled only on activation, so an update that copied files without reactivating left it unscheduled. It is now restored on every load when missing.
* Removed: an empty Dynamic Pricing compatibility class whose two callbacks did nothing.

= 1.41.0 =
* Security (high): a logged-out visitor could open and submit the withdrawal form for any guest order, because a guest order's customer id and a logged-out visitor's user id are both 0. Guests now go through the existing order lookup and email link only.
* Security (medium): Quick View, compare and wishlist returned or stored draft, private and password-protected products, including disabled variations. They now accept only products a visitor could open themselves.
* Security (low): product Q&A accepted questions and answers on any post id. Questions now go only to published products, and an answer must reply to an approved question on the same product.
* Added: the waitlist works on variable products. The form appears when an out-of-stock variation is selected, and the restock email goes out when that variation is back in stock, however the stock changed. No longer beta.
* Added: the price history chart shows the selected variation's history on variable products. No longer beta.
* Added: with the VIES module on, checkout and My Account accept an EU VAT number from another country (for example DE811569869). A plain 10-digit NIP keeps its checksum and GUS lookup. No longer beta.

= 1.40.1 =
* Security (medium): three read-only withdrawal abilities (eligibility, remaining items, deadline) let any logged-in customer read them for another customer's order by guessing its id. They now require the order's own customer or a shop manager.

= 1.40.0 =
Every one of the 84 modules was switched on, configured and used in a live shop, then switched off again. This release fixes what that found, and marks as beta what does not fully work yet.
* Fixed: the unit price dropped the base quantity, so a price per 1000 ml or per 100 g was labelled per ml or per g. It now shows the base ("15,00 zl / 100 g").
* Fixed: the lowest-price (Omnibus) notice quoted the sale price itself for an ordinary unscheduled sale, missed same-day price changes, ignored the price still in force when the 30-day window opens, and never showed on variable products. A scheduled sale is no longer recorded before it starts. The notice now follows the WooCommerce tax display.
* Fixed: the cart threshold discount added VAT on top of the discount, so 5% came out as 6.15% in shops that show prices including tax.
* Fixed: the VAT notice said "incl. 23% VAT" next to net prices. It now shows only when prices are displayed including tax.
* Fixed: the VAT margin scheme invoice still printed per-line VAT, and a merchant warning about mixed orders appeared on the customer's invoice.
* Fixed: saving the billing address in My Account always failed with "NIP not valid" while the NIP module was on. The GUS autofill on the block checkout filled a hidden billing form.
* Fixed: switching the withdrawal module off hid orders that already had a withdrawal status from Orders and from My Account.
* Fixed: saving the withdrawal settings page switched the digital-content consent on at checkout although it was off, and physical-only orders showed a consent line.
* Fixed: variable products were added to the cart twice by AJAX add to cart, and a failed add showed "Added to cart".
* Fixed: custom checkout field values from block orders disappeared from the order screen and emails; select fields showed the option key instead of its label.
* Fixed: switching a module off could silently delete its product data on the next product save (medical device and 18+ flags among others).
* Fixed: product JSON-LD replaced WooCommerce's sale and list price with the Omnibus figure; it is now added next to them. No invalid nutrition on Product, no QAPage for several questions, no second Product node for expert reviews, and the manufacturer follows its setting.
* Fixed: CSV import did not map Polski columns back from an export.
* Fixed: AJAX filters, compare, infinite scroll, gallery zoom, product slider, social proof, featured video, badges and tabs misbehaved on Storefront (duplicated tables and pagination, lost lightbox, duplicate products).
* Fixed: security incidents, store health, site audit, CRA, DPA, SBOM, data layer and exports: wrong dates, false audit results, reports reachable with the module off.
* Changed: the dispute resolution texts no longer send consumers to the EU ODR platform, which closed on 20 July 2025.
* Removed: two switches nothing read, the gross/net display mode (WooCommerce owns it) and the Checkout Toolkit integration.
* Beta: VIES (non-Polish VAT numbers cannot be entered at checkout), cookie consent (only Polski's own tags wait for consent), double opt-in (accounts created at checkout stay logged in), custom checkout fields and legal checkboxes on the block checkout, waitlist on variable products, the price history chart on variable products, and the AI bridge. Each module card now says exactly what does not work yet.
* Corrected: module descriptions that promised more than the code does (legal page drafts are empty, the complaint template has no REST route, the business block has no REGON, live search needs its shortcode or block).

= 1.39.0 =
* Fixed: nine settings on the modules screen changed nothing when edited. The withdrawal form drew its own headings and ignored the order items heading, the product and quantity column labels and the exemption notice; it never printed the legal notice at all. The two order notes the module writes ignored their wording settings. Editing any of them looked like it worked, saved, and did nothing.
* Fixed: the dashboard status cards ignored all thirteen wording settings offered for them, and the VAT and double opt-in cards were missing entirely while the page still worked out what they would have said. Both cards are back, and every card now uses the wording from the screen.
* Fixed: the DSA report form ignored its success message setting.
* Removed: the withdrawal "Price column" setting named a column the form does not have.
* Note for anyone who edited those fields before this release: your wording was stored the whole time and takes effect now, so check it says what you want.

= 1.38.4 =
* Fixed: the lowest-price notice could quote a figure in a currency the shop no longer uses. The price history records the currency of every snapshot, but the lookups ignored that column, so a shop that switched currency, or a multi-currency plugin that switches it per request, would show "lowest price in the last 30 days" in one currency beside a selling price in another. Two amounts in different currencies do not compare, and that notice is a comparison, so the window, the archive lookup and the price history chart now all read one currency: the shop's current one. A shop that switches starts a fresh 30-day window.

= 1.38.3 =
* Fixed: a single timed-out call to an external register was reported to the customer as a definite answer. A GUS lookup that never got through said "no data found for that VAT ID", and a VIES check that timed out read as a failed check. Calls that are safe to repeat, the GUS lookup, the VIES check, the legal-page fetch and the social-login profile read, are now retried once after a timeout or a 5xx. Writes are not retried, so nothing is posted, charged or consumed twice.

= 1.38.2 =
* Fixed: the NIP field accepted anything that was not a number. Sanitising ran before validation and stripped every non-digit, so "abcdef" reached the check as an empty string, and an empty string means "left blank". The customer saw their text accepted, the shop received no NIP. Letters, a too-short number and a wrong checksum are now all rejected, on the checkout and in My Account. Reported by strid3rr on the support forum.
* Fixed: the NIP field was registered as an address field, so WooCommerce rendered one copy in the billing address and a second in the shipping address, and nothing compared the two numbers. A VAT ID belongs to the buyer rather than to a place, so it is now a contact field and appears once. Values saved under the old address key are moved on first save.
* Fixed: with both the NIP and the B2B checkout modules on, the NIP field was registered by nobody. The NIP module stepped aside "because B2B handles it" and B2B never had a NIP field, so it vanished from block checkout and from My Account.
* Fixed: a NIP saved in My Account had to be typed again at checkout, and the address summary could show a different number than the edit form. The two screens wrote to two different customer meta keys and nothing bridged them. Every screen now reads and writes the same number.
* Fixed: entering a NIP on the block checkout did not fill in the company details. The lookup wrote to the DOM ids the shortcode checkout uses, and block checkout renders its inputs from a data store, so anything written that way was discarded on the next render. It now goes through the store, which is what the block fields read.
* Fixed: My Account > Addresses saved an invalid NIP without a word. WooCommerce checks a custom field's required flag and its type there and nothing else, so the checksum is now checked on that form too.

= 1.38.1 =
* Fixed: a product found by the AJAX search dropdown could be missing from the full results page. The dropdown and the results page share the same extra matching (SKU, GTIN, manufacturer, category, ingredients), but on the results page the extra product IDs were being attached to the wrong part of the SQL WHERE clause, the one WordPress adds for logged-out visitors to exclude password-protected posts. For a logged-out shopper, which is nearly every shopper, the results page therefore searched titles and descriptions only, and a password-protected product matching the term would have been listed. Reported by strid3rr on the support forum.
* Fixed: "View all results" carried the phrase from the response that was on screen, not the phrase in the search box. Typing on and clicking it immediately led to the results for the previous keystroke. The link, and Enter on it, now always use the current contents of the box.
* Fixed: a keystroke landing while a request was in flight could leave the dropdown a phrase behind the box; the search now re-runs for the current value.
* Fixed: the AJAX dropdown listed products protected with a password, with their name, price and link. The storefront results page has always hidden them; the dropdown queried products without any notion of a password. Found while reproducing the report above.

= 1.38.0 =
* Added: Product markings module. Two per-product flags that print the statutory notice on the product page: medical device (Regulation 2017/745 and the Medical Devices Act) and goods sold to adults only, such as alcohol or tobacco. Both notices are editable in the module settings.
* Added: five new privacy policy checks. The page is now also read for things that must NOT be there: the repealed 1997 data protection act, the invalidated Privacy Shield, GIODO instead of PUODO, unfilled template placeholders, and sentences left over from a chat with an AI assistant.
* Added: four new terms and conditions checks: telephone number (required since the Omnibus implementation), the rights of a sole trader buying outside their profession, unfilled placeholders, and AI leftovers.
* Added: both legal pages are now checked for links to somebody else's terms or privacy policy, which is what a copied template looks like.
* Added: two site audit checks. Gravatar (avatars send a hash of the commenter email to a US service, and it is on by default even when comments are closed), and a published accessibility statement, which the European Accessibility Act has required of online shops since 28 June 2025.
* Fixed: an empty legal page scored 23% instead of zero, because it trivially passed every "must not contain" check. Nothing to check now counts as not checked.

= 1.37.11 =
* Fixed: from WooCommerce 8.6 on, three modules stopped registering their classic checkout fields, on the belief that WooCommerce renders additional checkout fields on the shortcode checkout as well. It does not: core renders those on the block checkout, the order confirmation and My Account only. On a shop using the classic (shortcode) checkout this silently removed the B2B company toggle, REGON and IBAN fields; every custom checkout field defined in the Custom Checkout Fields module, including its validation and its save; and the digital-content consent checkbox required before performance begins (Art. 16(m)). All three register both paths again.
* Fixed: the duplicate-field guard is now one generic filter (`AddressFormFieldDedupe`) covering NIP, REGON and IBAN on My Account > Addresses, instead of the NIP-only one added in 1.37.10.

= 1.37.10 =
* Fixed: with the "NIP - Verification and Autocomplete" module on, My Account > Addresses > Billing address showed two NIP fields: the plugin's own one above "Country / region" and a second, inert one below the email field. The second came from the WooCommerce additional-checkout-fields copy of the field, which WooCommerce also renders on that form. Both registrations are still needed (the classic checkout renders only the first, the block checkout only the second), so the duplicate is now dropped from the edit-address form only. Reported by strid3rr on the support forum.
* Fixed: the lowest-price notice in the cart, mini-cart and order review was prefixed with the technical label "Omnibus:", e.g. "Omnibus: Lowest price in the last 30 days: 20.00". WooCommerce renders cart item data as "label: value" and the whole sentence was being passed as the value. The notice is now split, so the customer sees only "Lowest price in the last 30 days: 20.00". Reported by strid3rr on the support forum.
* Fixed (PRO): the PRO NIP validator registered a second `billing_nip` billing field on the same filter and priority as the FREE module, overwriting it. The field lost the attribute the GUS autocomplete binds to, and it appeared even with the NIP module switched off. PRO now leaves the checkout field to the FREE module and only adds the editable NIP row on the admin order screen, gated on that module.

= 1.37.9 =
* Fixed: turning the "Right of withdrawal (14 days)" module off left most of it running. The withdrawal call to action kept appearing in customer order emails, the "Polski withdrawal" box kept rendering on the order edit screen, the plugin's own withdrawal order statuses stayed registered, and the per-product withdrawal exemption field stayed on the product editor. Eighteen services belonging to the module now refuse to register their hooks while it is off. This matters to any shop using the WooCommerce 11.1 native Order Withdrawal feature, which until now ran alongside ours. Reported by strid3rr on the support forum.
* Note: the privacy exporter and eraser for withdrawal data deliberately keep running with the module off, so a GDPR request still reaches data the shop already collected.

= 1.37.8 =
* Fixed: the order CSV export held every matching order in memory before writing a row, the stock CSV export held every product, and the daily double opt-in cleanup asked for every unverified account in one query. A store with a real backlog could exhaust the memory limit halfway through. Both exports now read their IDs in one query and hydrate 200 rows at a time, and the cleanup walks 200 accounts per query; the order export batch size is filterable via `polski/order_export/batch_size`, the cleanup batch via `polski/doi/cleanup_batch_size`.
* Fixed: an export could repeat or lose a row. The list of IDs is fixed before the first row is written, so an order or product placed, saved or trashed while the export runs can no longer push a row into the file twice or out of it. A row whose order or product is gone by the time its batch is read is simply missing, and no other row moves. The values are read with the batch, not held from the snapshot, so an edit that lands before a row is read is in the file. An order keeps its row through a status change. A product does not: the stock export asks for published products, so one moved to draft after the snapshot has no row, the same as one that was trashed.
* Fixed: both CSV exports sent their download headers before doing any work, so a fatal, an exhausted memory limit or a hit time limit reached the browser as HTTP 200 with a short file attached, which looks like a complete export. Each file is now built to a temporary file first, so a failure produces a visible error page again.
* Fixed: the double opt-in cleanup advanced its position only past accounts it kept. If WordPress refused to delete an account, the loop re-read and re-attempted the same batch instead of finishing. Accounts it does not remove are now excluded from the next query, so every pass makes progress.
* Changed: the expert review editor picks a product with WooCommerce's own search field instead of a dropdown that held every published product.
* Changed: the stock export preview reports how many rows it is showing instead of the size of the whole catalogue, which is what made it load the catalogue.
* Note: 1.37.4 through 1.37.7 were never released, so this is the first release after 1.37.3. 1.37.4 claimed a secondary sort made paging safe; it does not, paging over a table the store is still writing to repeats and skips rows whatever the sort is. 1.37.5 replaced the paging with hydration by ID, but asked the product query for `post__in`, which it does not accept: it overwrites that argument with its own empty `include` default and drops it, leaving the limit to hand back the newest products instead. On a catalogue of 450 products that export wrote 200 rows and lost 250. Products are now hydrated by `include`, two unit tests walk the export over a catalogue larger than one batch, and both of them run in the release preflight, next to a check that fails when a function in the source both queries products and mentions `post__in`. That check reads the source as PHP tokens, so it sees a call to `wc_get_products()` and a `new WC_Product_Query`, each written plain or with a leading backslash; it does not see a query reached through a variable function name, a callable string or a wrapper of our own, and it does not see arguments assembled in a different function than the call.

= 1.37.3 =
* Fixed: arrow glyphs in the admin menu paths, and in the strings handed to translators. An arrow inside a translatable string makes the glyph every translator's problem and changes the layout in any locale that drops it.

= 1.37.2 =
* Fixed: the hourly CRA incident check was scheduled but cleared nowhere, so the event outlived the plugin. A WP-Cron event stays in the options table once created, waking on every request to fire a hook nothing listens to. The store health check was also cleared on deactivation but not on uninstall. The list of events now lives in one place that both paths read, because keeping two copies by hand is how they drifted.

= 1.37.1 =
* Security (high): the value social login used to tie a sign-in to the browser that started it was the same for every logged-out visitor for twelve hours, and the public address that begins the flow handed it out to anyone who asked. Someone could start a sign-in of their own, keep it unused, and send a customer a link that signed that customer in to the attacker's account instead of their own, where the address and order details they went on to enter would be the attacker's to read. Each sign-in now gets its own single-use value, held in a cookie, so a callback is only accepted from the browser that began it.

= 1.37.0 =
* Security (high): social login signed a visitor in to an existing WordPress account whenever Google or Facebook reported a matching email address, without checking that the provider had actually verified that address. Anyone who could register with a provider claiming somebody else's address was handed that person's account, an administrator's included. The address must now be verified by the provider before any account is matched, and an account that can edit the site is never linked automatically: its owner signs in with a password as before. Social login is off unless you switched it on and entered provider credentials, so a default installation was never exposed.

= 1.36.9 =
* Fixed: `[polski_wishlist]` and `[polski_compare]` rendered unstyled with dead buttons on an ordinary page. The assets loaded only on shop, product, product category and My Account pages, so a merchant who put either shortcode on a page of their own got markup with no stylesheet and no script behind it.
* Fixed: three sentences on the withdrawal form were hardcoded Polish inside a JavaScript file, so every shop saw them in Polish whatever its language, while the form around them was translated. They now come from the server and are translated with the rest of the plugin.

Only the most recent releases are listed here. The full history is on the plugin's changelog
page, [plogins.com/polski/changelog/](https://plogins.com/polski/changelog/),
and in changelog.txt inside the plugin folder. WordPress.org silently truncates
a changelog over 5000 words, which is why this one is kept short on purpose.

== Upgrade Notice ==

= 1.41.3 =
Security release. Not exposed to visitors: only a logged-in user who can write posts could read another customer's order number and date, or an unpublished product's details, through a shortcode in a post preview. Update, nothing else to do.

= 1.41.1 =
The Elementor widgets and the Google for WooCommerce integration work again, and the daily maintenance job reschedules itself.

= 1.41.0 =
Security release. Withdrawal forms for guest orders, and hidden products in Quick View, compare, wishlist and Q&A, are no longer reachable by other visitors. Update recommended.

= 1.40.1 =
Security release. Withdrawal abilities no longer expose another customer's order items. Update recommended.

= 1.37.1 =
Security release. This is the second half of 1.37.0. Default installations are not exposed: social login is off unless you enabled it and entered provider credentials. If you use it, update now.

= 1.37.0 =
Security release. Default installations are not exposed: social login is off unless you enabled the module and entered Google or Facebook credentials. If you use it, update now; nothing else to do.
