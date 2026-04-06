<?php

declare(strict_types=1);
namespace Polski\Integration;

defined('ABSPATH') || exit;

use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;

/**
 * Detects installed third-party plugins and activates integration layers.
 */
final class IntegrationManager implements Bootable, HasHooks
{
    public function __construct()
    {
    }

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        add_action('plugins_loaded', [$this, 'detectIntegrations'], 20);
    }

    public function detectIntegrations(): void
    {
        $this->detectOmnibusPlugins();
        $this->detectCheckoutFieldsPlugin();
        $this->detectCookiesPlugin();
        $this->detectGPSRPlugin();
        $this->detectConsentModePlugin();
        $this->detectPaymentGateways();
    }

    /**
     * Detect compatible Omnibus extensions.
     * If found, integrate. Otherwise the built-in OmnibusService is used as fallback.
     */
    private function detectOmnibusPlugins(): void
    {
        if ($this->isPluginActive('wc-price-history/wc-price-history.php')) {
            do_action('polski/integration/omnibus_detected', 'omnibus-extension-a');
            return;
        }

        if ($this->isPluginActive('omnibus/omnibus.php')) {
            do_action('polski/integration/omnibus_detected', 'omnibus-extension-b');
            return;
        }

        // No external Omnibus plugin - use built-in fallback.
        do_action('polski/integration/omnibus_fallback');
    }

    private function detectCheckoutFieldsPlugin(): void
    {
        if ($this->isPluginActive('flexible-checkout-fields/flexible-checkout-fields.php')) {
            do_action('polski/integration/checkout_fields_detected', 'checkout-fields-extension');
        }
    }

    private function detectCookiesPlugin(): void
    {
        if ($this->isPluginActive('flexible-cookies/flexible-cookies.php')) {
            do_action('polski/integration/cookies_detected', 'cookies-extension');
        }
    }

    private function detectGPSRPlugin(): void
    {
        if ($this->isPluginActive('gpsr-for-woocommerce/gpsr-for-woocommerce.php')) {
            do_action('polski/integration/gpsr_detected', 'gpsr-extension');
        }
    }

    private function detectConsentModePlugin(): void
    {
        if ($this->isPluginActive('simple-consent-mode/simple-consent-mode.php')) {
            do_action('polski/integration/consent_mode_detected', 'consent-mode-extension');
        }
    }

    private function detectPaymentGateways(): void
    {
        $gateways = [
            'przelewy24' => 'woocommerce-przelewy24/woocommerce-przelewy24.php',
            'payu' => 'woo-payu-payment-gateway/woo-payu-payment-gateway.php',
            'tpay' => 'tpay-com-payment-gateway/tpay-com-payment-gateway.php',
            'autopay' => 'autopay-woocommerce/autopay-woocommerce.php',
        ];

        $detected = [];

        foreach ($gateways as $name => $file) {
            if ($this->isPluginActive($file)) {
                $detected[] = $name;
                do_action('polski/integration/payment_detected', $name);
            }
        }

        foreach ($this->detectActiveGatewayIds() as $gatewayId) {
            $gatewayName = $this->mapGatewayIdToIntegration($gatewayId);

            if ($gatewayName === null || in_array($gatewayName, $detected, true)) {
                continue;
            }

            $detected[] = $gatewayName;
            do_action('polski/integration/payment_detected', $gatewayName);
        }
    }

    /**
     * @return list<string>
     */
    private function detectActiveGatewayIds(): array
    {
        if (! function_exists('WC')) {
            return [];
        }

        $wc = WC();

        if (! $wc instanceof \WooCommerce) {
            return [];
        }

        $paymentGateways = $wc->payment_gateways();

        if (! $paymentGateways instanceof \WC_Payment_Gateways) {
            return [];
        }

        $gatewayObjects = $paymentGateways->payment_gateways();

        if (! is_array($gatewayObjects)) {
            return [];
        }

        $detected = [];

        foreach ($gatewayObjects as $gateway) {
            if (! $gateway instanceof \WC_Payment_Gateway || $gateway->enabled !== 'yes') {
                continue;
            }

            $gatewayId = strtolower((string) $gateway->id);

            if ($gatewayId !== '') {
                $detected[] = $gatewayId;
            }
        }

        return array_values(array_unique($detected));
    }

    private function mapGatewayIdToIntegration(string $gatewayId): ?string
    {
        return match (true) {
            str_contains($gatewayId, 'przelewy24'),
            str_contains($gatewayId, 'p24') => 'przelewy24',
            str_contains($gatewayId, 'payu') => 'payu',
            str_contains($gatewayId, 'tpay') => 'tpay',
            str_contains($gatewayId, 'autopay'),
            str_contains($gatewayId, 'bluepayment'),
            str_contains($gatewayId, 'blue_media') => 'autopay',
            str_contains($gatewayId, 'blik') => 'blik',
            default => null,
        };
    }

    private function isPluginActive(string $plugin): bool
    {
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active($plugin);
    }
}
