<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * DPA (Data Processing Agreement) tracker.
 *
 * Auto-detects active plugins/services that likely process personal data
 * and helps shop owners track DPA status for each.
 *
 * @author wppoland.com
 */
final class DPATrackerService implements HasHooks
{
    private const OPTION_REGISTRY = 'polski_dpa_registry';
    private const NONCE_ACTION = 'polski_dpa_save';
    private const NONCE_FIELD = '_polski_dpa_nonce';

    public function registerHooks(): void
    {
        // Internal hooks can be added here if needed in the future.
    }

    /**
     * Render the DPA tracker admin page with detected services and save form.
     */
    public function renderTrackerPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to manage DPA registry.', 'polski'));
        }

        // Handle save.
        if (isset($_POST[self::NONCE_FIELD])) {
            check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

            $registry = [];
            $submittedServices = $_POST['polski_dpa'] ?? [];

            if (is_array($submittedServices)) {
                foreach ($submittedServices as $serviceKey => $serviceData) {
                    $registry[sanitize_key($serviceKey)] = [
                        'has_dpa' => ! empty($serviceData['has_dpa']),
                        'notes'   => sanitize_textarea_field($serviceData['notes'] ?? ''),
                    ];
                }
            }

            update_option(self::OPTION_REGISTRY, $registry);
        }

        $detectedServices = $this->detectServices();
        $registry = get_option(self::OPTION_REGISTRY, []);

        if (! is_array($registry)) {
            $registry = [];
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Rejestr umów powierzenia danych (DPA)', 'polski') . '</h1>';
        echo '<p>' . esc_html__('Wykryte usługi zewnętrzne przetwarzające dane osobowe Twoich klientów. Oznacz, dla których masz podpisaną umowę powierzenia.', 'polski') . '</p>';

        echo '<form method="post">';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        echo '<table class="widefat fixed striped"><thead><tr>';
        echo '<th>' . esc_html__('Usługa', 'polski') . '</th>';
        echo '<th>' . esc_html__('Typ', 'polski') . '</th>';
        echo '<th style="width:80px;">' . esc_html__('DPA', 'polski') . '</th>';
        echo '<th>' . esc_html__('Notatki', 'polski') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($detectedServices as $service) {
            $serviceKey = $service['key'];
            $hasDpa = $registry[$serviceKey]['has_dpa'] ?? false;
            $notes = $registry[$serviceKey]['notes'] ?? '';

            echo '<tr>';
            echo '<td><strong>' . esc_html($service['name']) . '</strong></td>';
            echo '<td>' . esc_html($service['type']) . '</td>';
            echo '<td><input type="checkbox" name="polski_dpa[' . esc_attr($serviceKey) . '][has_dpa]" value="1" ' . checked($hasDpa, true, false) . '></td>';
            echo '<td><input type="text" name="polski_dpa[' . esc_attr($serviceKey) . '][notes]" value="' . esc_attr($notes) . '" class="regular-text" style="width:100%;"></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        submit_button(__('Zapisz rejestr', 'polski'));
        echo '</form></div>';
    }

    /**
     * Detect active services that likely process personal data.
     *
     * @return list<array{key: string, name: string, type: string}>
     */
    private function detectServices(): array
    {
        $services = [];

        // Hosting is always present.
        $services[] = [
            'key'  => 'hosting',
            'name' => __('Hosting (serwer)', 'polski'),
            'type' => __('Hosting', 'polski'),
        ];

        // WooCommerce payment gateways and shipping methods.
        if (function_exists('WC') && WC()->payment_gateways()) {
            $availableGateways = WC()->payment_gateways()->get_available_payment_gateways();

            foreach ($availableGateways as $gatewayId => $gateway) {
                $services[] = [
                    'key'  => 'payment_' . sanitize_key($gatewayId),
                    'name' => $gateway->get_title(),
                    'type' => __('Płatności', 'polski'),
                ];
            }
        }

        if (function_exists('WC') && class_exists('\WC_Shipping_Zones')) {
            $zones = \WC_Shipping_Zones::get_zones();
            $seenMethodIds = [];

            foreach ($zones as $zoneData) {
                if (! isset($zoneData['shipping_methods']) || ! is_array($zoneData['shipping_methods'])) {
                    continue;
                }

                foreach ($zoneData['shipping_methods'] as $method) {
                    $methodId = $method->id;

                    if (isset($seenMethodIds[$methodId])) {
                        continue;
                    }

                    $services[] = [
                        'key'  => 'shipping_' . sanitize_key($methodId),
                        'name' => $method->get_title(),
                        'type' => __('Wysyłka', 'polski'),
                    ];

                    $seenMethodIds[$methodId] = true;
                }
            }
        }

        // Known analytics, email marketing, and advertising plugins.
        $knownPlugins = [
            'google-analytics-for-wordpress/googleanalytics.php' => [
                'Google Analytics',
                __('Analityka', 'polski'),
            ],
            'google-site-kit/google-site-kit.php' => [
                'Google Site Kit',
                __('Analityka', 'polski'),
            ],
            'mailchimp-for-woocommerce/mailchimp-woocommerce.php' => [
                'Mailchimp',
                __('Email marketing', 'polski'),
            ],
            'mailpoet/mailpoet.php' => [
                'MailPoet',
                __('Email marketing', 'polski'),
            ],
            'woocommerce-google-analytics-integration/woocommerce-google-analytics-integration.php' => [
                'Google Analytics (WC)',
                __('Analityka', 'polski'),
            ],
            'facebook-for-woocommerce/facebook-for-woocommerce.php' => [
                'Meta/Facebook',
                __('Reklamy', 'polski'),
            ],
            'pinterest-for-woocommerce/pinterest-for-woocommerce.php' => [
                'Pinterest',
                __('Reklamy', 'polski'),
            ],
        ];

        foreach ($knownPlugins as $pluginFile => [$pluginName, $pluginType]) {
            if (is_plugin_active($pluginFile)) {
                $services[] = [
                    'key'  => 'plugin_' . sanitize_key($pluginName),
                    'name' => $pluginName,
                    'type' => $pluginType,
                ];
            }
        }

        return $services;
    }
}
