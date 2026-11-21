<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;
use Polski\Util\SafeHttp;

// existing-checked: src/Service/NipLookupService.php, src/Service/DigitalConsentService.php - both own a
// different module (NIP, art. 16(m) consent); nothing in src/ registers shipping methods or talks to InPost.

/**
 * InPost shipping: two shipping methods (parcel locker, courier) and the
 * parcel locker picker on the classic and block checkout.
 *
 * The InPost Points API answers server-side calls without a token but refuses
 * browsers (no CORS), so the picker asks our own REST route, which asks InPost
 * and caches the answer. Only locker data passes through it, never anything
 * about the shopper.
 */
final class InPostShippingService implements HasHooks
{
    public const MODULE = 'inpost_shipping';
    public const LOCKER_METHOD = 'polski_inpost_locker';
    public const COURIER_METHOD = 'polski_inpost_courier';

    private const API = 'https://api-pl-points.easypack24.net/v1/points';
    private const API_FIELDS = 'name,address,location_description,opening_hours';

    private const FIELD_KEY = 'polski_inpost_locker';
    private const BLOCK_FIELD_ID = 'polski/inpost-locker';
    private const BLOCK_FIELD_META = '_wc_other/polski/inpost-locker';
    private const META_CODE = '_polski_inpost_locker';
    private const META_ADDRESS = '_polski_inpost_locker_address';

    private const RATE_LIMIT = 30;

    public function registerHooks(): void
    {
        // Polski boots every service unconditionally; a module's service has to
        // refuse its own hooks.
        if (! ModulesPage::isModuleEnabled(self::MODULE)) {
            return;
        }

        add_filter('woocommerce_shipping_methods', [$this, 'addMethods']);
        add_action('rest_api_init', [$this, 'registerRoute']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        // Block checkout. woocommerce_init has already fired when Polski boots
        // on init, so hooking it directly would never run.
        $this->runAfter('woocommerce_init', [$this, 'registerBlockField']);
        add_action('woocommerce_store_api_checkout_update_order_from_request', [$this, 'persistBlockLocker'], 10, 2);
        // The order screen gets our own summary; WooCommerce's editable copy of
        // the field would show the code twice.
        add_filter('woocommerce_admin_shipping_fields', [$this, 'removeAdminField'], 20);

        // Classic checkout.
        add_action('woocommerce_after_shipping_rate', [$this, 'renderClassicPicker'], 10, 2);
        add_action('woocommerce_checkout_process', [$this, 'validateClassic']);
        add_action('woocommerce_checkout_order_created', [$this, 'persistClassic']);

        // Where the chosen locker is shown.
        add_action('woocommerce_admin_order_data_after_shipping_address', [$this, 'renderAdmin']);
        add_action('woocommerce_email_after_order_table', [$this, 'renderEmail'], 15, 3);
        add_action('woocommerce_order_details_after_order_table', [$this, 'renderOrderDetails']);
    }

    private function runAfter(string $action, callable $callback): void
    {
        if (did_action($action) > 0) {
            $callback();
            return;
        }

        add_action($action, $callback);
    }

    // ------------------------------------------------------------------
    // Rules (pure, unit tested).
    // ------------------------------------------------------------------

    /**
     * Offered only to Poland, and only up to the weight limit when one is set.
     * An empty limit means no limit: casting it would read as 0 kg and hide the
     * method from every cart.
     */
    public static function isAvailableFor(string $country, float $weightKg, string $maxWeightKg): bool
    {
        if (strtoupper($country) !== 'PL') {
            return false;
        }

        $maxWeightKg = trim($maxWeightKg);

        return $maxWeightKg === '' || $weightKg <= (float) $maxWeightKg;
    }

    /**
     * Rate cost. An empty threshold means never free: casting it would read as
     * 0 and make every order free.
     */
    public static function rateCost(string $cost, string $freeAbove, float $orderTotal): float
    {
        $freeAbove = trim($freeAbove);

        if ($freeAbove !== '' && $orderTotal >= (float) $freeAbove) {
            return 0.0;
        }

        return max(0.0, (float) $cost);
    }

    public static function normalizeCode(string $raw): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $raw));
    }

    /**
     * Parcel locker codes as InPost issues them: 2 to 4 letters, 1 to 4 digits
     * and an optional suffix (GDY144M, GDY06BAPP, ANTN01HP, BB011M). Checked
     * against 3000 live codes.
     */
    public static function isValidCode(string $code): bool
    {
        return preg_match('/^[A-Z]{2,4}[0-9]{1,4}[A-Z0-9]{0,6}$/', $code) === 1;
    }

    /**
     * A Polish postcode as NN-NNN, or null when it is not one.
     */
    public static function normalizePostcode(string $raw): ?string
    {
        $raw = trim($raw);

        if (preg_match('/^(\d{2})-?(\d{3})$/', $raw, $m) !== 1) {
            return null;
        }

        return $m[1] . '-' . $m[2];
    }

    // ------------------------------------------------------------------
    // Shipping methods.
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $methods
     * @return array<string, mixed>
     */
    public function addMethods(array $methods): array
    {
        $methods[self::LOCKER_METHOD] = \Polski\Shipping\InPostLockerMethod::class;
        $methods[self::COURIER_METHOD] = \Polski\Shipping\InPostCourierMethod::class;

        return $methods;
    }

    // ------------------------------------------------------------------
    // InPost Points API.
    // ------------------------------------------------------------------

    /**
     * Up to 10 parcel lockers nearest to a postcode, or null when InPost could
     * not be reached.
     *
     * @return list<array{code: string, address: string, description: string, hours: string}>|null
     */
    public function findNearby(string $postcode): ?array
    {
        $key = 'polski_inpost_pc_' . str_replace('-', '', $postcode);
        // The raw items are cached and mapped on every read, so a cached entry
        // goes through the same whitelist as a fresh one.
        $items = get_transient($key);

        if (! is_array($items)) {
            $data = $this->request(self::API . '?' . http_build_query([
            'relative_post_code' => $postcode,
            'type' => 'parcel_locker',
            'per_page' => 10,
                'fields' => self::API_FIELDS,
            ]));

            if (! is_array($data) || ! isset($data['items']) || ! is_array($data['items'])) {
                return null;
            }

            $items = $data['items'];
            set_transient($key, $items, 12 * HOUR_IN_SECONDS);
        }

        $points = [];
        foreach ($items as $item) {
            $point = is_array($item) ? self::mapPoint($item) : null;
            if ($point !== null) {
                $points[] = $point;
            }
        }

        return $points;
    }

    /**
     * One parcel locker: the point, false when InPost says there is no such
     * point, null when InPost could not be reached.
     *
     * @return array{code: string, address: string, description: string, hours: string}|false|null
     */
    public function lookupPoint(string $code): array|false|null
    {
        if (! self::isValidCode($code)) {
            return false;
        }

        $key = 'polski_inpost_pt_' . strtolower($code);
        $data = get_transient($key);
        $fresh = ! is_array($data);

        if ($fresh) {
            $data = $this->request(self::API . '/' . rawurlencode($code) . '?fields=' . self::API_FIELDS);

            if ($data === null) {
                return null;
            }
        }

        // An unknown point comes back as HTTP 200 with key point_not_found.
        $point = self::mapPoint($data);

        if ($point === null || $point['code'] !== $code) {
            return false;
        }

        if ($fresh) {
            set_transient($key, $data, 12 * HOUR_IN_SECONDS);
        }

        return $point;
    }

    /**
     * @return array<string, mixed>|null Decoded body, or null when unreachable.
     */
    private function request(string $url): ?array
    {
        $response = SafeHttp::get($url, [
            'timeout' => 5,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 500) {
            return null;
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        return is_array($data) ? $data : null;
    }

    /**
     * Only the fields the picker shows; opening_hours can be null.
     *
     * @param array<mixed> $item
     * @return array{code: string, address: string, description: string, hours: string}|null
     */
    private static function mapPoint(array $item): ?array
    {
        $code = self::normalizeCode(is_string($item['name'] ?? null) ? $item['name'] : '');

        if (! self::isValidCode($code)) {
            return null;
        }

        $address = is_array($item['address'] ?? null) ? $item['address'] : [];
        $lines = array_filter([
            is_string($address['line1'] ?? null) ? sanitize_text_field($address['line1']) : '',
            is_string($address['line2'] ?? null) ? sanitize_text_field($address['line2']) : '',
        ]);

        return [
            'code' => $code,
            'address' => implode(', ', $lines),
            'description' => is_string($item['location_description'] ?? null) ? sanitize_text_field($item['location_description']) : '',
            'hours' => is_string($item['opening_hours'] ?? null) ? sanitize_text_field($item['opening_hours']) : '',
        ];
    }

    // ------------------------------------------------------------------
    // REST route for the picker.
    // ------------------------------------------------------------------

    public function registerRoute(): void
    {
        register_rest_route('polski/v1', '/inpost/points', [
            'methods' => 'GET',
            'callback' => [$this, 'handlePoints'],
            // Shoppers are guests; the route returns public locker data only.
            'permission_callback' => '__return_true',
            'args' => [
                'postcode' => ['type' => 'string', 'required' => false],
                'code' => ['type' => 'string', 'required' => false],
            ],
        ]);
    }

    public function handlePoints(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $postcode = self::normalizePostcode((string) $request->get_param('postcode'));
        $code = self::normalizeCode((string) $request->get_param('code'));

        if ($postcode === null && ! self::isValidCode($code)) {
            return new \WP_Error('polski_inpost_invalid', __('Enter a Polish postcode, for example 81-001.', 'polski'), ['status' => 400]);
        }

        if ($this->rateLimited()) {
            return new \WP_Error('polski_inpost_rate_limited', __('Too many searches. Wait a minute and try again.', 'polski'), ['status' => 429]);
        }

        if ($postcode !== null) {
            $points = $this->findNearby($postcode);
        } else {
            $point = $this->lookupPoint($code);
            $points = is_array($point) ? [$point] : ($point === false ? [] : null);
        }

        if ($points === null) {
            return new \WP_Error('polski_inpost_unavailable', __('The parcel locker search is not available right now. Try again in a moment.', 'polski'), ['status' => 503]);
        }

        return new \WP_REST_Response(['points' => $points]);
    }

    private function rateLimited(): bool
    {
        $key = 'polski_inpost_rl_' . md5(\Polski\Util\ClientIp::resolve());
        // The options table hands a transient back as a string.
        $count = (int) get_transient($key);

        if ($count >= self::RATE_LIMIT) {
            return true;
        }

        set_transient($key, $count + 1, MINUTE_IN_SECONDS);

        return false;
    }

    // ------------------------------------------------------------------
    // Checkout assets.
    // ------------------------------------------------------------------

    public function enqueueAssets(): void
    {
        if (! function_exists('is_checkout') || ! is_checkout() || is_order_received_page()) {
            return;
        }

        wp_enqueue_script(
            'polski-inpost-picker',
            \Polski\Plugin::instance()->url('assets/js/inpost-picker.js'),
            [],
            \Polski\VERSION,
            ['in_footer' => true, 'strategy' => 'defer'],
        );

        wp_localize_script('polski-inpost-picker', 'polskiInpostParams', [
            'restUrl' => esc_url_raw(rest_url('polski/v1/inpost/points')),
            'method' => self::LOCKER_METHOD,
            'fieldId' => self::BLOCK_FIELD_ID,
            'i18n' => [
                'choose' => __('Choose a parcel locker', 'polski'),
                'change' => __('Change parcel locker', 'polski'),
                'postcode' => __('Postcode', 'polski'),
                'search' => __('Search', 'polski'),
                'searching' => __('Searching for parcel lockers...', 'polski'),
                'invalidPostcode' => __('Enter a Polish postcode, for example 81-001.', 'polski'),
                'none' => __('No parcel lockers found near this postcode.', 'polski'),
                /* translators: %d: number of parcel lockers found */
                'found' => __('%d parcel lockers found.', 'polski'),
                'error' => __('The parcel locker search is not available right now. Try again in a moment.', 'polski'),
                'chosen' => __('Chosen parcel locker:', 'polski'),
                'hours' => __('Opening hours:', 'polski'),
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // Block checkout.
    // ------------------------------------------------------------------

    public function registerBlockField(): void
    {
        if (! function_exists('woocommerce_register_additional_checkout_field')) {
            return;
        }

        $field = [
            'id' => self::BLOCK_FIELD_ID,
            'label' => __('Parcel locker', 'polski'),
            'location' => 'order',
            'type' => 'text',
            // We print our own summary with the address; WooCommerce would add
            // the bare code a second time.
            'show_in_order_confirmation' => false,
            'sanitize_callback' => static fn (mixed $value): string => self::normalizeCode((string) $value),
            'validate_callback' => [$this, 'validateBlockValue'],
        ];

        // Conditional rules (WooCommerce 9.9+): shown and required only when a
        // parcel locker rate is the selected one. cart.shipping_rates is the
        // list of selected rate ids, such as polski_inpost_locker:7. Older
        // WooCommerce gets an optional field, the script hides it, and
        // persistBlockLocker() still requires a code for a locker order.
        if (self::supportsConditionalFields()) {
            $selectsLocker = ['contains' => ['type' => 'string', 'pattern' => '^' . self::LOCKER_METHOD . ':']];
            $field['hidden'] = [
                'cart' => ['properties' => ['shipping_rates' => ['not' => $selectsLocker]]],
            ];
            $field['required'] = [
                'cart' => [
                    'properties' => ['shipping_rates' => $selectsLocker],
                    'required' => ['shipping_rates'],
                ],
            ];
        }

        woocommerce_register_additional_checkout_field($field);
    }

    private static function supportsConditionalFields(): bool
    {
        // The stubs know only a WooCommerce that has the method, so the check
        // goes through reflection of a runtime class name.
        $class = 'Automattic\\WooCommerce\\Blocks\\Domain\\Services\\CheckoutFields';

        return class_exists($class) && in_array('is_conditional_field', get_class_methods($class), true);
    }

    public function validateBlockValue(mixed $value): true|\WP_Error
    {
        $code = self::normalizeCode((string) $value);

        if ($code === '') {
            return true;
        }

        $error = $this->codeError($code);

        return $error === null ? true : new \WP_Error('polski_inpost_locker', $error);
    }

    /**
     * Server-side backstop for the block checkout, independent of the field's
     * rules: a locker order needs a valid code, any other order drops a code
     * left over from a rate the shopper switched away from.
     */
    public function persistBlockLocker(\WC_Order $order, \WP_REST_Request $request): void
    {
        if (! self::orderUsesLocker($order)) {
            $order->delete_meta_data(self::BLOCK_FIELD_META);
            $order->delete_meta_data(self::META_CODE);
            $order->delete_meta_data(self::META_ADDRESS);
            return;
        }

        // PUT updates a draft order while the shopper is still typing.
        $placing = $request->get_method() === 'POST';
        $code = self::normalizeCode((string) $order->get_meta(self::BLOCK_FIELD_META, true));
        $error = $code === '' ? __('Choose a parcel locker for InPost Paczkomat delivery.', 'polski') : $this->codeError($code);

        if ($error !== null) {
            if ($placing) {
                throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException('polski_inpost_locker', esc_html($error), 400);
            }
            return;
        }

        $this->storeLocker($order, $code, $placing);
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public function removeAdminField(array $fields): array
    {
        unset($fields[self::BLOCK_FIELD_ID]);

        return $fields;
    }

    // ------------------------------------------------------------------
    // Classic checkout.
    // ------------------------------------------------------------------

    public function renderClassicPicker(mixed $method, mixed $index = 0): void
    {
        unset($index);

        if (! $method instanceof \WC_Shipping_Rate || $method->get_method_id() !== self::LOCKER_METHOD) {
            return;
        }

        // The rate list also renders on the cart page.
        if (! is_checkout() || ! in_array($method->get_id(), self::chosenRates(), true)) {
            return;
        }

        // The script fills this in and restores it after every order review refresh.
        printf(
            '<div class="polski-inpost-picker" data-polski-inpost="classic"><input type="hidden" name="%s" value="" /></div>',
            esc_attr(self::FIELD_KEY),
        );
    }

    public function validateClassic(): void
    {
        if (! self::cartUsesLocker()) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC checkout verifies its own nonce.
        $code = self::normalizeCode(sanitize_text_field(wp_unslash((string) ($_POST[self::FIELD_KEY] ?? ''))));
        $error = $code === '' ? __('Choose a parcel locker for InPost Paczkomat delivery.', 'polski') : $this->codeError($code);

        if ($error !== null) {
            wc_add_notice($error, 'error');
        }
    }

    /**
     * Saved once the order exists, so the "not verified" note has an order to
     * attach to.
     */
    public function persistClassic(\WC_Order $order): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC checkout verifies its own nonce.
        if (! isset($_POST[self::FIELD_KEY]) || ! self::orderUsesLocker($order)) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC checkout verifies its own nonce.
        $code = self::normalizeCode(sanitize_text_field(wp_unslash((string) $_POST[self::FIELD_KEY])));

        if (! self::isValidCode($code)) {
            return;
        }

        $this->storeLocker($order, $code, true);
        $order->save();
    }

    // ------------------------------------------------------------------
    // Shared checkout helpers.
    // ------------------------------------------------------------------

    /**
     * Why a code cannot be accepted, or null when it can. InPost being
     * unreachable is not a reason: the order goes through on the format alone
     * and storeLocker() leaves a note.
     */
    private function codeError(string $code): ?string
    {
        if (! self::isValidCode($code)) {
            return __('Enter a valid parcel locker code, for example GDY144M.', 'polski');
        }

        if ($this->lookupPoint($code) === false) {
            return __('That parcel locker was not found. Choose one from the list.', 'polski');
        }

        return null;
    }

    private function storeLocker(\WC_Order $order, string $code, bool $noteIfUnverified): void
    {
        $point = $this->lookupPoint($code);

        $order->update_meta_data(self::META_CODE, $code);
        $order->update_meta_data(self::META_ADDRESS, is_array($point) ? $point['address'] : '');

        if (! is_array($point) && $noteIfUnverified && $order->get_id() > 0) {
            $order->add_order_note(sprintf(
                /* translators: %s: parcel locker code */
                __('Parcel locker %s could not be verified with InPost at checkout. Check the code before shipping.', 'polski'),
                $code,
            ));
        }
    }

    /**
     * @return list<string>
     */
    private static function chosenRates(): array
    {
        if (! function_exists('WC') || WC()->session === null) {
            return [];
        }

        $chosen = WC()->session->get('chosen_shipping_methods', []);

        return is_array($chosen) ? array_values(array_map('strval', $chosen)) : [];
    }

    private static function cartUsesLocker(): bool
    {
        foreach (self::chosenRates() as $rate) {
            if (str_starts_with($rate, self::LOCKER_METHOD . ':') || $rate === self::LOCKER_METHOD) {
                return true;
            }
        }

        return false;
    }

    private static function orderUsesLocker(\WC_Order $order): bool
    {
        foreach ($order->get_shipping_methods() as $item) {
            if ($item->get_method_id() === self::LOCKER_METHOD) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Display.
    // ------------------------------------------------------------------

    /**
     * @return array{code: string, address: string}|null
     */
    private static function lockerOf(mixed $order): ?array
    {
        if (! $order instanceof \WC_Order) {
            return null;
        }

        $code = (string) $order->get_meta(self::META_CODE, true);

        return $code === '' ? null : ['code' => $code, 'address' => (string) $order->get_meta(self::META_ADDRESS, true)];
    }

    public function renderAdmin(mixed $order): void
    {
        $locker = self::lockerOf($order);

        if ($locker === null) {
            return;
        }

        echo '<p class="polski-inpost-admin"><strong>' . esc_html__('Parcel locker:', 'polski') . '</strong> '
            . esc_html($locker['code'])
            . ($locker['address'] !== '' ? '<br />' . esc_html($locker['address']) : '')
            . '</p>';
    }

    public function renderEmail(mixed $order, mixed $sentToAdmin = false, mixed $plainText = false): void
    {
        unset($sentToAdmin);
        $locker = self::lockerOf($order);

        if ($locker === null) {
            return;
        }

        $line = $locker['code'] . ($locker['address'] !== '' ? ', ' . $locker['address'] : '');

        if ($plainText) {
            echo "\n" . esc_html__('Parcel locker:', 'polski') . ' ' . esc_html($line) . "\n";
            return;
        }

        echo '<p class="polski-inpost-email"><strong>' . esc_html__('Parcel locker:', 'polski') . '</strong> ' . esc_html($line) . '</p>';
    }

    public function renderOrderDetails(mixed $order): void
    {
        $locker = self::lockerOf($order);

        if ($locker === null) {
            return;
        }

        echo '<section class="polski-inpost-order"><h2 class="woocommerce-column__title">' . esc_html__('Parcel locker', 'polski') . '</h2><p>'
            . esc_html($locker['code'])
            . ($locker['address'] !== '' ? '<br />' . esc_html($locker['address']) : '')
            . '</p></section>';
    }
}
