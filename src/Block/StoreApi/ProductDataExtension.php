<?php

declare(strict_types=1);
namespace Polski\Block\StoreApi;

defined('ABSPATH') || exit;

use Automattic\WooCommerce\StoreApi\Schemas\V1\ProductSchema;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\SchemaController;
use Polski\Contract\HasHooks;
use Polski\Service\DeliveryTimeService;
use Polski\Service\OmnibusService;
use Polski\Service\PriceDisplayService;
use Polski\Service\ProductInfoService;

/**
 * Extends WooCommerce Store API product data with Polski fields.
 *
 * This makes unit price, omnibus price, delivery time, manufacturer, etc.
 * available in block-based product displays and checkout.
 */
final class ProductDataExtension implements HasHooks
{
    private const NAMESPACE = 'polski';

    public function __construct(
        private readonly PriceDisplayService $priceDisplay,
        private readonly OmnibusService $omnibus,
        private readonly DeliveryTimeService $deliveryTime,
        private readonly ProductInfoService $productInfo,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('woocommerce_blocks_loaded', [$this, 'registerExtensions']);
    }

    public function registerExtensions(): void
    {
        if (! function_exists('woocommerce_store_api_register_endpoint_data')) {
            return;
        }

        woocommerce_store_api_register_endpoint_data([
            'endpoint' => ProductSchema::IDENTIFIER,
            'namespace' => self::NAMESPACE,
            'data_callback' => [$this, 'getProductData'],
            'schema_callback' => [$this, 'getProductSchema'],
            'schema_type' => ARRAY_A,
        ]);

        // Extend cart item data.
        woocommerce_store_api_register_endpoint_data([
            'endpoint' => 'cart-item',
            'namespace' => self::NAMESPACE,
            'data_callback' => [$this, 'getCartItemData'],
            'schema_callback' => [$this, 'getCartItemSchema'],
            'schema_type' => ARRAY_A,
        ]);
    }

    /**
     * Product data added to Store API response.
     *
     * @return array<string, mixed>
     */
    public function getProductData(\WC_Product $product): array
    {
        $unitPrice = $this->priceDisplay->getUnitPrice($product);

        return [
            'unit_price_html' => $this->priceDisplay->getUnitPriceHtml($product),
            'unit_price' => $unitPrice !== null ? [
                'price_per_unit' => $unitPrice->pricePerUnit,
                'base_amount' => $unitPrice->baseAmount,
                'unit' => $unitPrice->unit,
                'product_amount' => $unitPrice->productAmount,
            ] : null,
            'omnibus_price_html' => $this->priceDisplay->getOmnibusPriceHtml($product),
            'omnibus_lowest' => $this->getOmnibusLowest($product),
            'delivery_time_html' => $this->deliveryTime->getDeliveryTimeHtml($product),
            'delivery_time_text' => $this->deliveryTime->getDeliveryTimeText($product),
            'vat_notice_html' => $this->priceDisplay->getVatNoticeHtml($product),
            'shipping_notice_html' => $this->priceDisplay->getShippingNoticeHtml(),
            'manufacturer' => $this->productInfo->getManufacturer($product),
            'manufacturer_html' => $this->productInfo->getManufacturerHtml($product),
            'gpsr_responsible' => $this->productInfo->getGPSRResponsible($product),
            'withdrawal_exempt' => $product->get_meta('_polski_withdrawal_exempt', true) === 'yes',
        ];
    }

    /**
     * Product data schema for Store API.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getProductSchema(): array
    {
        return [
            'unit_price_html' => [
                'description' => __('Unit price HTML', 'polski'),
                'type' => 'string',
                'context' => ['view', 'edit'],
                'readonly' => true,
            ],
            'unit_price' => [
                'description' => __('Unit price (data)', 'polski'),
                'type' => ['object', 'null'],
                'context' => ['view', 'edit'],
                'readonly' => true,
                'properties' => [
                    'price_per_unit' => ['type' => 'number'],
                    'base_amount' => ['type' => 'number'],
                    'unit' => ['type' => 'string'],
                    'product_amount' => ['type' => 'number'],
                ],
            ],
            'omnibus_price_html' => [
                'description' => __('Lowest Omnibus price HTML', 'polski'),
                'type' => 'string',
                'context' => ['view'],
                'readonly' => true,
            ],
            'omnibus_lowest' => [
                'description' => __('Lowest Omnibus price (data)', 'polski'),
                'type' => ['object', 'null'],
                'context' => ['view'],
                'readonly' => true,
            ],
            'delivery_time_html' => [
                'description' => __('Delivery time HTML', 'polski'),
                'type' => 'string',
                'context' => ['view'],
                'readonly' => true,
            ],
            'delivery_time_text' => [
                'description' => __('Delivery time (text)', 'polski'),
                'type' => 'string',
                'context' => ['view'],
                'readonly' => true,
            ],
            'vat_notice_html' => [
                'description' => __('VAT notice HTML', 'polski'),
                'type' => 'string',
                'context' => ['view'],
                'readonly' => true,
            ],
            'shipping_notice_html' => [
                'description' => __('Shipping notice HTML', 'polski'),
                'type' => 'string',
                'context' => ['view'],
                'readonly' => true,
            ],
            'manufacturer' => [
                'description' => __('Manufacturer', 'polski'),
                'type' => 'string',
                'context' => ['view'],
                'readonly' => true,
            ],
            'manufacturer_html' => [
                'description' => __('Manufacturer HTML', 'polski'),
                'type' => 'string',
                'context' => ['view'],
                'readonly' => true,
            ],
            'gpsr_responsible' => [
                'description' => __('GPSR Responsible Person', 'polski'),
                'type' => 'string',
                'context' => ['view'],
                'readonly' => true,
            ],
            'withdrawal_exempt' => [
                'description' => __('Excluded from the right of withdrawal', 'polski'),
                'type' => 'boolean',
                'context' => ['view'],
                'readonly' => true,
            ],
        ];
    }

    /**
     * Cart item extension data.
     *
     * @param array<string, mixed> $cartItem
     * @return array<string, mixed>
     */
    public function getCartItemData(array $cartItem): array
    {
        $product = $cartItem['data'] ?? null;

        if (! $product instanceof \WC_Product) {
            return [];
        }

        return [
            'unit_price_html' => $this->priceDisplay->getUnitPriceHtml($product),
            'delivery_time_text' => $this->deliveryTime->getDeliveryTimeText($product),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getCartItemSchema(): array
    {
        return [
            'unit_price_html' => [
                'description' => __('Unit price HTML', 'polski'),
                'type' => 'string',
                'context' => ['view'],
                'readonly' => true,
            ],
            'delivery_time_text' => [
                'description' => __('Delivery time', 'polski'),
                'type' => 'string',
                'context' => ['view'],
                'readonly' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getOmnibusLowest(\WC_Product $product): ?array
    {
        $lowest = $this->omnibus->getLowestPrice($product->get_id());

        if ($lowest === null) {
            return null;
        }

        return [
            'price' => $lowest->effectivePrice(),
            'currency' => $lowest->currency,
            'recorded_at' => $lowest->recordedAt->format('c'),
        ];
    }
}
