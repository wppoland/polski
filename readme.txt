=== Polski for WooCommerce ===
Contributors: motylanogha
Tags: faktury, jpk, ksef, gpsr, zwroty
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 1.43.6
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

One free, open-source plugin with many modules, built for shops selling in Poland. PHP 8.1+, a React admin panel, REST API and WP-CLI, classic and block cart and checkout, HPOS compatible.

= Key modules =

* **GPSR product fields** - manufacturer, importer and EU responsible person with the addresses and contact art. 19 asks for, product identifiers, safety warnings and instructions, with CSV import and export.
* **Omnibus price history** - records and displays the lowest price from the last 30 days on discounted products.
* **Product markings** - per-product flags that print the statutory notice on the product page: medical device, and goods sold to adults only such as alcohol or tobacco.
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
* **AI-classified return reasons** - return reasons sorted by the optional AI classifier carry a "classified by AI" marker and can be filtered in the admin withdrawals list.

= Checkout, consents and returns =

* **Consent checkboxes** (beta on the block checkout) - consents at order, registration and reviews, with the option to enable only selected fields. On the block checkout the labels show without links and the conditional boxes are not shown.
* **Right of withdrawal** - withdrawal/return forms and requests from the customer account.
* **Double e-mail confirmation** (beta) - e-mail address confirmation during customer registration. An account created at checkout stays logged in for that session.
* **Shop pages** - link the terms, privacy policy and withdrawal content into WooCommerce notices.
* **Dispute resolution** - a notice about out-of-court complaint and redress options (consumer ombudsman, Trade Inspection) on the cart and checkout pages and as a shortcode.
* **Consent log** - logging of consents with date, context, IP address and content version.

= Product data and labelling =

* **Unit prices** - price per kg, litre, metre, piece or a custom unit.
* **GTIN validation** - GS1 check-digit validation (GTIN-8/12/13/14) in the product editor and CSV import, warning without blocking saves.
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
* **AJAX search** - live product search by title, SKU, category and chosen attributes, placed with a shortcode or block.
* **Product badges** - sale, new, featured and custom labels.
* **Promotional popups** - popup campaigns in the shop.

= Admin and developer tools =

* **React panel** - manage modules and settings.
* **REST API** - an API for settings, checkboxes, legal pages, withdrawals and search.
* **WP-CLI** - commands to manage selected features from the terminal.
* **CSV import and export** - bulk management of product data, including GPSR and GTIN validation warnings.
* **Shortcodes** - embed GPSR information, withdrawal forms, DSA reports and other elements.
* **Database migrations** - versioned and safe updates of data structures.
* **Integration hooks** - filters and actions for KSeF, invoicing and integrations with other plugins.

= You may also like these plugins =

More free WooCommerce plugins from WPPoland:

* [Plogins Tiers](https://wordpress.org/plugins/plogins-tiers/) - quantity and volume pricing tiers with a server-rendered price table.
* [Plogins Waitlist](https://wordpress.org/plugins/plogins-waitlist/) - back-in-stock waitlist that emails shoppers the moment a product returns.
* [Sieve - Search & Filter](https://wordpress.org/plugins/sieve/) - fast AJAX product search and filtering for WooCommerce, with no jQuery.

Browse the full catalogue at [plogins.com/](https://plogins.com/) .

Reporting a security issue: email hello@wppoland.com, and under our [coordinated disclosure policy](https://wppoland.com/en/security-policy/) we confirm within two business days, assess within five, and patch a critical issue within seven days of confirming it.

== Polski PRO ==

Polski PRO is a separate paid plugin.

Included in Polski for WooCommerce:

* Numbered VAT invoices
* GPSR, Omnibus, consents, withdrawals and unit prices
* NIP detection, VIES check, KSeF hooks

Polski PRO adds:

* PDF invoices, UBL/PEPPOL XML, JPK_FA report
* KSeF 2.0 sending (FA(3), online mode)
* Shipping integrations (InPost, DPD, DHL, Poczta Polska)
* Multi-step checkout, subscriptions and gift cards
* Fulfilment, affiliates, pre-orders, bundles, catalog mode and RFQ

The free edition is not a trial and its modules remain available without a time limit.

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

= Getting started =

1. **Check the legal pages**: go to **Polski > Modules** and make sure the legal pages module is active. In its settings choose your terms, privacy policy and withdrawal page.
2. **Configure the checkboxes**: open the legal checkboxes module and enable the consents your shop requires.
3. **Check VAT rates**: make sure WooCommerce has the correct tax rates for your shop.
4. **Fill in unit prices**: for products sold by weight or volume, fill in the data in the **Polski** tab of the product editor.
5. **Enable Omnibus**: the module records price history and can display the lowest price from 30 days.
6. **Fill in GPSR**: for physical products add the manufacturer, importer and responsible person data and safety information.

= Configuration =

Polski works in a modular way. You can enable only the features you need:

* **Product data**: GPSR, unit prices, delivery time, food data.
* **Checkout and consents**: checkboxes, right of withdrawal, legal pages.
* **Storefront**: wishlist, comparison, search, filters and badges.

Active modules with settings appear in the **Polski** menu or have a settings link on the modules page.

== Frequently Asked Questions ==

= Is Polski translated? =

Polski for WooCommerce is fully translatable and ships the `polski.pot` template. Translations are delivered by WordPress.org language packs from translate.wordpress.org, which is where Polish, German and Spanish are being contributed; the package itself carries no compiled translation files.

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

Yes. The Invoices module adds an Issue invoice button to the order screen and numbers VAT invoices per year (FV/1/2026). The customer opens the invoice from My Account and prints it from the browser. It does not send invoices to KSeF and does not replace an accounting system. Invoices as PDF files, UBL/PEPPOL XML and the JPK_FA report come with Polski PRO.

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

The plugin makes no external calls except the ones listed below. Each one is off until you switch on the module and enter the account, ID or address it needs. The Compliance checklist fetches your own shop's pages only.

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

= Swiss UID register (Federal Statistical Office) =

When the EU VAT ID check module is enabled and the number is Swiss (CHE), the same check from the order screen asks the Swiss UID register instead of VIES.

* Data sent: the customer's Swiss UID or VAT number.
* Service address: [https://www.uid-wse.admin.ch/](https://www.uid-wse.admin.ch/)
* Terms of use and data protection: [https://www.uid.admin.ch/Disclaimer.aspx](https://www.uid.admin.ch/Disclaimer.aspx)

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

= Tracking tags (advertising and analytics) =

When the Tracking Tags module is enabled, each provider below loads only if you switch it on and enter your own ID, key or domain. Its script is placed in the page as an inert placeholder and runs in the visitor's browser only after the visitor grants the matching consent category in the cookie banner (marketing for advertising pixels, analytics for measurement tools). Nothing is sent from your server.

* Data sent: the page address, referrer, browser and device data, the visitor's IP address and the provider's own cookie identifiers, plus a page-view event. The plugin sends no order or customer data through these tags.
* Meta Pixel: [https://connect.facebook.net/](https://connect.facebook.net/), [terms](https://www.facebook.com/legal/terms/businesstools), [privacy policy](https://www.facebook.com/privacy/policy/)
* TikTok Pixel: [https://analytics.tiktok.com/](https://analytics.tiktok.com/), [terms](https://ads.tiktok.com/i18n/official/policy/business-products-terms), [privacy policy](https://www.tiktok.com/legal/page/eea/privacy-policy/en)
* Microsoft Advertising (Bing UET): [https://bat.bing.com/](https://bat.bing.com/), [terms](https://about.ads.microsoft.com/en/resources/policies/microsoft-advertising-agreement), [privacy policy](https://privacy.microsoft.com/en-us/privacystatement)
* Microsoft Clarity: [https://www.clarity.ms/](https://www.clarity.ms/), [terms](https://clarity.microsoft.com/terms), [privacy policy](https://privacy.microsoft.com/en-us/privacystatement)
* LinkedIn Insight Tag: [https://snap.licdn.com/](https://snap.licdn.com/), [terms](https://www.linkedin.com/legal/sas-terms), [privacy policy](https://www.linkedin.com/legal/privacy-policy)
* Pinterest Tag: [https://s.pinimg.com/](https://s.pinimg.com/), [terms](https://policy.pinterest.com/en/terms-of-service), [privacy policy](https://policy.pinterest.com/en/privacy-policy)
* X (Twitter) Ads: [https://static.ads-twitter.com/](https://static.ads-twitter.com/), [terms](https://x.com/en/tos), [privacy policy](https://x.com/en/privacy)
* Google Ads: [https://www.googletagmanager.com/](https://www.googletagmanager.com/), [terms](https://policies.google.com/terms), [privacy policy](https://policies.google.com/privacy)
* Matomo: the Matomo address you enter (your own server or Matomo Cloud), [terms](https://matomo.org/terms/), [privacy policy](https://matomo.org/privacy-policy/)
* Plausible: [https://plausible.io/](https://plausible.io/), [terms](https://plausible.io/terms), [privacy policy](https://plausible.io/privacy)
* PostHog: [https://app.posthog.com/](https://app.posthog.com/), [terms](https://posthog.com/terms), [privacy policy](https://posthog.com/privacy)
* Hotjar: [https://static.hotjar.com/](https://static.hotjar.com/), [terms](https://www.hotjar.com/legal/policies/terms-of-service/), [privacy policy](https://www.hotjar.com/legal/policies/privacy/)
* Inspectlet: [https://cdn.inspectlet.com/](https://cdn.inspectlet.com/), [terms and privacy policy](https://www.inspectlet.com/legal)
* Crazy Egg: [https://script.crazyegg.com/](https://script.crazyegg.com/), [terms](https://www.crazyegg.com/terms), [privacy policy](https://www.crazyegg.com/privacy)
* Simple Analytics: [https://scripts.simpleanalyticscdn.com/](https://scripts.simpleanalyticscdn.com/), [terms](https://simpleanalytics.com/terms), [privacy policy](https://simpleanalytics.com/privacy-policy)

= Google Fonts preconnect =

When the Safe Fonts module is enabled and Google Fonts are not held back until consent, the plugin adds preconnect hints for fonts.googleapis.com and fonts.gstatic.com, so the visitor's browser opens a connection to Google before your theme loads its Google Fonts. Data sent: the visitor's IP address and browser data. [Terms](https://policies.google.com/terms), [privacy policy](https://policies.google.com/privacy), [Google Fonts privacy FAQ](https://developers.google.com/fonts/faq/privacy).

= Featured product video =

When the Featured Video module is enabled and you enter a video address (for example YouTube or Vimeo), WordPress fetches the embed code from that provider through its oEmbed feature, and the visitor's browser loads the player from the provider. Data sent: the video address from your server; the visitor's IP address and browser data from the player. The provider's own terms and privacy policy apply.

= AI features (WordPress AI Client) =

The optional AI features do not call a provider themselves: they hand a prompt to the WordPress AI Client, and the AI provider plugin you install and configure (for example OpenAI, Anthropic or Google) sends it to that provider. Nothing is sent when no provider is configured.

* Withdrawal reason classification (off by default, Withdrawal settings): after a declaration is filed, the customer's free-text reason is sent to classify it into a fixed category.
* GPSR safety text draft and product summary (AI Bridge module, off by default): when an admin clicks generate, the product name, short description and description are sent.
* The terms and privacy policy of the provider you configured apply.

= Webhooks you configure =

The Store health monitor and the CRA incident log (security.txt module settings) can post to a webhook address you enter. Nothing is sent unless you fill in that address.

* Store health monitor: an alert text with the store status and the failed checks, sent as JSON to your webhook URL.
* CRA incident log: the incident record you dispatch (reference, type, severity, status, title, summary, affected component and versions, dates, reporter and notes), sent as JSON to your webhook URL.
* The receiving service is the one you choose; its terms and privacy policy apply.

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
9. The KSeF column on the WooCommerce orders list: a dash for orders not flagged for KSeF, an amber dot for a flagged order awaiting an invoice, a green dot once an integration records the invoice as accepted.

== Changelog ==

= 1.43.6 =
* Security (low): uninstalling the free plugin with data removal enabled no longer deletes the settings of Polski PRO.
* Fix: on the block checkout, the "I need a VAT invoice" answer now reaches the order field the invoice and KSeF modules read, as it already did on the classic checkout.

= 1.43.5 =
* Security (medium): order, stock, withdrawal, consent and security-incident CSV exports now neutralise cells that a spreadsheet would run as a formula. Text a shopper typed (a name, address, order note, withdrawal reason or browser user agent) could otherwise execute in Excel or LibreOffice when a shop manager opened the export.
* Security (low): the recent-purchase popups no longer name or link private, draft or password-protected products.
* Security (low): guest product questions are limited to five per hour per visitor, so a script can no longer flood the moderation queue and the shop mailbox.
* Security (low): the double opt-in activation link refuses an empty token, and the public withdrawal-exemption ability answers only for products the visitor can see.
* Security (low): the VAT ID (NIP) lookup at checkout is limited to ten per hour per visitor, so the shop can no longer be used as an unlimited proxy to GUS on its own API key. The DSA notice limit now also holds on sites without a persistent object cache, where it never applied.

= 1.43.4 =
* The Upgrade to PRO links open the pricing page in the admin's language where plogins.com has one, and tell plogins.com which plugin the visit came from (utm_source, utm_medium and utm_campaign in the link). Nothing is sent from your site.
* Readme: the PRO section lists what the free plugin includes and what the paid edition adds as two lists, which WordPress.org can display (it does not display tables).

= 1.43.3 =
* Changed: the Polski PRO card on the settings screen no longer lists VAT invoices as a PRO feature, because the free plugin issues them; it now offers the invoice as a PDF file. The readme FAQ says the free plugin issues invoices.

= 1.43.2 =
* Readme only: new screenshot of the KSeF status column on the orders list.

= 1.43.1 =
* Fixed: Omnibus: a product that was already on sale when price history began showed no lowest-price notice (or repeated the sale price). The notice now shows the regular price recorded on the row that opens the sale, which is the price it was reduced from. A product with no history at all still follows the "no history" setting.
* Docs: The readme Description tab is back under the WordPress.org 2,500-word limit; Getting Started and Configuration moved to Installation, Translations to the FAQ.

= 1.43.0 =
* Fixed: Withdrawal from the contract (art. 11a Directive 2011/83/EU) is now available for the whole withdrawal period. It used to count 14 days from the order date until the order was completed, so a paid order still in processing after 14 days (slow shipping, a preorder) lost the withdrawal button before the goods arrived. It is now offered from the moment the contract is concluded (order processing or on hold), stays open until the order is completed, and closes 14 days from the date the order was completed, as before; an order that never reaches the countdown status keeps it open. Pending, failed, cancelled and refunded orders no longer offer it.
* Fixed: The withdrawal statement (My Account form, one-click confirmation and guest form) now shows the customer's name and the e-mail address the confirmation goes to (art. 11a(2)).
* Fixed: The order button text is no longer written into the settings at activation. The checkout prints the statutory wording in the shop language at runtime, so a shop installed before its language pack arrived no longer shows the English "Order with an obligation to pay". Shops that already have a stored text keep it.
* Changed: The Privacy Policy checkbox is described as optional (order data rests on GDPR art. 6(1)(b)) and no longer counts towards the checkbox setup score; the Terms and Withdrawal checkboxes are "recommended" rather than "required". Checkbox behaviour and defaults are unchanged.
* Changed: Omnibus settings warn when the lowest-price period is below 30 days, and the "Today" and "Show current price" options are labelled as not compliant with art. 6a(2) Directive 98/6/EC. Defaults are unchanged.
* Changed: Legal wording corrected in the site audit, module descriptions and order e-mails: the withdrawal reminder cites art. 9 of Directive 2011/83/EU; the forced-account check no longer cites Directive 2023/2673; security.txt, the incident log and the SBOM are not described as CRA duties for shops; the 2024/825 consumer information is not the harmonised notice or label of Implementing Regulation (EU) 2025/1960; the environmental claim fields and the DSA check say when they apply.
* Docs: The readme lists every external service the plugin can contact, including the Swiss UID register, all tracking tag providers, the optional AI features, the Google Fonts preconnect, featured video embeds and the webhooks you configure.

= 1.42.0 =
* Fixed: On block themes, Product Collection grids outside the shop archive (a homepage grid, related products, cart cross-sells) showed no unit price, no Omnibus 30-day lowest price, no delivery time and no brand. WooCommerce fires the classic loop hooks there only for the shop grid, so these now follow the price block of every other grid, once per card.
* Fixed: With "Omnibus: Cart" on, a block-theme shop page and the product page price showed the Omnibus notice twice. Cart cross-sells now follow the "product lists" Omnibus setting like every other grid; cart items keep the "Cart" setting.
* Fixed: Block themes: product badges and a featured video set to "before summary" landed above the breadcrumbs on the product page. Both now sit with the image gallery. Badges also show on Product Collection cards outside the shop archive, and replace the Product Sale Badge block where Polski shows its own sale badge.
* Fixed: Wishlist, Compare and Quick View buttons were missing from Product Collection grids outside the shop archive, and their scripts did not load there. Each card gets one button per feature.
* Fixed: Social login buttons now show above the Checkout block, which fires none of the classic checkout hooks.
* Fixed: Product authors now show on Product Collection cards outside the shop archive.

= 1.41.8 =
* Fixed: The iworks Omnibus import read the raw WooCommerce sale field, so a sale that was only scheduled counted as a price the product sold at, and its sale rows were never closed. It now takes the selling price, imports only what lies before Polski's own history for a product, and on upgrade removes the rows 1.41.7 imported wrongly. On some products these made the lowest price equal the running sale price.

= 1.41.7 =
* Fixed: The Omnibus lowest price now shows on single product pages built with an Elementor Pro template, which replaces the WooCommerce summary hook the notice was attached to. It is placed under the price widget, once, also when the template carries the Polski Omnibus widget.
* Added: Import of the price history kept by the Omnibus plugin by iworks (v2 meta and v3 price log), run once on upgrade and on demand with `wp polski import-iworks`, so a shop switching to Polski keeps its 30-day window.

= 1.41.6 =
* Added: GS1 modulo-10 check-digit validation for GTIN-8, GTIN-12, GTIN-13, and GTIN-14 barcodes in product editor and CSV importer, warning merchants of invalid barcodes without blocking saves.

= 1.41.5 =
* Added: Borlabs-style Content Blocker for third-party media embeds (YouTube, Vimeo, Google Maps, OpenStreetMap, SoundCloud) with placeholder opt-in and dynamic runtime unblocking when marketing consent is granted.
* Added: Swiss UID (CHE) VAT number format validation and SOAP integration for cross-border DACH trade.
* Fixed: Omnibus archive price cache key alignment between windowed lookup and cache warmer invalidation.

= 1.41.4 =
* Added: native administrator email notification ("New withdrawal declaration (Admin)") registered in WooCommerce > Settings > Emails, alerting the shop whenever a customer files a return.
* Fixed: guest withdrawal lookup form now auto-detects pages with the [polski_withdrawal_lookup] shortcode even when unselected in settings, wraps magic link emails in WooCommerce HTML styling with proper From headers, and provides feedback if an order is not eligible.
* Fixed: guest order lookup now supports sequential order number plugins and trims hash/whitespace prefixes.

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

Only the most recent releases are listed here. The full history is on the plugin's changelog
page, [plogins.com/polski/changelog/](https://plogins.com/polski/changelog/),
and in changelog.txt inside the plugin folder. WordPress.org silently truncates
a changelog over 5000 words, which is why this one is kept short on purpose.

== Upgrade Notice ==

= 1.43.5 =
Security release. Default installations are exposed only when a shop manager opens a CSV export in a spreadsheet; the other fixes apply to optional modules. Update, nothing else to do.

= 1.41.5 =
Adds Borlabs-style media embed content blocking, Swiss UID validation, and improves Omnibus cache key alignment.

= 1.41.4 =
Adds native administrator email notifications for customer returns and improves guest return lookup deliverability.

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
