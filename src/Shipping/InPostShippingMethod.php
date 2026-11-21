<?php

declare(strict_types=1);
namespace Polski\Shipping;

defined('ABSPATH') || exit;

use Polski\Service\InPostShippingService;

// existing-checked: polski-pro/src/Shipping/FlexibleShippingMethod.php - PRO-only table rate method
// (id polski_flexible_shipping); FREE has no WC_Shipping_Method to extend. Ids differ, no option collision.

/**
 * Shared base of the two InPost shipping methods. WooCommerce needs one class
 * per method id, so the subclasses only set the id and the default title.
 */
abstract class InPostShippingMethod extends \WC_Shipping_Method
{
    abstract protected function defaultTitle(): string;

    /**
     * @param int $instance_id
     */
    public function __construct($instance_id = 0)
    {
        $this->instance_id = absint($instance_id);
        $this->method_title = $this->defaultTitle();
        $this->method_description = __('Delivery in Poland only. Set a cost, an optional free shipping threshold and an optional weight limit.', 'polski');
        $this->supports = ['shipping-zones', 'instance-settings', 'instance-settings-modal'];

        $this->instance_form_fields = [
            'title' => [
                'title' => __('Method title', 'polski'),
                'type' => 'text',
                'default' => $this->defaultTitle(),
            ],
            'cost' => [
                'title' => __('Cost', 'polski'),
                'type' => 'price',
                'default' => '0',
                'placeholder' => '0',
            ],
            'tax_status' => [
                'title' => __('Tax status', 'polski'),
                'type' => 'select',
                'default' => 'taxable',
                'options' => [
                    'taxable' => __('Taxable', 'polski'),
                    'none' => __('None', 'polski'),
                ],
            ],
            'free_above' => [
                'title' => __('Free shipping from', 'polski'),
                'type' => 'price',
                'default' => '',
                'description' => __('Free when the order subtotal after coupons reaches this amount, the same way WooCommerce Free shipping counts it. Leave empty to always charge the cost.', 'polski'),
                'desc_tip' => true,
            ],
            'max_weight' => [
                'title' => __('Maximum cart weight (kg)', 'polski'),
                'type' => 'decimal',
                'default' => '',
                'description' => __('The method is not offered above this weight. Leave empty for no limit.', 'polski'),
                'desc_tip' => true,
            ],
        ];

        $this->title = (string) $this->get_option('title', $this->defaultTitle());
        $this->tax_status = (string) $this->get_option('tax_status', 'taxable');

        add_action('woocommerce_update_options_shipping_' . $this->id, function (): void {
            $this->process_admin_options();
        });
    }

    /**
     * @param array<string, mixed> $package
     */
    public function is_available($package)
    {
        $country = (string) ($package['destination']['country'] ?? '');

        if (! InPostShippingService::isAvailableFor($country, self::weightKg($package), (string) $this->get_option('max_weight', ''))) {
            return false;
        }

        return parent::is_available($package);
    }

    /**
     * @param array<string, mixed> $package
     * @return void
     */
    public function calculate_shipping($package = [])
    {
        $this->add_rate([
            'id' => $this->get_rate_id(),
            'label' => $this->title,
            'cost' => InPostShippingService::rateCost(
                (string) $this->get_option('cost', '0'),
                (string) $this->get_option('free_above', ''),
                self::thresholdTotal(),
            ),
            'package' => $package,
        ]);
    }

    /**
     * The amount WooCommerce Free shipping compares with its minimum when
     * coupons are not ignored: displayed subtotal minus discounts.
     */
    private static function thresholdTotal(): float
    {
        $cart = WC()->cart;

        if ($cart === null) {
            return 0.0;
        }

        $total = (float) $cart->get_displayed_subtotal() - (float) $cart->get_discount_total();

        if ($cart->display_prices_including_tax()) {
            $total -= (float) $cart->get_discount_tax();
        }

        return round($total, wc_get_price_decimals());
    }

    /**
     * @param array<string, mixed> $package
     */
    private static function weightKg(array $package): float
    {
        $weight = 0.0;

        foreach ((array) ($package['contents'] ?? []) as $item) {
            $product = $item['data'] ?? null;
            if ($product instanceof \WC_Product && $product->needs_shipping()) {
                $weight += (float) $product->get_weight() * (float) ($item['quantity'] ?? 1);
            }
        }

        return (float) wc_get_weight($weight, 'kg');
    }
}
