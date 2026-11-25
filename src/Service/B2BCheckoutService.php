<?php

declare(strict_types=1);

namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Util\NipValidator;
use WC_Order;

/**
 * B2B checkout fields for Polish stores.
 *
 * Adds an optional "Buying as company" toggle plus optional REGON, IBAN,
 * and NIP fields to WooCommerce classic checkout. The fields are saved
 * to standard order meta keys so KSeF and invoice integrations can consume
 * them without additional integration.
 *
 * The NIP field is not this service's. It belongs to the NIP module, which is
 * the switch a merchant can see and which also brings validation and GUS
 * autofill. This service used to add its own from an option no screen writes,
 * so switching the NIP module off left the field on checkout with nothing to
 * remove it. REGON, IBAN and the VAT invoice toggle are still added here.
 */
final class B2BCheckoutService
{
    public const OPTION = 'polski_b2b';

    private const META_COMPANY_FLAG = '_polski_buying_as_company';
    private const META_NEEDS_INVOICE = '_polski_needs_invoice';
    private const META_REGON = '_billing_regon';
    private const META_IBAN = '_billing_iban';

    /** Block checkout: the "Buying as" choice and the company name it reveals. */
    public const BUYER_TYPE_FIELD = 'polski/buyer_type';
    public const COMPANY_NAME_FIELD = 'polski/company_name';

    /**
     * @return array<string, bool>
     */
    public function fieldSettings(): array
    {
        $settings = get_option(self::OPTION, []);
        $defaults = [
            'enabled' => true,
            'show_company_toggle' => true,
            'show_needs_invoice_toggle' => false,
            'nip' => true,
            'regon' => false,
            'iban' => false,
            'block_buyer_type' => false,
        ];

        if (! is_array($settings)) {
            return $defaults;
        }

        return array_replace($defaults, array_intersect_key($settings, $defaults));
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->fieldSettings()['enabled'] ?? true);
    }

    /**
     * Whether the block checkout asks "Buying as: Personal / Company".
     *
     * Its own switch, off by default: show_company_toggle has always been on
     * for the classic checkout, so keying the block field on it alone would add
     * a required question to every block checkout on update.
     */
    public function buyerTypeChoiceEnabled(): bool
    {
        $settings = $this->fieldSettings();

        return $settings['enabled'] && $settings['show_company_toggle'] && $settings['block_buyer_type'];
    }

    /**
     * Rule on the block checkout document for the fields only a company fills
     * in. Required: the buyer chose Company. Hidden: a choice is in the
     * document and it is not Company, which on the checkout includes "not
     * chosen yet" (defaultBuyerType() puts an empty value there).
     *
     * My Account builds its document without any field values, so there
     * nothing is hidden and nothing is required: the form shows the stored
     * company name and NIP and saves them unchanged.
     *
     * @return array<string, mixed>
     */
    public static function companyOnlyRule(bool $forHidden): array
    {
        return [
            'customer' => [
                'properties' => [
                    'additional_fields' => [
                        // An empty list arrives as a JSON array, which the
                        // object keywords below would wave through.
                        'type' => 'object',
                        'properties' => [
                            self::BUYER_TYPE_FIELD => $forHidden ? ['not' => ['const' => 'company']] : ['const' => 'company'],
                        ],
                        'required' => [self::BUYER_TYPE_FIELD],
                    ],
                ],
                'required' => ['additional_fields'],
            ],
        ];
    }

    /**
     * The checkout starts with an empty "Buying as" rather than none, so the
     * company fields are hidden until the buyer picks Company.
     *
     * Hook: woocommerce_get_default_value_for_polski/buyer_type.
     */
    public function defaultBuyerType(mixed $value): mixed
    {
        return $value ?? '';
    }

    /**
     * Append polski B2B fields to the WC billing fields array.
     *
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, array<string, mixed>>
     */
    public function addBillingFields(array $fields): array
    {
        // This used to bail whenever the WC 8.6+ additional-fields API existed,
        // on the belief that WooCommerce renders those on the classic checkout
        // too. It does not: core feeds `address` fields to the block checkout,
        // the order confirmation and My Account only. Every shop on the
        // shortcode checkout therefore lost the company toggle, REGON and IBAN
        // the moment it reached WC 8.6. The duplicate this guard was aimed at
        // exists on one screen, the edit-address form, and AddressFormFieldDedupe
        // removes it there.
        if (! $this->isEnabled()) {
            return $fields;
        }

        $settings = $this->fieldSettings();

        if ($settings['show_company_toggle']) {
            $fields['polski_buying_as_company'] = [
                'label' => __('Buying as a company', 'polski'),
                'required' => false,
                'class' => ['form-row-wide'],
                'priority' => 25,
                'type' => 'checkbox',
            ];
        }

        if ($settings['show_needs_invoice_toggle']) {
            $fields['polski_needs_invoice'] = [
                'label' => __('I need a VAT invoice', 'polski'),
                'required' => false,
                'class' => ['form-row-wide'],
                'priority' => 26,
                'type' => 'checkbox',
            ];
        }

        if ($settings['regon']) {
            $fields['billing_regon'] = [
                'label' => __('REGON (statistical number)', 'polski'),
                'placeholder' => __('9 or 14 digits', 'polski'),
                'required' => false,
                'class' => ['form-row-wide', 'polski-b2b-field'],
                'priority' => 36,
                'type' => 'text',
            ];
        }

        if ($settings['iban']) {
            $fields['billing_iban'] = [
                'label' => __('Bank account (IBAN)', 'polski'),
                'placeholder' => __('PL00 0000 0000 0000 0000 0000 0000', 'polski'),
                'required' => false,
                'class' => ['form-row-wide', 'polski-b2b-field'],
                'priority' => 37,
                'type' => 'text',
            ];
        }

        return $fields;
    }

    public function validateAtCheckout(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC handles checkout nonce.
        $post = wp_unslash($_POST);

        $settings = $this->fieldSettings();

        if ($settings['regon']) {
            $regon = preg_replace('/\s+/', '', sanitize_text_field((string) ($post['billing_regon'] ?? '')));
            if ($regon !== '' && ! preg_match('/^\d{9}$|^\d{14}$/', (string) $regon)) {
                wc_add_notice(
                    __('REGON must contain exactly 9 or 14 digits.', 'polski'),
                    'error',
                );
            }
        }

        if ($settings['iban']) {
            $iban = preg_replace('/\s+/', '', strtoupper(sanitize_text_field((string) ($post['billing_iban'] ?? ''))));
            if ($iban !== '' && ! $this->isPlausibleIban((string) $iban)) {
                wc_add_notice(
                    __('The IBAN is invalid. Check the country code, length, and check digits.', 'polski'),
                    'error',
                );
            }
        }
    }

    /**
     * Persist B2B fields onto the order before WC writes it.
     *
     * @param array<string, mixed> $data
     */
    public function saveToOrder(WC_Order $order, array $data): void
    {
        unset($data); // WC passes the parsed POST but we read from $_POST for consistency.

        if (! $this->isEnabled()) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC handles checkout nonce.
        $post = wp_unslash($_POST);
        $settings = $this->fieldSettings();

        if ($settings['show_company_toggle']) {
            $isCompany = ! empty($post['polski_buying_as_company']);
            $order->update_meta_data(self::META_COMPANY_FLAG, $isCompany ? 'yes' : 'no');
        }

        if ($settings['show_needs_invoice_toggle']) {
            $needsInvoice = ! empty($post['polski_needs_invoice']);
            $order->update_meta_data(self::META_NEEDS_INVOICE, $needsInvoice ? 'yes' : 'no');
        }

        if ($settings['regon']) {
            $regon = preg_replace('/\s+/', '', sanitize_text_field((string) ($post['billing_regon'] ?? '')));
            if (is_string($regon) && $regon !== '') {
                $order->update_meta_data(self::META_REGON, $regon);
            }
        }

        if ($settings['iban']) {
            $iban = preg_replace('/\s+/', '', strtoupper(sanitize_text_field((string) ($post['billing_iban'] ?? ''))));
            if (is_string($iban) && $iban !== '') {
                $order->update_meta_data(self::META_IBAN, $iban);
            }
        }
    }

    /**
     * Append polski B2B fields to admin order billing display.
     *
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, array<string, mixed>>
     */
    public function addAdminBillingFields(array $fields): array
    {
        if (! $this->isEnabled()) {
            return $fields;
        }

        $settings = $this->fieldSettings();

        if ($settings['regon']) {
            $fields['regon'] = ['label' => __('REGON', 'polski'), 'show' => true];
        }

        if ($settings['iban']) {
            $fields['iban'] = ['label' => __('IBAN', 'polski'), 'show' => true];
        }

        return $fields;
    }

    /**
     * Show the company toggle's answer on the admin order screen.
     *
     * The flag is stored on the order at checkout but WooCommerce has no place
     * for it: addAdminBillingFields() only reaches _billing_* keys, so without
     * this the merchant could never see whether the customer declared a company
     * purchase. Rendered only when the value exists, so old orders stay clean.
     */
    public function renderCompanyFlagInAdmin(WC_Order $order): void
    {
        $flag = (string) $order->get_meta(self::META_COMPANY_FLAG, true);

        if ($flag === '') {
            return;
        }

        printf(
            '<p><strong>%s:</strong> %s</p>',
            esc_html__('Buying as a company', 'polski'),
            esc_html($flag === 'yes' ? __('Yes', 'polski') : __('No', 'polski')),
        );
    }

    /**
     * Whether WooCommerce 8.6+ unified additional checkout fields API is available.
     */
    public static function hasAdditionalFieldsApi(): bool
    {
        return function_exists('woocommerce_register_additional_checkout_field');
    }

    /**
     * Register fields via the unified WooCommerce API (WC 8.6+). Fields appear
     * in both classic and Block checkouts. Values land under
     * _wc_billing/<id> on the order; we mirror them to legacy
     * _billing_nip / _billing_regon / _billing_iban meta on save so the KSeF
     * and invoice modules pick them up without changes.
     */
    public function registerAdditionalCheckoutFields(): void
    {
        if (! $this->isEnabled() || ! self::hasAdditionalFieldsApi()) {
            return;
        }

        $settings = $this->fieldSettings();

        if ($settings['show_needs_invoice_toggle']) {
            woocommerce_register_additional_checkout_field([
                'id' => 'polski/needs_invoice',
                'label' => __('I need a VAT invoice', 'polski'),
                'location' => 'order',
                'type' => 'checkbox',
                'required' => false,
            ]);
        }

        if ($settings['regon']) {
            woocommerce_register_additional_checkout_field([
                'id' => 'polski/regon',
                'label' => __('REGON (statistical number)', 'polski'),
                'location' => 'address',
                'type' => 'text',
                'required' => false,
                'sanitize_callback' => static fn (string $value): string => (string) preg_replace('/\s+/', '', sanitize_text_field($value)),
                'validate_callback' => static function (string $value) {
                    if ($value === '') {
                        return null;
                    }
                    if (! preg_match('/^\d{9}$|^\d{14}$/', $value)) {
                        return new \WP_Error(
                            'polski_invalid_regon',
                            __('REGON must contain exactly 9 or 14 digits.', 'polski'),
                        );
                    }
                    return null;
                },
            ]);
        }

        if ($settings['iban']) {
            woocommerce_register_additional_checkout_field([
                'id' => 'polski/iban',
                'label' => __('Bank account (IBAN)', 'polski'),
                'location' => 'address',
                'type' => 'text',
                'required' => false,
                'sanitize_callback' => static fn (string $value): string => (string) preg_replace('/\s+/', '', strtoupper(sanitize_text_field($value))),
                'validate_callback' => function (string $value) {
                    if ($value === '') {
                        return null;
                    }
                    if (! $this->isPlausibleIban($value)) {
                        return new \WP_Error(
                            'polski_invalid_iban',
                            __('The IBAN is invalid. Check the country code, length, and check digits.', 'polski'),
                        );
                    }
                    return null;
                },
            ]);
        }
    }

    /**
     * Block checkout: "Buying as" right under the e-mail, then the company
     * name, shown and required only for Company. Registered after the contact
     * fields of CheckoutHooks (init 21) and before the NIP field (init 23), as
     * WooCommerce lists contact fields in the order they were registered.
     *
     * The company name is a field of its own because WooCommerce's Company
     * field is shown, hidden or required by one store-wide option, and no rule
     * can switch it per order. applyBuyerTypeToOrder() copies it to the
     * order's billing company, which invoices and KSeF read.
     */
    public function registerBuyerTypeFields(): void
    {
        if (! $this->buyerTypeChoiceEnabled() || ! self::hasAdditionalFieldsApi()) {
            return;
        }

        woocommerce_register_additional_checkout_field([
            'id' => self::BUYER_TYPE_FIELD,
            'label' => __('Buying as', 'polski'),
            'location' => 'contact',
            'type' => 'select',
            'required' => true,
            // WooCommerce's own placeholder reads "Select a buying as".
            'placeholder' => __('Personal or company', 'polski'),
            'options' => [
                ['value' => 'personal', 'label' => __('Personal', 'polski')],
                ['value' => 'company', 'label' => __('Company', 'polski')],
            ],
        ]);

        woocommerce_register_additional_checkout_field([
            'id' => self::COMPANY_NAME_FIELD,
            'label' => __('Company name', 'polski'),
            'location' => 'contact',
            'type' => 'text',
            'attributes' => ['autocomplete' => 'organization'],
            'hidden' => self::companyOnlyRule(true),
            'required' => self::companyOnlyRule(false),
        ]);
    }

    /**
     * After a block checkout order is saved: record the buyer type in the same
     * meta as the classic toggle, put a company's name in billing_company, and
     * drop the company data of a personal buyer. WooCommerce stores every
     * posted field, including one the buyer filled in and then hid by
     * switching to Personal, so a NIP typed by mistake would otherwise reach
     * the invoice.
     *
     * Hook: woocommerce_store_api_checkout_order_processed, after the NIP
     * module copied the number (priority 10).
     */
    public function applyBuyerTypeToOrder(WC_Order $order): void
    {
        if (! $this->buyerTypeChoiceEnabled()) {
            return;
        }

        $isCompany = $order->get_meta('_wc_other/' . self::BUYER_TYPE_FIELD, true) === 'company';
        $order->update_meta_data(self::META_COMPANY_FLAG, $isCompany ? 'yes' : 'no');

        if ($isCompany) {
            $order->set_billing_company((string) $order->get_meta('_wc_other/' . self::COMPANY_NAME_FIELD, true));
        } else {
            $order->set_billing_company('');
            foreach (['_wc_other/' . self::COMPANY_NAME_FIELD, '_wc_other/polski/nip', '_billing_nip', '_polski_billing_nip'] as $key) {
                $order->delete_meta_data($key);
            }
        }

        $order->save();
    }

    /**
     * Mirror values written by WC's additional-fields API to the legacy
     * _billing_* meta keys consumed by KSeF, the invoice module, and the
     * AI Feed invoice exporter.
     *
     * Hook: woocommerce_set_additional_field_value (5 args).
     *
     * @param string $key      Field key, e.g. polski/nip.
     * @param mixed  $value    Sanitized value.
     * @param string $group    Group: billing|shipping for address fields, other for order fields.
     * @param mixed  $document Either WC_Customer or WC_Order depending on context.
     */
    public function mirrorAdditionalFieldToLegacyMeta(string $key, mixed $value, string $group, mixed $document): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        // Order-location fields (needs_invoice) arrive as group 'other', address
        // fields once per address group. Only the billing copy is mirrored.
        $expectedGroup = $key === 'polski/needs_invoice' ? 'other' : 'billing';
        if ($group !== $expectedGroup) {
            return;
        }

        $metaKey = match ($key) {
            'polski/nip' => '_billing_nip',
            'polski/regon' => self::META_REGON,
            'polski/iban' => self::META_IBAN,
            'polski/needs_invoice' => self::META_NEEDS_INVOICE,
            default => null,
        };

        if ($metaKey === null) {
            return;
        }

        if ($key === 'polski/needs_invoice') {
            $stringValue = ! empty($value) ? 'yes' : 'no';
        } else {
            $stringValue = is_scalar($value) ? (string) $value : '';
            if ($stringValue === '') {
                return;
            }
        }

        if (is_object($document) && method_exists($document, 'update_meta_data')) {
            $document->update_meta_data($metaKey, $stringValue);
        }
    }

    /**
     * Validate an IBAN end to end: country prefix, length, character set,
     * country-specific length, and the mod-97 checksum (ISO 13616).
     *
     * The official algorithm:
     *   1. Move the first four characters (country code + check digits) to the end.
     *   2. Replace each letter with two digits (A=10, B=11, ..., Z=35).
     *   3. The resulting integer must be congruent to 1 mod 97.
     */
    private function isPlausibleIban(string $iban): bool
    {
        if ($iban === '') {
            return false;
        }

        $length = strlen($iban);
        if ($length < 15 || $length > 34) {
            return false;
        }

        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }

        $countryLength = self::IBAN_COUNTRY_LENGTHS[substr($iban, 0, 2)] ?? null;
        if ($countryLength !== null && $countryLength !== $length) {
            return false;
        }

        return $this->ibanChecksumMatches($iban);
    }

    /**
     * Mod-97 check digit verification per ISO 13616.
     *
     * BCMath isn't always available, so we compute the modulo digit by
     * digit, carrying an integer remainder small enough to fit in PHP_INT.
     */
    private function ibanChecksumMatches(string $iban): bool
    {
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);

        $expanded = '';
        $length = strlen($rearranged);
        for ($i = 0; $i < $length; $i++) {
            $char = $rearranged[$i];
            if ($char >= '0' && $char <= '9') {
                $expanded .= $char;
                continue;
            }
            if ($char >= 'A' && $char <= 'Z') {
                $expanded .= (string) (ord($char) - 55);
                continue;
            }
            return false;
        }

        $remainder = 0;
        $expandedLength = strlen($expanded);
        for ($i = 0; $i < $expandedLength; $i++) {
            $remainder = ($remainder * 10 + (int) $expanded[$i]) % 97;
        }

        return $remainder === 1;
    }

    /**
     * Country-code -> total IBAN length lookup (selected EU/CH/GB markets
     * a Polish merchant is most likely to encounter). Unknown country codes
     * pass length validation; the mod-97 check still applies.
     *
     * @var array<string, int>
     */
    private const IBAN_COUNTRY_LENGTHS = [
        'AT' => 20, 'BE' => 16, 'BG' => 22, 'CH' => 21, 'CY' => 28, 'CZ' => 24,
        'DE' => 22, 'DK' => 18, 'EE' => 20, 'ES' => 24, 'FI' => 18, 'FR' => 27,
        'GB' => 22, 'GR' => 27, 'HR' => 21, 'HU' => 28, 'IE' => 22, 'IT' => 27,
        'LT' => 20, 'LU' => 20, 'LV' => 21, 'MT' => 31, 'NL' => 18, 'NO' => 15,
        'PL' => 28, 'PT' => 25, 'RO' => 24, 'SE' => 24, 'SI' => 19, 'SK' => 24,
    ];
}
