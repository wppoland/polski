<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * Art. 16(m) of Directive 2011/83/EU (Art. 38 pkt 13 ustawy o prawach konsumenta):
 * a consumer loses the right of withdrawal for digital content delivered on a
 * non-tangible medium only if they actively consented before performance began.
 *
 * Three operating modes (configurable on the withdrawal settings page):
 *  - `required` - checkout blocks until the consumer ticks the consent box, the
 *    consent is then stored on the order and any digital-only order is exempt.
 *  - `optional` - the box is shown but unchecked; only orders where the consumer
 *    ticked it become exempt.
 *  - `hidden`   - no box is shown; digital orders retain the right of withdrawal.
 *
 * The stored consent record is a JSON blob in order meta with the wording snapshot,
 * timestamp and IP, so the store can prove the consent was collected.
 */
final class DigitalConsentService implements HasHooks
{
    public const MODE_REQUIRED = 'required';
    public const MODE_OPTIONAL = 'optional';
    public const MODE_HIDDEN = 'hidden';

    private const FIELD_KEY = 'polski_digital_consent';
    private const ORDER_META = '_polski_digital_consent';
    private const SETTING_OPTION = 'polski_withdrawal';

    private const BLOCK_FIELD_ID = 'polski/digital_consent';
    private const BLOCK_FIELD_META = '_wc_other/polski/digital_consent';

    public function registerHooks(): void
    {
        add_filter('polski/withdrawal/eligible', [$this, 'filterEligibility'], 20, 2);

        // Modern WooCommerce: a single Additional Checkout Field renders and
        // validates on BOTH classic and block checkout, shown/required only when
        // the cart contains digital content via a declarative rule on a cart-level
        // extension flag. This covers the block checkout (WC 8.3+ default).
        //
        // This plugin boots on `init`, by which point `woocommerce_blocks_loaded`
        // and `woocommerce_init` have already fired; registering on those actions
        // would silently no-op. Register immediately when they have already run,
        // otherwise defer to the action. The registries are read per-request
        // (Store API response / checkout render), so registering on `init` is in
        // time.
        $this->runAfter('woocommerce_blocks_loaded', [$this, 'registerCartFlag']);
        $this->runAfter('woocommerce_init', [$this, 'registerBlockField']);

        // Legacy classic-checkout field. These self-skip when the Additional
        // Checkout Fields API is available (it also renders in classic checkout,
        // so running both would double the field). On older WC without the API
        // they are the only path.
        add_action('woocommerce_review_order_before_submit', [$this, 'renderCheckoutField']);
        add_action('woocommerce_checkout_process', [$this, 'validateCheckout']);
        add_action('woocommerce_checkout_create_order', [$this, 'persistConsent'], 10, 2);
    }

    public static function hasAdditionalFieldsApi(): bool
    {
        return function_exists('woocommerce_register_additional_checkout_field');
    }

    /**
     * Invoke a callback immediately if the action already fired, else hook it.
     */
    private function runAfter(string $action, callable $callback): void
    {
        if (did_action($action) > 0) {
            $callback();
            return;
        }

        add_action($action, $callback);
    }

    /**
     * Expose a cart-level boolean the conditional checkout-field rule keys on.
     * `items_type` only reports product get_type() (simple/variable), not
     * downloadable/virtual, so a custom flag is required; it also correctly
     * covers mixed (physical + digital) carts that needs_shipping cannot.
     */
    public function registerCartFlag(): void
    {
        if (! function_exists('woocommerce_store_api_register_endpoint_data')) {
            return;
        }

        woocommerce_store_api_register_endpoint_data([
            'endpoint' => 'cart', // CartSchema::IDENTIFIER; literal mirrors ProductDataExtension's 'cart-item'.
            // Dedicated namespace (ProductDataExtension already registers 'polski'
            // on the product/cart-item endpoints).
            'namespace' => 'polski_consent',
            'data_callback' => fn (): array => ['has_digital_content' => $this->hasDigitalContentInCart()],
            'schema_callback' => static fn (): array => ['has_digital_content' => [
                'description' => 'Cart contains downloadable or virtual products',
                'type' => 'boolean',
                'context' => ['view', 'edit'],
                'readonly' => true,
            ]],
            'schema_type' => ARRAY_A,
        ]);
    }

    public function registerBlockField(): void
    {
        if (! function_exists('woocommerce_register_additional_checkout_field')) {
            return;
        }

        $mode = $this->mode();
        if ($mode === self::MODE_HIDDEN) {
            return;
        }

        $field = [
            'id' => self::BLOCK_FIELD_ID,
            // Additional-field labels are plain text (no rich HTML), unlike the
            // classic field which uses wp_kses_post.
            'label' => wp_strip_all_tags($this->consentLabel()),
            'location' => 'order',
            'type' => 'checkbox',
            // Hide the box unless the cart has digital content.
            'hidden' => $this->cartDigitalRule(false),
        ];

        if ($mode === self::MODE_REQUIRED) {
            // Require acceptance only when the cart has digital content.
            $field['required'] = $this->cartDigitalRule(true);
            $field['error_message'] = __('Aby zakupić produkty cyfrowe musisz wyrazić zgodę na rozpoczęcie świadczenia przed upływem terminu odstąpienia.', 'polski');
        }

        woocommerce_register_additional_checkout_field($field);
    }

    /**
     * Draft-07 JSON-Schema rule matched against the checkout document object:
     * fires when cart.extensions.polski.has_digital_content === $expected.
     *
     * @return array<string, mixed>
     */
    private function cartDigitalRule(bool $expected): array
    {
        return [
            'cart' => [
                'properties' => [
                    'extensions' => [
                        'properties' => [
                            'polski_consent' => [
                                'properties' => [
                                    'has_digital_content' => ['const' => $expected],
                                ],
                                'required' => ['has_digital_content'],
                            ],
                        ],
                        'required' => ['polski_consent'],
                    ],
                ],
            ],
        ];
    }

    public function renderCheckoutField(): void
    {
        if (self::hasAdditionalFieldsApi()) {
            return; // handled by the Additional Checkout Field in both checkouts.
        }

        if (! $this->hasDigitalContentInCart()) {
            return;
        }

        $mode = $this->mode();

        if ($mode === self::MODE_HIDDEN) {
            return;
        }

        $required = $mode === self::MODE_REQUIRED;
        $label = $this->consentLabel();

        ?>
        <p class="form-row polski-digital-consent">
            <label for="<?php echo esc_attr(self::FIELD_KEY); ?>">
                <input
                    type="checkbox"
                    id="<?php echo esc_attr(self::FIELD_KEY); ?>"
                    name="<?php echo esc_attr(self::FIELD_KEY); ?>"
                    value="1"
                    <?php echo $required ? 'required' : ''; ?>
                >
                <?php echo wp_kses_post($label); ?>
                <?php if ($required) : ?>
                    <abbr class="required" title="<?php esc_attr_e('required', 'polski'); ?>">*</abbr>
                <?php endif; ?>
            </label>
        </p>
        <?php
    }

    public function validateCheckout(): void
    {
        if (self::hasAdditionalFieldsApi()) {
            return; // the Additional Checkout Field enforces required on its own.
        }

        if ($this->mode() !== self::MODE_REQUIRED) {
            return;
        }

        if (! $this->hasDigitalContentInCart()) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC checkout already verifies its own nonce.
        if (empty($_POST[self::FIELD_KEY])) {
            wc_add_notice(
                __('Aby zakupić produkty cyfrowe musisz wyrazić zgodę na rozpoczęcie świadczenia przed upływem terminu odstąpienia.', 'polski'),
                'error',
            );
        }
    }

    public function persistConsent(\WC_Order $order, array $data): void
    {
        unset($data);

        if (self::hasAdditionalFieldsApi()) {
            return; // WC stores the Additional Checkout Field value itself.
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC checkout already verifies its own nonce.
        $accepted = ! empty($_POST[self::FIELD_KEY]);

        if (! $accepted) {
            return;
        }

        $record = [
            'accepted' => true,
            'mode' => $this->mode(),
            'wording' => $this->consentLabel(),
            'recorded_at' => current_time('mysql', true),
            'ip' => $this->clientIp(),
        ];

        $order->update_meta_data(self::ORDER_META, $record);
    }

    /**
     * If the order is entirely digital and the consumer accepted the Art. 16(m)
     * consent, the order is exempt from the right of withdrawal.
     */
    public function filterEligibility(bool $eligible, \WC_Order $order): bool
    {
        if (! $eligible) {
            return false;
        }

        if (! $this->isOrderEntirelyDigital($order)) {
            return $eligible;
        }

        $record = $order->get_meta(self::ORDER_META, true);
        if (is_array($record) && ! empty($record['accepted'])) {
            return false;
        }

        // Block / Additional Checkout Fields path: WC stores the checkbox value
        // under the field meta key. A truthy value is an explicit consent.
        $blockValue = $order->get_meta(self::BLOCK_FIELD_META, true);
        if (in_array($blockValue, ['1', 1, true, 'true', 'yes'], true)) {
            return false;
        }

        return $eligible;
    }

    public function mode(): string
    {
        $settings = get_option(self::SETTING_OPTION, []);
        $settings = is_array($settings) ? $settings : [];
        $mode = (string) ($settings['digital_consent_mode'] ?? self::MODE_OPTIONAL);

        return in_array($mode, [self::MODE_REQUIRED, self::MODE_OPTIONAL, self::MODE_HIDDEN], true)
            ? $mode
            : self::MODE_OPTIONAL;
    }

    private function consentLabel(): string
    {
        $settings = get_option(self::SETTING_OPTION, []);
        $settings = is_array($settings) ? $settings : [];
        $label = (string) ($settings['digital_consent_label'] ?? '');

        if ($label === '') {
            $label = __('Wyrażam zgodę na rozpoczęcie spełniania świadczenia (np. dostarczenie treści cyfrowych) przed upływem terminu odstąpienia od umowy i przyjmuję do wiadomości utratę prawa odstąpienia w odniesieniu do dostarczonych w ten sposób treści cyfrowych.', 'polski');
        }

        /**
         * Filter the Art. 16(m) consent label.
         *
         * @param string $label
         */
        return (string) apply_filters('polski/digital_consent/label', $label);
    }

    private function hasDigitalContentInCart(): bool
    {
        if (! function_exists('WC') || WC()->cart === null) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $item) {
            if (! isset($item['data']) || ! $item['data'] instanceof \WC_Product) {
                continue;
            }

            if ($this->isDigitalProduct($item['data'])) {
                return true;
            }
        }

        return false;
    }

    private function isOrderEntirelyDigital(\WC_Order $order): bool
    {
        $hasDigital = false;

        foreach ($order->get_items() as $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }
            $product = $item->get_product();
            if (! $product instanceof \WC_Product) {
                continue;
            }
            if (! $this->isDigitalProduct($product)) {
                return false;
            }
            $hasDigital = true;
        }

        return $hasDigital;
    }

    private function isDigitalProduct(\WC_Product $product): bool
    {
        return $product->is_downloadable() || $product->is_virtual();
    }

    private function clientIp(): string
    {
        // Trust only REMOTE_ADDR unless an explicit reverse proxy is configured;
        // spoofable forwarding headers would forge the consent-log audit IP.
        return \Polski\Util\ClientIp::resolve();
    }
}
