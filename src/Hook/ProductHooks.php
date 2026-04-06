<?php

declare(strict_types=1);
namespace Polski\Hook;

defined('ABSPATH') || exit;

use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
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

        // Extend structured data for SEO.
        add_filter('woocommerce_structured_data_product', [$this, 'enrichStructuredData'], 10, 2);

        // Clear structured data cache on product save
        add_action('woocommerce_update_product', [$this, 'clearSchemaCache'], 10, 1);
        add_action('woocommerce_new_product', [$this, 'clearSchemaCache'], 10, 1);
    }

    /**
     * Filter variable product price HTML to show "od {lowest_price}" instead of a range.
     */
    public function filterVariablePriceHtml(string $priceHtml, \WC_Product $product): string
    {
        return $this->priceDisplay->getFromPriceHtml($priceHtml, $product);
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

        $parts = array_filter([
            $this->productInfo->getSafetyDocumentsHtml($product),
            $this->productInfo->getPowerSupplyHtml($product),
            $this->productInfo->getDefectDescriptionHtml($product),
        ]);

        $gpsr = $this->productInfo->getGPSRResponsible($product);
        if ($gpsr !== '') {
            array_unshift($parts, sprintf(
                '<div class="polski-gpsr"><span class="polski-gpsr__label">%s:</span> %s</div>',
                esc_html__('Osoba odpowiedzialna (GPSR)', 'polski'),
                esc_html($gpsr),
            ));
        }

        if (! empty($parts)) {
            $html = '<div class="polski-safety">' . implode('', $parts) . '</div>';
            $this->templateLoader->include('single-product/safety-info', [
                'safety_html' => $html,
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

        $productId = $product->get_id();
        $cacheKey = 'polski_schema_' . $productId;
        $cachedExtra = get_transient($cacheKey);

        if ($cachedExtra !== false && is_array($cachedExtra)) {
            return array_merge($data, $cachedExtra);
        }

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
                $extraData['additionalProperty'][] = [
                    '@type' => 'PropertyValue',
                    'name' => 'Cena jednostkowa',
                    'value' => sprintf('%s / %s %s', $unitPrice->pricePerUnit, $unitPrice->baseAmount, $unitPrice->unit),
                ];
                
                $extraData['polski_unit_price'] = [
                    'price' => $unitPrice->pricePerUnit,
                    'unit' => $unitPrice->unit,
                    'base_amount' => $unitPrice->baseAmount,
                ];
            }
        }

        // Add Delivery Time (OfferShippingDetails) if available.
        if ($settings['schema_delivery_time'] ?? true) {
            $deliveryTime = $this->deliveryTime->getDeliveryTime($product);
            if ($deliveryTime !== null && $deliveryTime !== '') {
                $extraData['shippingDetails'] = [
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
                            'minValue' => 1,
                            'maxValue' => (int) preg_replace('/\D/', '', $deliveryTime) ?: 5,
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
        $gpsrManufacturer = get_post_meta($productId, '_polski_manufacturer_name', true);
        $gpsrContact = get_post_meta($productId, '_polski_manufacturer_contact', true);
        if (! empty($gpsrManufacturer)) {
            $extraData['manufacturer'] = $extraData['manufacturer'] ?? [
                '@type' => 'Organization',
                'name' => $gpsrManufacturer,
            ];
            if (! empty($gpsrContact)) {
                $extraData['manufacturer']['contactPoint'] = [
                    '@type' => 'ContactPoint',
                    'contactType' => 'product safety',
                    'description' => $gpsrContact,
                ];
            }
        }

        // Add Food/Nutrition data if available.
        $nutrients = get_post_meta($productId, '_polski_nutrients', true);
        if (is_array($nutrients) && ! empty($nutrients)) {
            $nutritionData = ['@type' => 'NutritionInformation'];
            $nutrientMap = [
                'energy_kcal' => 'calories',
                'fat' => 'fatContent',
                'saturated_fat' => 'saturatedFatContent',
                'carbohydrates' => 'carbohydrateContent',
                'sugars' => 'sugarContent',
                'protein' => 'proteinContent',
                'salt' => 'sodiumContent',
                'fiber' => 'fiberContent',
            ];
            foreach ($nutrientMap as $metaKey => $schemaKey) {
                if (isset($nutrients[$metaKey]) && $nutrients[$metaKey] !== '') {
                    $unit = $metaKey === 'energy_kcal' ? ' kcal' : ' g';
                    $nutritionData[$schemaKey] = $nutrients[$metaKey] . $unit;
                }
            }
            if (count($nutritionData) > 1) {
                $extraData['nutrition'] = $nutritionData;
            }
        }

        // Cache the additional generated schema array for 12 hours (cache gets invalidated on product save)
        set_transient($cacheKey, $extraData, 12 * HOUR_IN_SECONDS);

        return array_merge($data, $extraData);
    }

    /**
     * Clear the Schema.org object cache on product save.
     */
    public function clearSchemaCache(int $productId): void
    {
        delete_transient('polski_schema_' . $productId);
    }
}
