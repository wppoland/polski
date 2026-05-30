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

    public function registerHooks(): void
    {
        add_action('woocommerce_review_order_before_submit', [$this, 'renderCheckoutField']);
        add_action('woocommerce_checkout_process', [$this, 'validateCheckout']);
        add_action('woocommerce_checkout_create_order', [$this, 'persistConsent'], 10, 2);
        add_filter('polski/withdrawal/eligible', [$this, 'filterEligibility'], 20, 2);
    }

    public function renderCheckoutField(): void
    {
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
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }
            $value = sanitize_text_field((string) wp_unslash($_SERVER[$key]));
            $value = trim(explode(',', $value)[0] ?? '');
            if ($value !== '') {
                return $value;
            }
        }
        return '0.0.0.0';
    }
}
