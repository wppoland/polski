<?php

declare(strict_types=1);
namespace Polski\Shortcode;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Plugin;
use Polski\Service\DeliveryTimeService;
use Polski\Service\FoodService;
use Polski\Service\LegalPageService;
use Polski\Service\PriceDisplayService;
use Polski\Service\ProductInfoService;
use Polski\Service\WithdrawalService;
use Polski\Service\CompareService;
use Polski\Service\WishlistService;
use Polski\Util\TemplateLoader;

/**
 * Registers all Polski shortcodes.
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
        add_shortcode('polski_unit_price', [$this, 'unitPrice']);
        add_shortcode('polski_delivery_time', [$this, 'deliveryTime']);
        add_shortcode('polski_omnibus_price', [$this, 'omnibusPrice']);
        add_shortcode('polski_tax_notice', [$this, 'taxNotice']);
        add_shortcode('polski_shipping_notice', [$this, 'shippingNotice']);
        add_shortcode('polski_manufacturer', [$this, 'manufacturer']);
        add_shortcode('polski_safety_info', [$this, 'safetyInfo']);
        add_shortcode('polski_safety_docs', [$this, 'safetyDocs']);
        add_shortcode('polski_power_supply', [$this, 'powerSupply']);
        add_shortcode('polski_defect_description', [$this, 'defectDescription']);
        add_shortcode('polski_nutrients', [$this, 'nutrients']);
        add_shortcode('polski_allergens', [$this, 'allergens']);
        add_shortcode('polski_ingredients', [$this, 'ingredients']);
        add_shortcode('polski_nutri_score', [$this, 'nutriScore']);
        add_shortcode('polski_food_info', [$this, 'foodInfo']);
        add_shortcode('polski_withdrawal_form', [$this, 'withdrawalForm']);
        add_shortcode('polski_wishlist', [$this, 'wishlist']);
        add_shortcode('polski_compare', [$this, 'compare']);
        add_shortcode('polski_complaints', [$this, 'complaints']);
        add_shortcode('polski_payment_methods', [$this, 'paymentMethods']);
        add_shortcode('polski_small_business_notice', [$this, 'smallBusinessNotice']);
        add_shortcode('polski_dsa_report', [$this, 'dsaReport']);
        add_shortcode('polski_gpsr', [$this, 'gpsrInfo']);
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
            '<div class="polski-safety-instructions"><span class="polski-safety-instructions__label">%s:</span> %s</div>',
            esc_html__('Instrukcje bezpieczeństwa', 'polski'),
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
            return '<p>' . esc_html__('Proszę podać numer zamówienia.', 'polski') . '</p>';
        }

        $order = wc_get_order($orderId);
        if (! $order instanceof \WC_Order) {
            return '<p>' . esc_html__('Niestety, nie udało nam się znaleźć takiego zamówienia.', 'polski') . '</p>';
        }

        $service = $this->container()->get(WithdrawalService::class);

        if (! $service->isEligible($order)) {
            return '<p>' . esc_html__('To zamówienie nie kwalifikuje się do odstąpienia.', 'polski') . '</p>';
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
        $settings = get_option('polski_general', []);
        $text = is_array($settings) ? ($settings['dispute_resolution_text'] ?? '') : '';

        if ($text === '') {
            return '';
        }

        return sprintf(
            '<div class="polski-complaints"><p>%s</p></div>',
            wp_kses_post($text),
        );
    }

    public function wishlist(array|string $atts = []): string
    {
        return $this->container()->get(WishlistService::class)->renderWishlist();
    }

    public function compare(array|string $atts = []): string
    {
        return $this->container()->get(CompareService::class)->renderCompareTable();
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
            '<div class="polski-payment-methods"><h4>%s</h4><ul>%s</ul></div>',
            esc_html__('Dostępne metody płatności', 'polski'),
            $items,
        );
    }

    public function smallBusinessNotice(array|string $atts = []): string
    {
        $settings = get_option('polski_taxes', []);
        $notice = is_array($settings) ? ($settings['vat_exempt_notice'] ?? '') : '';
        $isSmall = get_option('polski_general', []);
        $isSmall = is_array($isSmall) && (bool) ($isSmall['small_business'] ?? false);

        if (! $isSmall || $notice === '') {
            return '';
        }

        return sprintf(
            '<div class="polski-small-business-notice"><p>%s</p></div>',
            esc_html($notice),
        );
    }

    /**
     * Render the DSA report form.
     */
    public function dsaReport(array|string $atts = []): string
    {
        return \Polski\Plugin::instance()->container()->get(\Polski\Service\DSAService::class)->renderReportForm();
    }

    /**
     * Render GPSR product safety information.
     */
    public function gpsrInfo(array|string $atts = []): string
    {
        $product = $this->resolveProduct($atts);

        if ($product === null) {
            return '';
        }

        $service = \Polski\Plugin::instance()->container()->get(\Polski\Service\GPSRService::class);

        if (! $service->isEnabled()) {
            return '';
        }

        $data = $service->getGPSRData($product);
        $hasData = array_filter($data, static fn (string $v): bool => $v !== '');

        if (empty($hasData)) {
            return '';
        }

        ob_start();
        $service->renderGPSRSection($product);
        return (string) ob_get_clean();
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

    private function container(): \Polski\Container
    {
        return Plugin::instance()->container();
    }
}
