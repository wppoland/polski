<?php

declare(strict_types=1);
namespace Polski\Hook;

defined('ABSPATH') || exit;

use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Service\ConsumerInformationService;
use Polski\Service\DeliveryTimeService;
use Polski\Service\FoodService;
use Polski\Service\PriceDisplayService;
use Polski\Service\ProductInfoService;
use Polski\Shopmark\Location;
use Polski\Shopmark\Shopmark;
use Polski\Shopmark\ShopmarkManager;
use Polski\Util\TemplateLoader;

/**
 * Registers shopmarks and hooks for single product page display.
 */
final class ProductHooks implements Bootable, HasHooks
{
    public function __construct(
        private readonly PriceDisplayService $priceDisplay,
        private readonly DeliveryTimeService $deliveryTime,
        private readonly ProductInfoService $productInfo,
        private readonly FoodService $foodService,
        private readonly ConsumerInformationService $consumerInfo,
        private readonly ShopmarkManager $shopmarks,
        private readonly TemplateLoader $templateLoader,
    ) {
    }

    public function boot(): void
    {
        $this->registerShopmarks();
    }

    public function registerHooks(): void
    {
        // Attach registered shopmarks to WooCommerce hooks.
        add_action('woocommerce_single_product_summary', [$this, 'renderSingleProductShopmarks'], 25);

        // "From {price}" for variable products (replaces price range with "od XX PLN").
        add_filter('woocommerce_get_price_html', [$this, 'filterVariablePriceHtml'], 10, 2);

        // A selected variation brings its own unit price, Omnibus notice and delivery time.
        add_filter('woocommerce_available_variation', [$this, 'addVariationMarks'], 10, 3);
        add_action('wp_enqueue_scripts', [$this, 'enqueueVariationMarksScript'], 20);

        // Extend structured data for SEO.
        add_filter('woocommerce_structured_data_product', [$this, 'enrichStructuredData'], 10, 2);
    }

    /**
     * Filter variable product price HTML to show "od {lowest_price}" instead of a range.
     */
    public function filterVariablePriceHtml(string $priceHtml, \WC_Product $product): string
    {
        return $this->priceDisplay->getFromPriceHtml($priceHtml, $product);
    }

    /**
     * Append the variation's own price marks to the price WooCommerce shows once
     * the shopper picks a variation. The summary marks describe the parent, so
     * the script below hides them while a variation is selected.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function addVariationMarks(array $data, \WC_Product $product, \WC_Product $variation): array
    {
        $omnibus = \Polski\Util\OptionCache::get('polski_omnibus', []);
        $showOmnibus = ! is_array($omnibus) || ($omnibus['show_on_single'] ?? true);

        $marks = $this->priceDisplay->getUnitPriceHtml($variation)
            . ($showOmnibus ? $this->priceDisplay->getOmnibusPriceHtml($variation) : '')
            . $this->deliveryTime->getDeliveryTimeHtml($variation);

        if ($marks !== '') {
            $data['price_html'] = (string) ($data['price_html'] ?? '') . $marks;
        }

        return $data;
    }

    public function enqueueVariationMarksScript(): void
    {
        if (! is_product()) {
            return;
        }

        wp_add_inline_script(
            'wc-add-to-cart-variation',
            'jQuery(function($){var m=".polski-unit-price,.polski-omnibus-price,.polski-delivery-time";'
            . 'function own(f){return f.closest(".product").find(m).not(f.find(m));}'
            . '$(document.body).on("found_variation","form.variations_form",function(){own($(this)).hide();})'
            . '.on("reset_data","form.variations_form",function(){own($(this)).show();});});',
        );
    }

    /**
     * Register all single product shopmarks.
     */
    private function registerShopmarks(): void
    {
        // Unit price (cena jednostkowa).
        $this->shopmarks->register(new Shopmark(
            id: 'unit_price',
            location: Location::SingleProduct,
            hookName: 'woocommerce_single_product_summary',
            priority: 25,
            callback: fn () => $this->renderUnitPrice(),
        ));

        // VAT / tax info notice.
        $this->shopmarks->register(new Shopmark(
            id: 'tax_info',
            location: Location::SingleProduct,
            hookName: 'woocommerce_single_product_summary',
            priority: 26,
            callback: fn () => $this->renderTaxInfo(),
        ));

        // Shipping costs notice.
        $this->shopmarks->register(new Shopmark(
            id: 'shipping_notice',
            location: Location::SingleProduct,
            hookName: 'woocommerce_single_product_summary',
            priority: 27,
            callback: fn () => $this->renderShippingNotice(),
        ));

        // Omnibus lowest price.
        $this->shopmarks->register(new Shopmark(
            id: 'omnibus_price',
            location: Location::SingleProduct,
            hookName: 'woocommerce_single_product_summary',
            priority: 28,
            callback: fn () => $this->renderOmnibusPrice(),
        ));

        // Delivery time.
        $this->shopmarks->register(new Shopmark(
            id: 'delivery_time',
            location: Location::SingleProduct,
            hookName: 'woocommerce_single_product_summary',
            priority: 29,
            callback: fn () => $this->renderDeliveryTime(),
        ));

        // Manufacturer (GPSR).
        $this->shopmarks->register(new Shopmark(
            id: 'brand',
            location: Location::SingleProduct,
            hookName: 'woocommerce_single_product_summary',
            priority: 34,
            callback: fn () => $this->renderBrand(),
        ));

        // Manufacturer (GPSR).
        $this->shopmarks->register(new Shopmark(
            id: 'manufacturer',
            location: Location::SingleProduct,
            hookName: 'woocommerce_single_product_summary',
            priority: 35,
            callback: fn () => $this->renderManufacturer(),
        ));

        // Safety info (GPSR).
        $this->shopmarks->register(new Shopmark(
            id: 'safety_info',
            location: Location::SingleProduct,
            hookName: 'woocommerce_single_product_summary',
            priority: 36,
            callback: fn () => $this->renderSafetyInfo(),
        ));

        // Consumer information (Directive 2024/825, applies 27 September 2026).
        $this->shopmarks->register(new Shopmark(
            id: 'consumer_information',
            location: Location::SingleProduct,
            hookName: 'woocommerce_single_product_summary',
            priority: 37,
            callback: fn () => $this->renderConsumerInformation(),
        ));

        // Environmental claim substantiation (anti-greenwashing directive).
        $this->shopmarks->register(new Shopmark(
            id: 'green_claim',
            location: Location::SingleProduct,
            hookName: 'woocommerce_single_product_summary',
            priority: 38,
            callback: fn () => $this->renderGreenClaim(),
        ));

        // Food info (nutrients, allergens, ingredients).
        $this->shopmarks->register(new Shopmark(
            id: 'food_info',
            location: Location::SingleProduct,
            hookName: 'woocommerce_single_product_summary',
            priority: 40,
            callback: fn () => $this->renderFoodInfo(),
        ));

        /**
         * Fires after default shopmarks are registered for single product pages.
         *
         * @param ShopmarkManager $shopmarks The shopmark manager.
         */
        do_action('polski/shopmarks/registered', $this->shopmarks);
    }

    /**
     * Render all shopmarks for the single product page.
     */
    public function renderSingleProductShopmarks(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $marks = $this->shopmarks->getForLocation(Location::SingleProduct);

        foreach ($marks as $mark) {
            ($mark->callback)();
        }
    }

    private function renderUnitPrice(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $html = $this->priceDisplay->getUnitPriceHtml($product);

        if ($html !== '') {
            $this->templateLoader->include('single-product/unit-price', [
                'unit_price_html' => $html,
                'product' => $product,
            ]);
        }
    }

    private function renderTaxInfo(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $html = $this->priceDisplay->getVatNoticeHtml($product);

        if ($html !== '') {
            $this->templateLoader->include('single-product/tax-info', [
                'tax_info_html' => $html,
                'product' => $product,
            ]);
        }
    }

    private function renderShippingNotice(): void
    {
        $html = $this->priceDisplay->getShippingNoticeHtml();

        if ($html !== '') {
            $this->templateLoader->include('single-product/shipping-notice', [
                'shipping_notice_html' => $html,
            ]);
        }
    }

    private function renderOmnibusPrice(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $settings = \Polski\Util\OptionCache::get('polski_omnibus', []);

        if (is_array($settings) && ! ($settings['show_on_single'] ?? true)) {
            return;
        }

        $html = $this->priceDisplay->getOmnibusPriceHtml($product);

        if ($html !== '') {
            $this->templateLoader->include('single-product/omnibus-price', [
                'omnibus_price_html' => $html,
                'product' => $product,
            ]);
        }
    }

    private function renderDeliveryTime(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $html = $this->deliveryTime->getDeliveryTimeHtml($product);

        if ($html !== '') {
            $this->templateLoader->include('single-product/delivery-time', [
                'delivery_time_html' => $html,
                'product' => $product,
            ]);
        }
    }

    private function renderManufacturer(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $html = $this->productInfo->getManufacturerHtml($product);

        if ($html !== '') {
            $this->templateLoader->include('single-product/manufacturer', [
                'manufacturer_html' => $html,
                'product' => $product,
            ]);
        }
    }

    private function renderBrand(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $settings = get_option('polski_brand', []);

        if (is_array($settings) && ! ($settings['show_on_single'] ?? true)) {
            return;
        }

        $html = $this->productInfo->getBrandHtml($product);

        if ($html !== '') {
            $this->templateLoader->include('single-product/brand', [
                'brand_html' => $html,
                'product' => $product,
            ]);
        }
    }

    private function renderSafetyInfo(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $html = $this->productInfo->getSafetyInfoHtml($product);

        if ($html !== '') {
            $this->templateLoader->include('single-product/safety-info', [
                'safety_html' => $html,
                'product' => $product,
            ]);
        }
    }

    private function renderConsumerInformation(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $html = $this->consumerInfo->getHtml($product);

        if ($html !== '') {
            $this->templateLoader->include('single-product/consumer-info', [
                'consumer_html' => $html,
                'product' => $product,
            ]);
        }
    }

    private function renderGreenClaim(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $html = $this->productInfo->getGreenClaimHtml($product);

        if ($html !== '') {
            $this->templateLoader->include('single-product/green-claim', [
                'green_claim_html' => $html,
                'product' => $product,
            ]);
        }
    }

    private function renderFoodInfo(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $html = $this->foodService->getFoodInfoHtml($product);

        if ($html !== '') {
            $this->templateLoader->include('single-product/food-info', [
                'food_info_html' => $html,
                'product' => $product,
            ]);
        }
    }

    /**
     * Add Polski data to structured data (Schema.org).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function enrichStructuredData(array $data, \WC_Product $product): array
    {
        // Check if Schema.org module and setting are enabled
        if (! \Polski\Admin\ModulesPage::isModuleEnabled('schema_org')) {
            return $data; // Fallback
        }
        
        $settings = get_option('polski_seo', []);
        if (is_array($settings) && ! ($settings['schema_enabled'] ?? true)) {
            return $data;
        }

        // Not cached: a 12-hour transient keyed on the product kept serving
        // data after a settings or module change (a GTIN switched off stayed
        // on the page). Every read below is already in the object cache.
        $productId = $product->get_id();
        $extraData = [];

        // Add Brand if available AND enabled
        if ($settings['schema_brand'] ?? true) {
            $brands = $this->productInfo->getBrands($product);
            if ($brands !== []) {
                $extraData['brand'] = [
                    '@type' => 'Brand',
                    'name' => $brands[0],
                ];
            }
        }

        // Add Manufacturer if available AND enabled
        if ($settings['schema_manufacturer'] ?? true) {
            $manufacturer = $this->productInfo->getManufacturer($product);
            if ($manufacturer !== '') {
                $extraData['manufacturer'] = [
                    '@type' => 'Organization',
                    'name' => $manufacturer,
                ];
            }
        }

        // Add GTIN if available AND enabled
        if ($settings['schema_gtin'] ?? true) {
            $gtin = $this->productInfo->getGTIN($product);
            if ($gtin !== '') {
                if (strlen($gtin) === 8) {
                    $extraData['gtin8'] = $gtin;
                } elseif (strlen($gtin) === 12) {
                    $extraData['gtin12'] = $gtin;
                } elseif (strlen($gtin) === 13) {
                    $extraData['gtin13'] = $gtin;
                } elseif (strlen($gtin) === 14) {
                    $extraData['gtin14'] = $gtin;
                } else {
                    $extraData['gtin'] = $gtin;
                }
            }
        }

        // Add Unit Price if enabled
        if ($settings['schema_unit_price'] ?? true) {
            $unitPrice = $this->priceDisplay->getUnitPrice($product);
            if ($unitPrice !== null) {
                // Schema.org has no unit-price property, so the figure rides in
                // additionalProperty. The label used to be a hardcoded Polish
                // string, printed as-is to every non-Polish shop.
                $extraData['additionalProperty'][] = [
                    '@type' => 'PropertyValue',
                    'name' => __('Unit price', 'polski'),
                    'value' => sprintf('%s / %s %s', $unitPrice->pricePerUnit, $unitPrice->baseAmount, $unitPrice->unit),
                ];
            }
        }

        // Add Delivery Time (OfferShippingDetails) if available.
        if ($settings['schema_delivery_time'] ?? true) {
            $deliveryTime = $this->deliveryTime->getDeliveryTimeText($product);
            // "2-3 dni robocze" is a range, not 23: take the first and last
            // number. A single number keeps the old 1..n reading.
            preg_match_all('/\d+/', $deliveryTime, $days);
            $days = array_map('intval', $days[0]);

            // shippingDetails belongs to the Offer, not the Product.
            if ($days !== [] && isset($data['offers'][0]) && is_array($data['offers'][0])) {
                $data['offers'][0]['shippingDetails'] = [
                    '@type' => 'OfferShippingDetails',
                    'deliveryTime' => [
                        '@type' => 'ShippingDeliveryTime',
                        'handlingTime' => [
                            '@type' => 'QuantitativeValue',
                            'minValue' => 0,
                            'maxValue' => 1,
                            'unitCode' => 'DAY',
                        ],
                        'transitTime' => [
                            '@type' => 'QuantitativeValue',
                            'minValue' => count($days) > 1 ? min($days[0], end($days)) : min(1, $days[0]),
                            'maxValue' => max($days[0], end($days)),
                            'unitCode' => 'DAY',
                        ],
                    ],
                    'shippingDestination' => [
                        '@type' => 'DefinedRegion',
                        'addressCountry' => 'PL',
                    ],
                ];
            }
        }

        // Add GPSR (Product Safety) data if available.
        $gpsrManufacturer = get_post_meta($productId, '_polski_gpsr_manufacturer_name', true);
        $gpsrContact = get_post_meta($productId, '_polski_gpsr_manufacturer_contact', true);
        if (! empty($gpsrManufacturer)) {
            $manufacturerSchema = [
                '@type' => 'Organization',
                'name' => $gpsrManufacturer,
            ];
            if (! empty($gpsrContact)) {
                $manufacturerSchema['contactPoint'] = [
                    '@type' => 'ContactPoint',
                    'contactType' => 'product safety',
                    'description' => $gpsrContact,
                ];
            }
            $extraData['manufacturer'] = $manufacturerSchema;
        }

        // No 'nutrition' here: NutritionInformation is not a Product property
        // in Schema.org (it belongs to Recipe and MenuItem), so validators flag
        // it. The nutrition table still renders on the product page.

        return array_merge($data, $extraData);
    }
}
