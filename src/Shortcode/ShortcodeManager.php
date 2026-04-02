<?php

declare(strict_types=1);

namespace Spolszczony\Shortcode;

use Spolszczony\Contract\HasHooks;
use Spolszczony\Plugin;
use Spolszczony\Service\DeliveryTimeService;
use Spolszczony\Service\FoodService;
use Spolszczony\Service\LegalPageService;
use Spolszczony\Service\PriceDisplayService;
use Spolszczony\Service\ProductInfoService;
use Spolszczony\Service\WithdrawalService;
use Spolszczony\Util\TemplateLoader;

/**
 * Registers all Spolszczony shortcodes.
 *
 * All shortcodes accept an optional `product` attribute to specify the product ID.
 * Without it, they use the global $product from the WooCommerce loop.
 */
final class ShortcodeManager implements HasHooks
{
    public function registerHooks(): void
    {
        add_action('init', [$this, 'registerShortcodes']);
    }

    public function registerShortcodes(): void
    {
        add_shortcode('spolszczony_unit_price', [$this, 'unitPrice']);
        add_shortcode('spolszczony_delivery_time', [$this, 'deliveryTime']);
        add_shortcode('spolszczony_omnibus_price', [$this, 'omnibusPrice']);
        add_shortcode('spolszczony_tax_notice', [$this, 'taxNotice']);
        add_shortcode('spolszczony_shipping_notice', [$this, 'shippingNotice']);
        add_shortcode('spolszczony_manufacturer', [$this, 'manufacturer']);
        add_shortcode('spolszczony_safety_info', [$this, 'safetyInfo']);
        add_shortcode('spolszczony_safety_docs', [$this, 'safetyDocs']);
        add_shortcode('spolszczony_power_supply', [$this, 'powerSupply']);
        add_shortcode('spolszczony_defect_description', [$this, 'defectDescription']);
        add_shortcode('spolszczony_nutrients', [$this, 'nutrients']);
        add_shortcode('spolszczony_allergens', [$this, 'allergens']);
        add_shortcode('spolszczony_ingredients', [$this, 'ingredients']);
        add_shortcode('spolszczony_nutri_score', [$this, 'nutriScore']);
        add_shortcode('spolszczony_food_info', [$this, 'foodInfo']);
        add_shortcode('spolszczony_withdrawal_form', [$this, 'withdrawalForm']);
        add_shortcode('spolszczony_complaints', [$this, 'complaints']);
        add_shortcode('spolszczony_payment_methods', [$this, 'paymentMethods']);
        add_shortcode('spolszczony_small_business_notice', [$this, 'smallBusinessNotice']);
    }

    /**
     * @param array<string, string>|string $atts
     */
    public function unitPrice(array|string $atts = []): string
    {
        $product = $this->resolveProduct($atts);
        if ($product === null) {
            return '';
        }

        return $this->container()->get(PriceDisplayService::class)->getUnitPriceHtml($product);
    }

    public function deliveryTime(array|string $atts = []): string
    {
        $product = $this->resolveProduct($atts);
        if ($product === null) {
            return '';
        }

        return $this->container()->get(DeliveryTimeService::class)->getDeliveryTimeHtml($product);
    }

    public function omnibusPrice(array|string $atts = []): string
    {
        $product = $this->resolveProduct($atts);
        if ($product === null) {
            return '';
        }

        return $this->container()->get(PriceDisplayService::class)->getOmnibusPriceHtml($product);
    }

    public function taxNotice(array|string $atts = []): string
    {
        $product = $this->resolveProduct($atts);
        if ($product === null) {
            return '';
        }

        return $this->container()->get(PriceDisplayService::class)->getVatNoticeHtml($product);
    }

    public function shippingNotice(array|string $atts = []): string
    {
        return $this->container()->get(PriceDisplayService::class)->getShippingNoticeHtml();
    }

    public function manufacturer(array|string $atts = []): string
    {
        $product = $this->resolveProduct($atts);
        if ($product === null) {
            return '';
        }

        return $this->container()->get(ProductInfoService::class)->getManufacturerHtml($product);
    }

    public function safetyInfo(array|string $atts = []): string
    {
        $product = $this->resolveProduct($atts);
        if ($product === null) {
            return '';
        }

        $service = $this->container()->get(ProductInfoService::class);
        $instructions = $service->getSafetyInstructions($product);

        if ($instructions === '') {
            return '';
        }

        return sprintf(
            '<div class="spolszczony-safety-instructions"><span class="spolszczony-safety-instructions__label">%s:</span> %s</div>',
            esc_html__('Safety Instructions', 'spolszczony'),
            esc_html($instructions),
        );
    }

    public function safetyDocs(array|string $atts = []): string
    {
        $product = $this->resolveProduct($atts);
        if ($product === null) {
            return '';
        }

        return $this->container()->get(ProductInfoService::class)->getSafetyDocumentsHtml($product);
    }

    public function powerSupply(array|string $atts = []): string
    {
        $product = $this->resolveProduct($atts);
        if ($product === null) {
            return '';
        }

        return $this->container()->get(ProductInfoService::class)->getPowerSupplyHtml($product);
    }

    public function defectDescription(array|string $atts = []): string
    {
        $product = $this->resolveProduct($atts);
        if ($product === null) {
            return '';
        }

        return $this->container()->get(ProductInfoService::class)->getDefectDescriptionHtml($product);
    }

    public function nutrients(array|string $atts = []): string
    {
        $product = $this->resolveProduct($atts);
        if ($product === null) {
            return '';
        }

        return $this->container()->get(FoodService::class)->getNutrientsHtml($product);
    }

    public function allergens(array|string $atts = []): string
    {
        $product = $this->resolveProduct($atts);
        if ($product === null) {
            return '';
        }

        return $this->container()->get(FoodService::class)->getAllergensHtml($product);
    }

    public function ingredients(array|string $atts = []): string
    {
        $product = $this->resolveProduct($atts);
        if ($product === null) {
            return '';
        }

        return $this->container()->get(FoodService::class)->getIngredientsHtml($product);
    }

    public function nutriScore(array|string $atts = []): string
    {
        $product = $this->resolveProduct($atts);
        if ($product === null) {
            return '';
        }

        return $this->container()->get(FoodService::class)->getNutriScoreHtml($product);
    }

    public function foodInfo(array|string $atts = []): string
    {
        $product = $this->resolveProduct($atts);
        if ($product === null) {
            return '';
        }

        return $this->container()->get(FoodService::class)->getFoodInfoHtml($product);
    }

    public function withdrawalForm(array|string $atts = []): string
    {
        $atts = shortcode_atts(['order_id' => 0], is_array($atts) ? $atts : []);
        $orderId = (int) $atts['order_id'];

        if ($orderId <= 0) {
            return '<p>' . esc_html__('Please provide an order ID.', 'spolszczony') . '</p>';
        }

        $order = wc_get_order($orderId);
        if (! $order instanceof \WC_Order) {
            return '<p>' . esc_html__('Order not found.', 'spolszczony') . '</p>';
        }

        $service = $this->container()->get(WithdrawalService::class);

        if (! $service->isEligible($order)) {
            return '<p>' . esc_html__('This order is not eligible for withdrawal.', 'spolszczony') . '</p>';
        }

        $templateLoader = $this->container()->get(TemplateLoader::class);

        return $templateLoader->render('forms/withdrawal-form', [
            'order' => $order,
            'fields' => $service->getFormFields(),
            'action_url' => wc_get_account_endpoint_url('orders'),
        ]);
    }

    public function complaints(array|string $atts = []): string
    {
        $settings = get_option('spolszczony_general', []);
        $text = is_array($settings) ? ($settings['dispute_resolution_text'] ?? '') : '';

        if ($text === '') {
            return '';
        }

        return sprintf(
            '<div class="spolszczony-complaints"><p>%s</p></div>',
            wp_kses_post($text),
        );
    }

    public function paymentMethods(array|string $atts = []): string
    {
        $gateways = WC()->payment_gateways()->get_available_payment_gateways();

        if (empty($gateways)) {
            return '';
        }

        $items = '';
        foreach ($gateways as $gateway) {
            $items .= sprintf(
                '<li>%s</li>',
                esc_html($gateway->get_title()),
            );
        }

        return sprintf(
            '<div class="spolszczony-payment-methods"><h4>%s</h4><ul>%s</ul></div>',
            esc_html__('Available Payment Methods', 'spolszczony'),
            $items,
        );
    }

    public function smallBusinessNotice(array|string $atts = []): string
    {
        $settings = get_option('spolszczony_taxes', []);
        $notice = is_array($settings) ? ($settings['vat_exempt_notice'] ?? '') : '';
        $isSmall = get_option('spolszczony_general', []);
        $isSmall = is_array($isSmall) && (bool) ($isSmall['small_business'] ?? false);

        if (! $isSmall || $notice === '') {
            return '';
        }

        return sprintf(
            '<div class="spolszczony-small-business-notice"><p>%s</p></div>',
            esc_html($notice),
        );
    }

    /**
     * Resolve a WC_Product from shortcode attributes or global.
     */
    private function resolveProduct(array|string $atts): ?\WC_Product
    {
        $atts = shortcode_atts(['product' => 0], is_array($atts) ? $atts : []);
        $productId = (int) $atts['product'];

        if ($productId > 0) {
            $product = wc_get_product($productId);
            return $product instanceof \WC_Product ? $product : null;
        }

        global $product;
        return $product instanceof \WC_Product ? $product : null;
    }

    private function container(): \Spolszczony\Container
    {
        return Plugin::instance()->container();
    }
}
