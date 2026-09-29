<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * GA4 ecommerce DataLayer integration.
 *
 * Pushes standard GA4 ecommerce events to the dataLayer for use with
 * Google Tag Manager or gtag.js. Tracks the full purchase funnel:
 * view_item_list, view_item, add_to_cart, begin_checkout, purchase.
 *
 * Web Vitals friendly: all scripts are deferred, inline JS is minimal,
 * no external script blocking.
 */
final class DataLayerService implements HasHooks
{
    private const OPTION = 'polski_datalayer';
    private const SESSION_PENDING = 'polski_datalayer_pending';

    /**
     * GA4 items of the products listed on this page, keyed by product ID, so
     * the AJAX add_to_cart push uses the same ID and active price as PHP.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $pageItems = [];

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('datalayer')) {
            return;
        }

        // Initialize dataLayer early in head.
        add_action('wp_head', [$this, 'initDataLayer'], 1);

        // GTM container snippet.
        add_action('wp_head', [$this, 'renderGtmHead'], 2);
        add_action('wp_body_open', [$this, 'renderGtmBody'], 1);

        // Product list impressions.
        add_action('woocommerce_after_shop_loop_item', [$this, 'trackProductImpression'], 20);

        // Single product view.
        add_action('woocommerce_after_single_product', [$this, 'trackViewItem']);

        // Add to cart: AJAX in the browser, a form post through the session.
        // After footer scripts (priority 20), so jQuery and wp.data exist.
        add_action('wp_footer', [$this, 'trackAddToCart'], 100);
        add_action('woocommerce_add_to_cart', [$this, 'queueAddToCart'], 10, 4);
        add_action('wp_footer', [$this, 'printPendingEvents']);

        // Checkout (classic and block checkout both render the footer).
        add_action('wp_footer', [$this, 'trackBeginCheckout']);

        // Purchase (thank you page).
        add_action('woocommerce_thankyou', [$this, 'trackPurchase'], 10, 1);

        // Remove from cart.
        add_action('wp_footer', [$this, 'trackRemoveFromCart'], 100);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        return wp_parse_args(
            get_option(self::OPTION, []),
            [
                'gtm_container_id' => '',
                'ga4_measurement_id' => '',
                'track_user_id' => false,
                'use_sku_as_id' => false,
            ],
        );
    }

    /**
     * Initialize the dataLayer array.
     */
    public function initDataLayer(): void
    {
        wp_print_inline_script_tag('window.dataLayer=window.dataLayer||[];');

        $ga4Id = $this->getSettings()['ga4_measurement_id'] ?? '';

        if (! empty($ga4Id)) {
            wp_print_script_tag([
                'src' => 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode($ga4Id),
                'async' => true,
            ]);
            wp_print_inline_script_tag(
                'function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",'
                . wp_json_encode($ga4Id) . ');',
            );
        }
    }

    /**
     * Render GTM container head snippet.
     */
    public function renderGtmHead(): void
    {
        $containerId = $this->getSettings()['gtm_container_id'] ?? '';

        if (empty($containerId)) {
            return;
        }

        wp_print_inline_script_tag(
            "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',"
            . wp_json_encode($containerId) . ');',
        );
    }

    /**
     * Render GTM noscript fallback.
     */
    public function renderGtmBody(): void
    {
        $containerId = $this->getSettings()['gtm_container_id'] ?? '';

        if (empty($containerId)) {
            return;
        }

        printf(
            '<!-- Google Tag Manager (noscript) --><noscript><iframe src="https://www.googletagmanager.com/ns.html?id=%s" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript><!-- End Google Tag Manager (noscript) -->' . "\n",
            esc_attr($containerId),
        );
    }

    /**
     * Track product impression on listing pages.
     */
    public function trackProductImpression(): void
    {
        global $product, $woocommerce_loop;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $item = $this->buildItemData($product);
        $this->pageItems[$product->get_id()] = $item;
        $item['index'] = (int) ($woocommerce_loop['loop'] ?? 0);
        $item['item_list_name'] = is_search() ? 'Search Results' : 'Product List';

        wp_print_inline_script_tag(
            'window.dataLayer.push({event:"view_item_list",ecommerce:{items:['
            . wp_json_encode($item) . ']}});',
        );
    }

    /**
     * Track single product view.
     */
    public function trackViewItem(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $item = $this->buildItemData($product);

        $payload = [
            'event' => 'view_item',
            'ecommerce' => [
                'currency' => get_woocommerce_currency(),
                'value' => (float) wc_format_decimal($product->get_price(), 2),
                'items' => [$item],
            ],
        ];

        wp_print_inline_script_tag(
            'window.dataLayer.push(' . wp_json_encode($payload) . ');',
        );
    }

    /**
     * Track the AJAX add_to_cart from product lists. The item comes from the
     * PHP map of listed products, so it carries the configured ID (SKU or
     * product ID) and the active price. A form post is queued server-side.
     */
    public function trackAddToCart(): void
    {
        if ($this->pageItems === []) {
            return;
        }

        $script = "(function(){"
            . "if(!window.jQuery){return;}"
            . "var items=" . wp_json_encode((object) $this->pageItems) . ";"
            . "var currency=" . wp_json_encode(get_woocommerce_currency()) . ";"
            . "jQuery(document.body).on('added_to_cart',function(e,fragments,hash,\$btn){"
            . "var id=\$btn?\$btn.data('product_id'):'';"
            . "var item=items[id];"
            . "if(!item){return;}"
            . "var qty=parseInt(\$btn.data('quantity'),10)||1;"
            . "item=Object.assign({},item,{quantity:qty});"
            . "window.dataLayer.push({event:'add_to_cart',ecommerce:{currency:currency,value:item.price*qty,items:[item]}});"
            . "});"
            . "})();";

        wp_print_inline_script_tag($script);
    }

    /**
     * A non-AJAX add to cart (the single product form) reloads the page, so a
     * push made on submit is lost. Queue it in the session instead and print
     * it on the page the shopper lands on.
     */
    public function queueAddToCart(string $cartItemKey, int $productId, int $quantity, int $variationId): void
    {
        unset($cartItemKey);

        if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || ! WC()->session) {
            return;
        }

        $product = wc_get_product($variationId > 0 ? $variationId : $productId);

        if (! $product instanceof \WC_Product) {
            return;
        }

        $item = $this->buildItemData($product);
        $item['quantity'] = $quantity;

        $pending = WC()->session->get(self::SESSION_PENDING, []);
        $pending = is_array($pending) ? $pending : [];
        $pending[] = [
            'event' => 'add_to_cart',
            'ecommerce' => [
                'currency' => get_woocommerce_currency(),
                'value' => (float) wc_format_decimal((float) $item['price'] * $quantity, 2),
                'items' => [$item],
            ],
        ];

        WC()->session->set(self::SESSION_PENDING, $pending);
    }

    public function printPendingEvents(): void
    {
        if (! function_exists('WC') || ! WC()->session) {
            return;
        }

        $pending = WC()->session->get(self::SESSION_PENDING, []);

        if (! is_array($pending) || $pending === []) {
            return;
        }

        WC()->session->set(self::SESSION_PENDING, []);

        foreach ($pending as $payload) {
            wp_print_inline_script_tag('window.dataLayer.push(' . wp_json_encode($payload) . ');');
        }
    }

    /**
     * Track begin_checkout event.
     */
    public function trackBeginCheckout(): void
    {
        if (! is_checkout() || is_order_received_page() || is_checkout_pay_page() || ! WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        $items = [];
        $value = 0.0;

        foreach (WC()->cart->get_cart() as $cartItem) {
            $product = $cartItem['data'] ?? null;

            if (! $product instanceof \WC_Product) {
                continue;
            }

            $item = $this->buildItemData($product);
            $item['quantity'] = (int) $cartItem['quantity'];
            $items[] = $item;
            $value += (float) $product->get_price() * (int) $cartItem['quantity'];
        }

        $payload = [
            'event' => 'begin_checkout',
            'ecommerce' => [
                'currency' => get_woocommerce_currency(),
                'value' => (float) wc_format_decimal($value, 2),
                'items' => $items,
            ],
        ];

        wp_print_inline_script_tag(
            'window.dataLayer.push(' . wp_json_encode($payload) . ');',
        );
    }

    /**
     * Track purchase event on thank you page.
     */
    public function trackPurchase(int $orderId): void
    {
        $order = wc_get_order($orderId);

        if (! $order instanceof \WC_Order) {
            return;
        }

        // Prevent double tracking.
        if ($order->get_meta('_polski_datalayer_tracked')) {
            return;
        }

        $items = [];

        /** @var \WC_Order_Item_Product $item */
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            if (! $product instanceof \WC_Product) {
                continue;
            }

            $itemData = $this->buildItemData($product);
            $itemData['quantity'] = $item->get_quantity();
            $lineTotal = (float) wc_format_decimal((string) $item->get_total(), 2);
            $itemData['price'] = (float) wc_format_decimal((string) ($lineTotal / max(1, $item->get_quantity())), 2);
            $items[] = $itemData;
        }

        $coupons = $order->get_coupon_codes();

        $ecommerce = [
            'transaction_id' => (string) $order->get_order_number(),
            'affiliation' => get_bloginfo('name'),
            'value' => (float) wc_format_decimal($order->get_total(), 2),
            'tax' => (float) wc_format_decimal($order->get_total_tax(), 2),
            'shipping' => (float) wc_format_decimal($order->get_shipping_total(), 2),
            'currency' => $order->get_currency(),
            'coupon' => ! empty($coupons) ? implode(',', $coupons) : '',
            'items' => $items,
        ];

        wp_print_inline_script_tag(
            'window.dataLayer.push({event:"purchase",ecommerce:' . wp_json_encode($ecommerce) . '});',
        );

        $order->update_meta_data('_polski_datalayer_tracked', '1');
        $order->save();
    }

    /**
     * Track remove_from_cart. The classic cart and mini cart remove links carry
     * the cart item key in their href; the block cart is watched through the
     * wc/store/cart data store. Items come from the cart, built in PHP.
     */
    public function trackRemoveFromCart(): void
    {
        if (! WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        $items = [];

        foreach (WC()->cart->get_cart() as $key => $cartItem) {
            $product = $cartItem['data'] ?? null;

            if ($product instanceof \WC_Product) {
                $item = $this->buildItemData($product);
                $item['quantity'] = (int) $cartItem['quantity'];
                $items[$key] = $item;
            }
        }

        $script = "(function(){"
            . "var items=" . wp_json_encode((object) $items) . ";"
            . "var currency=" . wp_json_encode(get_woocommerce_currency()) . ";"
            . "function push(item,qty){window.dataLayer.push({event:'remove_from_cart',ecommerce:{currency:currency,value:item.price*qty,items:[Object.assign({},item,{quantity:qty})]}});}"
            . "document.addEventListener('click',function(e){"
            . "var a=e.target.closest&&e.target.closest('a.remove[href*=\"remove_item=\"]');"
            . "var m=a&&/[?&]remove_item=([^&]+)/.exec(a.getAttribute('href'));"
            . "var item=m&&items[decodeURIComponent(m[1])];"
            . "if(item){push(item,item.quantity);}"
            . "},true);"
            . "if(window.wp&&wp.data&&wp.data.select('wc/store/cart')){"
            . "var prev=null;"
            . "wp.data.subscribe(function(){"
            . "var cart=wp.data.select('wc/store/cart').getCartData();"
            . "if(!cart||!cart.items){return;}"
            . "var now={};cart.items.forEach(function(i){now[i.key]=i.quantity;});"
            . "if(prev){Object.keys(prev).forEach(function(k){var gone=prev[k]-(now[k]||0);if(gone>0&&items[k]){push(items[k],gone);}});}"
            . "prev=now;"
            . "});"
            . "}"
            . "})();";

        wp_print_inline_script_tag($script);
    }

    // ── Helpers ──────────────────────────────────────────

    /**
     * Build GA4 item data array from a product.
     *
     * @return array<string, mixed>
     */
    private function buildItemData(\WC_Product $product): array
    {
        $useSku = $this->getSettings()['use_sku_as_id'] ?? false;

        $id = $useSku && $product->get_sku()
            ? $product->get_sku()
            : (string) $product->get_id();

        $categories = wp_get_post_terms(
            $product->get_parent_id() ?: $product->get_id(),
            'product_cat',
            ['fields' => 'names'],
        );

        $item = [
            'item_id' => $id,
            'item_name' => $product->get_name(),
            'price' => (float) wc_format_decimal($product->get_price(), 2),
        ];

        if (! empty($categories) && ! is_wp_error($categories)) {
            $item['item_category'] = $categories[0] ?? '';

            if (isset($categories[1])) {
                $item['item_category2'] = $categories[1];
            }

            if (isset($categories[2])) {
                $item['item_category3'] = $categories[2];
            }
        }

        // Brand from GPSR manufacturer.
        $brand = $product->get_meta('_polski_gpsr_manufacturer_name');

        if ($brand) {
            $item['item_brand'] = $brand;
        }

        // Variant for variable products.
        if ($product instanceof \WC_Product_Variation) {
            $attrs = $product->get_variation_attributes();
            $item['item_variant'] = implode(' / ', array_filter($attrs));
        }

        return $item;
    }
}
