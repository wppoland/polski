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
            // Polski boots on `init` priority 0, and WooCommerce fires
            // `woocommerce_init` from its own `init` callback registered while the
            // plugin file was still being included, so it has ALWAYS fired by the
            // time we get here. Hooking it registers a callback that can never run,
            // which is why the NIP field never appeared on block checkout. Call it
            // directly when the action is already spent.
            if (did_action('woocommerce_init')) {
                $this->registerAdditionalCheckoutFields();
            } else {
                add_action('woocommerce_init', [$this, 'registerAdditionalCheckoutFields']);
            }
            add_action('woocommerce_validate_additional_field', [$this, 'validateBlockNipField'], 10, 3);
            add_action('woocommerce_set_additional_field_value', [$this, 'mirrorAdditionalFieldToLegacyMeta'], 10, 4);
            add_action('woocommerce_checkout_order_created', [$this, 'saveBlockNipToOrder']);
            add_action('woocommerce_store_api_checkout_order_processed', [$this, 'saveBlockNipToOrder']);
        }

        // Validate NIP on checkout.
        add_action('woocommerce_checkout_process', [$this, 'validateNipOnCheckout']);

        // Save NIP to order meta.
        add_action('woocommerce_checkout_create_order', [$this, 'saveNipToOrder'], 10, 2);

        // And onto the customer, in every key the other forms read.
        add_action('woocommerce_checkout_update_customer', [$this, 'syncCustomerFromClassicCheckout'], 10, 2);

        // My Account > Addresses > Billing. WooCommerce validates a custom
        // field's "required" flag and its type there, nothing else, so an
        // invalid NIP saved without a word until now.
        add_action('woocommerce_after_save_address_validation', [$this, 'validateSavedAddress'], 10, 4);
        add_action('woocommerce_customer_save_address', [$this, 'syncCustomerAfterAddressSave'], 20, 2);

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
     * Whether the NIP field is mandatory for this checkout.
     *
     * The shop-wide setting is the default. The filter lets an add-on answer
     * per cart, which is what a per-product "receipt requires NIP" marking
     * needs: at registration time there is no cart yet, so the answer there is
     * the setting, and validation is where a cart-dependent answer lands.
     *
     * @param bool $atRegistration True while registering the block field, when
     *                             no cart exists to inspect.
     */
    public function nipRequired(bool $atRegistration = false): bool
    {
        $settings = $this->getSettings();

        return (bool) apply_filters(
            'polski/nip_required',
            ! empty($settings['nip_required']),
            $atRegistration,
        );
    }

    /**
     * Add NIP field to WooCommerce billing fields.
     *
     * @param array<string, array<string, mixed>> $fields Billing fields.
     * @return array<string, array<string, mixed>>
     */
    public function addNipField(array $fields): array
    {
        $required = $this->nipRequired();

        $fields['billing_nip'] = [
            'type'        => 'text',
            'label'       => __('NIP', 'polski'),
            'placeholder' => __('e.g. 1234563218', 'polski'),
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
            // WooCommerce enforces the field's own "required" flag. This covers
            // the case it cannot see: a requirement that depends on what is in
            // the cart, which is only known now.
            if ($this->nipRequired()) {
                wc_add_notice(
                    __('One of the products in your order needs a VAT ID (NIP) on the receipt. Enter it to continue.', 'polski'),
                    'error',
                );
            }

            return;
        }

        if (! self::isValidNip($nip)) {
            wc_add_notice(
                __('That VAT ID (NIP) is not valid. Check the number and try again.', 'polski'),
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

        // This used to bail when the B2B module was on, "because B2B handles
        // its own NIP field". B2B registers needs_invoice, REGON and IBAN and
        // has never registered a NIP, so with both modules on the field was
        // registered by nobody and disappeared from block checkout and from
        // My Account entirely.

        $required = $this->nipRequired(true);

        woocommerce_register_additional_checkout_field([
            'id' => 'polski/nip',
            // Contact, not address. A VAT ID belongs to the buyer, not to a
            // place: at 'address' WooCommerce renders one copy in the billing
            // form and another in the shipping form, so a customer could enter
            // two different numbers and nothing compared them.
            'location' => 'contact',
            'label' => __('NIP', 'polski'),
            'type' => 'text',
            'required' => $required,
            // Strip formatting only. Stripping every non-digit here turned
            // "not a number at all" into an empty string, and an empty string
            // reads as "left blank", so typing letters passed validation
            // silently and the shop got no NIP.
            'sanitize_callback' => static fn (string $value): string => (string) preg_replace('/[\s\-]/', '', sanitize_text_field($value)),
            'validate_callback' => static function (string $value) {
                if (trim($value) === '') {
                    return null;
                }
                if (! self::isValidNip($value)) {
                    return new \WP_Error(
                        'polski_invalid_nip',
                        __('That VAT ID (NIP) is not valid. Enter 10 digits.', 'polski'),
                    );
                }
                return null;
            },
        ]);
    }

    /**
     * Mirror additional field value to standard order/customer meta on save.
     */
    /**
     * Block checkout counterpart of validateNipOnCheckout().
     *
     * The field is registered before a cart exists, so a requirement that
     * depends on the cart cannot be expressed by its "required" flag. This runs
     * when the Store API validates the submitted checkout, where it can.
     */
    public function validateBlockNipField(\WP_Error $errors, string $fieldId, mixed $value): void
    {
        if ($fieldId !== 'polski/nip') {
            return;
        }

        $clean = (string) preg_replace('/[^0-9]/', '', (string) $value);

        if ($clean === '' && $this->nipRequired()) {
            $errors->add(
                'polski_nip_required',
                __('One of the products in your order needs a VAT ID (NIP) on the receipt. Enter it to continue.', 'polski'),
            );
        }
    }

    public function mirrorAdditionalFieldToLegacyMeta(string $key, mixed $value, string $group, mixed $document): void
    {
        // 'other' is the group a contact-location field lands in; 'billing' is
        // kept for values written before the field moved out of the address.
        if (! $this->isEnabled() || $key !== 'polski/nip' || ! in_array($group, ['billing', 'other'], true)) {
            return;
        }

        $clean = is_scalar($value) ? (string) preg_replace('/[^0-9]/', '', (string) $value) : '';
        if ($clean === '') {
            return;
        }

        if (! is_object($document) || ! method_exists($document, 'update_meta_data')) {
            return;
        }

        $document->update_meta_data('_billing_nip', $clean);
        $document->update_meta_data('_polski_billing_nip', $clean);

        // The shortcode checkout renders our own billing_nip field, and
        // WooCommerce fills that from customer meta of the same name. Without
        // this line a NIP saved in My Account was invisible at checkout and had
        // to be typed again, because the two forms wrote to different keys.
        if ($document instanceof \WC_Customer) {
            $document->update_meta_data('billing_nip', $clean);
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
     * Reject an invalid NIP on the edit-address form.
     *
     * @param int $userId
     * @param string $loadAddress Which address is being saved: billing or shipping.
     * @param array<string, mixed> $address
     * @param \WC_Customer|null $customer
     */
    public function validateSavedAddress(int $userId, string $loadAddress, array $address, mixed $customer = null): void
    {
        unset($userId, $customer);

        if ($loadAddress !== 'billing') {
            return;
        }

        $nip = sanitize_text_field((string) ($address['billing_nip'] ?? ''));

        if (trim($nip) === '') {
            return;
        }

        if (! self::isValidNip($nip)) {
            wc_add_notice(
                __('That VAT ID (NIP) is not valid. Enter 10 digits.', 'polski'),
                'error',
            );
        }
    }

    /**
     * Keep the edit-address form and the block checkout on the same value.
     *
     * The form writes customer meta billing_nip; WooCommerce's own field, which
     * renders the address summary and the block checkout, reads its own key.
     * Writing one and not the other is why the number shown in the summary and
     * the number in the edit form could differ.
     */
    public function syncCustomerAfterAddressSave(int $userId, string $loadAddress): void
    {
        if ($loadAddress !== 'billing' || $userId <= 0) {
            return;
        }

        $customer = new \WC_Customer($userId);
        $clean = (string) preg_replace('/[^0-9]/', '', (string) $customer->get_meta('billing_nip', true));

        if ($clean === '' || ! self::isValidNip($clean)) {
            return;
        }

        foreach (['_billing_nip', '_polski_billing_nip', '_wc_other/polski/nip'] as $key) {
            $customer->update_meta_data($key, $clean);
        }

        // The field lived in the address group before it moved to contact.
        // Clearing the old copy stops the summary rendering a stale number.
        $customer->delete_meta_data('_wc_billing/polski/nip');
        $customer->save();
    }

    /**
     * Carry a NIP typed on the shortcode checkout back to the customer.
     *
     * WooCommerce stores the shortcode field under customer meta billing_nip,
     * while the block checkout and My Account read WooCommerce's own
     * additional-field key. Writing only one of them is what made the number
     * appear in one screen and stay blank in the other.
     *
     * @param \WC_Customer $customer
     * @param array<string, mixed> $data
     */
    public function syncCustomerFromClassicCheckout(\WC_Customer $customer, array $data): void
    {
        $nip = isset($data['billing_nip'])
            ? sanitize_text_field((string) $data['billing_nip'])
            : sanitize_text_field(wp_unslash($_POST['billing_nip'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC verifies the checkout nonce.

        $clean = (string) preg_replace('/[^0-9]/', '', $nip);

        if ($clean === '' || ! self::isValidNip($clean)) {
            return;
        }

        foreach (['billing_nip', '_billing_nip', '_polski_billing_nip', '_wc_other/polski/nip'] as $key) {
            $customer->update_meta_data($key, $clean);
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
            wp_send_json_error(['message' => __('Invalid VAT ID (NIP).', 'polski')]);
        }

        $nip = preg_replace('/[\s\-]/', '', $nip) ?? '';
        $result = $this->lookupNip($nip);

        if ($result === null) {
            wp_send_json_error(['message' => __('No data found for that VAT ID (NIP). Check the number or fill the details in by hand.', 'polski')]);
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

        if (! $this->checkoutUsesBlocks()) {
            return;
        }

        // Block checkout renders its inputs from a data store, so the classic
        // script's DOM writes are discarded on the next render. This one talks
        // to the store instead.
        wp_enqueue_script(
            'polski-nip-block-lookup',
            \Polski\Plugin::instance()->url('assets/js/nip-block-lookup.js'),
            ['wp-data'],
            \Polski\VERSION,
            ['in_footer' => true, 'strategy' => 'defer'],
        );

        wp_localize_script('polski-nip-block-lookup', 'polskiNipBlockParams', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => $params['nipNonce'],
        ]);
    }

    /**
     * True when the checkout page is the block checkout.
     */
    private function checkoutUsesBlocks(): bool
    {
        // Named as a string: the class ships with WooCommerce Blocks, so a
        // ::class reference makes static analysis assume it is always there.
        $utils = 'Automattic\\WooCommerce\\Blocks\\Utils\\CartCheckoutUtils';

        if (! is_callable([$utils, 'is_checkout_block_default'])) {
            return false;
        }

        return (bool) call_user_func([$utils, 'is_checkout_block_default']);
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
