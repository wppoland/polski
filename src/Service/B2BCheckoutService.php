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
 * to standard order meta keys so polski-pro (KSeF, invoices) can consume
 * them without additional integration.
 *
 * Coexistence with polski-pro NipValidator: when pro registers its own
 * billing_nip field this service skips its own NIP registration to avoid
 * a duplicate field. REGON and IBAN are always added by free.
 */
final class B2BCheckoutService
{
    public const OPTION = 'polski_b2b';

    private const META_COMPANY_FLAG = '_polski_buying_as_company';
    private const META_REGON = '_billing_regon';
    private const META_IBAN = '_billing_iban';

    /**
     * @return array<string, bool>
     */
    public function fieldSettings(): array
    {
        $settings = get_option(self::OPTION, []);
        $defaults = [
            'enabled' => true,
            'show_company_toggle' => true,
            'nip' => true,
            'regon' => false,
            'iban' => false,
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
     * Should we register our own NIP field? Skipped when polski-pro's
     * NipValidator is active to avoid double registration.
     */
    public function shouldRegisterNipField(): bool
    {
        if (! ($this->fieldSettings()['nip'] ?? true)) {
            return false;
        }

        if (class_exists('\Polski\Pro\Validation\NipValidator')) {
            return false;
        }

        return true;
    }

    /**
     * Append polski B2B fields to the WC billing fields array.
     *
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, array<string, mixed>>
     */
    public function addBillingFields(array $fields): array
    {
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

        if ($this->shouldRegisterNipField()) {
            $fields['billing_nip'] = [
                'label' => __('NIP (Tax ID)', 'polski'),
                'placeholder' => __('e.g. 123-456-78-90', 'polski'),
                'required' => false,
                'class' => ['form-row-wide', 'polski-b2b-field'],
                'priority' => 35,
                'type' => 'text',
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

        if ($this->shouldRegisterNipField()) {
            $nip = sanitize_text_field((string) ($post['billing_nip'] ?? ''));
            if ($nip !== '' && ! NipValidator::isValid($nip)) {
                wc_add_notice(
                    __('The provided NIP number is invalid. Please check and try again.', 'polski'),
                    'error',
                );
            }
        }

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
                    __('The IBAN format is not recognised. Use PL followed by 26 digits, or another valid IBAN.', 'polski'),
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

        if ($this->shouldRegisterNipField()) {
            $nip = NipValidator::normalize(sanitize_text_field((string) ($post['billing_nip'] ?? '')));
            if ($nip !== '') {
                $order->update_meta_data('_billing_nip', $nip);
            }
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

        if ($this->shouldRegisterNipField()) {
            $fields['nip'] = ['label' => __('NIP', 'polski'), 'show' => true];
        }

        if ($settings['regon']) {
            $fields['regon'] = ['label' => __('REGON', 'polski'), 'show' => true];
        }

        if ($settings['iban']) {
            $fields['iban'] = ['label' => __('IBAN', 'polski'), 'show' => true];
        }

        return $fields;
    }

    /**
     * Plausible IBAN: 2 letters + 2 digits + up to 30 alphanumeric chars,
     * length 15..34. Strict mod-97 check is left to integrators.
     */
    private function isPlausibleIban(string $iban): bool
    {
        if ($iban === '') {
            return false;
        }
        $length = strlen($iban);

        return $length >= 15 && $length <= 34 && (bool) preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban);
    }
}
