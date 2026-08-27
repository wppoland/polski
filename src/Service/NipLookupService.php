<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * NIP (Polish tax identification number) field, validation, and company data lookup.
 *
 * Adds a NIP field to checkout, validates the checksum, and optionally
 * fetches company data from the public GUS REGON API.
 */
final class NipLookupService implements HasHooks
{
    /** NIP checksum weights per Polish tax law. */
    private const WEIGHTS = [6, 5, 7, 2, 3, 4, 5, 6, 7];

    public function registerHooks(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        // Add NIP field to checkout billing form (classic checkout).
        add_filter('woocommerce_billing_fields', [$this, 'addNipField']);

        // Modern WC 8.6+ unified additional checkout fields API (Block + classic in one go).
        if (function_exists('woocommerce_register_additional_checkout_field')) {
            add_action('woocommerce_init', [$this, 'registerAdditionalCheckoutFields']);
            add_action('woocommerce_set_additional_field_value', [$this, 'mirrorAdditionalFieldToLegacyMeta'], 10, 4);
            add_action('woocommerce_checkout_order_created', [$this, 'saveBlockNipToOrder']);
            add_action('woocommerce_store_api_checkout_order_processed', [$this, 'saveBlockNipToOrder']);
        }

        // Validate NIP on checkout.
        add_action('woocommerce_checkout_process', [$this, 'validateNipOnCheckout']);

        // Save NIP to order meta.
        add_action('woocommerce_checkout_create_order', [$this, 'saveNipToOrder'], 10, 2);

        // Display NIP in admin order billing section.
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'displayNipInAdmin']);

        // AJAX lookup endpoint.
        add_action('wp_ajax_polski_nip_lookup', [$this, 'handleNipLookup']);
        add_action('wp_ajax_nopriv_polski_nip_lookup', [$this, 'handleNipLookup']);

        // Enqueue frontend script for auto-fill.
        add_action('wp_enqueue_scripts', [$this, 'enqueueCheckoutScript']);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('nip_lookup');
    }

    /**
     * Add NIP field to WooCommerce billing fields.
     *
     * @param array<string, array<string, mixed>> $fields Billing fields.
     * @return array<string, array<string, mixed>>
     */
    public function addNipField(array $fields): array
    {
        $settings = $this->getSettings();
        $required = ! empty($settings['nip_required']);

        $fields['billing_nip'] = [
            'type'        => 'text',
            'label'       => __('NIP', 'polski'),
            'placeholder' => __('np. 1234563218', 'polski'),
            'required'    => $required,
            'class'       => ['form-row-wide'],
            'priority'    => 31, // After company name (priority 30).
            'maxlength'   => 13, // 10 digits + optional dashes.
            'custom_attributes' => [
                'pattern'                => '[0-9\-]{10,13}',
                'data-polski-nip-field'  => '1',
            ],
        ];

        return $fields;
    }

    /**
     * Validate NIP checksum during checkout.
     */
    public function validateNipOnCheckout(): void
    {
        $nip = sanitize_text_field(wp_unslash($_POST['billing_nip'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if ($nip === '') {
            return; // Empty is OK if not required (WooCommerce handles required validation).
        }

        if (! self::isValidNip($nip)) {
            wc_add_notice(
                __('Podany NIP jest nieprawidlowy. Sprawdz numer i sprobuj ponownie.', 'polski'),
                'error',
            );
        }
    }

    /**
     * Validate a Polish NIP number using the checksum algorithm.
     *
     * Weights: 6, 5, 7, 2, 3, 4, 5, 6, 7.
     * The check digit (10th) must equal (weighted sum mod 11).
     */
    public static function isValidNip(string $nip): bool
    {
        // Strip dashes and spaces.
        $nip = preg_replace('/[\s\-]/', '', $nip);

        if ($nip === null || strlen($nip) !== 10 || ! ctype_digit($nip)) {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $nip[$i] * self::WEIGHTS[$i];
        }

        return ($sum % 11) === (int) $nip[9];
    }

    /**
     * Register NIP as an additional checkout field on WooCommerce 8.6+ (Block + classic).
     */
    public function registerAdditionalCheckoutFields(): void
    {
        if (! $this->isEnabled() || ! function_exists('woocommerce_register_additional_checkout_field')) {
            return;
        }

        // If B2B module is active, it handles its own NIP field to avoid duplication.
        if (ModulesPage::isModuleEnabled('b2b_checkout')) {
            return;
        }

        $settings = $this->getSettings();
        $required = ! empty($settings['nip_required']);

        woocommerce_register_additional_checkout_field([
            'id' => 'polski/nip',
            'label' => __('NIP', 'polski'),
            'location' => 'address',
            'type' => 'text',
            'required' => $required,
            'sanitize_callback' => static fn (string $value): string => (string) preg_replace('/[^0-9]/', '', sanitize_text_field($value)),
            'validate_callback' => static function (string $value) {
                $clean = (string) preg_replace('/[^0-9]/', '', $value);
                if ($clean === '') {
                    return null;
                }
                if (! self::isValidNip($clean)) {
                    return new \WP_Error(
                        'polski_invalid_nip',
                        __('Podany numer NIP jest nieprawidłowy.', 'polski'),
                    );
                }
                return null;
            },
        ]);
    }

    /**
     * Mirror additional field value to standard order/customer meta on save.
     */
    public function mirrorAdditionalFieldToLegacyMeta(string $key, mixed $value, string $group, mixed $document): void
    {
        if (! $this->isEnabled() || $group !== 'billing' || $key !== 'polski/nip') {
            return;
        }

        $clean = is_scalar($value) ? (string) preg_replace('/[^0-9]/', '', (string) $value) : '';
        if ($clean === '') {
            return;
        }

        if (is_object($document) && method_exists($document, 'update_meta_data')) {
            $document->update_meta_data('_billing_nip', $clean);
            $document->update_meta_data('_polski_billing_nip', $clean);
        }
    }

    /**
     * Save block checkout NIP to standard order meta.
     */
    public function saveBlockNipToOrder(\WC_Order $order): void
    {
        $nip = $order->get_meta('_wc_billing/polski/nip', true);
        if ($nip === '' || $nip === false) {
            $nip = $order->get_meta('_wc_other/polski/nip', true);
        }
        if ($nip !== '' && $nip !== false && is_scalar($nip)) {
            $clean = (string) preg_replace('/[^0-9]/', '', (string) $nip);
            if ($clean !== '') {
                $order->update_meta_data('_billing_nip', $clean);
                $order->update_meta_data('_polski_billing_nip', $clean);
                $order->save_meta_data();
            }
        }
    }

    /**
     * Save NIP to order meta on checkout.
     *
     * @param array<string, mixed> $data
     */
    public function saveNipToOrder(\WC_Order $order, array $data): void
    {
        $nip = sanitize_text_field(wp_unslash($_POST['billing_nip'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if ($nip !== '') {
            $clean = (string) preg_replace('/[^0-9]/', '', $nip);
            $order->update_meta_data('_billing_nip', $clean);
            $order->update_meta_data('_polski_billing_nip', $clean);
        }
    }

    /**
     * Display NIP in admin order billing address section.
     */
    public function displayNipInAdmin(\WC_Order $order): void
    {
        $nip = $order->get_meta('_billing_nip', true);
        if ($nip === '' || $nip === false) {
            $nip = $order->get_meta('_polski_billing_nip', true);
        }
        if ($nip === '' || $nip === false) {
            $nip = $order->get_meta('_wc_billing/polski/nip', true);
        }
        if ($nip === '' || $nip === false) {
            $nip = $order->get_meta('_wc_other/polski/nip', true);
        }

        if ($nip !== '' && $nip !== false) {
            printf(
                '<p><strong>%s:</strong> %s</p>',
                esc_html__('NIP', 'polski'),
                esc_html((string) $nip),
            );
        }
    }

    /**
     * AJAX handler: look up company data by NIP from GUS REGON API.
     */
    public function handleNipLookup(): void
    {
        check_ajax_referer('polski_nip_lookup', '_nonce');

        $nip = sanitize_text_field(wp_unslash($_POST['nip'] ?? ''));

        if (! self::isValidNip($nip)) {
            wp_send_json_error(['message' => __('Nieprawidlowy NIP.', 'polski')]);
        }

        $nip = preg_replace('/[\s\-]/', '', $nip) ?? '';
        $result = $this->lookupNip($nip);

        if ($result === null) {
            wp_send_json_error(['message' => __('Nie znaleziono danych dla podanego NIP. Sprawdz numer lub uzupelnij dane recznie.', 'polski')]);
        }

        wp_send_json_success($result);
    }

    /**
     * Look up company data from GUS REGON API (public BIR1 service).
     *
     * Uses direct SOAP 1.2 HTTP POST requests compatible with GUS MTOM/XOP responses.
     *
     * @return array{name: string, address: string, postcode: string, city: string, regon: string}|null
     */
    private function lookupNip(string $nip): ?array
    {
        $settings    = $this->getSettings();
        $environment = $settings['gus_environment'] ?? 'test';

        if ($environment === 'production') {
            $url    = 'https://wyszukiwarkaregon.stat.gov.pl/wsBIR/UslugaBIRzewnPubl.svc';
            $apiKey = (string) ($settings['gus_api_key'] ?? '');
        } else {
            $url    = 'https://wyszukiwarkaregontest.stat.gov.pl/wsBIR/UslugaBIRzewnPubl.svc';
            $apiKey = 'abcde12345abcde12345';
        }

        if ($apiKey === '') {
            return null;
        }

        // 1. Zaloguj (obtain session id).
        $loginXml = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:ns="http://CIS/BIR/PUBL/2014/07">'
            . '<soap:Header xmlns:wsa="http://www.w3.org/2005/08/addressing">'
            . '<wsa:To>' . esc_url($url) . '</wsa:To>'
            . '<wsa:Action>http://CIS/BIR/PUBL/2014/07/IUslugaBIRzewnPubl/Zaloguj</wsa:Action>'
            . '</soap:Header>'
            . '<soap:Body>'
            . '<ns:Zaloguj><ns:pKluczUzytkownika>' . esc_xml($apiKey) . '</ns:pKluczUzytkownika></ns:Zaloguj>'
            . '</soap:Body>'
            . '</soap:Envelope>';

        $loginResponse = wp_remote_post($url, [
            'timeout'   => 10,
            'sslverify' => true,
            'headers'   => [
                'Content-Type' => 'application/soap+xml;charset=UTF-8;action="http://CIS/BIR/PUBL/2014/07/IUslugaBIRzewnPubl/Zaloguj"',
            ],
            'body'      => $loginXml,
        ]);

        if (is_wp_error($loginResponse)) {
            return null;
        }

        $loginBody = wp_remote_retrieve_body($loginResponse);
        if (! preg_match('/<ZalogujResult>(.*?)<\/ZalogujResult>/', $loginBody, $matches) || empty($matches[1])) {
            return null;
        }

        $sessionId = trim($matches[1]);

        // 2. DaneSzukajPodmioty (search by NIP).
        $searchXml = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:ns="http://CIS/BIR/PUBL/2014/07" xmlns:dat="http://CIS/BIR/PUBL/2014/07/DataContract">'
            . '<soap:Header xmlns:wsa="http://www.w3.org/2005/08/addressing">'
            . '<wsa:To>' . esc_url($url) . '</wsa:To>'
            . '<wsa:Action>http://CIS/BIR/PUBL/2014/07/IUslugaBIRzewnPubl/DaneSzukajPodmioty</wsa:Action>'
            . '</soap:Header>'
            . '<soap:Body>'
            . '<ns:DaneSzukajPodmioty>'
            . '<ns:pParametryWyszukiwania><dat:Nip>' . esc_xml($nip) . '</dat:Nip></ns:pParametryWyszukiwania>'
            . '</ns:DaneSzukajPodmioty>'
            . '</soap:Body>'
            . '</soap:Envelope>';

        $searchResponse = wp_remote_post($url, [
            'timeout'   => 10,
            'sslverify' => true,
            'headers'   => [
                'Content-Type' => 'application/soap+xml;charset=UTF-8;action="http://CIS/BIR/PUBL/2014/07/IUslugaBIRzewnPubl/DaneSzukajPodmioty"',
                'sid'          => $sessionId,
            ],
            'body'      => $searchXml,
        ]);

        // 3. Wyloguj (cleanup session).
        $logoutXml = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:ns="http://CIS/BIR/PUBL/2014/07">'
            . '<soap:Header xmlns:wsa="http://www.w3.org/2005/08/addressing">'
            . '<wsa:To>' . esc_url($url) . '</wsa:To>'
            . '<wsa:Action>http://CIS/BIR/PUBL/2014/07/IUslugaBIRzewnPubl/Wyloguj</wsa:Action>'
            . '</soap:Header>'
            . '<soap:Body>'
            . '<ns:Wyloguj><ns:pIdentyfikatorSesji>' . esc_xml($sessionId) . '</ns:pIdentyfikatorSesji></ns:Wyloguj>'
            . '</soap:Body>'
            . '</soap:Envelope>';

        wp_remote_post($url, [
            'timeout'   => 5,
            'sslverify' => true,
            'headers'   => [
                'Content-Type' => 'application/soap+xml;charset=UTF-8;action="http://CIS/BIR/PUBL/2014/07/IUslugaBIRzewnPubl/Wyloguj"',
                'sid'          => $sessionId,
            ],
            'body'      => $logoutXml,
        ]);

        if (is_wp_error($searchResponse)) {
            return null;
        }

        $searchBody = wp_remote_retrieve_body($searchResponse);
        if (! preg_match('/<DaneSzukajPodmiotyResult>(.*?)<\/DaneSzukajPodmiotyResult>/s', $searchBody, $searchMatches)) {
            return null;
        }

        $innerXml = html_entity_decode($searchMatches[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
        if ($innerXml === '') {
            return null;
        }

        $doc = simplexml_load_string($innerXml);
        if ($doc === false || ! isset($doc->dane) || isset($doc->dane->ErrorCode)) {
            return null;
        }

        $dane            = $doc->dane;
        $lokalu          = trim((string) ($dane->NrLokalu ?? ''));
        $ulica           = trim((string) ($dane->Ulica ?? ''));
        $nrNieruchomosci = trim((string) ($dane->NrNieruchomosci ?? ''));
        $address         = trim(($ulica !== '' ? $ulica . ' ' : '') . $nrNieruchomosci . ($lokalu !== '' ? '/' . $lokalu : ''));

        return [
            'name'     => trim((string) ($dane->Nazwa ?? '')),
            'address'  => $address,
            'postcode' => trim((string) ($dane->KodPocztowy ?? '')),
            'city'     => trim((string) ($dane->Miejscowosc ?? '')),
            'regon'    => trim((string) ($dane->Regon ?? '')),
        ];
    }

    /**
     * Enqueue checkout script params and inline script for NIP auto-fill via AJAX.
     */
    public function enqueueCheckoutScript(): void
    {
        if (! function_exists('is_checkout') || (! is_checkout() && ! is_account_page())) {
            return;
        }

        $params = [
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nipNonce' => wp_create_nonce('polski_nip_lookup'),
        ];

        wp_localize_script('polski-checkout', 'polskiCheckoutParams', $params);
        wp_localize_script('wc-checkout', 'polskiCheckoutParams', $params);
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        $settings = get_option('polski_nip', []);

        return is_array($settings) ? $settings : [];
    }
}
