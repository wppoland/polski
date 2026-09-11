=== Polski for WooCommerce ===
Contributors: motylanogha
Tags: faktury, jpk, ksef, gpsr, zwroty
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 1.37.3
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
* **Deposit scheme (system kaucyjny)** - adds the statutory deposit to beverages in covered packaging, untaxed and on its own checkout line.
* **GDPR consents and checkboxes** - configurable consents at checkout, registration and reviews, with a consent log.
* **Right of withdrawal and returns** - requests from the customer account, e-mail confirmations and a request log.
* **VAT ID (NIP) and KSeF hooks** - detection of orders with a VAT ID, a KSeF flag and hooks for invoicing integrations.
* **EU VAT ID check (VIES)** - confirms a customer's EU VAT number against the European Commission's register and records the consultation number on the order.
* **VAT margin scheme** - the art. 120 annotation on invoices for second-hand goods, works of art, collectors' items and antiques.
* **GTU markings** - one of the thirteen JPK_V7 goods and services groups per product, printed against its own line on the invoice.
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

= 1.36.8 =
* Fixed: deleting the plugin left the per-user "dismiss" flag from the PRO notice in the database. Uninstall now removes it for every user, not just the one who dismissed it.

= 1.36.7 =
* Fixed: food products published two and a half times as much sodium as their own label. Annex XV declares salt, Schema.org only has `sodiumContent`, and salt is sodium multiplied by 2.5, but the two were mapped straight across, so a product labelled 2.5 g of salt told Google it contained 2.5 g of sodium. The figure is now converted before it is published.
* Fixed: the unit price in structured data carried a hardcoded Polish label, printed as-is on every non-Polish shop, and a private `polski_unit_price` key nothing reads. The label is translated and the private key is gone.

= 1.36.6 =
* Added: the Food and supplements module now has fields. The Modules screen has always said you can enter ingredients, nutrition, Nutri-Score, alcohol content, country of origin and the food business operator per product, and until now the only way to write any of them was a CSV import, so a shop without a spreadsheet could not use the module at all. The Polski tab on the product screen carries all of them, with one input per nutrient instead of the importer's `slug:value|slug:value` syntax. Values typed here and values imported from CSV end up identical, so an export still round-trips.
* Fixed: every multi-line field on that tab lost its line breaks when saved. The save pass ran `sanitize_text_field` over each value before choosing a sanitiser, and that function folds newlines into spaces, so a GPSR manufacturer address, a list of safety warnings, repair information and the substantiation of an environmental claim were all stored as one long line.

= 1.36.5 =
* Fixed: Units, Allergens, Nutrients, Manufacturers, Delivery Times and a second "Brands" appeared under Products even when their modules were off. The six taxonomies registered on every request regardless of the Modules screen, so a shop that never turned on the food module or brands still got four admin screens it could not use, and the brands one sat next to WooCommerce core Brands under the same name. Each taxonomy now registers only when its module is enabled. Terms already saved are untouched and come back with the module.

= 1.36.4 =
* Fixed: the GPSR manufacturer never reached the product's structured data, the data layer or the product feed. All three read a meta key the plugin does not write, while the field on the product screen saves under the GPSR key, so the manufacturer was simply absent from everything downstream.
* Added: the "Buying as a company" answer is now shown on the order screen. It was stored on every order and displayed nowhere, so a merchant could turn the toggle on and never see a reply.
* Added: product safety instructions, safety documents, food distributor and the product-level right-of-withdrawal exemption reason can now be set through the product CSV import and export. Their shortcodes and Elementor widgets read them and nothing could fill them in, so those blocks were always empty.
* Removed: two order and user meta keys that were written and never read back, a copy of the checkout consent states (the consent log already records them, with its own screen and CSV export) and a social login provider name (the provider is already part of the stored provider ID).

= 1.36.3 =
* Fixed: the plugin's WooCommerce emails could be silently absent for a whole request. Loading the mailer is not enough on its own: if anything built it before this plugin registered its filter, and third-party plugins do build it on plugins_loaded, the cached mailer was assembled without our classes and the filter could never run again. Measured in a test install: zero of the classes present instead of all of them. The mailer is now topped up when that happens.
* Removed: an integration-detection class that could never have run. It was built by the service container but never listed as a hook subscriber, so nothing ever called it, and its one registration was on `plugins_loaded`, which has already fired by the time the plugin boots. Its signals had no listeners in either the free or the paid plugin. Nothing used it and nothing changes; the code is gone rather than left to read as a working feature. A build-time check now covers this shape across the whole plugin family, so a hook that can never fire cannot ship again unnoticed.

= 1.36.2 =
* Fixed: a partial write to the settings REST endpoint overwrote every key the request left out with the packaged default, instead of leaving it as stored. For the storefront text keys that default is a translated string resolved at the moment of the request, so one such write stamped whatever language the request ran in into the database and the wording stopped following the shop's locale. An omitted key now keeps its stored value, and an empty request body no longer resets the whole group.

The last ten releases are below. The full history is on the plugin's changelog
page, [plogins.com/polski/changelog/](https://plogins.com/polski/changelog/),
and in changelog.txt inside the plugin folder. WordPress.org silently truncates
a changelog over 5000 words, which is why this one is kept short on purpose.

= 1.36.1 =
* Changelog: this readme now carries the last ten releases and links to the full history, instead of every release ever. WordPress.org silently truncates a changelog over 5000 words and only warns the plugin authors by email, so the page had been one release away from quietly dropping its oldest entries. Nothing about the plugin changes.

= 1.36.0 =
* Added: the NIP field can now be required by what is in the cart, not only by the shop-wide switch. One place decides and it is filterable (`polski/nip_required`), so a paid rule that marks a product as needing the buyer's VAT ID on the receipt can make the field mandatory for that order. Enforced on both the classic and the block checkout, since the block field is registered before a cart exists and its own required flag cannot see one.

= 1.35.0 =
* Fixed: the DSA report form's handler was registered for logged-out visitors whether or not the module was on. With the module off the table it writes to may not exist, so an anonymous submission was lost while the sender was still redirected to a thank-you page, and the shop notification was still sent. Nothing is registered now unless the module is on, and a check covers every public handler in the plugin so this cannot come back unnoticed.
* Fixed: Quick View answered its AJAX call and rendered product markup even when the module was switched off. Its other three callbacks already checked; this one did not.
* Fixed: searching by barcode missed WooCommerce's own GTIN field. The search matched only the plugin's fallback meta key, so a shop that filled in the GTIN field WooCommerce added in 8.4 could not find the product by its barcode at all.
* Fixed: the environmental claim fields collected a claim's basis, its certificate link and an expiry date, and then showed none of it to anybody. The substantiation now appears on the product page. An expired certificate is labelled as expired rather than quietly presented as current proof, which is the failure the directive is about.
* Added: `polski_gtin` as a CSV import and export column. The meta key behind it was read in six places and written by nothing, and the documentation already promised a column for it.
* Docs: rewrote the CSV import and export page in all four languages. It listed about forty columns that do not exist, three filters the plugin never registered, and a validation pass that does not run. The column table is now generated from the plugin's own map, so it cannot drift again.

= 1.34.1 =
* Corrected the PRO price shown in two places: this readme and the upgrade panel inside the plugin both said 69 EUR per year, and the charge is 99 EUR. Freemius is what actually bills and it was changed first, so for a short window both quoted a price the checkout did not honour. Nothing about the free plugin itself changes.

= 1.34.0 =
* Fixed: six modules had a service in the plugin and no card on the Modules screen, so there was no way to switch them on: the compliance checklist, the business identification block, the complaint form template, the copyright and image credit shortcodes, the SBOM generator and the RODO training documents. Each now has a card, keeping the state it already defaulted to, so nothing changes on a shop until the merchant turns it on.
* New: the modules registry and the module default states run through the `polski/modules` and `polski/module_defaults` filters, so an add-on can put its own cards on the Modules screen instead of gating a feature on a switch that exists nowhere.
* A release check (tests/module-ids-are-reachable-check.php) now fails when a module id has a default state but no card in either plugin, which is what let this happen.

= 1.33.2 =
* Listing only, no functional change. The short description now names JPK_FA and the NIP lookup, which the plugin has done for a long time without saying so where anyone searching would see it. Tags: "rodo" is dropped, because that search is dominated by cookie and newsletter plugins and this plugin does not appear in it at all, and "jpk" and "zwroty" take its place.

= 1.33.1 =
* Fixed: the "withdraw from this order" link in the order email did not work for a logged-in customer. It led to "Oops, something went wrong on our side" instead of the form, so the withdrawal could not be started from the email at all. The link never carried a one-time token and the page demanded one. Opening the form changes nothing and the order's owner is checked separately, so the token is no longer required there; the form submission that actually files the declaration keeps its own, unchanged.

= 1.33.0 =
* New: a GTU marking on the product, under Product data > Polski > Invoicing. Pick one of the thirteen groups from the JPK_V7 regulation, or leave it at no marking. The code is stored with the product and printed against that line on the invoice, so an order mixing marked and unmarked goods says which line the marking belongs to. Shipping and fees never carry one, and a product left unmarked prints nothing.

= 1.32.1 =
* Fixed: the PRO promo was shown to people who had already bought PRO. It never checked whether the paid plugin was installed, so a paying customer kept being sold the thing they were running. It now disappears as soon as PRO is active.
* Changed: the "Legal Email Attachments" module is now called "Legal texts in emails", because it attached nothing. It prints the text of your legal pages under the order table in four customer emails. The documentation described PDF files and a per-email-type document matrix that no released version ever had; that page has been rewritten to match the code, and the PDF version is a PRO feature. Reported on GitHub (discussion #49).
* Changed: the PRO promo now also appears on the Modules, Settings and Reports screens, not only the Dashboard. It stays dismissible and never leaves this plugin's own screens.

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

== Upgrade Notice ==

= 1.37.1 =
Security release. This is the second half of 1.37.0. Default installations are not exposed: social login is off unless you enabled it and entered provider credentials. If you use it, update now.

= 1.37.0 =
Security release. Default installations are not exposed: social login is off unless you enabled the module and entered Google or Facebook credentials. If you use it, update now; nothing else to do.
