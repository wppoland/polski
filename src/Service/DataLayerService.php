<?php

declare(strict_types=1);

namespace Polski\Service;

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

        // Add to cart (AJAX + non-AJAX).
        add_action('wp_footer', [$this, 'trackAddToCart']);

        // Checkout.
        add_action('woocommerce_before_checkout_form', [$this, 'trackBeginCheckout']);

        // Purchase (thank you page).
        add_action('woocommerce_thankyou', [$this, 'trackPurchase'], 10, 1);

        // Remove from cart.
        add_action('wp_footer', [$this, 'trackRemoveFromCart']);
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
        echo '<script>window.dataLayer=window.dataLayer||[];</script>' . "\n";

        $ga4Id = $this->getSettings()['ga4_measurement_id'] ?? '';

        if (! empty($ga4Id)) {
            printf(
                '<script async src="https://www.googletagmanager.com/gtag/js?id=%s"></script>' . "\n",
                esc_attr($ga4Id),
            );
            printf(
                '<script>function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","%s");</script>' . "\n",
                esc_js($ga4Id),
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

        printf(
            "<!-- Google Tag Manager -->\n<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','%s');</script>\n<!-- End Google Tag Manager -->\n",
            esc_js($containerId),
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
        $item['index'] = (int) ($woocommerce_loop['loop'] ?? 0);
        $item['item_list_name'] = is_search() ? 'Search Results' : 'Product List';

        printf(
            '<script>window.dataLayer.push({event:"view_item_list",ecommerce:{items:[%s]}});</script>',
            wp_json_encode($item),
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

        printf(
            '<script>window.dataLayer.push({event:"view_item",ecommerce:{currency:"%s",value:%s,items:[%s]}});</script>',
            esc_js(get_woocommerce_currency()),
            esc_js(wc_format_decimal($product->get_price(), 2)),
            wp_json_encode($item),
        );
    }

    /**
     * Track add to cart event via JS.
     */
    public function trackAddToCart(): void
    {
        if (! is_shop() && ! is_product_category() && ! is_product_tag() && ! is_product()) {
            return;
        }

        $useSku = $this->getSettings()['use_sku_as_id'] ?? false;

        ?>
        <script>
        (function(){
            var useSku = <?php echo $useSku ? 'true' : 'false'; ?>;
            jQuery(document.body).on('added_to_cart',function(e,fragments,hash,$btn){
                var $item = $btn.closest('.product,.type-product');
                var id = $btn.data('product_id') || $item.find('.add_to_cart_button').data('product_id') || '';
                var name = $item.find('.woocommerce-loop-product__title, .product_title').first().text().trim();
                var price = $item.find('.woocommerce-Price-amount').first().text().replace(/[^\d.,]/g,'').replace(',','.');
                window.dataLayer.push({event:'add_to_cart',ecommerce:{currency:'<?php echo esc_js(get_woocommerce_currency()); ?>',value:parseFloat(price)||0,items:[{item_id:String(id),item_name:name,price:parseFloat(price)||0,quantity:1}]}});
            });
            jQuery(document).on('submit','form.cart',function(){
                var $f = jQuery(this);
                var id = $f.find('input[name="product_id"]').val() || $f.find('button[name="add-to-cart"]').val() || '';
                var name = jQuery('.product_title').first().text().trim();
                var price = jQuery('.woocommerce-Price-amount').first().text().replace(/[^\d.,]/g,'').replace(',','.');
                var qty = parseInt($f.find('input[name="quantity"]').val()) || 1;
                window.dataLayer.push({event:'add_to_cart',ecommerce:{currency:'<?php echo esc_js(get_woocommerce_currency()); ?>',value:(parseFloat(price)||0)*qty,items:[{item_id:String(id),item_name:name,price:parseFloat(price)||0,quantity:qty}]}});
            });
        })();
        </script>
        <?php
    }

    /**
     * Track begin_checkout event.
     */
    public function trackBeginCheckout(): void
    {
        if (! WC()->cart || WC()->cart->is_empty()) {
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

        printf(
            '<script>window.dataLayer.push({event:"begin_checkout",ecommerce:{currency:"%s",value:%s,items:%s}});</script>',
            esc_js(get_woocommerce_currency()),
            esc_js(wc_format_decimal($value, 2)),
            wp_json_encode($items),
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

        printf(
            '<script>window.dataLayer.push({event:"purchase",ecommerce:%s});</script>',
            wp_json_encode($ecommerce),
        );

        $order->update_meta_data('_polski_datalayer_tracked', '1');
        $order->save();
    }

    /**
     * Track remove from cart event.
     */
    public function trackRemoveFromCart(): void
    {
        if (! is_cart()) {
            return;
        }

        ?>
        <script>
        jQuery(document.body).on('removed_from_cart',function(e,fragments,hash,$btn){
            var name = $btn.closest('tr').find('.product-name a').first().text().trim();
            window.dataLayer.push({event:'remove_from_cart',ecommerce:{items:[{item_name:name}]}});
        });
        </script>
        <?php
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
        $brand = $product->get_meta('_polski_manufacturer_name');

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
