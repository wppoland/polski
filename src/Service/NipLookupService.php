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

        // Add NIP field to checkout billing form.
        add_filter('woocommerce_billing_fields', [$this, 'addNipField']);

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
     * Save NIP to order meta on checkout.
     */
    public function saveNipToOrder(\WC_Order $order, array $data): void
    {
        $nip = sanitize_text_field(wp_unslash($_POST['billing_nip'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if ($nip !== '') {
            $nip = preg_replace('/[\s\-]/', '', $nip) ?? '';
            $order->update_meta_data('_polski_billing_nip', $nip);
        }
    }

    /**
     * Display NIP in admin order billing address section.
     */
    public function displayNipInAdmin(\WC_Order $order): void
    {
        $nip = $order->get_meta('_polski_billing_nip', true);

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
     * Uses the SOAP-based BIR1 service. Falls back to null on any error.
     *
     * @return array{name: string, address: string, postcode: string, city: string, regon: string}|null
     */
    private function lookupNip(string $nip): ?array
    {
        $settings = $this->getSettings();
        $environment = $settings['gus_environment'] ?? 'test';

        if ($environment === 'production') {
            $wsdl   = 'https://wyszukiwarkaregon.stat.gov.pl/wsBIR/UslugaBIRzworking.svc?singleWsdl';
            $apiKey = $settings['gus_api_key'] ?? '';
        } else {
            $wsdl   = 'https://wyszukiwarkaregontest.stat.gov.pl/wsBIR/UslugaBIRzworking.svc?singleWsdl';
            $apiKey = 'abcde12345abcde12345';
        }

        if ($apiKey === '') {
            return null;
        }

        try {
            $client = new \SoapClient($wsdl, [
                'soap_version'   => SOAP_1_2,
                'trace'          => false,
                'exceptions'     => true,
                'stream_context' => stream_context_create(['ssl' => ['verify_peer' => true]]),
            ]);

            // Login to get session ID.
            $loginResult = $client->Zaloguj(['pKluczUzytkownika' => $apiKey]);
            $sessionId   = $loginResult->ZalogujResult ?? '';

            if ($sessionId === '') {
                return null;
            }

            // Set session header for the search request.
            $header = new \SoapHeader(
                'http://www.w3.org/2005/08/addressing',
                'Action',
                'http://CIS/BIR/PUBL/2014/07/IUslugaBIRzworking/DaneSzukajPodmioty',
                true,
            );
            $client->__setSoapHeaders([$header]);

            // Search by NIP.
            $searchResult = $client->DaneSzukajPodmioty([
                'pParametryWyszukiwania' => ['Nip' => $nip],
            ]);

            $xml = $searchResult->DaneSzukajPodmiotyResult ?? '';

            if ($xml === '') {
                $client->Wyloguj(['pIdentyfikatorSesji' => $sessionId]);

                return null;
            }

            // Parse XML response.
            $doc = simplexml_load_string($xml);

            if ($doc === false || ! isset($doc->dane)) {
                $client->Wyloguj(['pIdentyfikatorSesji' => $sessionId]);

                return null;
            }

            $dane = $doc->dane;

            $lokalu  = trim((string) ($dane->NrLokalu ?? ''));
            $address = trim(
                (string) ($dane->Ulica ?? '') . ' '
                . (string) ($dane->NrNieruchomosci ?? '')
                . ($lokalu !== '' ? '/' . $lokalu : ''),
            );

            $result = [
                'name'     => trim((string) ($dane->Nazwa ?? '')),
                'address'  => $address,
                'postcode' => trim((string) ($dane->KodPocztowy ?? '')),
                'city'     => trim((string) ($dane->Miejscowosc ?? '')),
                'regon'    => trim((string) ($dane->Regon ?? '')),
            ];

            // Logout.
            $client->Wyloguj(['pIdentyfikatorSesji' => $sessionId]);

            return $result;
        } catch (\Throwable $e) {
            // Silently fail - GUS API is often unreliable.
            return null;
        }
    }

    /**
     * Enqueue checkout script for NIP auto-fill via AJAX.
     */
    public function enqueueCheckoutScript(): void
    {
        if (! is_checkout()) {
            return;
        }

        $script = "
        (function() {
            var debounce;
            document.addEventListener('change', function(e) {
                if (!e.target || !e.target.hasAttribute('data-polski-nip-field')) return;
                var nip = e.target.value.replace(/[\\s\\-]/g, '');
                if (nip.length !== 10) return;

                clearTimeout(debounce);
                debounce = setTimeout(function() {
                    var fd = new FormData();
                    fd.append('action', 'polski_nip_lookup');
                    fd.append('_nonce', '" . esc_js(wp_create_nonce('polski_nip_lookup')) . "');
                    fd.append('nip', nip);

                    fetch('" . esc_js(admin_url('admin-ajax.php')) . "', {
                        method: 'POST',
                        body: fd,
                        credentials: 'same-origin'
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data.success || !data.data) return;
                        var d = data.data;
                        var company = document.getElementById('billing_company');
                        var addr1 = document.getElementById('billing_address_1');
                        var postcode = document.getElementById('billing_postcode');
                        var city = document.getElementById('billing_city');
                        if (company && d.name && !company.value) company.value = d.name;
                        if (addr1 && d.address && !addr1.value) addr1.value = d.address;
                        if (postcode && d.postcode && !postcode.value) postcode.value = d.postcode;
                        if (city && d.city && !city.value) city.value = d.city;
                        // Trigger WooCommerce update.
                        if (company) company.dispatchEvent(new Event('change', {bubbles: true}));
                    })
                    .catch(function() {});
                }, 500);
            });
        })();
        ";

        wp_add_inline_script('wc-checkout', $script);
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
