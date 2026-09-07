<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * System kaucyjny: Poland's deposit return scheme, in force since 1 October 2025.
 *
 * A shop selling a beverage in covered packaging charges a deposit on top of the
 * price and shows it separately. The statutory packaging and amounts are:
 *
 *  - PET bottle up to 3 l          0.50 zl
 *  - metal can up to 1 l           0.50 zl
 *  - reusable glass up to 1.5 l    1.00 zl
 *
 * The amounts are settings rather than constants because the scheme's operator
 * can revise them; the defaults are what the regulation set at launch.
 *
 * The deposit is NOT part of the price and is outside the VAT base at the point
 * of sale: VAT on unreturned packaging is settled annually by the operator, not
 * charged to the shopper here. That is why the cart fee is added as
 * non-taxable. Treating it as a taxable fee would overcharge every order.
 *
 * A single product can carry several units of packaging (a six-pack is six
 * bottles), so the per-product unit count multiplies the amount.
 *
 * Optional module, OFF by default.
 */
final class DepositService implements HasHooks
{
    private const OPTION = 'polski_deposit';

    public const META_TYPE = '_polski_deposit_type';
    public const META_UNITS = '_polski_deposit_units';

    /** Statutory amounts in zloty at the scheme's launch. */
    private const DEFAULTS = [
        'pet' => 0.50,
        'can' => 0.50,
        'glass' => 1.00,
    ];

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('deposit');
    }

    public function registerHooks(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        add_action('woocommerce_cart_calculate_fees', [$this, 'applyCartFee']);
        add_action('woocommerce_single_product_summary', [$this, 'renderProductNotice'], 11);
    }

    /**
     * Packaging types offered in the product editor, keyed by meta value.
     *
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            '' => __('None (not covered by the deposit scheme)', 'polski'),
            'pet' => __('PET bottle, up to 3 l', 'polski'),
            'can' => __('Metal can, up to 1 l', 'polski'),
            'glass' => __('Reusable glass bottle, up to 1.5 l', 'polski'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $settings = get_option(self::OPTION, []);

        return is_array($settings) ? $settings : [];
    }

    /**
     * Deposit for one unit of the given packaging type, in the shop currency.
     */
    public function amountForType(string $type): float
    {
        if (! isset(self::DEFAULTS[$type])) {
            return 0.0;
        }

        $configured = $this->settings()['amount_' . $type] ?? null;

        if ($configured === null || $configured === '') {
            return self::DEFAULTS[$type];
        }

        return max(0.0, (float) $configured);
    }

    /**
     * Deposit carried by one item of this product, units included.
     *
     * A variation inherits its parent's packaging unless it sets its own, so a
     * shop only has to fill this in once for a product sold in several sizes of
     * the same bottle.
     */
    public function depositFor(\WC_Product $product): float
    {
        $type = (string) $product->get_meta(self::META_TYPE, true);
        $units = $product->get_meta(self::META_UNITS, true);

        if ($type === '' && $product->get_parent_id() > 0) {
            $parent = wc_get_product($product->get_parent_id());
            if ($parent instanceof \WC_Product) {
                $type = (string) $parent->get_meta(self::META_TYPE, true);
                $units = $parent->get_meta(self::META_UNITS, true);
            }
        }

        $amount = $this->amountForType($type);

        if ($amount <= 0.0) {
            return 0.0;
        }

        $units = max(1, (int) $units);

        return $amount * $units;
    }

    public function applyCartFee(\WC_Cart $cart): void
    {
        if (is_admin() && ! wp_doing_ajax()) {
            return;
        }

        $total = 0.0;

        foreach ($cart->get_cart() as $item) {
            $product = $item['data'] ?? null;

            if (! $product instanceof \WC_Product) {
                continue;
            }

            $total += $this->depositFor($product) * max(1, (int) ($item['quantity'] ?? 1));
        }

        if ($total <= 0.0) {
            return;
        }

        // Non-taxable on purpose: the deposit sits outside the VAT base at the
        // point of sale. See the class docblock.
        $cart->add_fee($this->feeLabel(), round($total, wc_get_price_decimals()), false);
    }

    public function renderProductNotice(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $deposit = $this->depositFor($product);

        if ($deposit <= 0.0) {
            return;
        }

        $amount = wc_price($deposit);
        $custom = trim((string) ($this->settings()['product_notice'] ?? ''));

        // A merchant's own sentence uses the named token {amount}, not a printf
        // placeholder. A stray % in a hand-typed sentence would otherwise make
        // sprintf() throw, and a translator cannot mangle a token the way they
        // can mangle %s.
        $text = $custom !== ''
            ? str_replace('{amount}', $amount, $custom)
            : sprintf(
                /* translators: %s: formatted deposit amount, for example 0,50 zl */
                __('Plus %s deposit, refunded when you return the packaging.', 'polski'),
                $amount,
            );

        printf('<p class="polski-deposit-notice">%s</p>', wp_kses_post($text));
    }

    private function feeLabel(): string
    {
        $label = trim((string) ($this->settings()['fee_label'] ?? ''));

        return $label !== '' ? $label : __('Deposit', 'polski');
    }
}
