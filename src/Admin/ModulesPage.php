<?php

declare(strict_types=1);
namespace Polski\Admin;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Service\CacheHelper;

/**
 * Module management page - toggleable feature groups.
 *
 * Each module corresponds to a feature set that can be enabled/disabled.
 * Modules are stored as a serialized array in polski_modules option.
 */
final class ModulesPage implements HasHooks
{
    private const OPTION = 'polski_modules';

    /**
     * Default documentation URL when a module has no dedicated article on polski.wppoland.com yet.
     */
    private const MODULE_DOCS_FALLBACK_URL = 'https://polski.wppoland.com/getting-started/configuration/';

    /** @var array<string, string>|null */
    private static ?array $moduleDocumentationUrls = null;

    public function registerHooks(): void
    {
        add_action('admin_post_polski_save_module_settings', [$this, 'handleSaveModuleSettings']);
        add_action('wp_ajax_polski_toggle_module', [$this, 'ajaxToggleModule']);
    }

    /**
     * Public documentation URL for a module on polski.wppoland.com.
     */
    private function getModuleDocumentationUrl(string $moduleId): string
    {
        $slug = preg_replace('/[^a-z0-9_-]/', '', $moduleId);

        if (self::$moduleDocumentationUrls === null) {
            $loaded = require dirname(__DIR__, 2) . '/config/module-documentation-urls.php';
            self::$moduleDocumentationUrls = is_array($loaded) ? $loaded : [];
        }

        return self::$moduleDocumentationUrls[$slug] ?? self::MODULE_DOCS_FALLBACK_URL;
    }

    /**
     * Tooltip text: optional `tooltip` on the module, otherwise the card description.
     *
     * @param array<string, mixed> $module
     */
    private function getModuleHelpTooltip(array $module): string
    {
        // Explicit per-module help text ("what it is + what happens when enabled")
        // takes priority, then an inline tooltip override, then the description.
        $id = isset($module['id']) && is_string($module['id']) ? $module['id'] : '';
        if ($id !== '') {
            static $tips = null;
            if ($tips === null) {
                $tips = $this->getModuleTooltips();
            }
            if (isset($tips[$id]) && $tips[$id] !== '') {
                return $tips[$id];
            }
        }

        if (isset($module['tooltip']) && is_string($module['tooltip']) && $module['tooltip'] !== '') {
            return $module['tooltip'];
        }

        return (string) ($module['description'] ?? '');
    }

    /**
     * Long-form help shown in the module help tooltip: what the module is
     * and what happens once it is enabled. Keyed by module id.
     *
     * @return array<string, string>
     */
    private function getModuleTooltips(): array
    {
        return [
            'dynamic_pricing' => __('Automatic cart discounts based on spending or quantity. When enabled, shoppers get a percentage off once the cart subtotal hits a threshold, and bulk discounts when a product\'s quantity hits a threshold; applied automatically in the cart. Off by default.', 'polski'),
            'unit_price' => __('Shows the price per unit, such as per 1 kg or per 100 ml. When enabled, a per-unit price appears next to product prices on shop and product pages, helping customers compare value, in line with Polish consumer law.', 'polski'),
            'omnibus' => __('Tracks price history and shows the lowest price from the last 30 days on discounted products. When enabled, sale products display their lowest 30-day price near the price, helping you meet the EU Omnibus Directive (2019/2161).', 'polski'),
            'tax_display' => __('Controls how VAT is shown on prices. When enabled, you can set gross or net price display, show VAT rate info, and support the small business VAT exemption (Art. 113), affecting prices shown to shoppers across the store.', 'polski'),
            'oss_observer' => __('Watches your cross-border EU B2C sales against the OSS threshold. When enabled, it installs the One Stop Shop plugin, which monitors intra-EU B2C sales and flags when you approach the 10,000 EUR threshold for the year.', 'polski'),
            'delivery_time' => __('Shows an estimated delivery time on product pages. When enabled, each product page displays its delivery estimate; you can set it per product or variation, with a default used when none is specified. Off until enabled.', 'polski'),
            'shipping_notice' => __('Adds a link to your shipping costs page near prices. When enabled, a shipping costs info link appears next to the product price, so customers can check delivery charges before buying. Off until enabled.', 'polski'),
            'checkout_button' => __('Changes the order button wording at checkout. When enabled, the place-order button reads "Order with obligation to pay", as required by Polish law, so it\'s clear the order creates a payment obligation.', 'polski'),
            'legal_checkboxes' => __('Adds built-in consent checkboxes to checkout. When enabled, you can show up to 7 checkboxes (terms, privacy policy, right of withdrawal, digital content, delivery notifications, review reminder, marketing) for customers to accept during purchase.', 'polski'),
            'nip_lookup' => __('Adds a NIP (tax ID) field at checkout. When enabled, customers can enter a NIP that is checked for a valid checksum, and company data is fetched automatically from the GUS REGON database to fill in business details.', 'polski'),
            'consent_logging' => __('Keeps a record of customer consents. When enabled, every consent a customer gives is logged with IP address, browser (user agent), and timestamp, giving you an audit trail to support GDPR record-keeping. Off until enabled.', 'polski'),
            'consent_manager' => __('A native cookie-consent banner. When enabled, visitors see a consent banner with categories, scripts and iframes are blocked until the matching category is granted, Google Consent Mode v2 signals are sent, and each decision is recorded. Provides tools, not legal advice.', 'polski'),
            'returns_rma' => __('Lets customers request returns or complaints (RMA). When enabled, shoppers can open a complaint or return request from My Account on eligible orders; they get a confirmation email and you manage requests in an admin queue with statuses. Provides tools, not legal advice.', 'polski'),
            'legal_pages' => __('Generates standard legal pages for you. When enabled, it can create Terms and Conditions, Privacy Policy, Right of Withdrawal, and Complaints pages, giving you a starting point for your store\'s required documents. Off until enabled.', 'polski'),
            'withdrawal' => __('Handles 14-day right-of-withdrawal requests. When enabled, customers get a withdrawal form and a My Account action with a confirmation step and email; you can exclude specific products that are not eligible for withdrawal. Off until enabled.', 'polski'),
            'dispute_resolution' => __('Shows EU Online Dispute Resolution (ODR) info. When enabled, your store displays information about the European Commission\'s ODR platform, typically in the footer or legal area, helping with consumer dispute transparency. Off until enabled.', 'polski'),
            'email_attachments' => __('Attaches your legal documents to order confirmation emails. When enabled, the content of pages like Terms and Conditions, Privacy Policy, and Right of Withdrawal is included with the confirmation email each customer receives after placing an order.', 'polski'),
            'manufacturer' => __('Adds manufacturer and GPSR product safety details to your products. When enabled, you can enter manufacturer info, an EU responsible person, safety documents, and instructions per product, which then appear for shoppers on the product page.', 'polski'),
            'food_module' => __('Adds food and supplement product details to your products. When enabled, you can enter nutrition facts, allergens, ingredients, Nutri-Score, alcohol content, country of origin, and distributor per product, which then show to shoppers on the product page.', 'polski'),
            'power_supply' => __('Adds energy consumption details for electrical devices. When enabled, you can enter energy label data per product, which is then displayed to shoppers on the product page for electrical items.', 'polski'),
            'double_opt_in' => __('Verifies a customer\'s email address when they register an account. When enabled, new sign-ups receive an activation link by email and cannot log in until they confirm it, helping confirm real email addresses. Off until enabled.', 'polski'),
            'ajax_search' => __('Speeds up product search with instant suggestions as shoppers type. When enabled, your store\'s search box shows live product matches (including by SKU and category) without reloading the page, kept lightweight for fast page performance. Off until enabled.', 'polski'),
            'brands' => __('Adds product brands as a separate feature from the manufacturer. When enabled, you can assign brands to products using a dedicated brand taxonomy, and brand info appears for shoppers on product pages and listings. Off until enabled.', 'polski'),
            'ajax_filters' => __('Lets shoppers filter product listings without reloading the page. When enabled, customers can narrow results by category, brand, price, stock status, sale, and attributes, with the listing updating instantly. Off until enabled.', 'polski'),
            'wishlist' => __('Lets shoppers save favorite products for later. When enabled, both guests and logged-in customers can add or remove items instantly, and logged-in customers see their saved list in My Account. Off until enabled.', 'polski'),
            'compare' => __('Lets shoppers compare products side by side. When enabled, guests and logged-in customers can add products to a comparison table that highlights differences, and customers can view it in My Account. Off until enabled.', 'polski'),
            'quick_view' => __('Lets shoppers preview a product without leaving the listing. When enabled, a lightweight pop-up opens from product listings showing the price, gallery, variations, and basic purchase info. Off until enabled.', 'polski'),
            'badge_management' => __('Adds merchandising badges to your products. When enabled, badges appear on product pages and listings for shoppers, set either automatically by conditions you choose or manually per product. Off until enabled.', 'polski'),
            'tab_manager' => __('Lets you add extra tabs to product pages. When enabled, shoppers see additional tabs with content you set per product, plus global tabs for shipping, returns, and business information. Off until enabled.', 'polski'),
            'featured_video' => __('Adds a video to your product pages. When enabled, shoppers see a product video on the product page, embedded from YouTube, Vimeo, or uploaded as a local MP4 file. Off until enabled.', 'polski'),
            'gallery_zoom' => __('Adds lightweight image zoom and a simple gallery lightbox, with no heavy external slider libraries. When enabled, product image zoom and a click-to-enlarge lightbox become available to shoppers on product pages. Off until enabled.', 'polski'),
            'product_slider_carousel' => __('Adds a lightweight product slider using scroll-snap to show related, sale or featured products. When enabled, you can place a swipeable product carousel that displays to shoppers on the storefront. Off until enabled.', 'polski'),
            'waitlist' => __('Captures interest in out-of-stock products. When enabled, shoppers can enter their email on sold-out products to join a waitlist and get an automatic notification when the item is back in stock. Off until enabled.', 'polski'),
            'infinite_scroll' => __('Loads more products as shoppers browse listings. When enabled, WooCommerce archive pages load further products automatically or via a load-more button instead of paging. Off until enabled.', 'polski'),
            'popup' => __('Shows a promotional or lead-capture popup to visitors. When enabled, a lightweight popup appears based on the delay, frequency, and page locations you set. Off until enabled.', 'polski'),
            'gpsr' => __('Provides tools for displaying EU product safety (GPSR) details. When enabled, you can add manufacturer and importer data, responsible person, product identifiers, safety warnings, and instructions to products, with CSV bulk import or export. Off until enabled.', 'polski'),
            'verified_review' => __('Shows a trust badge on reviews left by real buyers. When enabled, reviews from customers who actually bought the product display a verified purchase badge on the product page, so shoppers can tell genuine buyer feedback from the rest.', 'polski'),
            'green_claims' => __('Adds product fields for backing up environmental claims. When enabled, each product gains fields for the basis of an ecological claim, a certificate link, and an expiration date, helping you prepare for the anti-greenwashing directive (September 2026).', 'polski'),
            'dsa_toolkit' => __('Provides Digital Services Act tools for your store. When enabled, you get contact point settings, a public report form for illegal content or products via the [polski_dsa_report] shortcode, and an admin screen where staff review submitted reports.', 'polski'),
            'ksef_ready' => __('Helps you spot orders that may need KSeF invoicing. When enabled, orders are checked by NIP and flagged when KSeF invoicing may apply, with integration hooks and an admin status indicator shown to your team.', 'polski'),
            'security_incidents' => __('A log for tracking security incidents, built with CRA in mind. When enabled, staff get an admin log to record vulnerabilities, breaches, and third-party failures, track their status and follow-up, and export everything to CSV.', 'polski'),
            'store_health' => __('Quietly watches your store for problems. When enabled, it passively monitors front-end fatal errors, checkout failure rate, and sales anomalies, checking every 5 minutes and sending email or webhook alerts. It never places test orders.', 'polski'),
            'ai_bridge' => __('Lets AI assistants safely read selected store data. When enabled, read-only data like price history, product safety info, store health, page checks, and product facts is exposed to AI assistants and the Site Editor via the WordPress Abilities API. Off by default.', 'polski'),
            'schema_org' => __('Adds structured data so search engines understand your products. When enabled, advanced JSON-LD tags are injected automatically into your pages to support product indexing by Google, while preserving the plugin\'s own data.', 'polski'),
            'tracking_tags' => __('A consent-gated tag manager for marketing tags. When enabled, it adds a unified place to manage tracking tags that fire only after visitor consent, so tools like GA4 and GTM load on your store in line with your consent settings.', 'polski'),
            'safe_fonts' => __('Reduces and controls external Google Fonts requests on your store. When enabled, font-display and preconnect hints are added to your pages, and the Google Fonts stylesheet can be held back until a visitor grants the matching consent, helping with privacy and load speed.', 'polski'),
            'custom_integrations' => __('Lets you add your own scripts or snippets to the page head or footer. When enabled, each snippet you add is tied to a consent category and runs only after the visitor grants that consent through the Consent Manager, so your custom code respects shoppers\' choices.', 'polski'),
            'custom_triggers' => __('Lets you push your own dataLayer events based on simple page conditions. When enabled, events fire when a visitor lands on a chosen URL or clicks a chosen element, feeding into the GA4 DataLayer module for your analytics and tag setup.', 'polski'),
            'checkout_toolkit_integration' => __('Keeps your settings and messages compatible with popular checkout field add-ons and product data. When enabled, the plugin detects supported checkout extensions, cookies, and product data, then adjusts its own behaviour so labels and consent prompts display correctly at checkout.', 'polski'),
            'site_audit' => __('Automatically checks your store for the most common store-setup issues. When enabled, it scans for things like missing legal pages, pre-ticked checkboxes, company data, GDPR, and Omnibus items, and shows you a report in the admin so you can fix gaps yourself.', 'polski'),
            'plugin_data' => __('Controls what happens to Polski\'s data if you remove the plugin. When enabled, you decide whether Polski deletes its database tables, settings, and stored logs on uninstall, so you can keep your data or wipe it cleanly when removing the plugin from WordPress.', 'polski'),
            'cra_readiness' => __('Provides tools to help with Cyber Resilience Act (CRA) readiness. When enabled, it can publish a security.txt file (RFC 9116) with your security contact and vulnerability reporting policy, making it easier for researchers to report security issues responsibly.', 'polski'),
            'dpa_tracker' => __('Helps you keep a GDPR data-processing registry for your store. When enabled, it detects third-party services that process your customers\' personal data and lets you track the status of each data processing agreement (DPA) from the admin.', 'polski'),
            'minimum_order' => __('Sets a minimum order value or item count before customers can check out. When enabled, shoppers who haven\'t reached the minimum see a notice on the cart and checkout pages, and checkout stays blocked until they add enough.', 'polski'),
            'review_requests' => __('Sends automatic emails asking customers to review what they bought. When enabled, after an order is completed buyers get an email with product images and review links, plus an opt-out option.', 'polski'),
            'from_price' => __('Shows a single from-price instead of a price range on variable products. When enabled, shop archives and product pages display the lowest price as a clean from-amount rather than a low-to-high range.', 'polski'),
            'auto_restore_stock' => __('Puts stock back automatically when orders don\'t go through. When enabled, products from orders that are cancelled, refunded or failed are returned to inventory without any manual work.', 'polski'),
            'ajax_add_to_cart' => __('Lets customers add items to the cart without reloading the page, including variable products. When enabled, products are added in the background on single product pages and a toast notification confirms success.', 'polski'),
            'datalayer' => __('Tracks ecommerce activity for Google Analytics 4 via dataLayer. When enabled, events like view_item, add_to_cart, begin_checkout and purchase are sent to your GA4 setup, working with a GTM container or gtag.js.', 'polski'),
            'stock_export' => __('Exports your WooCommerce product stock to a CSV file. When enabled, a Stock Export tool appears under Products where you can choose fields, filter by stock threshold and include variations.', 'polski'),
            'social_login' => __('Lets customers register and sign in using Google or Facebook. When enabled, branded login buttons appear on My Account, checkout and the WordPress login form, and customer accounts are created automatically.', 'polski'),
            'product_authors' => __('Adds a custom taxonomy for product authors or creators. When enabled, you can assign authors to products and group them, useful for stores selling books or creator-made items.', 'polski'),
            'expert_reviews' => __('Lets you publish editorial, in-house reviews of products. When enabled, you get a new section to write expert reviews with ratings and verdicts, linked to products and shown on product pages with Schema.org markup that helps with SEO.', 'polski'),
            'social_proof' => __('Displays recent-purchase popups to build trust. When enabled, small floating notifications about recent orders appear to your shoppers in a position and timing you choose, loaded smoothly via AJAX and built to be privacy-aware.', 'polski'),
            'product_qa' => __('Adds a questions-and-answers section to product pages. When enabled, customers can ask questions and anyone can answer, you get email alerts for new questions, answers can be voted on, and Schema.org QAPage markup helps with SEO.', 'polski'),
            'trust_badges' => __('Shows reassurance icons like secure payment, fast delivery, returns, and quality guarantee. When enabled, these configurable trust signals appear to shoppers on your product, cart, and checkout pages.', 'polski'),
            'live_cart' => __('Adds a slide-in cart drawer. When enabled, a sidebar opens for the shopper whenever they add a product, showing cart items, the subtotal, a free-shipping progress bar, and a quick link to checkout.', 'polski'),
            'price_history_chart' => __('Shows how a product\'s price has changed over time. When enabled, shoppers see a small SVG price-trend chart on product pages covering the last 30, 90, or 180 days, using your Omnibus price data.', 'polski'),
            'order_export' => __('Exports your WooCommerce orders to a spreadsheet file. When enabled, you get an admin tool to download orders as CSV, choosing which fields to include and filtering by date range and order status.', 'polski'),
            'faq' => __('Lets you create a frequently-asked-questions section. When enabled, you can add FAQs with categories and place them on any page as an accordion shortcode, with Schema.org FAQPage data that helps with SEO rich snippets.', 'polski'),
            'custom_checkout_fields' => __('Lets you customise your checkout form. When enabled, you can add, edit, reorder, and choose types for checkout fields, and the collected values appear in the admin order, customer emails, and My Account.', 'polski'),
        ];
    }

    /**
     * Get all module definitions with their current state.
     *
     * @return list<array<string, mixed>>
     */
    public function getModules(): array
    {
        $saved = get_option(self::OPTION, []);
        $saved = is_array($saved) ? $saved : [];

        $modules = [
            // === Merchandising ===
            [
                'id' => 'dynamic_pricing',
                'name' => __('Promotions / dynamic pricing', 'polski'),
                'description' => __('Automatic cart discounts: a percentage off when the cart subtotal reaches a threshold, and a percentage off a product line when its quantity reaches a threshold (bulk discount). Off by default.', 'polski'),
                'group' => 'Merchandising',
                'enabled' => false,
                'icon' => 'dashicons-tag',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_pricing|bulk_min_qty', 'label' => __('Bulk discount: minimum quantity per product', 'polski'), 'type' => 'number', 'default' => 0, 'hint' => __('0 disables the bulk discount', 'polski')],
                    ['key' => 'polski_pricing|bulk_discount_percent', 'label' => __('Bulk discount: percent off (%)', 'polski'), 'type' => 'number', 'default' => 0],
                    ['key' => 'polski_pricing|cart_threshold', 'label' => __('Cart discount: subtotal threshold', 'polski'), 'type' => 'number', 'default' => 0, 'hint' => __('0 disables the cart discount', 'polski')],
                    ['key' => 'polski_pricing|cart_discount_percent', 'label' => __('Cart discount: percent off (%)', 'polski'), 'type' => 'number', 'default' => 0],
                ],
            ],
            // === Prices and Display ===
            [
                'id' => 'unit_price',
                'name' => __('Unit price', 'polski'),
                'description' => __('Display price per unit (e.g. per 1 kg, per 100 ml) according to Polish consumer law.', 'polski'),
                'group' => 'Prices and Display',
                'enabled' => true,
                'icon' => 'dashicons-tag',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_prices|unit_price_text', 'label' => __('Display format', 'polski'), 'type' => 'text', 'default' => '{price} / {unit}', 'hint' => __('Variables: {price}, {unit}', 'polski')],
                    ['key' => 'polski_prices|unit_price_show_loop', 'label' => __('Show on product lists', 'polski'), 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'omnibus',
                'name' => __('Lowest price (Omnibus)', 'polski'),
                'description' => __('Track price history and display the lowest price from the last 30 days for products on sale. Required by the Omnibus Directive (EU 2019/2161).', 'polski'),
                'group' => 'Prices and Display',
                'enabled' => true,
                'icon' => 'dashicons-chart-line',
                'links' => [],
                'settings' => [
                    ['key' => '_omnibus_integration_status', 'label' => '', 'type' => 'html', 'html' => $this->getOmnibusIntegrationStatus()],
                    ['key' => '_omnibus_header_1', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;">' . __('Price tracking', 'polski') . '</strong>'],
                    ['key' => 'polski_omnibus|days', 'label' => __('Tracking period (days)', 'polski'), 'type' => 'number', 'default' => 30, 'hint' => __('Directive requires a minimum of 30 days', 'polski')],
                    ['key' => 'polski_omnibus|prune_after_days', 'label' => __('Keep history (days)', 'polski'), 'type' => 'number', 'default' => 90, 'hint' => __('Older data will be automatically deleted', 'polski')],
                    ['key' => 'polski_omnibus|include_tax', 'label' => __('Prices with tax', 'polski'), 'type' => 'checkbox', 'default' => true, 'hint' => __('Track and display gross prices', 'polski')],

                    ['key' => '_omnibus_header_2', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;margin-top:8px;display:block;">' . __('Display', 'polski') . '</strong>'],
                    ['key' => 'polski_omnibus|display_on_sale_only', 'label' => __('Only products on sale', 'polski'), 'type' => 'checkbox', 'default' => true, 'hint' => __('Show info only when the product has a sale price', 'polski')],
                    ['key' => 'polski_omnibus|show_on_single', 'label' => __('Product page', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_omnibus|show_on_loop', 'label' => __('Product lists (shop, categories)', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_omnibus|show_on_related', 'label' => __('Related and featured products', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_omnibus|show_on_cart', 'label' => __('Cart', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_omnibus|show_regular_price', 'label' => __('Show regular price (before sale)', 'polski'), 'type' => 'checkbox', 'default' => false, 'hint' => __('Display additional information about the price before the sale started', 'polski')],

                    ['key' => '_omnibus_header_3', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;margin-top:8px;display:block;">' . __('Message template', 'polski') . '</strong>'],
                    ['key' => 'polski_omnibus|display_text', 'label' => __('Message content', 'polski'), 'type' => 'text', 'default' => 'Lowest price in the last {days} days: {price}', 'hint' => __('Variables: {price}, {days}, {date}, {regular_price}', 'polski')],
                    ['key' => 'polski_omnibus|no_history_text', 'label' => __('No price history', 'polski'), 'type' => 'select', 'default' => 'hide', 'options' => ['hide' => __('Hide message', 'polski'), 'current' => __('Show current price', 'polski'), 'custom' => __('Custom text', 'polski')]],
                    ['key' => 'polski_omnibus|no_history_custom_text', 'label' => __('Custom text (no history)', 'polski'), 'type' => 'text', 'default' => 'Price has not changed in {days} days'],
                    ['key' => 'polski_omnibus|price_count_from', 'label' => __('Calculated from', 'polski'), 'type' => 'select', 'default' => 'sale_start', 'options' => ['sale_start' => __('Sale start date', 'polski'), 'today' => __('Today', 'polski')], 'hint' => __('Reference point for calculating the lowest price', 'polski')],

                    ['key' => '_omnibus_header_4', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;margin-top:8px;display:block;">' . __('Variable products', 'polski') . '</strong>'],
                    ['key' => 'polski_omnibus|variable_tracking', 'label' => __('Track variations separately', 'polski'), 'type' => 'checkbox', 'default' => true, 'hint' => __('Each variation has its own price history', 'polski')],
                ],
            ],
            [
                'id' => 'tax_display',
                'name' => __('VAT Display', 'polski'),
                'description' => __('Configuration for gross/net prices display, VAT rate info, and small business exemption support (Art. 113 of the VAT Act).', 'polski'),
                'group' => 'Prices and Display',
                'enabled' => true,
                'icon' => 'dashicons-money-alt',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_taxes|tax_display_mode', 'label' => __('Price display mode', 'polski'), 'type' => 'select', 'default' => 'brutto', 'options' => ['brutto' => __('Gross (including VAT)', 'polski'), 'netto' => __('Net (excluding VAT)', 'polski')]],
                    ['key' => 'polski_taxes|vat_notice_text', 'label' => __('VAT notice text', 'polski'), 'type' => 'text', 'default' => 'including {rate}% VAT', 'hint' => __('Variables: {rate}', 'polski')],
                    ['key' => 'polski_general|small_business', 'label' => __('Small business exemption (Art. 113)', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_taxes|vat_exempt_notice', 'label' => __('Exemption text', 'polski'), 'type' => 'text', 'default' => 'Exempt from VAT based on Art. 113 par. 1 of the VAT Act'],
                ],
            ],
            [
                'id' => 'oss_observer',
                'name' => __('OSS observer', 'polski'),
                'description' => __('Observe the OSS delivery threshold of the current year. Enabling this will install the One Stop Shop plugin, which monitors your intra-EU B2C sales and flags when you approach the €10,000 threshold.', 'polski'),
                'group' => 'Prices and Display',
                'enabled' => false,
                'icon' => 'dashicons-chart-area',
                'links' => [
                    ['label' => __('About OSS procedure', 'polski'), 'url' => 'https://polski.wppoland.com/prices/oss-observer/'],
                ],
                'settings' => [
                    ['key' => '_oss_integration_status', 'label' => '', 'type' => 'html', 'html' => $this->getOssStatusHtml()],
                ],
            ],
            [
                'id' => 'delivery_time',
                'name' => __('Delivery time', 'polski'),
                'description' => __('Display estimated delivery time on the product page. Configuration per product or variation with default fallback.', 'polski'),
                'group' => 'Prices and Display',
                'enabled' => true,
                'icon' => 'dashicons-clock',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_delivery|display_format', 'label' => __('Display format', 'polski'), 'type' => 'text', 'default' => 'Delivery time: {time}', 'hint' => __('Variables: {time}', 'polski')],
                    ['key' => 'polski_delivery|default_delivery_time', 'label' => __('Default delivery time', 'polski'), 'type' => 'delivery_time_select'],
                    ['key' => 'polski_delivery|show_in_loop', 'label' => __('Show on product listings', 'polski'), 'type' => 'checkbox', 'default' => false, 'hint' => __('Also display the delivery time on shop and archive product cards.', 'polski')],
                ],
            ],
            [
                'id' => 'shipping_notice',
                'name' => __('Shipping costs info', 'polski'),
                'description' => __('Link to a shipping costs page displayed next to the product price.', 'polski'),
                'group' => 'Prices and Display',
                'enabled' => true,
                'icon' => 'dashicons-car',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_prices|shipping_costs_text', 'label' => __('Link text', 'polski'), 'type' => 'text', 'default' => 'plus shipping costs'],
                ],
            ],

            // === Checkout and Orders ===
            [
                'id' => 'checkout_button',
                'name' => __('Checkout button', 'polski'),
                'description' => __('Change order button text to "Order with obligation to pay" according to Polish law.', 'polski'),
                'group' => 'Checkout and Orders',
                'enabled' => true,
                'icon' => 'dashicons-cart',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_checkout|order_button_text', 'label' => __('Button text', 'polski'), 'type' => 'text', 'default' => 'Order with obligation to pay'],
                ],
            ],
            [
                'id' => 'legal_checkboxes',
                'name' => __('Legal checkboxes', 'polski'),
                'description' => __('7 built-in checkboxes: terms and conditions, privacy policy, right of withdrawal, digital content, delivery notifications, review reminder, marketing.', 'polski'),
                'group' => 'Checkout and Orders',
                'enabled' => true,
                'icon' => 'dashicons-yes-alt',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_checkout|terms_checkbox_enabled', 'label' => __('Terms and Conditions', 'polski'), 'type' => 'checkbox', 'default' => true],
                    /* translators: %s: terms page URL placeholder */
                    ['key' => 'polski_checkout|terms_checkbox_label', 'label' => __('Label - Terms', 'polski'), 'type' => 'textarea', 'default' => 'I have read and accept the <a href="%s" target="_blank">Terms and Conditions</a>.', 'hint' => __('Use %s as a placeholder for the terms page link', 'polski')],
                    ['key' => 'polski_checkout|terms_checkbox_error', 'label' => __('Error - Terms', 'polski'), 'type' => 'text', 'default' => 'You must accept the Terms and Conditions to place an order.'],
                    ['key' => 'polski_checkout|terms_checkbox_description', 'label' => __('Description - Terms', 'polski'), 'type' => 'text', 'default' => 'Acceptance of the Terms and Conditions.'],
                    ['key' => 'polski_checkout|privacy_checkbox_enabled', 'label' => __('Privacy Policy', 'polski'), 'type' => 'checkbox', 'default' => true],
                    /* translators: %s: privacy policy URL placeholder */
                    ['key' => 'polski_checkout|privacy_checkbox_label', 'label' => __('Label - Privacy Policy', 'polski'), 'type' => 'textarea', 'default' => 'I have read and accept the <a href="%s" target="_blank">Privacy Policy</a>.', 'hint' => __('Use %s as a placeholder for the privacy policy link', 'polski')],
                    ['key' => 'polski_checkout|privacy_checkbox_error', 'label' => __('Error - Privacy Policy', 'polski'), 'type' => 'text', 'default' => 'You must accept the Privacy Policy.'],
                    ['key' => 'polski_checkout|privacy_checkbox_description', 'label' => __('Description - Privacy Policy', 'polski'), 'type' => 'text', 'default' => 'Acceptance of the Privacy Policy.'],
                    ['key' => 'polski_checkout|withdrawal_checkbox_enabled', 'label' => __('Right of withdrawal', 'polski'), 'type' => 'checkbox', 'default' => true],
                    /* translators: %s: withdrawal information URL placeholder */
                    ['key' => 'polski_checkout|withdrawal_checkbox_label', 'label' => __('Label - Right of withdrawal', 'polski'), 'type' => 'textarea', 'default' => 'I confirm that I have been informed about the <a href="%s" target="_blank">right of withdrawal</a> within 14 days.', 'hint' => __('Use %s as a placeholder for the returns or withdrawal page link', 'polski')],
                    ['key' => 'polski_checkout|withdrawal_checkbox_error', 'label' => __('Error - Right of withdrawal', 'polski'), 'type' => 'text', 'default' => 'You must confirm that you have read the information about the right of withdrawal.'],
                    ['key' => 'polski_checkout|withdrawal_checkbox_description', 'label' => __('Description - Right of withdrawal', 'polski'), 'type' => 'text', 'default' => 'Confirmation of information about the 14-day right of withdrawal.'],
                    ['key' => 'polski_checkout|digital_waiver_checkbox_enabled', 'label' => __('Digital content (waiver)', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_checkout|digital_waiver_checkbox_label', 'label' => __('Label - Digital content', 'polski'), 'type' => 'textarea', 'default' => 'I agree to start the delivery of digital content before the expiry of the withdrawal period and I acknowledge the loss of the right of withdrawal.'],
                    ['key' => 'polski_checkout|digital_waiver_checkbox_error', 'label' => __('Error - Digital content', 'polski'), 'type' => 'text', 'default' => 'You must agree to the immediate delivery of digital content.'],
                    ['key' => 'polski_checkout|digital_waiver_checkbox_description', 'label' => __('Description - Digital content', 'polski'), 'type' => 'text', 'default' => 'Waiver of the right of withdrawal for digital content.'],
                    ['key' => 'polski_checkout|parcel_delivery_checkbox_enabled', 'label' => __('Delivery notifications', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_checkout|parcel_delivery_checkbox_label', 'label' => __('Label - Delivery notifications', 'polski'), 'type' => 'textarea', 'default' => 'I agree to receive SMS/email notifications about the delivery status of the shipment.'],
                    ['key' => 'polski_checkout|parcel_delivery_checkbox_description', 'label' => __('Description - Delivery notifications', 'polski'), 'type' => 'text', 'default' => 'Optional consent for delivery notifications.'],
                    ['key' => 'polski_checkout|review_reminder_checkbox_enabled', 'label' => __('Review reminder', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_checkout|review_reminder_checkbox_label', 'label' => __('Label - Review reminder', 'polski'), 'type' => 'textarea', 'default' => 'I agree to receive a reminder to leave a review via email after the purchase.'],
                    ['key' => 'polski_checkout|review_reminder_checkbox_description', 'label' => __('Description - Review reminder', 'polski'), 'type' => 'text', 'default' => 'Optional consent for review reminders.'],
                    ['key' => 'polski_checkout|marketing_checkbox_enabled', 'label' => __('Marketing consent', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_checkout|marketing_checkbox_label', 'label' => __('Label - Marketing', 'polski'), 'type' => 'textarea', 'default' => 'I agree to receive marketing communication and newsletter.'],
                    ['key' => 'polski_checkout|marketing_checkbox_description', 'label' => __('Description - Marketing', 'polski'), 'type' => 'text', 'default' => 'Optional marketing consent.'],
                ],
            ],
            [
                'id' => 'nip_lookup',
                'name' => __('NIP - Verification and Autocomplete', 'polski'),
                'description' => __('NIP field on the checkout page with checksum validation. Automatic retrieval of company data from the GUS REGON database after entering NIP.', 'polski'),
                'group' => 'Checkout and Orders',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-building',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_nip|nip_required', 'label' => __('NIP required on checkout', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_nip|gus_environment', 'label' => __('GUS API environment', 'polski'), 'type' => 'select', 'default' => 'test', 'options' => ['test' => __('Testing', 'polski'), 'production' => __('Production', 'polski')]],
                    ['key' => 'polski_nip|gus_api_key', 'label' => __('GUS API Key (production)', 'polski'), 'type' => 'text', 'default' => '', 'hint' => __('Request a key on the stat.gov.pl website. In test mode, a test key will be used.', 'polski')],
                ],
            ],
            [
                'id' => 'consent_logging',
                'name' => __('Consent Logging (GDPR)', 'polski'),
                'description' => __('Recording all consents given by customers with IP address, user agent, and timestamp. GDPR compliant.', 'polski'),
                'group' => 'Checkout and Orders',
                'enabled' => true,
                'icon' => 'dashicons-shield',
                'links' => [],
                'settings' => [],
            ],
            [
                'id' => 'consent_manager',
                'name' => __('Consent Manager (cookie banner)', 'polski'),
                'description' => __('A native cookie-consent banner with consent categories, Google Consent Mode v2 signalling, and blocking of scripts and iframes until the matching category is granted. Records each decision for your audit trail. Provides tools, not legal advice.', 'polski'),
                'group' => 'Legal & Compliance',
                'enabled' => false,
                'icon' => 'dashicons-privacy',
                'links' => [
                    ['label' => __('Consent records', 'polski'), 'url' => admin_url('admin.php?page=polski&tab=reports&view=consent')],
                ],
                'settings' => [
                    ['key' => '_consent_categories_header', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;">' . esc_html__('Enabled categories', 'polski') . '</strong><br><span class="description">' . esc_html__('Necessary cookies are always on and cannot be switched off.', 'polski') . '</span>'],
                    ['key' => 'polski_consent_manager|category_analytics', 'label' => __('Analytics', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_consent_manager|category_marketing', 'label' => __('Marketing', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_consent_manager|category_preferences', 'label' => __('Preferences', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_consent_manager|heading', 'label' => __('Banner heading', 'polski'), 'type' => 'text', 'default' => ''],
                    ['key' => 'polski_consent_manager|banner_text', 'label' => __('Banner text', 'polski'), 'type' => 'textarea', 'default' => __('We use cookies and similar technologies to run this site, measure traffic, and personalise content. You can accept all, reject non-essential ones, or manage your choices.', 'polski')],
                    ['key' => 'polski_consent_manager|accept_label', 'label' => __('Accept all label', 'polski'), 'type' => 'text', 'default' => __('Accept all', 'polski')],
                    ['key' => 'polski_consent_manager|reject_label', 'label' => __('Reject all label', 'polski'), 'type' => 'text', 'default' => __('Reject all', 'polski')],
                    ['key' => 'polski_consent_manager|manage_label', 'label' => __('Manage label', 'polski'), 'type' => 'text', 'default' => __('Manage', 'polski')],
                    ['key' => 'polski_consent_manager|save_label', 'label' => __('Save choices label', 'polski'), 'type' => 'text', 'default' => __('Save choices', 'polski')],
                    ['key' => 'polski_consent_manager|position', 'label' => __('Banner position', 'polski'), 'type' => 'select', 'default' => 'bottom', 'options' => [
                        'bottom' => __('Bottom', 'polski'),
                        'top' => __('Top', 'polski'),
                        'center' => __('Center', 'polski'),
                    ]],
                    ['key' => 'polski_consent_manager|google_consent_mode', 'label' => __('Google Consent Mode v2', 'polski'), 'type' => 'checkbox', 'default' => true, 'hint' => __('Sends consent signals to Google tags (gtag / GTM) before they run.', 'polski')],
                ],
            ],
            // === Consumer Rights ===
            [
                'id' => 'returns_rma',
                'name' => __('Returns & complaints (RMA)', 'polski'),
                'description' => __('Let customers open a complaint (reklamacja) or return (zwrot) request from My Account for eligible orders, with a confirmation email and an admin queue with statuses. Provides tools, not legal advice.', 'polski'),
                'group' => 'Consumer Rights',
                'enabled' => false,
                'icon' => 'dashicons-undo',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_returns|window_days', 'label' => __('Eligibility window (days from order date)', 'polski'), 'type' => 'number', 'default' => 365],
                    ['key' => 'polski_returns|notify_email', 'label' => __('Notification email (admin)', 'polski'), 'type' => 'text', 'default' => ''],
                ],
            ],
            [
                'id' => 'legal_pages',
                'name' => __('Legal Pages', 'polski'),
                'description' => __('Automatic generation of pages: Terms and Conditions, Privacy Policy, Right of Withdrawal, Complaints.', 'polski'),
                'group' => 'Consumer Rights',
                'enabled' => true,
                'icon' => 'dashicons-media-document',
                'links' => [],
                'settings' => [
                    ['key' => '_legal_pages_link', 'label' => '', 'type' => 'html', 'html' => '<a href="' . admin_url('admin.php?page=polski&tab=dashboard') . '">' . __('Manage legal pages on Dashboard &rarr;', 'polski') . '</a>'],
                ],
            ],
            [
                'id' => 'withdrawal',
                'name' => __('Right of withdrawal (14 days)', 'polski'),
                'description' => __('Withdrawal request form, My Account action with confirmation step, confirmation email, and per-product exclusions.', 'polski'),
                'group' => 'Consumer Rights',
                'enabled' => true,
                'icon' => 'dashicons-undo',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_withdrawal|button_text', 'label' => __('Order action label', 'polski'), 'type' => 'text', 'default' => __('Withdraw from contract', 'polski')],
                    ['key' => 'polski_withdrawal|form_title', 'label' => __('Form title', 'polski'), 'type' => 'text', 'default' => __('Withdrawal request', 'polski')],
                    ['key' => 'polski_withdrawal|form_intro_text', 'label' => __('Form introduction', 'polski'), 'type' => 'textarea', 'default' => __('You are submitting a withdrawal request for order #{order_number} placed on {order_date}.', 'polski'), 'hint' => __('Variables: {order_number}, {order_date}', 'polski')],
                    ['key' => 'polski_withdrawal|legal_notice_text', 'label' => __('Legal notice', 'polski'), 'type' => 'textarea', 'default' => __('Under Polish consumer law, you may withdraw from the contract within 14 days without giving a reason.', 'polski')],
                    ['key' => 'polski_withdrawal|items_heading', 'label' => __('Order items heading', 'polski'), 'type' => 'text', 'default' => __('Order items', 'polski')],
                    ['key' => 'polski_withdrawal|column_product', 'label' => __('Product column', 'polski'), 'type' => 'text', 'default' => __('Product', 'polski')],
                    ['key' => 'polski_withdrawal|column_quantity', 'label' => __('Quantity column', 'polski'), 'type' => 'text', 'default' => __('Quantity', 'polski')],
                    ['key' => 'polski_withdrawal|column_price', 'label' => __('Price column', 'polski'), 'type' => 'text', 'default' => __('Price', 'polski')],
                    ['key' => 'polski_withdrawal|exempt_notice_text', 'label' => __('Exemption notice', 'polski'), 'type' => 'text', 'default' => __('(This product is excluded from the right of withdrawal)', 'polski')],
                    ['key' => 'polski_withdrawal|reason_label', 'label' => __('Reason field label', 'polski'), 'type' => 'text', 'default' => __('Reason for withdrawal (optional)', 'polski')],
                    ['key' => 'polski_withdrawal|submit_button_text', 'label' => __('Submit button text', 'polski'), 'type' => 'text', 'default' => __('Submit withdrawal request', 'polski')],
                    ['key' => 'polski_withdrawal|invalid_nonce_text', 'label' => __('Invalid nonce message', 'polski'), 'type' => 'text', 'default' => __('Something went wrong on our side. Please try again.', 'polski')],
                    ['key' => 'polski_withdrawal|order_not_found_text', 'label' => __('Order not found message', 'polski'), 'type' => 'text', 'default' => __('We could not find that order.', 'polski')],
                    ['key' => 'polski_withdrawal|permission_error_text', 'label' => __('Permission error message', 'polski'), 'type' => 'text', 'default' => __('You do not have permission to withdraw from this order.', 'polski')],
                    ['key' => 'polski_withdrawal|success_text', 'label' => __('Success message', 'polski'), 'type' => 'text', 'default' => __('Your withdrawal request has been received. We will send a confirmation email shortly.', 'polski')],
                    ['key' => 'polski_withdrawal|not_eligible_text', 'label' => __('Not eligible message', 'polski'), 'type' => 'text', 'default' => __('This order is not eligible for withdrawal.', 'polski')],
                    ['key' => 'polski_withdrawal|status_heading', 'label' => __('Status section heading', 'polski'), 'type' => 'text', 'default' => __('Withdrawal request', 'polski')],
                    ['key' => 'polski_withdrawal|status_label', 'label' => __('Status label', 'polski'), 'type' => 'text', 'default' => __('Status', 'polski')],
                    ['key' => 'polski_withdrawal|submitted_label', 'label' => __('Submitted label', 'polski'), 'type' => 'text', 'default' => __('Submitted', 'polski')],
                    ['key' => 'polski_withdrawal|requested_order_note', 'label' => __('Order note after request', 'polski'), 'type' => 'text', 'default' => __('The customer submitted a withdrawal request.', 'polski')],
                    ['key' => 'polski_withdrawal|confirmed_order_note', 'label' => __('Order note after confirmation', 'polski'), 'type' => 'text', 'default' => __('The withdrawal request has been confirmed.', 'polski')],
                    ['key' => 'polski_withdrawal|status_date_format', 'label' => __('Status date format', 'polski'), 'type' => 'text', 'default' => __('Y-m-d H:i', 'polski')],
                    ['key' => 'polski_withdrawal|email_subject', 'label' => __('Confirmation email subject', 'polski'), 'type' => 'text', 'default' => __('Your withdrawal request for order #{order_number} has been confirmed.', 'polski'), 'hint' => __('Variables: {order_number}, {order_date}, {withdrawal_date}', 'polski')],
                    ['key' => 'polski_withdrawal|email_heading', 'label' => __('Confirmation email heading', 'polski'), 'type' => 'text', 'default' => __('Withdrawal confirmed', 'polski')],
                    ['key' => 'polski_withdrawal|email_greeting', 'label' => __('Email greeting', 'polski'), 'type' => 'text', 'default' => __('Hello {name},', 'polski'), 'hint' => __('Variable: {name}', 'polski')],
                    ['key' => 'polski_withdrawal|email_intro_text', 'label' => __('Confirmation email body', 'polski'), 'type' => 'textarea', 'default' => __('Your withdrawal request for order #{order_number} has been confirmed.', 'polski'), 'hint' => __('Variable: {order_number}', 'polski')],
                    ['key' => 'polski_withdrawal|email_reason_label', 'label' => __('Reason label in email', 'polski'), 'type' => 'text', 'default' => __('Your reason', 'polski')],
                    ['key' => 'polski_withdrawal|email_return_instruction', 'label' => __('Return instruction in email', 'polski'), 'type' => 'textarea', 'default' => __('Please return the products to the address below within 14 days:', 'polski')],
                    ['key' => 'polski_withdrawal|email_additional_content', 'label' => __('Additional email content', 'polski'), 'type' => 'textarea', 'default' => __('Your refund will be processed within 14 days after the returned products are received.', 'polski')],
                ],
            ],
            [
                'id' => 'dispute_resolution',
                'name' => __('Dispute Resolution (ODR)', 'polski'),
                'description' => __('Displaying information about the European Commission\'s Online Dispute Resolution (ODR) platform.', 'polski'),
                'group' => 'Consumer Rights',
                'enabled' => true,
                'icon' => 'dashicons-admin-site-alt3',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_general|dispute_resolution_text', 'label' => __('ODR info content', 'polski'), 'type' => 'textarea', 'default' => 'ODR Platform: https://ec.europa.eu/consumers/odr'],
                    ['key' => 'polski_general|admin_pages_generated_notice', 'label' => __('Message after generating legal pages', 'polski'), 'type' => 'textarea', 'default' => 'Ready! We have generated initial drafts of legal pages for you. Review them, adjust to your needs, and feel free to publish.'],
                    ['key' => 'polski_general|admin_modules_saved_notice', 'label' => __('Message after saving modules', 'polski'), 'type' => 'text', 'default' => 'Modules saved.'],
                    ['key' => 'polski_general|admin_setup_note_title', 'label' => __('Onboarding note title', 'polski'), 'type' => 'text', 'default' => 'Configure Polski for your store'],
                    ['key' => 'polski_general|admin_setup_note_content', 'label' => __('Onboarding note content', 'polski'), 'type' => 'textarea', 'default' => 'Just a moment and the store will be ready. Review the modules, set up legal pages, and finish the configuration in the Polski panel.'],
                    ['key' => 'polski_general|admin_setup_note_button', 'label' => __('Onboarding note button', 'polski'), 'type' => 'text', 'default' => 'Open Polski configuration'],
                    ['key' => 'polski_general|admin_status_active', 'label' => __('Active status', 'polski'), 'type' => 'text', 'default' => 'Active'],
                    ['key' => 'polski_general|admin_status_inactive', 'label' => __('Inactive status', 'polski'), 'type' => 'text', 'default' => 'Disabled'],
                    ['key' => 'polski_general|admin_status_unconfigured', 'label' => __('Unconfigured status', 'polski'), 'type' => 'text', 'default' => 'Unconfigured'],
                    ['key' => 'polski_general|admin_legal_pages_card_title', 'label' => __('Legal pages card title', 'polski'), 'type' => 'text', 'default' => 'Legal pages'],
                    ['key' => 'polski_general|admin_legal_pages_card_progress', 'label' => __('Legal pages configuration progress', 'polski'), 'type' => 'text', 'default' => 'You already have {done} out of {total} steps behind you. Excellent!', 'hint' => __('Variables: {done}, {total}', 'polski')],
                    ['key' => 'polski_general|admin_vat_card_title', 'label' => __('VAT card title', 'polski'), 'type' => 'text', 'default' => 'Tax display'],
                    ['key' => 'polski_general|admin_vat_small_business_text', 'label' => __('Small business exemption text', 'polski'), 'type' => 'text', 'default' => 'Small business exemption (Art. 113)'],
                    ['key' => 'polski_general|admin_vat_standard_text', 'label' => __('Standard VAT text', 'polski'), 'type' => 'text', 'default' => 'Standard VAT'],
                    ['key' => 'polski_general|admin_doi_card_title', 'label' => __('DOI card title', 'polski'), 'type' => 'text', 'default' => 'Double Opt-In (DOI)'],
                    ['key' => 'polski_general|admin_legal_pages_section_title', 'label' => __('Legal pages section title', 'polski'), 'type' => 'text', 'default' => 'Legal pages'],
                    ['key' => 'polski_general|admin_legal_pages_table_page', 'label' => __('Page column header', 'polski'), 'type' => 'text', 'default' => 'Page'],
                    ['key' => 'polski_general|admin_legal_pages_table_status', 'label' => __('Status column header', 'polski'), 'type' => 'text', 'default' => 'Status'],
                    ['key' => 'polski_general|admin_legal_pages_published', 'label' => __('Published status', 'polski'), 'type' => 'text', 'default' => 'Published'],
                    ['key' => 'polski_general|admin_legal_pages_draft', 'label' => __('Draft status', 'polski'), 'type' => 'text', 'default' => 'Draft'],
                    ['key' => 'polski_general|admin_legal_pages_missing', 'label' => __('Missing status', 'polski'), 'type' => 'text', 'default' => 'Not created'],
                    ['key' => 'polski_general|admin_edit_button_text', 'label' => __('Edit button text', 'polski'), 'type' => 'text', 'default' => 'Edit'],
                    ['key' => 'polski_general|admin_generate_pages_empty_text', 'label' => __('Legal pages empty state', 'polski'), 'type' => 'text', 'default' => 'No legal pages have been created yet. Generate them to get started.'],
                    ['key' => 'polski_general|admin_generate_pages_button_text', 'label' => __('Generate pages button text', 'polski'), 'type' => 'text', 'default' => 'Generate legal pages'],
                    ['key' => 'polski_general|admin_next_steps_title', 'label' => __('Next steps title', 'polski'), 'type' => 'text', 'default' => 'Next steps'],
                    ['key' => 'polski_general|admin_next_steps_publish_pages', 'label' => __('Step - publish pages', 'polski'), 'type' => 'text', 'default' => 'Publish your legal pages: Terms and Conditions, Privacy Policy, Right of Withdrawal and Complaints.'],
                    ['key' => 'polski_general|admin_next_steps_tax', 'label' => __('Step - VAT rates', 'polski'), 'type' => 'textarea', 'default' => 'Configure <a href="%s">VAT rates</a> in WooCommerce for the Polish market: 23%%, 8%%, 5%% and 0%%.'],
                    ['key' => 'polski_general|admin_next_steps_shipping', 'label' => __('Step - shipping zones', 'polski'), 'type' => 'textarea', 'default' => 'Configure <a href="%s">shipping zones</a> for deliveries in Poland.'],
                    ['key' => 'polski_general|admin_next_steps_products', 'label' => __('Step - product data', 'polski'), 'type' => 'textarea', 'default' => 'Complete product data, add unit prices and delivery times in the <a href="%s">Polski tab</a> for each product.'],
                    ['key' => 'polski_general|admin_next_steps_checkout', 'label' => __('Step - checkout test', 'polski'), 'type' => 'textarea', 'default' => 'Test the checkout, add a product to the cart and check the legal checkboxes and button text on the <a href="%s">order page</a>.'],
                    ['key' => 'polski_general|admin_omnibus_plugin_detected_text', 'label' => __('Omnibus plugin detected status', 'polski'), 'type' => 'text', 'default' => 'detected, data synchronized'],
                    ['key' => 'polski_general|admin_omnibus_plugin_missing_text', 'label' => __('Omnibus plugin missing status', 'polski'), 'type' => 'text', 'default' => 'not installed'],
                    ['key' => 'polski_general|admin_omnibus_no_external_text', 'label' => __('Communication without external Omnibus plugin', 'polski'), 'type' => 'textarea', 'default' => 'No external Omnibus plugin is installed. Polski uses the built-in price tracking system.'],
                    ['key' => 'polski_general|admin_omnibus_external_active_text', 'label' => __('Communication after external Omnibus plugin detection', 'polski'), 'type' => 'textarea', 'default' => 'External plugin detected. Polski uses its data instead of the built-in system.'],
                ],
            ],
            [
                'id' => 'email_attachments',
                'name' => __('Legal Email Attachments', 'polski'),
                'description' => __('Attaching legal pages content (terms, privacy policy, right of withdrawal) to order confirmation emails.', 'polski'),
                'group' => 'Consumer Rights',
                'enabled' => true,
                'icon' => 'dashicons-email',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_emails|attach_terms', 'label' => __('Attach Terms', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_emails|attach_privacy', 'label' => __('Attach Privacy Policy', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_emails|attach_withdrawal', 'label' => __('Attach Right of Withdrawal', 'polski'), 'type' => 'checkbox', 'default' => true],
                ],
            ],

            // === Product Information ===
            [
                'id' => 'manufacturer',
                'name' => __('Manufacturer and GPSR', 'polski'),
                'description' => __('Manufacturer information, responsible person (GPSR), safety documents, safety instructions.', 'polski'),
                'group' => 'Product Information',
                'enabled' => true,
                'icon' => 'dashicons-building',
                'links' => [],
                'settings' => [],
            ],
            [
                'id' => 'food_module',
                'name' => __('Food and Supplements', 'polski'),
                'description' => __('Nutrition facts table, allergens, ingredients, Nutri-Score, alcohol content, country of origin, distributor.', 'polski'),
                'group' => 'Product Information',
                'enabled' => false,
                'icon' => 'dashicons-carrot',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_food|show_ingredients', 'label' => __('Show ingredients', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_allergens', 'label' => __('Show allergens', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_nutrients', 'label' => __('Show nutrition facts table', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_nutri_score', 'label' => __('Show Nutri-Score', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_alcohol', 'label' => __('Show alcohol content', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_origin', 'label' => __('Show country of origin', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_distributor', 'label' => __('Show distributor', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_net_filling', 'label' => __('Show net content', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|ingredients_label', 'label' => __('Ingredients label', 'polski'), 'type' => 'text', 'default' => __('Ingredients', 'polski')],
                    ['key' => 'polski_food|allergens_label', 'label' => __('Allergens label', 'polski'), 'type' => 'text', 'default' => __('Allergens', 'polski')],
                    ['key' => 'polski_food|nutrients_caption_prefix', 'label' => __('Nutrition facts table header prefix', 'polski'), 'type' => 'text', 'default' => __('Nutrition facts per', 'polski')],
                    ['key' => 'polski_food|nutrients_reference_unit', 'label' => __('Default reference unit', 'polski'), 'type' => 'text', 'default' => '100 g'],
                    ['key' => 'polski_food|nutrients_column_name', 'label' => __('Nutrient column name', 'polski'), 'type' => 'text', 'default' => __('Nutrient', 'polski')],
                    ['key' => 'polski_food|nutrients_column_value', 'label' => __('Value column name', 'polski'), 'type' => 'text', 'default' => __('Value', 'polski')],
                    ['key' => 'polski_food|nutri_score_label', 'label' => __('Nutri-Score label', 'polski'), 'type' => 'text', 'default' => 'Nutri-Score'],
                    ['key' => 'polski_food|alcohol_label', 'label' => __('Alcohol content label', 'polski'), 'type' => 'text', 'default' => __('Alcohol content', 'polski')],
                    ['key' => 'polski_food|alcohol_suffix', 'label' => __('Alcohol suffix', 'polski'), 'type' => 'text', 'default' => '% vol.'],
                    ['key' => 'polski_food|origin_label', 'label' => __('Country of origin label', 'polski'), 'type' => 'text', 'default' => __('Country of origin', 'polski')],
                    ['key' => 'polski_food|distributor_label', 'label' => __('Distributor label', 'polski'), 'type' => 'text', 'default' => __('Distributor', 'polski')],
                    ['key' => 'polski_food|net_filling_label', 'label' => __('Net content label', 'polski'), 'type' => 'text', 'default' => __('Net content', 'polski')],
                ],
            ],
            [
                'id' => 'power_supply',
                'name' => __('Power Supply Info', 'polski'),
                'description' => __('Energy consumption data for electrical devices (energy labels).', 'polski'),
                'group' => 'Product Information',
                'enabled' => false,
                'icon' => 'dashicons-lightbulb',
                'links' => [],
                'settings' => [],
            ],

            // === Customer Account ===
            [
                'id' => 'double_opt_in',
                'name' => __('Double Opt-In (DOI)', 'polski'),
                'description' => __('Email address verification during account registration. Activation link sent by email, login block for unactivated accounts.', 'polski'),
                'group' => 'Customer Account',
                'enabled' => false,
                'icon' => 'dashicons-lock',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_doi|cleanup_days', 'label' => __('Delete inactive accounts after (days)', 'polski'), 'type' => 'number', 'default' => 7],
                    ['key' => 'polski_doi|login_blocked_text', 'label' => __('Login block message', 'polski'), 'type' => 'text', 'default' => __('Your account is pending activation! Please check your email and click the activation link.', 'polski')],
                    ['key' => 'polski_doi|invalid_link_text', 'label' => __('Invalid link message', 'polski'), 'type' => 'text', 'default' => __('Invalid activation link.', 'polski')],
                    ['key' => 'polski_doi|activation_success_text', 'label' => __('Activation success message', 'polski'), 'type' => 'text', 'default' => __('Excellent! Your account has been activated. You can now log in.', 'polski')],
                    ['key' => 'polski_doi|email_subject', 'label' => __('Email subject', 'polski'), 'type' => 'text', 'default' => __('Activate your account at {site_title}', 'polski'), 'hint' => __('Variables: {site_title}', 'polski')],
                    ['key' => 'polski_doi|email_heading', 'label' => __('Email heading', 'polski'), 'type' => 'text', 'default' => __('Confirm your email address', 'polski')],
                    ['key' => 'polski_doi|email_greeting', 'label' => __('Email greeting', 'polski'), 'type' => 'text', 'default' => __('Hello {name},', 'polski'), 'hint' => __('Variables: {name}', 'polski')],
                    ['key' => 'polski_doi|email_intro_html', 'label' => __('Email content HTML', 'polski'), 'type' => 'textarea', 'default' => __('Thank you for creating an account. Click the button below to activate your account:', 'polski')],
                    ['key' => 'polski_doi|email_button_text', 'label' => __('Email button text', 'polski'), 'type' => 'text', 'default' => __('Activate account', 'polski')],
                    ['key' => 'polski_doi|email_link_intro', 'label' => __('Fallback link text', 'polski'), 'type' => 'text', 'default' => __('If you prefer, copy and paste this link into your browser:', 'polski')],
                    ['key' => 'polski_doi|email_intro_plain', 'label' => __('Email content plain text', 'polski'), 'type' => 'textarea', 'default' => __('Thank you for creating an account. Visit the link below to activate your account:', 'polski')],
                    ['key' => 'polski_doi|additional_content', 'label' => __('Additional email content', 'polski'), 'type' => 'textarea', 'default' => __('If you did not create an account with us, please ignore this email.', 'polski')],
                ],
            ],

            // === Sales and B2B ===
            [
                'id' => 'ajax_search',
                'name' => __('AJAX Search', 'polski'),
                'description' => __('Fast product suggestions while typing, with SKU support, categories, and a lightweight front-end friendly for web vitals.', 'polski'),
                'group' => 'Sales and B2B',
                'enabled' => false,
                'icon' => 'dashicons-search',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_search|min_chars', 'label' => __('Minimum characters', 'polski'), 'type' => 'number', 'default' => 2, 'hint' => __('Minimum number of characters before search starts. Recommended: 2-3', 'polski')],
                    ['key' => 'polski_search|limit', 'label' => __('Results limit', 'polski'), 'type' => 'number', 'default' => 6, 'hint' => __('Maximum number of products shown in dropdown. Recommended: 4-8', 'polski')],
                    ['key' => 'polski_search|debounce_ms', 'label' => __('Query debounce (ms)', 'polski'), 'type' => 'number', 'default' => 180, 'hint' => __('Delay in milliseconds before sending search request. Lower = faster but more server load', 'polski')],
                    ['key' => 'polski_search|show_submit_button', 'label' => __('Show search button', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|show_image', 'label' => __('Show thumbnails', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|show_price', 'label' => __('Show prices', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|show_unit_price', 'label' => __('Show unit price', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|show_omnibus', 'label' => __('Show Omnibus lowest price', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|show_sku', 'label' => __('Show SKU', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|show_view_all_link', 'label' => __('Show view all results link', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|search_sku', 'label' => __('Search by SKU', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|search_categories', 'label' => __('Search by categories', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|include_out_of_stock', 'label' => __('Include out of stock products', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_search|search_label', 'label' => __('Search field label', 'polski'), 'type' => 'text', 'default' => __('Search products', 'polski')],
                    ['key' => 'polski_search|results_label', 'label' => __('Results label', 'polski'), 'type' => 'text', 'default' => __('Product search results', 'polski')],
                    ['key' => 'polski_search|sku_label', 'label' => __('SKU label', 'polski'), 'type' => 'text', 'default' => 'SKU'],
                    ['key' => 'polski_search|placeholder', 'label' => __('Placeholder', 'polski'), 'type' => 'text', 'default' => __('Search products, SKU codes or categories', 'polski')],
                    ['key' => 'polski_search|submit_button_text', 'label' => __('Button text', 'polski'), 'type' => 'text', 'default' => __('Search', 'polski')],
                    ['key' => 'polski_search|no_results_text', 'label' => __('No results text', 'polski'), 'type' => 'text', 'default' => __('No results found for your query.', 'polski')],
                    ['key' => 'polski_search|view_all_text', 'label' => __('View all results text', 'polski'), 'type' => 'text', 'default' => __('View all results', 'polski')],
                ],
            ],

            // === Merchandising ===
            [
                'id' => 'brands',
                'name' => __('Brands', 'polski'),
                'description' => __('Support for product brands independent of the manufacturer, with views on product pages and listings, and its own taxonomy.', 'polski'),
                'group' => 'Merchandising',
                'enabled' => false,
                'icon' => 'dashicons-tag',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_brand|show_on_single', 'label' => __('Show on product page', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_brand|show_on_loop', 'label' => __('Show on product listings', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_brand|label', 'label' => __('Label', 'polski'), 'type' => 'text', 'default' => 'Brand'],
                    ['key' => 'polski_brand|show_label', 'label' => __('Show label', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_brand|separator', 'label' => __('Brand separator', 'polski'), 'type' => 'text', 'default' => ', '],
                    ['key' => 'polski_brand|link_terms', 'label' => __('Link to brand archive', 'polski'), 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'ajax_filters',
                'name' => __('AJAX Filters', 'polski'),
                'description' => __('Filtering product listings without page reload, including categories, brands, price, stock status, sale, and attributes.', 'polski'),
                'group' => 'Merchandising',
                'enabled' => false,
                'icon' => 'dashicons-filter',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_filters|show_on_shop', 'label' => __('Show on shop archives', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_title', 'label' => __('Show form header', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_categories', 'label' => __('Category filter', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_brands', 'label' => __('Brand filter', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_price', 'label' => __('Price filter', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_stock', 'label' => __('Availability filter', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_sale', 'label' => __('Sale filter', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_attributes', 'label' => __('Attribute filters', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_active_filters', 'label' => __('Active filters chips', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_counts', 'label' => __('Show term counts', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_hierarchical_categories', 'label' => __('Hierarchical categories', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|enable_taxonomy_multiselect', 'label' => __('Enable taxonomy multi-select', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|taxonomy_multi_select_relation', 'label' => __('Multi-select relation', 'polski'), 'type' => 'select', 'default' => 'or', 'options' => ['or' => __('OR - match any selected term', 'polski'), 'and' => __('AND - match all selected terms', 'polski')]],
                    ['key' => 'polski_filters|enable_mobile_panel', 'label' => __('Enable mobile filter panel', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|enable_instant_filtering', 'label' => __('Enable instant filtering', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|instant_filtering_debounce_ms', 'label' => __('Instant filtering debounce (ms)', 'polski'), 'type' => 'number', 'default' => 350, 'hint' => __('Delay before auto-submitting text and price fields', 'polski')],
                    ['key' => 'polski_filters|presets_json', 'label' => __('Named presets JSON', 'polski'), 'type' => 'textarea', 'default' => '', 'hint' => __('Optional JSON map for shortcode preset="...". Example: {"fashion":{"title":"Fashion filters","attribute_taxonomies":"pa_color,pa_size","show_brands":false}}', 'polski')],
                    ['key' => 'polski_filters|archive_presets_json', 'label' => __('Archive preset mapping JSON', 'polski'), 'type' => 'textarea', 'default' => '', 'hint' => __('Optional JSON map from archive context to preset slug. Examples: {"shop":"default","product_cat:hoodies":"fashion","taxonomy:pa_color:red":"color_red","post_type_archive:product":"catalog"}', 'polski')],
                    ['key' => 'polski_filters|attribute_taxonomies', 'label' => __('Specific attribute taxonomies', 'polski'), 'type' => 'text', 'default' => '', 'hint' => __('Optional comma-separated list, e.g. pa_color,pa_size. If empty, the first N attributes are used.', 'polski')],
                    ['key' => 'polski_filters|max_attribute_taxonomies', 'label' => __('Max number of attributes', 'polski'), 'type' => 'number', 'default' => 4, 'hint' => __('How many product attributes to show as filter dropdowns', 'polski')],
                    ['key' => 'polski_filters|title', 'label' => __('Header', 'polski'), 'type' => 'text', 'default' => 'Product Filters'],
                    ['key' => 'polski_filters|active_filters_label', 'label' => __('Active filters label', 'polski'), 'type' => 'text', 'default' => 'Active filters'],
                    ['key' => 'polski_filters|mobile_toggle_text', 'label' => __('Mobile toggle button text', 'polski'), 'type' => 'text', 'default' => 'Show filters'],
                    ['key' => 'polski_filters|mobile_close_text', 'label' => __('Mobile close button text', 'polski'), 'type' => 'text', 'default' => 'Close'],
                    ['key' => 'polski_filters|mobile_panel_title', 'label' => __('Mobile panel title', 'polski'), 'type' => 'text', 'default' => 'Product Filters'],
                    ['key' => 'polski_filters|category_label', 'label' => __('Category label', 'polski'), 'type' => 'text', 'default' => 'Category'],
                    ['key' => 'polski_filters|category_all_text', 'label' => __('All categories text', 'polski'), 'type' => 'text', 'default' => 'All'],
                    ['key' => 'polski_filters|brand_label', 'label' => __('Brand label', 'polski'), 'type' => 'text', 'default' => 'Brand'],
                    ['key' => 'polski_filters|brand_all_text', 'label' => __('All brands text', 'polski'), 'type' => 'text', 'default' => 'All'],
                    ['key' => 'polski_filters|min_price_label', 'label' => __('Price from label', 'polski'), 'type' => 'text', 'default' => 'Price from'],
                    ['key' => 'polski_filters|max_price_label', 'label' => __('Price to label', 'polski'), 'type' => 'text', 'default' => 'Price to'],
                    ['key' => 'polski_filters|stock_label', 'label' => __('Availability label', 'polski'), 'type' => 'text', 'default' => 'Availability'],
                    ['key' => 'polski_filters|stock_any_text', 'label' => __('Any availability text', 'polski'), 'type' => 'text', 'default' => 'Any'],
                    ['key' => 'polski_filters|stock_instock_text', 'label' => __('Instock product text', 'polski'), 'type' => 'text', 'default' => 'Available immediately'],
                    ['key' => 'polski_filters|sale_label', 'label' => __('Sale label', 'polski'), 'type' => 'text', 'default' => 'Sales'],
                    ['key' => 'polski_filters|sale_active_text', 'label' => __('Active sale filter text', 'polski'), 'type' => 'text', 'default' => 'On sale only'],
                    ['key' => 'polski_filters|attribute_any_text', 'label' => __('Any attribute value text', 'polski'), 'type' => 'text', 'default' => 'Any'],
                    ['key' => 'polski_filters|show_reset_link', 'label' => __('Show reset link', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|submit_text', 'label' => __('Button text', 'polski'), 'type' => 'text', 'default' => 'Filter'],
                    ['key' => 'polski_filters|reset_text', 'label' => __('Reset text', 'polski'), 'type' => 'text', 'default' => 'Clear filters'],
                ],
            ],
            [
                'id' => 'wishlist',
                'name' => __('Wishlist', 'polski'),
                'description' => __('Saving favorite products for guests and logged-in users, with a list in the customer account and AJAX add/remove.', 'polski'),
                'group' => 'Merchandising',
                'enabled' => false,
                'icon' => 'dashicons-heart',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_wishlist|allow_guests', 'label' => __('Allow guests to save favorites', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_on_single', 'label' => __('Show on product page', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_on_loop', 'label' => __('Show on listings', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_in_account', 'label' => __('Show in My Account', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_title', 'label' => __('Show list title', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_product_image', 'label' => __('Show product images', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_product_name', 'label' => __('Show product names', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_price', 'label' => __('Show price in list', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_add_to_cart', 'label' => __('Show cart button in list', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_remove_button', 'label' => __('Show remove button in list', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|grid_columns', 'label' => __('Number of columns in list', 'polski'), 'type' => 'number', 'default' => 4, 'hint' => __('Number of product columns in grid layout. Recommended: 3-4', 'polski')],
                    ['key' => 'polski_wishlist|account_label', 'label' => __('Label in My Account', 'polski'), 'type' => 'text', 'default' => 'Favorites'],
                    ['key' => 'polski_wishlist|title', 'label' => __('List title', 'polski'), 'type' => 'text', 'default' => 'Your favorite products'],
                    ['key' => 'polski_wishlist|account_intro_text', 'label' => __('Section description', 'polski'), 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_wishlist|button_add_text', 'label' => __('Add text', 'polski'), 'type' => 'text', 'default' => 'Add to favorites'],
                    ['key' => 'polski_wishlist|button_remove_text', 'label' => __('Remove text', 'polski'), 'type' => 'text', 'default' => 'Remove from favorites'],
                    ['key' => 'polski_wishlist|login_required_text', 'label' => __('Login message', 'polski'), 'type' => 'text', 'default' => 'Please log in to use the wishlist.'],
                    ['key' => 'polski_wishlist|product_not_found_text', 'label' => __('Product not found message', 'polski'), 'type' => 'text', 'default' => 'Product not found.'],
                    ['key' => 'polski_wishlist|empty_text', 'label' => __('Empty state', 'polski'), 'type' => 'text', 'default' => 'The favorites list is empty.'],
                ],
            ],
            [
                'id' => 'compare',
                'name' => __('Product Comparison', 'polski'),
                'description' => __('Product comparison tool with features table, highlight differences, guest and customer support, and view in My Account.', 'polski'),
                'group' => 'Merchandising',
                'enabled' => false,
                'icon' => 'dashicons-randomize',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_compare|allow_guests', 'label' => __('Allow guests to compare products', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_on_single', 'label' => __('Show on product page', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_on_loop', 'label' => __('Show on listings', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_in_account', 'label' => __('Show in My Account', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_product_image', 'label' => __('Show product images', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_add_to_cart', 'label' => __('Show cart button', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_remove_button', 'label' => __('Show remove button', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|account_label', 'label' => __('Label in My Account', 'polski'), 'type' => 'text', 'default' => 'Comparison'],
                    ['key' => 'polski_compare|title', 'label' => __('Comparison title', 'polski'), 'type' => 'text', 'default' => 'Product Comparison'],
                    ['key' => 'polski_compare|max_items', 'label' => __('Maximum number of products', 'polski'), 'type' => 'number', 'default' => 4, 'hint' => __('Maximum products that can be compared side-by-side. Recommended: 3-5', 'polski')],
                    ['key' => 'polski_compare|button_add_text', 'label' => __('Add text', 'polski'), 'type' => 'text', 'default' => 'Add to comparison'],
                    ['key' => 'polski_compare|button_remove_text', 'label' => __('Remove text', 'polski'), 'type' => 'text', 'default' => 'Remove from comparison'],
                    ['key' => 'polski_compare|compare_link_text', 'label' => __('Comparison link text', 'polski'), 'type' => 'text', 'default' => 'Compare products'],
                    ['key' => 'polski_compare|clear_text', 'label' => __('Clear text', 'polski'), 'type' => 'text', 'default' => 'Clear comparison'],
                    ['key' => 'polski_compare|feature_label', 'label' => __('Feature column header', 'polski'), 'type' => 'text', 'default' => 'Feature'],
                    ['key' => 'polski_compare|differences_toggle_text', 'label' => __('Difference filter label', 'polski'), 'type' => 'text', 'default' => 'Show only differences'],
                    ['key' => 'polski_compare|login_required_text', 'label' => __('Login message', 'polski'), 'type' => 'text', 'default' => 'Please log in to use product comparison.'],
                    ['key' => 'polski_compare|product_not_found_text', 'label' => __('Product not found message', 'polski'), 'type' => 'text', 'default' => 'Product not found.'],
                    ['key' => 'polski_compare|limit_notice_text', 'label' => __('Limit message', 'polski'), 'type' => 'text', 'default' => 'You can compare up to {limit} products at once. The oldest entry was replaced automatically.', 'hint' => __('Variable: {limit}', 'polski')],
                    ['key' => 'polski_compare|clear_error_text', 'label' => __('Clear error message', 'polski'), 'type' => 'text', 'default' => 'You cannot clear the comparison.'],
                    ['key' => 'polski_compare|intro_text', 'label' => __('Section description', 'polski'), 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_compare|empty_text', 'label' => __('Empty state', 'polski'), 'type' => 'text', 'default' => 'The comparison list is empty.'],
                    ['key' => 'polski_compare|highlight_differences', 'label' => __('Highlight differences', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_only_differences', 'label' => __('Show only differences by default', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_compare|price_label', 'label' => __('Price label', 'polski'), 'type' => 'text', 'default' => 'Price'],
                    ['key' => 'polski_compare|unit_price_label', 'label' => __('Unit price label', 'polski'), 'type' => 'text', 'default' => 'Unit price'],
                    ['key' => 'polski_compare|sku_label', 'label' => __('SKU label', 'polski'), 'type' => 'text', 'default' => 'SKU'],
                    ['key' => 'polski_compare|availability_label', 'label' => __('Availability label', 'polski'), 'type' => 'text', 'default' => 'Availability'],
                    ['key' => 'polski_compare|delivery_time_label', 'label' => __('Delivery time label', 'polski'), 'type' => 'text', 'default' => 'Delivery time'],
                    ['key' => 'polski_compare|brand_label', 'label' => __('Brand label', 'polski'), 'type' => 'text', 'default' => 'Brand'],
                    ['key' => 'polski_compare|manufacturer_label', 'label' => __('Manufacturer label', 'polski'), 'type' => 'text', 'default' => 'Manufacturer'],
                    ['key' => 'polski_compare|gtin_label', 'label' => __('GTIN / EAN label', 'polski'), 'type' => 'text', 'default' => 'GTIN / EAN'],
                    ['key' => 'polski_compare|description_label', 'label' => __('Short description label', 'polski'), 'type' => 'text', 'default' => 'Short description'],
                    ['key' => 'polski_compare|show_description', 'label' => __('Show short description', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_attributes', 'label' => __('Show product attributes', 'polski'), 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'quick_view',
                'name' => __('Quick View', 'polski'),
                'description' => __('Lightweight product modal on listings, with support for variations, prices, gallery, and basic purchase information.', 'polski'),
                'group' => 'Merchandising',
                'enabled' => false,
                'icon' => 'dashicons-visibility',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_quick_view|show_on_loop', 'label' => __('Show on listings', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|button_text', 'label' => __('Button text', 'polski'), 'type' => 'text', 'default' => 'Quick View'],
                    ['key' => 'polski_quick_view|modal_title', 'label' => __('Modal label', 'polski'), 'type' => 'text', 'default' => 'Product Quick View'],
                    ['key' => 'polski_quick_view|close_label', 'label' => __('Close label', 'polski'), 'type' => 'text', 'default' => 'Close'],
                    ['key' => 'polski_quick_view|loading_text', 'label' => __('Loading text', 'polski'), 'type' => 'text', 'default' => 'Loading product...'],
                    ['key' => 'polski_quick_view|error_text', 'label' => __('AJAX error text', 'polski'), 'type' => 'text', 'default' => 'Could not load product preview.'],
                    ['key' => 'polski_quick_view|product_not_found_text', 'label' => __('Product not found text', 'polski'), 'type' => 'text', 'default' => 'Product not found.'],
                    ['key' => 'polski_quick_view|sku_label', 'label' => __('SKU label', 'polski'), 'type' => 'text', 'default' => 'SKU'],
                    ['key' => 'polski_quick_view|show_modal_label', 'label' => __('Show modal title in content', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_close_button', 'label' => __('Show close button', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_title', 'label' => __('Show product name', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_image', 'label' => __('Show main image', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_gallery', 'label' => __('Show mini gallery', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_price', 'label' => __('Show price', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_unit_price', 'label' => __('Show unit price', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_omnibus', 'label' => __('Show Omnibus lowest price', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_sku', 'label' => __('Show SKU', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_delivery_time', 'label' => __('Show delivery time', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_gpsr', 'label' => __('Show product safety (GPSR)', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_brand', 'label' => __('Show brand', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_manufacturer', 'label' => __('Show manufacturer', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_short_description', 'label' => __('Show short description', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_add_to_cart', 'label' => __('Show purchase form', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_view_product_link', 'label' => __('Show full product link', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|view_product_text', 'label' => __('Link text to product', 'polski'), 'type' => 'text', 'default' => 'View full product page'],
                    ['key' => 'polski_quick_view|view_product_target', 'label' => __('How to open full page', 'polski'), 'type' => 'select', 'default' => 'same_tab', 'options' => ['same_tab' => __('In the same tab', 'polski'), 'new_tab' => __('In a new tab', 'polski')]],
                    ['key' => 'polski_quick_view|show_backdrop_close', 'label' => __('Close by clicking background', 'polski'), 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'badge_management',
                'name' => __('Badge Management', 'polski'),
                'description' => __('Merchandising badges on product pages and listings, with automatic conditions and manual per-product highlights.', 'polski'),
                'group' => 'Merchandising',
                'enabled' => false,
                'icon' => 'dashicons-awards',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_badges|show_on_single', 'label' => __('Show on product page', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|show_on_loop', 'label' => __('Show on listings', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|show_manual_badge', 'label' => __('Show manual badge', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|manual_badge_style', 'label' => __('Default manual badge style', 'polski'), 'type' => 'select', 'default' => 'accent', 'options' => ['accent' => 'Accent', 'neutral' => 'Neutral', 'warning' => 'Warning', 'success' => 'Success']],
                    ['key' => 'polski_badges|show_secondary_badge', 'label' => __('Show secondary badge', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|secondary_badge_style', 'label' => __('Secondary badge style', 'polski'), 'type' => 'select', 'default' => 'neutral', 'options' => ['accent' => 'Accent', 'neutral' => 'Neutral', 'warning' => 'Warning', 'success' => 'Success']],
                    ['key' => 'polski_badges|shape', 'label' => __('Badge shape', 'polski'), 'type' => 'select', 'default' => 'pill', 'options' => ['pill' => 'Pill', 'rounded' => 'Rounded']],
                    ['key' => 'polski_badges|uppercase', 'label' => __('Uppercase', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_badges|max_badges_single', 'label' => __('Max badges on product page', 'polski'), 'type' => 'number', 'default' => 4],
                    ['key' => 'polski_badges|max_badges_loop', 'label' => __('Max badges on listings', 'polski'), 'type' => 'number', 'default' => 3],
                    ['key' => 'polski_badges|show_sale_badge', 'label' => __('Sale badge', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|sale_badge_text', 'label' => __('Sale badge text', 'polski'), 'type' => 'text', 'default' => 'Sale'],
                    ['key' => 'polski_badges|show_new_badge', 'label' => __('New badge', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|new_badge_text', 'label' => __('New badge text', 'polski'), 'type' => 'text', 'default' => 'New'],
                    ['key' => 'polski_badges|newness_days', 'label' => __('New for how many days', 'polski'), 'type' => 'number', 'default' => 30, 'hint' => __('Products published within this many days will show the "New" badge', 'polski')],
                    ['key' => 'polski_badges|show_low_stock_badge', 'label' => __('Low stock badge', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|low_stock_badge_text', 'label' => __('Low stock badge text', 'polski'), 'type' => 'text', 'default' => 'Last items'],
                    ['key' => 'polski_badges|low_stock_threshold', 'label' => __('Low stock threshold', 'polski'), 'type' => 'number', 'default' => 3, 'hint' => __('Badge appears when stock quantity is at or below this number', 'polski')],
                    ['key' => 'polski_badges|show_bestseller_badge', 'label' => __('Bestseller badge', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|bestseller_badge_text', 'label' => __('Bestseller badge text', 'polski'), 'type' => 'text', 'default' => 'Bestseller'],
                    ['key' => 'polski_badges|bestseller_threshold', 'label' => __('Bestseller threshold (sales)', 'polski'), 'type' => 'number', 'default' => 25, 'hint' => __('Minimum total sales count for the "Bestseller" badge to appear', 'polski')],
                ],
            ],
            [
                'id' => 'tab_manager',
                'name' => __('Tab Manager', 'polski'),
                'description' => __('Additional product tabs with content per product and global tabs for shipping, returns, and business information.', 'polski'),
                'group' => 'Merchandising',
                'enabled' => false,
                'icon' => 'dashicons-index-card',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_tabs|enable_global_shipping_tab', 'label' => __('Global shipping tab', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_tabs|shipping_tab_title', 'label' => __('Shipping tab title', 'polski'), 'type' => 'text', 'default' => 'Shipping & Delivery'],
                    ['key' => 'polski_tabs|shipping_tab_content', 'label' => __('Shipping tab content', 'polski'), 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_tabs|shipping_tab_priority', 'label' => __('Shipping tab priority', 'polski'), 'type' => 'number', 'default' => 47, 'hint' => __('Lower number = appears earlier. WooCommerce default: Description=10, Reviews=30', 'polski')],
                    ['key' => 'polski_tabs|enable_global_returns_tab', 'label' => __('Global returns tab', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_tabs|returns_tab_title', 'label' => __('Returns tab title', 'polski'), 'type' => 'text', 'default' => 'Returns & Complaints'],
                    ['key' => 'polski_tabs|returns_tab_content', 'label' => __('Returns tab content', 'polski'), 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_tabs|returns_tab_priority', 'label' => __('Returns tab priority', 'polski'), 'type' => 'number', 'default' => 48],
                    ['key' => 'polski_tabs|enable_product_tab_1', 'label' => __('Enable first product tab', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_tabs|product_tab_1_priority', 'label' => __('First product tab priority', 'polski'), 'type' => 'number', 'default' => 45],
                    ['key' => 'polski_tabs|enable_product_tab_2', 'label' => __('Enable second product tab', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_tabs|product_tab_2_priority', 'label' => __('Second product tab priority', 'polski'), 'type' => 'number', 'default' => 46],
                ],
            ],
            [
                'id' => 'featured_video',
                'name' => __('Featured Video', 'polski'),
                'description' => __('Product video on the product page, embedded from YouTube, Vimeo, or as a local MP4 file in the media section.', 'polski'),
                'group' => 'Merchandising',
                'enabled' => false,
                'icon' => 'dashicons-video-alt3',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_featured_video|show_on_single', 'label' => __('Show on product page', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_featured_video|position', 'label' => __('Position', 'polski'), 'type' => 'select', 'default' => 'after_gallery', 'options' => ['after_gallery' => __('Below gallery', 'polski'), 'before_summary' => __('Before product summary', 'polski')]],
                    ['key' => 'polski_featured_video|title', 'label' => __('Section header', 'polski'), 'type' => 'text', 'default' => 'Watch the product in action'],
                    ['key' => 'polski_featured_video|intro_text', 'label' => __('Section description', 'polski'), 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_featured_video|show_title', 'label' => __('Show section header', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_featured_video|show_intro', 'label' => __('Show section description', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_featured_video|autoplay', 'label' => __('Autoplay for supported embeds', 'polski'), 'type' => 'checkbox', 'default' => false],
                ],
            ],
            [
                'id' => 'gallery_zoom',
                'name' => __('Gallery & Zoom', 'polski'),
                'description' => __('Lightweight image zoom and simple gallery lightbox without external slider libraries.', 'polski'),
                'group' => 'Merchandising',
                'enabled' => false,
                'icon' => 'dashicons-format-gallery',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_gallery_zoom|enable_zoom', 'label' => __('Enable zoom on hover', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_gallery_zoom|zoom_scale', 'label' => __('Zoom scale', 'polski'), 'type' => 'number', 'default' => 1.45, 'hint' => __('Magnification factor on hover. 1.0 = no zoom, 2.0 = double size', 'polski')],
                    ['key' => 'polski_gallery_zoom|enable_lightbox', 'label' => __('Enable lightbox on click', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_gallery_zoom|dialog_label', 'label' => __('Lightbox window label', 'polski'), 'type' => 'text', 'default' => 'Product gallery preview'],
                    ['key' => 'polski_gallery_zoom|close_label', 'label' => __('Close label', 'polski'), 'type' => 'text', 'default' => 'Close gallery preview'],
                    ['key' => 'polski_gallery_zoom|show_backdrop_close', 'label' => __('Close by clicking background', 'polski'), 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'product_slider_carousel',
                'name' => __('Product Slider Carousel', 'polski'),
                'description' => __('Lightweight product slider based on scroll-snap, with related, sale, or featured products.', 'polski'),
                'group' => 'Merchandising',
                'enabled' => false,
                'icon' => 'dashicons-images-alt2',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_slider|show_on_single', 'label' => __('Show on product page', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_slider|source', 'label' => __('Product source', 'polski'), 'type' => 'select', 'default' => 'related', 'options' => ['related' => __('Related', 'polski'), 'upsell' => __('Upsell', 'polski'), 'sale' => __('Sale', 'polski'), 'featured' => __('Featured', 'polski')]],
                    ['key' => 'polski_slider|title', 'label' => __('Section header', 'polski'), 'type' => 'text', 'default' => 'Recommended products'],
                    ['key' => 'polski_slider|limit', 'label' => __('Number of products', 'polski'), 'type' => 'number', 'default' => 8],
                    ['key' => 'polski_slider|show_title', 'label' => __('Show section header', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_slider|show_intro_text', 'label' => __('Show section description', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_slider|intro_text', 'label' => __('Section description', 'polski'), 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_slider|show_image', 'label' => __('Show product images', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_slider|show_price', 'label' => __('Show prices', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_slider|show_name', 'label' => __('Show product name', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_slider|show_add_to_cart', 'label' => __('Show cart button', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_slider|show_view_all_link', 'label' => __('Show "view all" link', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_slider|show_empty_state', 'label' => __('Show empty state without products', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_slider|empty_text', 'label' => __('Empty state text', 'polski'), 'type' => 'text', 'default' => 'No products to display in this section.'],
                    ['key' => 'polski_slider|view_all_text', 'label' => __('"view all" link text', 'polski'), 'type' => 'text', 'default' => 'View all results'],
                    ['key' => 'polski_slider|view_all_target', 'label' => __('How to open "view all" link', 'polski'), 'type' => 'select', 'default' => 'same_tab', 'options' => ['same_tab' => __('In the same tab', 'polski'), 'new_tab' => __('In a new tab', 'polski')]],
                ],
            ],
            [
                'id' => 'waitlist',
                'name' => __('Waitlist', 'polski'),
                'description' => __('Waitlist for out-of-stock products, with email signup and automatic notifications upon restock.', 'polski'),
                'group' => 'Merchandising',
                'enabled' => false,
                'icon' => 'dashicons-email-alt',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_waitlist|show_on_single', 'label' => __('Show on product page', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_waitlist|allow_guests', 'label' => __('Allow guests to sign up', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_waitlist|title', 'label' => __('Header', 'polski'), 'type' => 'text', 'default' => 'Notify me when available'],
                    ['key' => 'polski_waitlist|show_title', 'label' => __('Show header', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_waitlist|intro_text', 'label' => __('Description', 'polski'), 'type' => 'textarea', 'default' => 'Leave your email address and we will let you know when the product is back in stock.'],
                    ['key' => 'polski_waitlist|show_intro', 'label' => __('Show description', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_waitlist|email_label', 'label' => __('Email field label', 'polski'), 'type' => 'text', 'default' => 'Email address'],
                    ['key' => 'polski_waitlist|email_placeholder', 'label' => __('Email field placeholder', 'polski'), 'type' => 'text', 'default' => 'Your email address'],
                    ['key' => 'polski_waitlist|button_text', 'label' => __('Button text', 'polski'), 'type' => 'text', 'default' => 'Notify me'],
                    ['key' => 'polski_waitlist|success_text', 'label' => __('Success message', 'polski'), 'type' => 'text', 'default' => 'Thank you. We have added you to the waitlist.'],
                    ['key' => 'polski_waitlist|privacy_label', 'label' => __('Consent text', 'polski'), 'type' => 'text', 'default' => 'I accept email contact regarding the availability of this product.'],
                    ['key' => 'polski_waitlist|product_not_found_text', 'label' => __('Product not found error', 'polski'), 'type' => 'text', 'default' => 'Product not found.'],
                    ['key' => 'polski_waitlist|disabled_text', 'label' => __('Waitlist unavailable error', 'polski'), 'type' => 'text', 'default' => 'Waitlist is unavailable for this product.'],
                    ['key' => 'polski_waitlist|invalid_email_text', 'label' => __('Invalid email error', 'polski'), 'type' => 'text', 'default' => 'Please enter a valid email address.'],
                    ['key' => 'polski_waitlist|privacy_error_text', 'label' => __('Missing consent error', 'polski'), 'type' => 'text', 'default' => 'You must accept the consent for email contact.'],
                    ['key' => 'polski_waitlist|login_required_text', 'label' => __('Login required error', 'polski'), 'type' => 'text', 'default' => 'Log in to sign up for the waitlist.'],
                    ['key' => 'polski_waitlist|notify_subject', 'label' => __('Email subject', 'polski'), 'type' => 'text', 'default' => 'Product back in stock - {product_name}'],
                    ['key' => 'polski_waitlist|notify_intro_text', 'label' => __('Email intro text', 'polski'), 'type' => 'text', 'default' => 'Product {product_name} is back in stock.', 'hint' => 'Zmienne: {product_name}'],
                    ['key' => 'polski_waitlist|notify_outro_text', 'label' => __('Email footer text', 'polski'), 'type' => 'text', 'default' => 'If you no longer wish to receive such messages, simply ignore this email.'],
                ],
            ],
            [
                'id' => 'infinite_scroll',
                'name' => __('Infinite Scrolling', 'polski'),
                'description' => __('Lightweight loading of subsequent products on WooCommerce archives, with button mode or automatic loading.', 'polski'),
                'group' => 'Merchandising',
                'enabled' => false,
                'icon' => 'dashicons-update-alt',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_infinite_scroll|mode', 'label' => __('Operation mode', 'polski'), 'type' => 'select', 'default' => 'button', 'options' => ['button' => __('Button', 'polski'), 'auto' => __('Automatic scroll', 'polski')]],
                    ['key' => 'polski_infinite_scroll|button_text', 'label' => __('Button text', 'polski'), 'type' => 'text', 'default' => 'Load more products'],
                    ['key' => 'polski_infinite_scroll|loading_text', 'label' => __('Loading text', 'polski'), 'type' => 'text', 'default' => 'Loading products...'],
                    ['key' => 'polski_infinite_scroll|error_text', 'label' => __('Loading error text', 'polski'), 'type' => 'text', 'default' => 'Could not load more products.'],
                    ['key' => 'polski_infinite_scroll|end_text', 'label' => __('End of list text', 'polski'), 'type' => 'text', 'default' => 'No more products.'],
                    ['key' => 'polski_infinite_scroll|show_status', 'label' => __('Show status messages', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_infinite_scroll|show_button_in_auto_mode', 'label' => __('Show button also in auto mode', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_infinite_scroll|show_on_shop', 'label' => __('Show on shop page', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_infinite_scroll|show_on_taxonomies', 'label' => __('Show on categories and tags', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_infinite_scroll|auto_after_pages', 'label' => __('How many pages to auto-load', 'polski'), 'type' => 'number', 'default' => 0, 'hint' => __('0 = unlimited. After this many pages, auto-loading stops and shows a manual button', 'polski')],
                ],
            ],
            [
                'id' => 'popup',
                'name' => __('WooCommerce Popup', 'polski'),
                'description' => __('Lightweight promotional or lead popup with frequency, delay, and display location control.', 'polski'),
                'group' => 'Merchandising',
                'enabled' => false,
                'icon' => 'dashicons-megaphone',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_popup|title', 'label' => __('Header', 'polski'), 'type' => 'text', 'default' => 'Have a question about the product or commercial terms?'],
                    ['key' => 'polski_popup|content', 'label' => __('Content', 'polski'), 'type' => 'textarea', 'default' => 'Contact us if you want to get a B2B offer, wholesale discount, or help with product selection.'],
                    ['key' => 'polski_popup|cta_text', 'label' => __('CTA text', 'polski'), 'type' => 'text', 'default' => 'Go to contact'],
                    ['key' => 'polski_popup|show_title', 'label' => __('Show header', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_popup|show_close_button', 'label' => __('Show close button', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_popup|close_label', 'label' => __('Close label', 'polski'), 'type' => 'text', 'default' => 'Close popup'],
                    ['key' => 'polski_popup|dialog_label', 'label' => __('Dialog box label', 'polski'), 'type' => 'text', 'default' => 'Promotional popup'],
                    ['key' => 'polski_popup|cta_url', 'label' => __('CTA URL', 'polski'), 'type' => 'text', 'default' => ''],
                    ['key' => 'polski_popup|fallback_cta_url', 'label' => __('CTA Fallback URL', 'polski'), 'type' => 'select', 'default' => 'account', 'options' => ['account' => __('My account', 'polski'), 'home' => __('Home page', 'polski'), 'shop' => __('Shop', 'polski')]],
                    ['key' => 'polski_popup|show_cta', 'label' => __('Show CTA button', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_popup|cta_target', 'label' => __('How to open CTA', 'polski'), 'type' => 'select', 'default' => 'same_tab', 'options' => ['same_tab' => __('In the same tab', 'polski'), 'new_tab' => __('In a new tab', 'polski')]],
                    ['key' => 'polski_popup|show_backdrop_close', 'label' => __('Close by clicking background', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_popup|show_on_home', 'label' => __('Home page', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_popup|show_on_shop', 'label' => __('Shop and archives', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_popup|show_on_product', 'label' => __('Product page', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_popup|show_on_cart', 'label' => __('Cart', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_popup|show_on_checkout', 'label' => __('Checkout', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_popup|delay_seconds', 'label' => __('Delay in seconds', 'polski'), 'type' => 'number', 'default' => 4, 'hint' => __('How long to wait after page load before showing the popup', 'polski')],
                    ['key' => 'polski_popup|frequency_days', 'label' => __('Frequency of re-showing (days)', 'polski'), 'type' => 'number', 'default' => 7, 'hint' => __('After closing, the popup will not appear again for this many days (uses cookie)', 'polski')],
                ],
            ],
            // === New Compliance Modules 2026 ===
            [
                'id' => 'gpsr',
                'name' => __('GPSR - Product safety', 'polski'),
                'description' => __('GPSR product safety tools: manufacturer and importer data, responsible person, product identifiers, safety warnings, instructions, and CSV bulk import or export.', 'polski'),
                'group' => 'Product Information',
                'enabled' => true,
                'icon' => 'dashicons-shield-alt',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_gpsr|display_mode', 'label' => __('Display mode', 'polski'), 'type' => 'select', 'default' => 'accordion', 'options' => ['accordion' => __('Accordion', 'polski'), 'section' => __('Section', 'polski')]],
                    ['key' => 'polski_gpsr|section_title', 'label' => __('Section title', 'polski'), 'type' => 'text', 'default' => __('Product safety', 'polski')],
                ],
            ],
            [
                'id' => 'verified_review',
                'name' => __('Verified purchase badge', 'polski'),
                'description' => __('Badge shown on reviews from customers who actually purchased the product.', 'polski'),
                'group' => 'Product Information',
                'enabled' => false,
                'icon' => 'dashicons-star-filled',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_verified_review|badge_text', 'label' => __('Badge text', 'polski'), 'type' => 'text', 'default' => __('Verified purchase', 'polski')],
                ],
            ],
            [
                'id' => 'green_claims',
                'name' => __('Anti-greenwashing', 'polski'),
                'description' => __('Fields for products: ecological claim basis, certificate link, expiration date. Compliance with anti-greenwashing directive (September 2026).', 'polski'),
                'group' => 'Product Information',
                'enabled' => false,
                'icon' => 'dashicons-palmtree',
                'links' => [],
                'settings' => [],
            ],
            [
                'id' => 'dsa_toolkit',
                'name' => __('DSA Toolkit', 'polski'),
                'description' => __('Digital Services Act tools: contact point settings, report form for illegal content or products, and an admin reports screen. Shortcode: [polski_dsa_report].', 'polski'),
                'group' => 'Consumer rights',
                'enabled' => false,
                'icon' => 'dashicons-megaphone',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_dsa|contact_name', 'label' => __('DSA contact name', 'polski'), 'type' => 'text', 'default' => ''],
                    ['key' => 'polski_dsa|contact_email', 'label' => __('DSA contact email', 'polski'), 'type' => 'email', 'default' => ''],
                    ['key' => 'polski_dsa|contact_phone', 'label' => __('DSA contact phone', 'polski'), 'type' => 'text', 'default' => ''],
                    ['key' => 'polski_dsa|form_title', 'label' => __('Form title', 'polski'), 'type' => 'text', 'default' => 'Report illegal content'],
                    ['key' => 'polski_dsa|success_text', 'label' => __('Success message', 'polski'), 'type' => 'text', 'default' => 'Thank you for your report. We will review it within 7 business days.'],
                ],
            ],
            [
                'id' => 'ksef_ready',
                'name' => __('KSeF readiness', 'polski'),
                'description' => __('Automatic detection of orders that may require KSeF invoicing based on NIP, plus integration hooks and an admin status indicator.', 'polski'),
                'group' => 'Consumer rights',
                'enabled' => false,
                'icon' => 'dashicons-media-spreadsheet',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_ksef|auto_detect_nip', 'label' => __('Automatically detect based on NIP', 'polski'), 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'security_incidents',
                'name' => __('Security incidents', 'polski'),
                'description' => __('CRA-oriented incident log for vulnerabilities, breaches, third-party failures, and operational follow-up. Includes status tracking and CSV export.', 'polski'),
                'group' => 'Consumer rights',
                'enabled' => true,
                'icon' => 'dashicons-shield',
                'links' => [
                    ['label' => __('Incident log', 'polski'), 'url' => admin_url('admin.php?page=polski-security-incidents')],
                ],
                'settings' => [
                    ['key' => 'polski_security|incident_contact_email', 'label' => __('Security contact email', 'polski'), 'type' => 'email', 'default' => 'security@example.com'],
                    ['key' => 'polski_security|default_reporter_name', 'label' => __('Default reporter name', 'polski'), 'type' => 'text', 'default' => 'Store administrator'],
                ],
            ],
            [
                'id' => 'store_health',
                'name' => __('Store health monitor', 'polski'),
                'description' => __('Continuous, passive monitoring of front-end fatal errors, the checkout failure rate, and sales anomalies ("traffic but no orders"). Sends email or webhook alerts. Checks run every 5 minutes and never place synthetic orders.', 'polski'),
                'group' => 'Consumer rights',
                'enabled' => false,
                'icon' => 'dashicons-heart',
                'links' => [
                    ['label' => __('Health dashboard', 'polski'), 'url' => admin_url('admin.php?page=polski&tab=reports&view=health')],
                ],
                'settings' => [
                    ['key' => 'polski_store_health|alert_email', 'label' => __('Alert email', 'polski'), 'type' => 'email', 'default' => (string) get_option('admin_email'), 'hint' => __('Where to send health alerts. Defaults to the site admin email.', 'polski')],
                    ['key' => 'polski_store_health|webhook_url', 'label' => __('Alert webhook URL', 'polski'), 'type' => 'text', 'default' => '', 'hint' => __('Optional. Sends a JSON {"text": ...} payload (Slack/Discord-compatible).', 'polski')],
                    ['key' => 'polski_store_health|payments_fail_percent', 'label' => __('Checkout failure threshold (%)', 'polski'), 'type' => 'number', 'default' => 30, 'hint' => __('Alert when this share of checkouts fails within the last 2 hours.', 'polski')],
                    ['key' => 'polski_store_health|payments_min_sample', 'label' => __('Minimum checkout sample', 'polski'), 'type' => 'number', 'default' => 5, 'hint' => __('Ignore the failure rate until at least this many checkouts are observed.', 'polski')],
                    ['key' => 'polski_store_health|sales_min_expected', 'label' => __('Sales anomaly threshold', 'polski'), 'type' => 'number', 'default' => 3, 'hint' => __('Alert only when this many orders are typical for the hour but none arrive.', 'polski')],
                    ['key' => 'polski_store_health|cooldown_minutes', 'label' => __('Alert cooldown (minutes)', 'polski'), 'type' => 'number', 'default' => 60, 'hint' => __('Minimum time between repeat alerts for an ongoing problem.', 'polski')],
                ],
            ],
            [
                'id' => 'ai_bridge',
                'name' => __('AI Bridge', 'polski'),
                'description' => __('Exposes read-only commerce data (price history, product safety data, store health, configured-page checks, product facts) to AI assistants and the Site Editor via the WordPress Abilities API. Off by default.', 'polski'),
                'group' => 'Tools',
                'enabled' => false,
                'icon' => 'dashicons-rest-api',
                'links' => [],
                'settings' => [],
            ],

            // === SEO and Optimization ===
            [
                'id' => 'schema_org',
                'name' => __('Structured Data (Schema.org)', 'polski'),
                'description' => __('Automatic injection of advanced JSON-LD tags, supporting product indexing by Google with plugin-specific data preservation.', 'polski'),
                'group' => 'SEO & Optimization',
                'enabled' => true,
                'icon' => 'dashicons-search',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_seo|schema_enabled', 'label' => __('Enable structured data integration', 'polski'), 'type' => 'checkbox', 'default' => true, 'hint' => __('Main switch for Schema.org JSON-LD modifications.', 'polski')],
                    ['key' => '_schema_header', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;margin-top:8px;display:block;">' . __('Data to include', 'polski') . '</strong>'],
                    ['key' => 'polski_seo|schema_brand', 'label' => __('Include Brand', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_seo|schema_manufacturer', 'label' => __('Include Manufacturer', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_seo|schema_gtin', 'label' => __('Include barcodes (GTIN)', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_seo|schema_unit_price', 'label' => __('Include Unit Price', 'polski'), 'type' => 'checkbox', 'default' => true],
                ],
            ],

            [
                'id' => 'tracking_tags',
                'name' => __('Tracking Tags', 'polski'),
                'description' => __('Unified, consent-gated tag manager for marketing pixels and analytics tools. Enter your own tracking IDs; each tag fires only after the visitor grants the matching consent category. GA4 and GTM are handled by the GA4 DataLayer module.', 'polski'),
                'group' => 'SEO & Optimization',
                'enabled' => false,
                'icon' => 'dashicons-chart-line',
                'links' => [],
                'settings' => [
                    ['key' => '_tt_header_marketing', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;display:block;">' . esc_html__('Marketing pixels', 'polski') . '</strong><span style="font-size:12px;color:#646970;">' . esc_html__('Gated under the Marketing consent category.', 'polski') . '</span>'],
                    ['key' => 'polski_tracking_tags|meta_pixel_enabled', 'label' => __('Meta Pixel', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_tracking_tags|meta_pixel_id', 'label' => __('Meta Pixel ID', 'polski'), 'type' => 'text', 'default' => '', 'hint' => __('Pixel ID', 'polski')],
                    ['key' => 'polski_tracking_tags|tiktok_enabled', 'label' => __('TikTok Pixel', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_tracking_tags|tiktok_id', 'label' => __('TikTok Pixel ID', 'polski'), 'type' => 'text', 'default' => '', 'hint' => __('Pixel ID', 'polski')],
                    ['key' => 'polski_tracking_tags|microsoft_ads_enabled', 'label' => __('Microsoft Advertising (Bing UET)', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_tracking_tags|microsoft_ads_id', 'label' => __('UET Tag ID', 'polski'), 'type' => 'text', 'default' => '', 'hint' => __('UET Tag ID', 'polski')],
                    ['key' => 'polski_tracking_tags|linkedin_enabled', 'label' => __('LinkedIn Insight', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_tracking_tags|linkedin_id', 'label' => __('LinkedIn Partner ID', 'polski'), 'type' => 'text', 'default' => '', 'hint' => __('Partner ID', 'polski')],
                    ['key' => 'polski_tracking_tags|pinterest_enabled', 'label' => __('Pinterest Tag', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_tracking_tags|pinterest_id', 'label' => __('Pinterest Tag ID', 'polski'), 'type' => 'text', 'default' => '', 'hint' => __('Tag ID', 'polski')],
                    ['key' => 'polski_tracking_tags|twitter_enabled', 'label' => __('X / Twitter Ads', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_tracking_tags|twitter_id', 'label' => __('X / Twitter Pixel ID', 'polski'), 'type' => 'text', 'default' => '', 'hint' => __('Pixel ID', 'polski')],
                    ['key' => 'polski_tracking_tags|google_ads_enabled', 'label' => __('Google Ads', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_tracking_tags|google_ads_id', 'label' => __('Google Ads Conversion ID', 'polski'), 'type' => 'text', 'default' => '', 'hint' => 'AW-XXXXXXXXX'],
                    ['key' => '_tt_header_analytics', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;margin-top:8px;display:block;">' . esc_html__('Analytics and heatmaps', 'polski') . '</strong><span style="font-size:12px;color:#646970;">' . esc_html__('Gated under the Analytics consent category.', 'polski') . '</span>'],
                    ['key' => 'polski_tracking_tags|clarity_enabled', 'label' => __('Microsoft Clarity', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_tracking_tags|clarity_id', 'label' => __('Clarity Project ID', 'polski'), 'type' => 'text', 'default' => '', 'hint' => __('Project ID', 'polski')],
                    ['key' => 'polski_tracking_tags|matomo_enabled', 'label' => __('Matomo', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_tracking_tags|matomo_url', 'label' => __('Matomo URL', 'polski'), 'type' => 'text', 'default' => '', 'hint' => 'https://analytics.example.com/'],
                    ['key' => 'polski_tracking_tags|matomo_site_id', 'label' => __('Matomo Site ID', 'polski'), 'type' => 'text', 'default' => '', 'hint' => __('Site ID', 'polski')],
                    ['key' => 'polski_tracking_tags|plausible_enabled', 'label' => __('Plausible', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_tracking_tags|plausible_domain', 'label' => __('Plausible domain', 'polski'), 'type' => 'text', 'default' => '', 'hint' => 'example.com'],
                    ['key' => 'polski_tracking_tags|posthog_enabled', 'label' => __('PostHog', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_tracking_tags|posthog_key', 'label' => __('PostHog project API key', 'polski'), 'type' => 'text', 'default' => '', 'hint' => __('Project API key', 'polski')],
                    ['key' => 'polski_tracking_tags|hotjar_enabled', 'label' => __('Hotjar', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_tracking_tags|hotjar_id', 'label' => __('Hotjar Site ID', 'polski'), 'type' => 'text', 'default' => '', 'hint' => __('Site ID', 'polski')],
                    ['key' => 'polski_tracking_tags|inspectlet_enabled', 'label' => __('Inspectlet', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_tracking_tags|inspectlet_id', 'label' => __('Inspectlet WID', 'polski'), 'type' => 'text', 'default' => '', 'hint' => __('WID', 'polski')],
                    ['key' => 'polski_tracking_tags|crazy_egg_enabled', 'label' => __('Crazy Egg', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_tracking_tags|crazy_egg_id', 'label' => __('Crazy Egg Account ID', 'polski'), 'type' => 'text', 'default' => '', 'hint' => __('Account ID', 'polski')],
                    ['key' => 'polski_tracking_tags|simple_analytics_enabled', 'label' => __('Simple Analytics', 'polski'), 'type' => 'checkbox', 'default' => false],
                ],
            ],

            [
                'id' => 'safe_fonts',
                'name' => __('Safe Fonts', 'polski'),
                'description' => __('Reduce and gate external Google Fonts requests. Adds font-display and preconnect hints, and can defer the Google Fonts stylesheet until the visitor grants consent. Self-hosting font files is out of scope; this lowers and gates the external calls.', 'polski'),
                'group' => 'SEO & Optimization',
                'enabled' => false,
                'icon' => 'dashicons-editor-textcolor',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_safe_fonts|optimize', 'label' => __('Optimise Google Fonts', 'polski'), 'type' => 'checkbox', 'default' => true, 'hint' => __('Append display=swap to the font URL and emit preconnect hints for the Google Fonts hosts.', 'polski')],
                    ['key' => 'polski_safe_fonts|gate_until_consent', 'label' => __('Defer Google Fonts until consent', 'polski'), 'type' => 'checkbox', 'default' => false, 'hint' => __('Hold the Google Fonts stylesheet until the visitor grants the chosen consent category. A no-script fallback keeps fonts working when JavaScript is off.', 'polski')],
                    ['key' => 'polski_safe_fonts|consent_category', 'label' => __('Consent category', 'polski'), 'type' => 'select', 'default' => 'preferences', 'options' => [
                        'necessary' => __('Necessary', 'polski'),
                        'preferences' => __('Preferences', 'polski'),
                        'analytics' => __('Analytics', 'polski'),
                        'marketing' => __('Marketing', 'polski'),
                    ], 'hint' => __('Used only when deferring fonts until consent. Requires the Consent Manager module to be enabled.', 'polski')],
                ],
            ],

            [
                'id' => 'custom_integrations',
                'name' => __('Custom Integrations', 'polski'),
                'description' => __('Add your own scripts or snippets to the page head or footer. Each snippet is assigned a consent category and runs only after the visitor grants it, via the Consent Manager.', 'polski'),
                'group' => 'Advanced & Tools',
                'enabled' => false,
                'icon' => 'dashicons-editor-code',
                'links' => [],
                'settings' => [
                    ['key' => '_ci_intro', 'label' => '', 'type' => 'html', 'html' => '<span style="font-size:12px;color:#646970;">' . esc_html__('Each snippet is emitted as a consent-gated placeholder and only executes once the matching category is granted. Necessary snippets always run. Requires the Consent Manager module to actually gate execution.', 'polski') . '</span>'],
                    ['key' => 'polski_custom_integrations|snippets', 'label' => __('Snippets', 'polski'), 'type' => 'integration_repeater', 'default' => ''],
                ],
            ],

            [
                'id' => 'custom_triggers',
                'name' => __('Custom Triggers', 'polski'),
                'description' => __('Push your own dataLayer events on simple page conditions, such as visiting a URL or clicking an element. Integrates with the GA4 DataLayer module.', 'polski'),
                'group' => 'Advanced & Tools',
                'enabled' => false,
                'icon' => 'dashicons-randomize',
                'links' => [],
                'settings' => [
                    ['key' => '_ct_intro', 'label' => '', 'type' => 'html', 'html' => '<span style="font-size:12px;color:#646970;">' . esc_html__('Each trigger pushes an event into window.dataLayer. Assign a consent category to hold a trigger until that category is granted (necessary always fires).', 'polski') . '</span>'],
                    ['key' => 'polski_custom_triggers|triggers', 'label' => __('Triggers', 'polski'), 'type' => 'trigger_repeater', 'default' => ''],
                ],
            ],

            // === Integrations ===
            [
                'id' => 'checkout_toolkit_integration',
                'name' => __('Checkout and consent integration', 'polski'),
                'description' => __('Detection of popular checkout field extensions, cookies, and product data to maintain compatibility of settings and messages.', 'polski'),
                'group' => 'Integrations',
                'enabled' => true,
                'icon' => 'dashicons-admin-plugins',
                'settings' => [
                    ['key' => '_checkout_toolkit_status', 'type' => 'html', 'html' => $this->getCheckoutToolkitStatus()],
                ],
                'links' => [
                    ['label' => 'Flexible Checkout Fields', 'url' => 'https://wordpress.org/plugins/flexible-checkout-fields/'],
                ],
            ],

            // === Tools ===
            [
                'id' => 'site_audit',
                'name' => __('Store Audit', 'polski'),
                'description' => __('Automatic verification of the most common issues: missing legal pages, pre-selected checkboxes, company data, GDPR, Omnibus.', 'polski'),
                'group' => 'Tools',
                'enabled' => true,
                'icon' => 'dashicons-search',
                'links' => [],
                'settings' => [
                    ['key' => '_site_audit_open', 'label' => '', 'type' => 'html', 'html' => '<a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=polski&tab=reports&view=audit')) . '">' . esc_html__('Open compliance audit report', 'polski') . '</a>'],
                ],
            ],
            [
                'id' => 'plugin_data',
                'name' => __('Plugin data on uninstall', 'polski'),
                'description' => __('Choose whether Polski deletes its database tables, settings, and stored logs when the plugin is removed from WordPress.', 'polski'),
                'group' => 'Tools',
                'enabled' => true,
                'icon' => 'dashicons-trash',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_general|remove_data_on_uninstall', 'label' => __('Delete plugin data on uninstall', 'polski'), 'type' => 'checkbox', 'default' => false, 'hint' => __('If enabled, uninstall removes plugin tables, settings, and stored logs including deactivation feedback.', 'polski')],
                ],
            ],
            [
                'id' => 'cra_readiness',
                'name' => __('CRA - Cyber Resilience', 'polski'),
                'description' => __('Cyber Resilience Act: security.txt file (RFC 9116), security contact, vulnerability reporting policy.', 'polski'),
                'group' => 'Tools',
                'enabled' => false,
                'icon' => 'dashicons-lock',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_cra|security_contact', 'label' => __('Security contact email', 'polski'), 'type' => 'email', 'default' => ''],
                    ['key' => 'polski_cra|security_policy_url', 'label' => __('Security policy URL', 'polski'), 'type' => 'text', 'default' => ''],
                ],
            ],
            [
                'id' => 'dpa_tracker',
                'name' => __('DPA Registry (GDPR)', 'polski'),
                'description' => __('Detection of third-party services processing customers\' personal data. Tracking the status of data processing agreements.', 'polski'),
                'group' => 'Tools',
                'enabled' => false,
                'icon' => 'dashicons-clipboard',
                'links' => [],
                'settings' => [],
            ],
            [
                'id' => 'minimum_order',
                'name' => __('Minimum Order Value / Quantity', 'polski'),
                'description' => __('Block checkout when cart does not meet minimum order value or minimum number of items. Displays a notice on cart and checkout pages.', 'polski'),
                'group' => 'Checkout and Orders',
                'enabled' => false,
                'icon' => 'dashicons-lock',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_minimum_order|min_value', 'label' => __('Minimum order value (0 = disabled)', 'polski'), 'type' => 'number', 'default' => 0, 'hint' => __('Cart subtotal must reach this amount to proceed to checkout.', 'polski')],
                    ['key' => 'polski_minimum_order|min_quantity', 'label' => __('Minimum number of items (0 = disabled)', 'polski'), 'type' => 'number', 'default' => 0, 'hint' => __('Total number of items in cart must reach this count.', 'polski')],
                    ['key' => 'polski_minimum_order|exclude_sale_items', 'label' => __('Exclude sale items from minimum value', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_minimum_order|min_value_message', 'label' => __('Message for minimum value', 'polski'), 'type' => 'textarea', 'default' => 'Minimum order value is {min_value}. Current cart value: {current_value}.', 'hint' => __('Tokens: {min_value}, {current_value}', 'polski')],
                    ['key' => 'polski_minimum_order|min_quantity_message', 'label' => __('Message for minimum quantity', 'polski'), 'type' => 'textarea', 'default' => 'Minimum number of items per order is {min_quantity}. Current quantity: {current_quantity}.', 'hint' => __('Tokens: {min_quantity}, {current_quantity}', 'polski')],
                ],
            ],
            [
                'id' => 'review_requests',
                'name' => __('Review Request Emails', 'polski'),
                'description' => __('Automatically send review request emails to customers after order completion. Includes product images, review links, and opt-out.', 'polski'),
                'group' => 'Email',
                'enabled' => false,
                'icon' => 'dashicons-star-filled',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_review_requests|delay_days', 'label' => __('Send after (days)', 'polski'), 'type' => 'number', 'default' => 7, 'hint' => __('Number of days after order completion before sending the review request.', 'polski')],
                    ['key' => 'polski_review_requests|email_subject', 'label' => __('Email subject', 'polski'), 'type' => 'text', 'default' => 'How was your purchase? Leave a review', 'hint' => __('Tokens: {first_name}, {order_number}', 'polski')],
                    ['key' => 'polski_review_requests|email_intro', 'label' => __('Email intro text', 'polski'), 'type' => 'textarea', 'default' => 'Hi {first_name}, thank you for your recent purchase. We would love to hear your feedback.', 'hint' => __('Tokens: {first_name}', 'polski')],
                    ['key' => 'polski_review_requests|review_cta_text', 'label' => __('Review button text', 'polski'), 'type' => 'text', 'default' => 'Leave a review'],
                ],
            ],
            [
                'id' => 'from_price',
                'name' => __('From Price for Variable Products', 'polski'),
                'description' => __('Display "from {price}" instead of a price range for variable products. Cleaner presentation on archives and product pages.', 'polski'),
                'group' => 'Prices and Omnibus',
                'enabled' => true,
                'icon' => 'dashicons-tag',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_prices|from_price_enabled', 'label' => __('Enable "from" price display', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_prices|from_price_text', 'label' => __('Price text template', 'polski'), 'type' => 'text', 'default' => 'from {price}', 'hint' => __('Token: {price}', 'polski')],
                ],
            ],

            // === Stock & Cart ===
            [
                'id' => 'auto_restore_stock',
                'name' => __('Auto Restore Stock', 'polski'),
                'description' => __('Automatically restore product stock when orders are cancelled, refunded or failed. Keeps inventory accurate without manual intervention.', 'polski'),
                'group' => 'Stock & Cart',
                'enabled' => false,
                'icon' => 'dashicons-update',
                'links' => [],
                'settings' => [],
            ],
            [
                'id' => 'ajax_add_to_cart',
                'name' => __('AJAX Add to Cart', 'polski'),
                'description' => __('Add products to cart without page reload, including variable products on single product pages. Shows a toast notification on success.', 'polski'),
                'group' => 'Stock & Cart',
                'enabled' => false,
                'icon' => 'dashicons-cart',
                'links' => [],
                'settings' => [],
            ],

            // === Checkout ===
            [
                'id' => 'datalayer',
                'name' => __('GA4 DataLayer / GTM', 'polski'),
                'description' => __('Google Analytics 4 ecommerce tracking via dataLayer. Tracks view_item, add_to_cart, begin_checkout, purchase events. Supports GTM container and gtag.js.', 'polski'),
                'group' => 'Analytics',
                'enabled' => false,
                'icon' => 'dashicons-chart-bar',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_datalayer|gtm_container_id', 'label' => __('GTM Container ID', 'polski'), 'type' => 'text', 'default' => '', 'hint' => 'GTM-XXXXXXX'],
                    ['key' => 'polski_datalayer|ga4_measurement_id', 'label' => __('GA4 Measurement ID', 'polski'), 'type' => 'text', 'default' => '', 'hint' => 'G-XXXXXXXXXX'],
                    ['key' => 'polski_datalayer|use_sku_as_id', 'label' => __('Use SKU as product ID', 'polski'), 'type' => 'checkbox', 'default' => false, 'hint' => __('Send SKU instead of WooCommerce product ID in ecommerce events', 'polski')],
                ],
            ],
            [
                'id' => 'stock_export',
                'name' => __('Stock Export', 'polski'),
                'description' => __('Export WooCommerce product stock data as CSV. Configurable fields, stock threshold filters, variation support. Available under Products > Stock Export.', 'polski'),
                'group' => 'Tools',
                'enabled' => false,
                'icon' => 'dashicons-download',
                'links' => [
                    ['label' => __('Export stock', 'polski'), 'url' => admin_url('edit.php?post_type=product&page=polski-stock-export')],
                ],
                'settings' => [],
            ],
            [
                'id' => 'social_login',
                'name' => __('Social Login', 'polski'),
                'description' => __('Let customers register and login via Google or Facebook. Displays branded buttons on My Account, checkout, and WordPress login forms. Auto-creates WooCommerce customer accounts.', 'polski'),
                'group' => 'Storefront',
                'enabled' => false,
                'icon' => 'dashicons-share',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_social_login|google_enabled', 'label' => __('Enable Google login', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_social_login|google_client_id', 'label' => __('Google Client ID', 'polski'), 'type' => 'text', 'default' => '', 'hint' => __('From Google Cloud Console > APIs & Services > Credentials', 'polski')],
                    ['key' => 'polski_social_login|google_client_secret', 'label' => __('Google Client Secret', 'polski'), 'type' => 'text', 'default' => '', 'hint' => __('OAuth 2.0 Client Secret from the same credential', 'polski')],
                    ['key' => 'polski_social_login|facebook_enabled', 'label' => __('Enable Facebook login', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_social_login|facebook_app_id', 'label' => __('Facebook App ID', 'polski'), 'type' => 'text', 'default' => '', 'hint' => __('From Meta for Developers > App Dashboard > Settings > Basic', 'polski')],
                    ['key' => 'polski_social_login|facebook_app_secret', 'label' => __('Facebook App Secret', 'polski'), 'type' => 'text', 'default' => '', 'hint' => __('App Secret from the same page', 'polski')],
                    ['key' => 'polski_social_login|auto_register', 'label' => __('Auto-register new users', 'polski'), 'type' => 'checkbox', 'default' => true, 'hint' => __('Create WooCommerce accounts automatically on first social login', 'polski')],
                ],
            ],
            [
                'id' => 'product_authors',
                'name' => __('Product Authors', 'polski'),
                'description' => __('Custom taxonomy for product authors/creators. Adds author names on product pages and archives with Schema.org Person markup. Ideal for bookstores and publishers.', 'polski'),
                'group' => 'Storefront',
                'enabled' => false,
                'icon' => 'dashicons-admin-users',
                'links' => [],
                'settings' => [],
            ],
            [
                'id' => 'expert_reviews',
                'name' => __('Expert Reviews', 'polski'),
                'description' => __('Custom post type for editorial product reviews. Link expert reviews to products with ratings (1-10), verdicts, and Schema.org markup for SEO.', 'polski'),
                'group' => 'Storefront',
                'enabled' => false,
                'icon' => 'dashicons-star-filled',
                'links' => [
                    ['label' => __('Manage reviews', 'polski'), 'url' => admin_url('edit.php?post_type=expert_review')],
                ],
                'settings' => [],
            ],
            [
                'id' => 'social_proof',
                'name' => __('Social Proof Notifications', 'polski'),
                'description' => __('Floating purchase notifications showing recent orders ("Jan from Warszawa just bought..."). Proven to increase conversions by 10-15%. Privacy-aware, AJAX-loaded, configurable position and timing.', 'polski'),
                'group' => 'Storefront',
                'enabled' => false,
                'icon' => 'dashicons-megaphone',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_social_proof|display_interval', 'label' => __('Interval between popups (seconds)', 'polski'), 'type' => 'number', 'default' => 8, 'hint' => __('Time between showing consecutive notifications. Recommended: 6-12', 'polski')],
                    ['key' => 'polski_social_proof|display_duration', 'label' => __('Display duration (seconds)', 'polski'), 'type' => 'number', 'default' => 5, 'hint' => __('How long each notification stays visible. Recommended: 4-6', 'polski')],
                    ['key' => 'polski_social_proof|position', 'label' => __('Position', 'polski'), 'type' => 'text', 'default' => 'bottom-left', 'hint' => 'bottom-left, bottom-right, top-left, top-right'],
                    ['key' => 'polski_social_proof|anonymize_name', 'label' => __('Anonymize customer names', 'polski'), 'type' => 'checkbox', 'default' => false, 'hint' => __('Shows "J. from Warszawa" instead of full names. Recommended for GDPR', 'polski')],
                    ['key' => 'polski_social_proof|hide_on_mobile', 'label' => __('Hide on mobile devices', 'polski'), 'type' => 'checkbox', 'default' => false, 'hint' => __('Disable on small screens to avoid obstructing content', 'polski')],
                ],
            ],
            [
                'id' => 'product_qa',
                'name' => __('Product Q&A', 'polski'),
                'description' => __('Amazon-style questions and answers on product pages. Customers ask, anyone answers. Admin email notifications, answer voting, Schema.org QAPage markup for SEO.', 'polski'),
                'group' => 'Storefront',
                'enabled' => false,
                'icon' => 'dashicons-format-chat',
                'links' => [],
                'settings' => [],
            ],
            [
                'id' => 'trust_badges',
                'name' => __('Trust Badges', 'polski'),
                'description' => __('Configurable trust signals on product, cart, and checkout pages: secure payment, fast delivery, returns, quality guarantee. Pure CSS + inline SVG for zero performance impact.', 'polski'),
                'group' => 'Storefront',
                'enabled' => false,
                'icon' => 'dashicons-shield',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_trust_badges|show_on_product', 'label' => __('Show on product page', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_trust_badges|show_on_cart', 'label' => __('Show on cart', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_trust_badges|show_on_checkout', 'label' => __('Show on checkout', 'polski'), 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'live_cart',
                'name' => __('Live Cart Sidebar', 'polski'),
                'description' => __('Slide-in cart drawer that opens when a product is added to cart. Shows cart items, subtotal, free shipping progress bar, and quick checkout link. No page reload needed.', 'polski'),
                'group' => 'Storefront',
                'enabled' => false,
                'icon' => 'dashicons-cart',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_live_cart|auto_open', 'label' => __('Auto-open on add to cart', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_live_cart|show_subtotal', 'label' => __('Show subtotal', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_live_cart|show_shipping_notice', 'label' => __('Show free shipping progress', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_live_cart|free_shipping_threshold', 'label' => __('Free shipping threshold', 'polski'), 'type' => 'number', 'default' => 0, 'hint' => __('Set to 0 to disable progress bar', 'polski')],
                    ['key' => 'polski_live_cart|position', 'label' => __('Panel position', 'polski'), 'type' => 'select', 'default' => 'right', 'options' => ['right' => __('Right', 'polski'), 'left' => __('Left', 'polski')]],
                    ['key' => 'polski_live_cart|overlay', 'label' => __('Show background overlay', 'polski'), 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'price_history_chart',
                'name' => __('Price History Chart', 'polski'),
                'description' => __('Visual SVG sparkline showing price trends over 30/90/180 days on product pages. Uses Omnibus price data. Shows lowest/highest prices. Increases trust and Omnibus transparency.', 'polski'),
                'group' => 'Prices and Omnibus',
                'enabled' => false,
                'icon' => 'dashicons-chart-area',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_price_history|days', 'label' => __('History period (days)', 'polski'), 'type' => 'number', 'default' => 30, 'hint' => __('Show price data from the last N days. Options: 30, 90, or 180', 'polski')],
                    ['key' => 'polski_price_history|show_min_max', 'label' => __('Show min/max prices', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_price_history|color', 'label' => __('Line color', 'polski'), 'type' => 'text', 'default' => '#0369a1', 'hint' => __('CSS hex color for the chart line, e.g. #0369a1', 'polski')],
                ],
            ],
            [
                'id' => 'order_export',
                'name' => __('Order Export', 'polski'),
                'description' => __('Export WooCommerce orders to CSV with configurable fields, date range and status filters. 30+ exportable fields including products, customer data, and coupons.', 'polski'),
                'group' => 'Tools',
                'enabled' => false,
                'icon' => 'dashicons-media-spreadsheet',
                'links' => [
                    ['label' => __('Export orders', 'polski'), 'url' => admin_url('admin.php?page=polski-order-export')],
                ],
                'settings' => [],
            ],
            [
                'id' => 'faq',
                'name' => __('FAQ', 'polski'),
                'description' => __('FAQ custom post type with categories and accordion shortcode [polski_faq]. Includes Schema.org FAQPage structured data for SEO rich snippets.', 'polski'),
                'group' => 'Storefront',
                'enabled' => false,
                'icon' => 'dashicons-editor-help',
                'links' => [
                    ['label' => __('Manage FAQ', 'polski'), 'url' => admin_url('edit.php?post_type=polski_faq')],
                ],
                'settings' => [],
            ],
            [
                'id' => 'custom_checkout_fields',
                'name' => __('Custom Checkout Fields', 'polski'),
                'description' => __('Add, modify and reorder checkout fields. Supports text, textarea, select, checkbox, radio, number, email, date and phone types. Fields appear in admin, emails and My Account.', 'polski'),
                'group' => 'Checkout',
                'enabled' => false,
                'icon' => 'dashicons-forms',
                'links' => [
                    ['label' => __('Manage fields', 'polski'), 'url' => admin_url('admin.php?page=polski-checkout-fields')],
                ],
                'settings' => [],
            ],
        ];

        // Apply saved states.
        foreach ($modules as &$module) {
            if (isset($saved[$module['id']])) {
                $module['enabled'] = (bool) $saved[$module['id']];
            }
        }

        return $modules;
    }

    /**
     * Group modules into MoSCoW-priority buckets for both the modules table
     * and the per-bucket settings subpages. Returns a stable ordered map.
     *
     * @return array<string, array{label: string, modules: list<array<string, mixed>>}>
     */
    public function getBucketedModules(): array
    {
        $bucketed = [];
        foreach ($this->getModules() as $module) {
            $originalLabel = isset($module['group']) && is_string($module['group']) ? $module['group'] : '';
            $bucketKey = $this->remapGroup($originalLabel);
            $bucketed[$bucketKey][] = $module;
        }

        $ordered = [];
        foreach ($this->getGroupOrder() as $bucketKey) {
            if (! empty($bucketed[$bucketKey])) {
                $ordered[$bucketKey] = [
                    'label'   => $this->getGroupLabel($bucketKey),
                    'modules' => $bucketed[$bucketKey],
                ];
            }
        }

        return $ordered;
    }

    /**
     * Map a module to its bucket key so the modules table can build pencil
     * links to the correct settings subpage.
     */
    public function getBucketKeyForModule(array $module): string
    {
        $originalLabel = isset($module['group']) && is_string($module['group']) ? $module['group'] : '';
        return $this->remapGroup($originalLabel);
    }

    /**
     * Render the modules management page as a WP list-table grouped by MoSCoW-prioritised bucket.
     */
    public function render(): void
    {
        $this->renderToggleStyles();

        echo '<table class="wp-list-table widefat polski-modules-table">';
        echo '<thead><tr>';
        echo '<th class="polski-modules-col-name">' . esc_html__('Name', 'polski') . '</th>';
        echo '<th class="polski-modules-col-toggle">' . esc_html__('Enabled', 'polski') . '</th>';
        echo '<th class="polski-modules-col-desc">' . esc_html__('Description', 'polski') . '</th>';
        echo '<th class="polski-modules-col-actions"><span class="screen-reader-text">' . esc_html__('Actions', 'polski') . '</span></th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($this->getBucketedModules() as $bucketKey => $bucket) {
            echo '<tr class="polski-modules-group-header"><th colspan="4">' . esc_html($bucket['label']) . '</th></tr>';

            // Sub-group within a bucket by the module's own (finer) group label, so a
            // broad bucket like "Advanced & Tools" reads as clear sections (AI,
            // Tracking & Scripts, Data Export, Audit & Security, ...). Stable sort
            // (PHP 8.0+) keeps same-group modules contiguous.
            $modules = $bucket['modules'];
            usort($modules, static fn (array $a, array $b): int => strcasecmp(
                (string) ($a['group'] ?? ''),
                (string) ($b['group'] ?? ''),
            ));

            $lastSub = null;
            foreach ($modules as $module) {
                $sub = (string) ($module['group'] ?? '');
                $subLabel = $sub !== '' ? $this->getGroupDisplayLabel($sub) : '';
                // Skip when the sub-group label is empty, duplicates the previous one
                // (e.g. casing variants), or just repeats the bucket heading.
                if ($subLabel !== '' && $subLabel !== $bucket['label'] && $subLabel !== $lastSub) {
                    echo '<tr class="polski-modules-subgroup-header"><td colspan="4">' . esc_html($subLabel) . '</td></tr>';
                }
                $lastSub = $subLabel;
                $this->renderModuleRow($module, $bucketKey);
            }
        }

        echo '</tbody></table>';
    }

    /**
     * Render a single module row in the list-table, plus an optional settings details row.
     *
     * @param array<string, mixed> $module
     */
    private function renderModuleRow(array $module, string $bucketKey = ''): void
    {
        $id = $module['id'];
        $enabled = (bool) $module['enabled'];
        $hasSettings = ! empty($module['settings']);
        $rowClasses = 'polski-modules-row' . ($enabled ? ' polski-modules-row--active' : '');

        echo '<tr id="polski-module-' . esc_attr($id) . '" class="' . esc_attr($rowClasses) . '">';

        // --- Name column.
        echo '<td class="polski-modules-col-name">';
        echo '<div class="polski-modules-name-inner">';

        if (! empty($module['icon'])) {
            echo '<span class="dashicons ' . esc_attr($module['icon']) . '" aria-hidden="true"></span>';
        }

        echo '<span class="polski-modules-name-text">';
        echo '<strong>' . esc_html($module['name']) . '</strong>';

        $helpTooltip = $this->getModuleHelpTooltip($module);
        if ($helpTooltip !== '') {
            $helpPlain = wp_strip_all_tags($helpTooltip);
            $helpSrId = 'polski-help-sr-' . $id;
            echo '<span class="sp-card__help-wrap">';
            echo '<span id="' . esc_attr($helpSrId) . '" class="screen-reader-text">' . esc_html($helpPlain) . '</span>';
            echo '<button type="button" class="sp-card__help" aria-describedby="' . esc_attr($helpSrId) . '" aria-label="' . esc_attr__('Help', 'polski') . '">';
            echo '<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>';
            echo '</button>';
            echo '<span class="sp-card__help-tooltip" aria-hidden="true">' . esc_html($helpPlain) . '</span>';
            echo '</span>';
        }

        echo '</span>';
        echo '</div>';
        echo '</td>';

        // --- Toggle column.
        echo '<td class="polski-modules-col-toggle">';
        echo '<label class="sp-toggle">';
        printf(
            '<input type="checkbox" data-polski-module-id="%s" value="1" %s>',
            esc_attr($id),
            checked($enabled, true, false),
        );
        echo '<span class="sp-toggle__track"></span>';
        echo '<span class="sp-toggle__knob"></span>';
        echo '</label>';
        echo '</td>';

        // --- Description column.
        echo '<td class="polski-modules-col-desc">';
        echo '<span class="polski-modules-desc-text">' . esc_html($module['description']) . '</span>';

        if (! empty($module['links'])) {
            echo '<span class="polski-modules-links">';
            foreach ($module['links'] as $link) {
                if ($enabled) {
                    printf(
                        ' &middot; <a href="%s" target="_blank" rel="noopener">%s &rarr;</a>',
                        esc_url($link['url']),
                        esc_html($link['label']),
                    );
                } else {
                    // Module is off: its pages (e.g. a custom post type screen) are not
                    // registered yet, so show the action muted with a hint instead of a
                    // link that would 404.
                    printf(
                        ' &middot; <span class="polski-modules-link-disabled" title="%s">%s</span>',
                        esc_attr__('Enable this module to use this.', 'polski'),
                        esc_html($link['label']),
                    );
                }
            }
            echo '</span>';
        }

        echo '</td>';

        // --- Actions column (edit pencil + docs).
        echo '<td class="polski-modules-col-actions">';

        if ($hasSettings) {
            $bucket = $bucketKey !== '' ? $bucketKey : $this->getBucketKeyForModule($module);
            $settingsUrl = admin_url(
                'admin.php?page=polski-group-' . $bucket . '#polski-module-' . $id
            );
            printf(
                '<a href="%s" class="button-link polski-modules-edit" aria-label="%s" title="%s"><span class="dashicons dashicons-edit" aria-hidden="true"></span></a>',
                esc_url($settingsUrl),
                esc_attr(sprintf(
                    /* translators: %s: module name */
                    __('Edit %s settings', 'polski'),
                    $module['name'],
                )),
                esc_attr__('Edit settings', 'polski'),
            );
        }

        $docsUrl = isset($module['docs_url']) && is_string($module['docs_url']) && $module['docs_url'] !== ''
            ? $module['docs_url']
            : $this->getModuleDocumentationUrl($id);
        printf(
            ' <a href="%s" target="_blank" rel="noopener noreferrer" class="button-link polski-modules-docs" aria-label="%s" title="%s"><span class="dashicons dashicons-book" aria-hidden="true"></span></a>',
            esc_url($docsUrl),
            esc_attr__('Open documentation', 'polski'),
            esc_attr__('Open documentation', 'polski'),
        );

        echo '</td>';

        echo '</tr>';
    }

    /**
     * Render a single module's settings form on a dedicated page (used by per-bucket subpages).
     *
     * @param array<string, mixed> $module
     */
    public function renderModuleSettingsForm(array $module): void
    {
        if (empty($module['settings'])) {
            return;
        }

        $id = $module['id'];
        echo '<div class="polski-modules-settings-panel">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('polski_save_module_' . $id, '_polski_module_nonce_' . $id);
        echo '<input type="hidden" name="action" value="polski_save_module_settings" />';
        echo '<input type="hidden" name="module_id" value="' . esc_attr($id) . '" />';

        foreach ($module['settings'] as $field) {
            $this->renderSettingsField($field);
        }

        echo '<p class="submit" style="margin:12px 0 0;">';
        printf(
            '<button type="submit" class="button button-primary button-small">%s</button>',
            esc_html__('Save', 'polski'),
        );
        echo '</p>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * Render a single settings field within a module card.
     *
     * @param array<string, mixed> $field
     */
    public function renderSettingsField(array $field, bool $tableRow = false): void
    {
        $type = $field['type'] ?? 'text';
        $key = $field['key'] ?? '';
        $label = $field['label'] ?? '';
        $hint = $field['hint'] ?? '';

        // HTML type - raw output.
        if ($type === 'html') {
            echo '<div style="margin-bottom:8px;">' . wp_kses_post($field['html'] ?? '') . '</div>';
            return;
        }

        // Parse key format: "option_name|field_key"
        [$optionName, $fieldKey] = explode('|', $key, 2) + ['', ''];

        // Get current value.
        $options = get_option($optionName, []);
        $options = is_array($options) ? $options : [];
        $currentValue = $options[$fieldKey] ?? ($field['default'] ?? '');
        $inputName = "polski_setting[{$optionName}][{$fieldKey}]";

        if ($tableRow) {
            echo '<tr>';
            echo '<th scope="row">' . esc_html($label) . '</th>';
            echo '<td>';
        } else {
            echo '<div style="margin-bottom:10px;">';
        }

        if ($type === 'checkbox') {
            echo '<label style="display:flex;align-items:center;gap:6px;font-size:13px;">';
            printf(
                '<input type="checkbox" name="%s" value="1" %s>',
                esc_attr($inputName),
                checked($currentValue, true, false),
            );
            echo esc_html($label);
            echo '</label>';
        } else {
            if ($label !== '') {
                echo '<label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px;">' . esc_html($label) . '</label>';
            }

            if ($type === 'text') {
                printf(
                    '<input type="text" name="%s" value="%s" class="regular-text" style="width:100%%;font-size:12px;">',
                    esc_attr($inputName),
                    esc_attr((string) $currentValue),
                );
            } elseif ($type === 'email') {
                printf(
                    '<input type="email" name="%s" value="%s" class="regular-text" style="width:100%%;font-size:12px;">',
                    esc_attr($inputName),
                    esc_attr((string) $currentValue),
                );
            } elseif ($type === 'number') {
                printf(
                    '<input type="number" name="%s" value="%s" class="small-text" style="width:80px;">',
                    esc_attr($inputName),
                    esc_attr((string) $currentValue),
                );
            } elseif ($type === 'textarea') {
                printf(
                    '<textarea name="%s" rows="3" style="width:100%%;font-size:12px;">%s</textarea>',
                    esc_attr($inputName),
                    esc_textarea((string) $currentValue),
                );
            } elseif ($type === 'select') {
                echo '<select name="' . esc_attr($inputName) . '" style="font-size:12px;">';
                foreach (($field['options'] ?? []) as $val => $optLabel) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($val),
                        selected($currentValue, $val, false),
                        esc_html($optLabel),
                    );
                }
                echo '</select>';
            } elseif ($type === 'delivery_time_select') {
                $terms = get_terms(['taxonomy' => 'polski_delivery_time', 'hide_empty' => false]);
                echo '<select name="' . esc_attr($inputName) . '" style="font-size:12px;">';
                echo '<option value="">-- ' . esc_html__('none', 'polski') . ' --</option>';
                if (is_array($terms)) {
                    foreach ($terms as $term) {
                        if ($term instanceof \WP_Term) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr((string) $term->term_id),
                                selected($currentValue, (string) $term->term_id, false),
                                esc_html($term->name),
                            );
                        }
                    }
                }
                echo '</select>';
            } elseif ($type === 'integration_repeater') {
                $this->renderIntegrationRepeater($inputName, (string) $currentValue);
            } elseif ($type === 'trigger_repeater') {
                $this->renderTriggerRepeater($inputName, (string) $currentValue);
            }
        }

        if ($hint !== '') {
            echo '<p class="description">' . esc_html($hint) . '</p>';
        }

        if ($tableRow) {
            echo '</td></tr>';
        } else {
            echo '</div>';
        }
    }

    /**
     * Consent category <option> list shared by the repeater editors.
     *
     * @return array<string, string>
     */
    private function consentCategoryOptions(): array
    {
        return [
            'necessary' => __('Necessary', 'polski'),
            'analytics' => __('Analytics', 'polski'),
            'marketing' => __('Marketing', 'polski'),
            'preferences' => __('Preferences', 'polski'),
        ];
    }

    /**
     * Decode a repeater JSON string into a list of associative rows.
     *
     * @return list<array<string, mixed>>
     */
    private function decodeRepeater(string $json): array
    {
        $decoded = $json !== '' ? json_decode($json, true) : [];

        if (! is_array($decoded)) {
            return [];
        }

        $rows = [];
        foreach ($decoded as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Render the Custom Integrations repeatable editor. Each row carries a label,
     * placement, consent category and code; the rows are serialised to a single
     * hidden JSON field on submit by an inline controller.
     */
    private function renderIntegrationRepeater(string $inputName, string $json): void
    {
        $rows = $this->decodeRepeater($json);
        $categories = $this->consentCategoryOptions();
        $uid = 'polski-ci-' . wp_rand();

        echo '<div class="polski-repeater" data-polski-repeater="integration" id="' . esc_attr($uid) . '">';
        echo '<input type="hidden" name="' . esc_attr($inputName) . '" value="" data-polski-repeater-store>';
        echo '<div data-polski-repeater-rows>';

        if ($rows === []) {
            $rows[] = ['label' => '', 'placement' => 'head', 'category' => 'necessary', 'code' => ''];
        }

        foreach ($rows as $row) {
            $this->renderIntegrationRow($row, $categories);
        }

        echo '</div>';
        printf(
            '<button type="button" class="button button-small" data-polski-repeater-add>%s</button>',
            esc_html__('Add snippet', 'polski'),
        );
        echo '</div>';

        $this->printRepeaterScript($uid);
    }

    /**
     * @param array<string, mixed>  $row
     * @param array<string, string> $categories
     */
    private function renderIntegrationRow(array $row, array $categories): void
    {
        $label = isset($row['label']) ? (string) $row['label'] : '';
        $placement = (isset($row['placement']) && $row['placement'] === 'footer') ? 'footer' : 'head';
        $category = isset($row['category']) ? (string) $row['category'] : 'necessary';
        $code = isset($row['code']) ? (string) $row['code'] : '';

        echo '<div class="polski-repeater-row" data-polski-repeater-row style="border:1px solid #dcdcde;padding:8px;margin-bottom:8px;border-radius:4px;">';

        printf(
            '<input type="text" data-f="label" placeholder="%s" value="%s" style="width:100%%;font-size:12px;margin-bottom:4px;">',
            esc_attr__('Label', 'polski'),
            esc_attr($label),
        );

        echo '<div style="display:flex;gap:6px;margin-bottom:4px;">';

        echo '<select data-f="placement" style="font-size:12px;">';
        printf('<option value="head" %s>%s</option>', selected($placement, 'head', false), esc_html__('Head', 'polski'));
        printf('<option value="footer" %s>%s</option>', selected($placement, 'footer', false), esc_html__('Footer', 'polski'));
        echo '</select>';

        echo '<select data-f="category" style="font-size:12px;">';
        foreach ($categories as $val => $catLabel) {
            printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($category, $val, false), esc_html($catLabel));
        }
        echo '</select>';

        echo '</div>';

        printf(
            '<textarea data-f="code" rows="3" placeholder="%s" style="width:100%%;font-size:12px;font-family:monospace;">%s</textarea>',
            esc_attr__('<script>...</script> or inline JS', 'polski'),
            esc_textarea($code),
        );

        printf(
            '<button type="button" class="button-link" data-polski-repeater-remove style="color:#b32d2e;font-size:12px;">%s</button>',
            esc_html__('Remove', 'polski'),
        );

        echo '</div>';
    }

    /**
     * Render the Custom Triggers repeatable editor.
     */
    private function renderTriggerRepeater(string $inputName, string $json): void
    {
        $rows = $this->decodeRepeater($json);
        $categories = $this->consentCategoryOptions();
        $uid = 'polski-ct-' . wp_rand();

        echo '<div class="polski-repeater" data-polski-repeater="trigger" id="' . esc_attr($uid) . '">';
        echo '<input type="hidden" name="' . esc_attr($inputName) . '" value="" data-polski-repeater-store>';
        echo '<div data-polski-repeater-rows>';

        if ($rows === []) {
            $rows[] = ['event' => '', 'condition' => 'page_url', 'value' => '', 'selector' => '', 'category' => 'necessary'];
        }

        foreach ($rows as $row) {
            $this->renderTriggerRow($row, $categories);
        }

        echo '</div>';
        printf(
            '<button type="button" class="button button-small" data-polski-repeater-add>%s</button>',
            esc_html__('Add trigger', 'polski'),
        );
        echo '</div>';

        $this->printRepeaterScript($uid);
    }

    /**
     * @param array<string, mixed>  $row
     * @param array<string, string> $categories
     */
    private function renderTriggerRow(array $row, array $categories): void
    {
        $event = isset($row['event']) ? (string) $row['event'] : '';
        $condition = (isset($row['condition']) && $row['condition'] === 'click') ? 'click' : 'page_url';
        $value = isset($row['value']) ? (string) $row['value'] : '';
        $selector = isset($row['selector']) ? (string) $row['selector'] : '';
        $category = isset($row['category']) ? (string) $row['category'] : 'necessary';

        echo '<div class="polski-repeater-row" data-polski-repeater-row style="border:1px solid #dcdcde;padding:8px;margin-bottom:8px;border-radius:4px;">';

        printf(
            '<input type="text" data-f="event" placeholder="%s" value="%s" style="width:100%%;font-size:12px;margin-bottom:4px;">',
            esc_attr__('Event name (e.g. cta_click)', 'polski'),
            esc_attr($event),
        );

        echo '<div style="display:flex;gap:6px;margin-bottom:4px;">';

        echo '<select data-f="condition" style="font-size:12px;">';
        printf('<option value="page_url" %s>%s</option>', selected($condition, 'page_url', false), esc_html__('URL contains', 'polski'));
        printf('<option value="click" %s>%s</option>', selected($condition, 'click', false), esc_html__('Click on selector', 'polski'));
        echo '</select>';

        echo '<select data-f="category" style="font-size:12px;">';
        foreach ($categories as $val => $catLabel) {
            printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($category, $val, false), esc_html($catLabel));
        }
        echo '</select>';

        echo '</div>';

        printf(
            '<input type="text" data-f="value" placeholder="%s" value="%s" style="width:100%%;font-size:12px;margin-bottom:4px;">',
            esc_attr__('URL fragment (for URL contains)', 'polski'),
            esc_attr($value),
        );
        printf(
            '<input type="text" data-f="selector" placeholder="%s" value="%s" style="width:100%%;font-size:12px;margin-bottom:4px;">',
            esc_attr__('CSS selector (for click)', 'polski'),
            esc_attr($selector),
        );

        printf(
            '<button type="button" class="button-link" data-polski-repeater-remove style="color:#b32d2e;font-size:12px;">%s</button>',
            esc_html__('Remove', 'polski'),
        );

        echo '</div>';
    }

    /**
     * Inline controller that clones rows, removes rows, and serialises the
     * repeater into its hidden JSON field on form submit. Scoped by container id.
     */
    private function printRepeaterScript(string $uid): void
    {
        $script = <<<JS
(function(){
    var root=document.getElementById({$this->jsString($uid)});
    if(!root||root.dataset.polskiBound){return;}
    root.dataset.polskiBound='1';
    var rows=root.querySelector('[data-polski-repeater-rows]');
    var store=root.querySelector('[data-polski-repeater-store]');
    var form=root.closest('form');
    function template(){
        var first=rows.querySelector('[data-polski-repeater-row]');
        var clone=first.cloneNode(true);
        clone.querySelectorAll('[data-f]').forEach(function(el){
            if(el.tagName==='SELECT'){el.selectedIndex=0;}else{el.value='';}
        });
        return clone;
    }
    root.addEventListener('click',function(e){
        var add=e.target.closest('[data-polski-repeater-add]');
        if(add){e.preventDefault();rows.appendChild(template());return;}
        var rm=e.target.closest('[data-polski-repeater-remove]');
        if(rm){e.preventDefault();var r=rm.closest('[data-polski-repeater-row]');
            if(rows.querySelectorAll('[data-polski-repeater-row]').length>1){r.remove();}
            else{r.querySelectorAll('[data-f]').forEach(function(el){if(el.tagName==='SELECT'){el.selectedIndex=0;}else{el.value='';}});}
        }
    });
    function serialise(){
        var out=[];
        rows.querySelectorAll('[data-polski-repeater-row]').forEach(function(row){
            var o={};
            row.querySelectorAll('[data-f]').forEach(function(el){o[el.getAttribute('data-f')]=el.value;});
            var empty=Object.keys(o).every(function(k){return String(o[k]).trim()==='';});
            if(!empty){out.push(o);}
        });
        store.value=JSON.stringify(out);
    }
    if(form){form.addEventListener('submit',serialise);}
})();
JS;

        wp_print_inline_script_tag($script);
    }

    /**
     * JSON-encode a string for safe inlining inside a script (quotes included).
     */
    private function jsString(string $value): string
    {
        return (string) wp_json_encode($value);
    }

    /**
     * AJAX: persist a single module enabled state (toggle). No separate Save button.
     */
    public function ajaxToggleModule(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }

        check_ajax_referer('polski_modules', 'nonce');

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $moduleId = isset($_POST['module_id']) ? sanitize_key((string) wp_unslash($_POST['module_id'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $enabled = isset($_POST['enabled']) && (string) $_POST['enabled'] === '1';

        if ($moduleId === '') {
            wp_send_json_error(['message' => 'invalid'], 400);
        }

        $modules = $this->getModules();
        $validIds = array_column($modules, 'id');
        if (! in_array($moduleId, $validIds, true)) {
            wp_send_json_error(['message' => 'invalid'], 400);
        }

        $saved = get_option(self::OPTION, []);
        $saved = is_array($saved) ? $saved : [];
        $saved[$moduleId] = $enabled;
        update_option(self::OPTION, $saved);

        CacheHelper::flush();

        wp_send_json_success(['enabled' => $enabled]);
    }

    /**
     * Handle per-module settings form submission (Save button on that module card only).
     */
    public function handleSaveModuleSettings(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Sorry, you are not allowed to access this page.', 'polski'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $moduleId = isset($_POST['module_id']) ? sanitize_key((string) wp_unslash($_POST['module_id'])) : '';

        if ($moduleId === '') {
            wp_die(esc_html__('Invalid request.', 'polski'));
        }

        check_admin_referer('polski_save_module_' . $moduleId, '_polski_module_nonce_' . $moduleId);

        $modules = $this->getModules();
        $module = null;

        foreach ($modules as $m) {
            if ($m['id'] === $moduleId) {
                $module = $m;
                break;
            }
        }

        if ($module === null || empty($module['settings'])) {
            wp_die(esc_html__('Invalid request.', 'polski'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $settingsData = isset($_POST['polski_setting']) ? wp_unslash($_POST['polski_setting']) : [];
        $settingsData = is_array($settingsData) ? $settingsData : [];

        // Group fields by option name; only keys defined for this module.
        $byOption = [];

        foreach ($module['settings'] as $field) {
            if (! is_array($field) || empty($field['key'])) {
                continue;
            }

            [$optName, $fKey] = explode('|', (string) $field['key'], 2) + ['', ''];

            if ($optName === '' || $fKey === '') {
                continue;
            }

            $byOption[$optName][] = ['field' => $field, 'fieldKey' => $fKey];
        }

        foreach ($byOption as $optionName => $items) {
            $existing = get_option($optionName, []);
            $existing = is_array($existing) ? $existing : [];

            foreach ($items as $item) {
                $field = $item['field'];
                $fKey = $item['fieldKey'];

                if (isset($settingsData[$optionName][$fKey])) {
                    $existing[$fKey] = $this->sanitizeFieldValue($settingsData[$optionName][$fKey], $field);
                } elseif (($field['type'] ?? '') === 'checkbox') {
                    $existing[$fKey] = false;
                }
            }

            update_option($optionName, $existing);
        }

        CacheHelper::flush();

        wp_safe_redirect(admin_url('admin.php?page=polski&tab=modules&modules_saved=1'));
        exit;
    }

    /**
     * @return array<string, bool>
     */
    public static function getDefaultModuleStates(): array
    {
        return [
            'unit_price' => true,
            'omnibus' => true,
            'tax_display' => true,
            'oss_observer' => false,
            'delivery_time' => true,
            'shipping_notice' => true,
            'checkout_button' => true,
            'legal_checkboxes' => true,
            'consent_logging' => true,
            'consent_manager' => false,
            'legal_pages' => true,
            'withdrawal' => true,
            'dispute_resolution' => true,
            'email_attachments' => true,
            'manufacturer' => true,
            'food_module' => false,
            'power_supply' => false,
            'double_opt_in' => false,
            'ajax_search' => false,
            'brands' => false,
            'ajax_filters' => false,
            'wishlist' => false,
            'compare' => false,
            'quick_view' => false,
            'badge_management' => false,
            'tab_manager' => false,
            'featured_video' => false,
            'gallery_zoom' => false,
            'product_slider_carousel' => false,
            'waitlist' => false,
            'infinite_scroll' => false,
            'popup' => false,
            'schema_org' => true,
            'checkout_toolkit_integration' => true,
            'gpsr' => true,
            'verified_review' => false,
            'green_claims' => false,
            'dsa_toolkit' => false,
            'ksef_ready' => false,
            'security_incidents' => true,
            'store_health' => false,
            'site_audit' => true,
            'plugin_data' => true,
            'cra_readiness' => false,
            'dpa_tracker' => false,
            'nip_lookup' => false,
            'minimum_order' => false,
            'review_requests' => false,
            'from_price' => true,
            'auto_restore_stock' => false,
            'ajax_add_to_cart' => false,
            'datalayer' => false,
            'tracking_tags' => false,
            'safe_fonts' => false,
            'custom_integrations' => false,
            'custom_triggers' => false,
            'stock_export' => false,
            'expert_reviews' => false,
            'social_login' => false,
            'product_authors' => false,
            'order_export' => false,
            'faq' => false,
            'social_proof' => false,
            'product_qa' => false,
            'trust_badges' => false,
            'live_cart' => false,
            'price_history_chart' => false,
            'custom_checkout_fields' => false,
            // Extended commerce modules.
            'fulfillment' => false,
            'delivery_date' => false,
            'abandoned_carts' => false,
            'gift_cards' => false,
            'subscriptions' => false,
            'affiliates' => false,
            'pre_order' => false,
            'product_bundles' => false,
            'product_add_ons' => false,
            'frequently_bought_together' => false,
            'catalog_mode' => false,
            'ai_descriptions' => false,
            'inventory_forecast' => false,
            'customer_insights' => false,
            'tax_rules' => false,
            'page_compliance' => true,
            'sbom' => false,
            'business_info' => false,
            'complaint_template' => false,
            'copyright_notice' => false,
            'rodo_training_docs' => false,
        ];
    }

    /**
     * Check if a module is enabled.
     */
    public static function isModuleEnabled(string $moduleId): bool
    {
        $saved = get_option(self::OPTION, []);

        if (! is_array($saved) || ! isset($saved[$moduleId])) {
            $defaults = self::getDefaultModuleStates();

            return $defaults[$moduleId] ?? false;
        }

        return (bool) $saved[$moduleId];
    }

    /**
     * Enqueue CSS and JS for toggle switches on the Polski Modules screen.
     */
    /**
     * MoSCoW-prioritised bucket keys in display order.
     *
     * @return list<string>
     */
    private function getGroupOrder(): array
    {
        return [
            'legal',
            'tax_pricing',
            'checkout_orders',
            'content_trust',
            'advanced_tools',
        ];
    }

    /**
     * Localised label for a bucket key.
     */
    private function getGroupLabel(string $bucketKey): string
    {
        switch ($bucketKey) {
            case 'legal':
                return __('Legal & Compliance', 'polski');
            case 'tax_pricing':
                return __('Tax & Pricing', 'polski');
            case 'checkout_orders':
                return __('Checkout & Orders', 'polski');
            case 'content_trust':
                return __('Content & Trust', 'polski');
            case 'advanced_tools':
                return __('Advanced & Tools', 'polski');
            default:
                return __('Other', 'polski');
        }
    }

    /**
     * Translate a module's (English, locale-stable) group label for display as a
     * sub-group header. Kept as explicit __() calls so the strings stay in the POT
     * even though the module definitions store the stable English key.
     */
    private function getGroupDisplayLabel(string $group): string
    {
        switch ($group) {
            case 'Prices and Display':
                return __('Prices and Display', 'polski');
            case 'Prices and Omnibus':
                return __('Prices and Omnibus', 'polski');
            case 'Checkout and Orders':
                return __('Checkout and Orders', 'polski');
            case 'Checkout':
                return __('Checkout', 'polski');
            case 'Email':
                return __('Email', 'polski');
            case 'Stock & Cart':
                return __('Stock & Cart', 'polski');
            case 'Merchandising':
                return __('Merchandising', 'polski');
            case 'Storefront':
                return __('Storefront', 'polski');
            case 'Product Information':
                return __('Product Information', 'polski');
            case 'Customer Account':
                return __('Customer Account', 'polski');
            case 'Sales and B2B':
                return __('Sales and B2B', 'polski');
            case 'Consumer Rights':
            case 'Consumer rights':
                return __('Consumer Rights', 'polski');
            case 'Legal & Compliance':
                return __('Legal & Compliance', 'polski');
            case 'Tools':
                return __('Tools', 'polski');
            case 'Analytics':
                return __('Analytics', 'polski');
            case 'SEO & Optimization':
                return __('SEO & Optimization', 'polski');
            case 'Integrations':
                return __('Integrations', 'polski');
            case 'Advanced & Tools':
                return __('Advanced & Tools', 'polski');
            default:
                return $group;
        }
    }

    /**
     * Map legacy group labels (various casings, multiple synonyms) to one of the MoSCoW buckets.
     */
    private function remapGroup(string $originalLabel): string
    {
        $normalised = strtolower(trim($originalLabel));

        $map = [
            // Legal & Compliance.
            'consumer rights'        => 'legal',
            'legal'                  => 'legal',
            'legal & compliance'     => 'legal',
            'compliance'             => 'legal',
            // Tax & Pricing.
            'prices and display'     => 'tax_pricing',
            'prices and omnibus'     => 'tax_pricing',
            'tax & pricing'          => 'tax_pricing',
            'pricing'                => 'tax_pricing',
            // Checkout & Orders.
            'checkout'               => 'checkout_orders',
            'checkout and orders'    => 'checkout_orders',
            'checkout & orders'      => 'checkout_orders',
            'email'                  => 'checkout_orders',
            'emails'                 => 'checkout_orders',
            'stock & cart'           => 'checkout_orders',
            'stock and cart'         => 'checkout_orders',
            // Content & Trust.
            'product information'    => 'content_trust',
            'storefront'             => 'content_trust',
            'merchandising'          => 'content_trust',
            'sales and b2b'          => 'content_trust',
            'sales & b2b'            => 'content_trust',
            'customer account'      => 'content_trust',
            'content & trust'        => 'content_trust',
            // Advanced & Tools.
            'analytics'              => 'advanced_tools',
            'seo & optimization'     => 'advanced_tools',
            'seo and optimization'   => 'advanced_tools',
            'integrations'           => 'advanced_tools',
            'tools'                  => 'advanced_tools',
            'advanced'               => 'advanced_tools',
            'advanced & tools'       => 'advanced_tools',
        ];

        return $map[$normalised] ?? 'advanced_tools';
    }

    private function renderToggleStyles(): void
    {
        wp_enqueue_style(
            'polski-admin-modules',
            plugins_url('assets/css/admin-modules.css', \Polski\PLUGIN_FILE),
            ['polski-brand'],
            \Polski\VERSION,
        );

        wp_enqueue_script(
            'polski-admin-modules',
            plugins_url('assets/js/admin-modules.js', \Polski\PLUGIN_FILE),
            [],
            \Polski\VERSION,
            true,
        );

        wp_localize_script('polski-admin-modules', 'polskiModules', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('polski_modules'),
            'errorGeneric' => __('Could not save module state. Please try again.', 'polski'),
        ]);
    }

    /**
     * Get HTML showing Omnibus plugin integration status.
     */
    private function getOmnibusIntegrationStatus(): string
    {
        $generalSettings = get_option('polski_general', []);
        $generalSettings = is_array($generalSettings) ? $generalSettings : [];

        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = [
            ['file' => 'wc-price-history/wc-price-history.php', 'name' => __('Compatible Omnibus Extension A', 'polski')],
            ['file' => 'omnibus/omnibus.php', 'name' => __('Compatible Omnibus Extension B', 'polski')],
        ];

        $html = '<div style="font-size:12px;">';

        $anyActive = false;

        foreach ($plugins as $plugin) {
            $active = is_plugin_active($plugin['file']);
            $icon = $active ? '<span style="color:#46b450;">&#10003;</span>' : '<span style="color:#999;">&#8212;</span>';
            $status = $active
                ? (string) ($generalSettings['admin_omnibus_plugin_detected_text'] ?? __('detected, data synchronized', 'polski'))
                : (string) ($generalSettings['admin_omnibus_plugin_missing_text'] ?? __('not installed', 'polski'));

            if ($active) {
                $anyActive = true;
            }

            $html .= sprintf(
                '<div style="margin-bottom:4px;">%s %s - <em>%s</em></div>',
                $icon,
                esc_html($plugin['name']),
                esc_html($status),
            );
        }

        if (! $anyActive) {
            $html .= '<div style="margin-top:6px;color:#666;">' . esc_html((string) ($generalSettings['admin_omnibus_no_external_text'] ?? __('No external Omnibus plugin is installed. Polski uses the built-in price tracking system.', 'polski'))) . '</div>';
        } else {
            $html .= '<div style="margin-top:6px;color:#46b450;">' . esc_html((string) ($generalSettings['admin_omnibus_external_active_text'] ?? __('External plugin detected. Polski uses its data instead of the built-in system.', 'polski'))) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Live-status panel shown inside the OSS observer module settings row.
     *
     * Mirrors the Germanized "OSS plugin is missing" note pattern: when the
     * external plugin is inactive we render an install CTA; when active we
     * confirm detection + link to the OSS settings tab.
     */
    private function getOssStatusHtml(): string
    {
        $service = $this->getOssObserverService();

        if ($service === null) {
            return '<div style="font-size:12px;color:#666;">' . esc_html__('OSS observer service is unavailable.', 'polski') . '</div>';
        }

        $html = '<div style="font-size:13px;line-height:1.6;">';

        if ($service->needsInstall()) {
            $html .= '<p style="margin:0 0 10px;color:#1d2327;">';
            $html .= esc_html__('The One Stop Shop plugin is not installed. Install it to start observing the €10,000 intra-EU B2C threshold automatically.', 'polski');
            $html .= '</p>';

            if (current_user_can('install_plugins')) {
                $html .= '<p style="margin:0;">';
                $html .= '<a href="' . esc_url($service->getInstallUrl()) . '" class="button button-primary button-small">';
                $html .= esc_html__('Install One Stop Shop', 'polski');
                $html .= '</a>';
                $html .= '</p>';
            } else {
                $html .= '<p style="margin:0;color:#d63638;">';
                $html .= esc_html__('You need the install_plugins capability to install it from here.', 'polski');
                $html .= '</p>';
            }
        } else {
            $ossActive = $service->isOssEnabled();
            $autoObserver = $service->isAutoObserverEnabled();

            $html .= '<p style="margin:0 0 6px;color:#2271b1;">';
            $html .= '<span class="dashicons dashicons-yes-alt" style="color:#46b450;vertical-align:text-bottom;"></span> ';
            $html .= esc_html__('One Stop Shop plugin detected.', 'polski');
            $html .= '</p>';

            $html .= '<p style="margin:0 0 4px;">';
            $html .= '<strong>' . esc_html__('OSS procedure', 'polski') . ':</strong> ';
            $html .= $ossActive
                ? '<em>' . esc_html__('enabled', 'polski') . '</em>'
                : '<em style="color:#646970;">' . esc_html__('disabled', 'polski') . '</em>';
            $html .= '</p>';

            $html .= '<p style="margin:0 0 10px;">';
            $html .= '<strong>' . esc_html__('Auto observer', 'polski') . ':</strong> ';
            $html .= $autoObserver
                ? '<em>' . esc_html__('watching threshold', 'polski') . '</em>'
                : '<em style="color:#646970;">' . esc_html__('off', 'polski') . '</em>';
            $html .= '</p>';

            $html .= '<p style="margin:0;">';
            $html .= '<a href="' . esc_url($service->getSettingsUrl()) . '" class="button button-small">';
            $html .= esc_html__('Open OSS settings', 'polski');
            $html .= '</a>';
            $html .= '</p>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Resolve OssObserverService from the plugin container.
     */
    private function getOssObserverService(): ?\Polski\Service\OssObserverService
    {
        if (! class_exists(\Polski\Plugin::class) || ! class_exists(\Polski\Service\OssObserverService::class)) {
            return null;
        }

        try {
            $plugin = \Polski\Plugin::instance();
            $container = $plugin->container();
            if ($container->has(\Polski\Service\OssObserverService::class)) {
                /** @var \Polski\Service\OssObserverService $service */
                $service = $container->get(\Polski\Service\OssObserverService::class);
                return $service;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    private function getCheckoutToolkitStatus(): string
    {
        $generalSettings = get_option('polski_general', []);
        $generalSettings = is_array($generalSettings) ? $generalSettings : [];

        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = [
            ['file' => 'flexible-checkout-fields/flexible-checkout-fields.php', 'name' => 'Flexible Checkout Fields'],
            ['file' => 'flexible-cookies/flexible-cookies.php', 'name' => 'Flexible Cookies'],
            ['file' => 'gpsr-for-woocommerce/gpsr-for-woocommerce.php', 'name' => 'GPSR for WooCommerce'],
        ];

        $html = '<div style="font-size:12px;">';
        $anyActive = false;

        foreach ($plugins as $plugin) {
            $active = is_plugin_active($plugin['file']);
            $icon = $active ? '<span style="color:#46b450;">&#10003;</span>' : '<span style="color:#999;">&#8212;</span>';

            if ($active) {
                $anyActive = true;
                $statusHtml = '<em>' . esc_html((string) ($generalSettings['admin_integration_detected_text'] ?? __('detected, integration active', 'polski'))) . '</em>';
            } else {
                $installUrl = admin_url('plugin-install.php?s=' . urlencode($plugin['name']) . '&tab=search&type=term');
                $statusHtml = '<a href="' . esc_url($installUrl) . '">' . esc_html__('install', 'polski') . '</a>';
            }

            $html .= sprintf(
                '<div style="margin-bottom:4px;">%s %s - %s</div>',
                $icon,
                esc_html($plugin['name']),
                $statusHtml,
            );
        }

        if (! $anyActive) {
            $html .= '<div style="margin-top:6px;color:#666;">' . esc_html((string) ($generalSettings['admin_checkout_toolkit_no_external_text'] ?? __('No supported checkout and cookies extensions detected. Polski continues to work independently.', 'polski'))) . '</div>';
        } else {
            $html .= '<div style="margin-top:6px;color:#46b450;">' . esc_html((string) ($generalSettings['admin_checkout_toolkit_external_active_text'] ?? __('Supported checkout, cookies, or product data extensions detected. Polski can adjust integration to the active set.', 'polski'))) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param mixed                     $value
     * @param array<string, mixed>|null $field
     * @return mixed
     */
    private function sanitizeFieldValue(mixed $value, ?array $field): mixed
    {
        $type = $field['type'] ?? 'text';

        return match ($type) {
            'checkbox' => (bool) $value,
            'number' => is_numeric($value) ? $value + 0 : 0,
            'textarea' => sanitize_textarea_field((string) $value),
            'email' => sanitize_email((string) $value),
            'integration_repeater' => $this->sanitizeIntegrationRepeater((string) $value),
            'trigger_repeater' => $this->sanitizeTriggerRepeater((string) $value),
            'select', 'delivery_time_select', 'text' => sanitize_text_field((string) $value),
            default => is_string($value) ? sanitize_text_field($value) : $value,
        };
    }

    /**
     * Sanitise the Custom Integrations repeater JSON. Each row keeps a plain-text
     * label, a constrained placement and consent category, and merchant code.
     *
     * The code is stored verbatim (it is the snippet the merchant chose to run on
     * their own site, saved behind a capability + nonce check) but normalised
     * with wp_check_invalid_utf8 to drop invalid byte sequences. It is never
     * executed in PHP; on the front end it is emitted as a consent-gated
     * text/plain placeholder.
     */
    private function sanitizeIntegrationRepeater(string $json): string
    {
        $rows = $this->decodeRepeater($json);
        $clean = [];

        $validCategories = ['necessary', 'analytics', 'marketing', 'preferences'];

        foreach ($rows as $row) {
            $code = isset($row['code']) ? wp_check_invalid_utf8((string) $row['code']) : '';

            if (trim($code) === '') {
                continue;
            }

            $placement = (isset($row['placement']) && $row['placement'] === 'footer') ? 'footer' : 'head';
            $category = isset($row['category']) ? (string) $row['category'] : 'necessary';

            if (! in_array($category, $validCategories, true)) {
                $category = 'necessary';
            }

            $clean[] = [
                'label' => isset($row['label']) ? sanitize_text_field((string) $row['label']) : '',
                'placement' => $placement,
                'category' => $category,
                'code' => $code,
            ];
        }

        return $clean === [] ? '' : (string) wp_json_encode($clean);
    }

    /**
     * Sanitise the Custom Triggers repeater JSON. Event names, URL fragments and
     * CSS selectors are stored as plain text; condition and category are
     * constrained to known values.
     */
    private function sanitizeTriggerRepeater(string $json): string
    {
        $rows = $this->decodeRepeater($json);
        $clean = [];

        $validCategories = ['necessary', 'analytics', 'marketing', 'preferences'];

        foreach ($rows as $row) {
            $event = isset($row['event']) ? sanitize_text_field((string) $row['event']) : '';

            if (trim($event) === '') {
                continue;
            }

            $condition = (isset($row['condition']) && $row['condition'] === 'click') ? 'click' : 'page_url';
            $category = isset($row['category']) ? (string) $row['category'] : 'necessary';

            if (! in_array($category, $validCategories, true)) {
                $category = 'necessary';
            }

            $clean[] = [
                'event' => $event,
                'condition' => $condition,
                'value' => isset($row['value']) ? sanitize_text_field((string) $row['value']) : '',
                'selector' => isset($row['selector']) ? sanitize_text_field((string) $row['selector']) : '',
                'category' => $category,
            ];
        }

        return $clean === [] ? '' : (string) wp_json_encode($clean);
    }
}
