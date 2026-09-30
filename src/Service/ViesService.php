<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Util\SafeHttp;
use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * EU VAT ID validation against VIES.
 *
 * `NipLookupService` validates a Polish NIP and reads company data from the GUS
 * REGON register. Neither can say anything about a VAT number issued by another
 * member state, which is exactly what an intra-EU B2B sale at 0% VAT turns on.
 * This adds that check.
 *
 * Endpoint (verified 2026-09-07):
 *   GET https://ec.europa.eu/taxation_customs/vies/rest-api/ms/{CC}/vat/{NUMBER}
 * returning `isValid`, `name`, `address`, `userError` and `requestIdentifier`.
 *
 * Supplying the shop's own VAT number turns it into a *qualified* check, and
 * only then does VIES return a `requestIdentifier`, the consultation number. That
 * number is the evidence that the check happened, so where the result is stored
 * on an order as proof the request is made fresh and never served from cache: a
 * cached identifier would document a consultation that did not take place for
 * that order.
 *
 * Optional module, OFF by default.
 */
final class ViesService implements HasHooks
{
    private const OPTION = 'polski_vies';
    private const ENDPOINT = 'https://ec.europa.eu/taxation_customs/vies/rest-api/ms/%s/vat/%s';
    private const CACHE_PREFIX = 'polski_vies_';

    public const META_RESULT = '_polski_vies_result';

    /**
     * Member state codes VIES accepts, each with the shape of its national
     * number (the European Commission's VIES FAQ). EL is Greece; XI is
     * Northern Ireland.
     */
    private const FORMATS = [
        'AT' => 'U\d{8}',
        'BE' => '[01]\d{9}',
        'BG' => '\d{9,10}',
        'CY' => '\d{8}[A-Z]',
        'CZ' => '\d{8,10}',
        'DE' => '\d{9}',
        'DK' => '\d{8}',
        'EE' => '\d{9}',
        'EL' => '\d{9}',
        'ES' => '[A-Z0-9]\d{7}[A-Z0-9]',
        'FI' => '\d{8}',
        'FR' => '[A-Z0-9]{2}\d{9}',
        'HR' => '\d{11}',
        'HU' => '\d{8}',
        'IE' => '\d[A-Z0-9]\d{5}[A-Z]{1,2}',
        'IT' => '\d{11}',
        'LT' => '\d{9}|\d{12}',
        'LU' => '\d{8}',
        'LV' => '\d{11}',
        'MT' => '\d{8}',
        'NL' => '\d{9}B\d{2}',
        'PL' => '\d{10}',
        'PT' => '\d{9}',
        'RO' => '\d{2,10}',
        'SE' => '\d{12}',
        'SI' => '\d{8}',
        'SK' => '\d{10}',
        'XI' => '\d{9}|\d{12}|GD\d{3}|HA\d{3}',
    ];

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('vies');
    }

    public function registerHooks(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'renderOrderPanel'], 20);
        add_action('admin_post_polski_vies_check', [$this, 'handleOrderCheck']);
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $settings = get_option(self::OPTION, []);

        return is_array($settings) ? $settings : [];
    }

    /**
     * Split a VAT ID into a member state code and the number itself.
     *
     * Accepts both "DE123456789" and a bare number with the country supplied
     * separately, and tolerates the spaces, dashes and lowercase people type.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function parse(string $vatId, string $fallbackCountry = ''): ?array
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $vatId) ?? '');

        if ($clean === '') {
            return null;
        }

        $prefix = substr($clean, 0, 2);

        if (isset(self::FORMATS[$prefix]) && strlen($clean) > 2) {
            return [$prefix, substr($clean, 2)];
        }

        $country = strtoupper(trim($fallbackCountry));
        // VIES calls Greece EL, while ISO country codes and WooCommerce use GR.
        if ($country === 'GR') {
            $country = 'EL';
        }

        if (! isset(self::FORMATS[$country])) {
            return null;
        }

        return [$country, $clean];
    }

    /**
     * Whether a VAT ID carries a member state prefix and a number of that
     * state's shape. Format only: whether it is registered is what check() asks.
     */
    public static function isWellFormed(string $vatId): bool
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $vatId) ?? '');
        $prefix = substr($clean, 0, 2);

        return isset(self::FORMATS[$prefix])
            && preg_match('/^(?:' . self::FORMATS[$prefix] . ')$/', substr($clean, 2)) === 1;
    }

    /**
     * Ask VIES about a VAT ID.
     *
     * @param bool $fresh Bypass the cache. Use for anything recorded as evidence.
     *
     * @return array{valid: bool, name: string, address: string, consultation: string, checked_at: string, error: string}
     */
    public function check(string $countryCode, string $number, bool $fresh = false): array
    {
        $countryCode = strtoupper($countryCode);
        $number = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $number) ?? '');

        $failure = static fn (string $error): array => [
            'valid' => false,
            'name' => '',
            'address' => '',
            'consultation' => '',
            'checked_at' => '',
            'error' => $error,
        ];

        if (! isset(self::FORMATS[$countryCode]) || $number === '') {
            return $failure('invalid_input');
        }

        $cacheKey = self::CACHE_PREFIX . md5($countryCode . $number);

        if (! $fresh) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                /** @var array{valid: bool, name: string, address: string, consultation: string, checked_at: string, error: string} $cached */
                return $cached;
            }
        }

        $url = sprintf(self::ENDPOINT, rawurlencode($countryCode), rawurlencode($number));
        $requester = $this->requester();

        if ($requester !== null) {
            $url = add_query_arg([
                'requesterMemberStateCode' => $requester[0],
                'requesterNumber' => $requester[1],
            ], $url);
        }

        // Retried once on a timeout or a 5xx. A VAT number check is a read.
        // Uncompressed on purpose: the VIES endpoint drops gzip responses
        // mid-stream under OpenSSL 3 (cURL error 56), which read as unreachable.
        $response = SafeHttp::get($url, [
            'timeout' => 10,
            'decompress' => false,
            'headers' => ['Accept' => 'application/json', 'Accept-Encoding' => 'identity'],
        ]);

        if (is_wp_error($response)) {
            return $failure('unreachable');
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return $failure('http_' . (int) wp_remote_retrieve_response_code($response));
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if (! is_array($body) || ! array_key_exists('isValid', $body)) {
            return $failure('unreadable');
        }

        // VIES fills unknown fields with "---" rather than leaving them empty.
        $clean = static fn (mixed $value): string => ($s = trim((string) $value)) === '---' ? '' : $s;

        $result = [
            'valid' => (bool) $body['isValid'],
            'name' => $clean($body['name'] ?? ''),
            'address' => $clean($body['address'] ?? ''),
            'consultation' => $clean($body['requestIdentifier'] ?? ''),
            'checked_at' => current_time('mysql', true),
            'error' => (bool) $body['isValid'] ? '' : (string) ($body['userError'] ?? 'INVALID'),
        ];

        // A definite answer is worth caching; a member state's registry being
        // temporarily down is not, so only cache when VIES actually decided.
        if ($result['error'] === '' || $result['error'] === 'INVALID') {
            set_transient($cacheKey, $result, $this->cacheSeconds());
        }

        return $result;
    }

    /**
     * The shop's own VAT number, which turns the call into a qualified check.
     *
     * @return array{0: string, 1: string}|null
     */
    private function requester(): ?array
    {
        $own = trim((string) ($this->settings()['requester_vat'] ?? ''));

        if ($own === '') {
            return null;
        }

        return self::parse($own, 'PL');
    }

    private function cacheSeconds(): int
    {
        $hours = (int) ($this->settings()['cache_hours'] ?? 24);

        return max(1, $hours) * HOUR_IN_SECONDS;
    }

    /**
     * The VAT ID recorded on an order, with the member state to check it against.
     *
     * @return array{0: string, 1: string}|null
     */
    public function vatIdForOrder(\WC_Order $order): ?array
    {
        $vatId = '';

        foreach (['_polski_billing_nip', '_billing_nip', '_billing_vat_id'] as $key) {
            $value = (string) $order->get_meta($key, true);
            if (trim($value) !== '') {
                $vatId = $value;
                break;
            }
        }

        if ($vatId === '') {
            return null;
        }

        return self::parse($vatId, (string) $order->get_billing_country());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function storedResult(\WC_Order $order): ?array
    {
        $stored = $order->get_meta(self::META_RESULT, true);

        return is_array($stored) ? $stored : null;
    }

    public function renderOrderPanel(\WC_Order $order): void
    {
        $parsed = $this->vatIdForOrder($order);

        if ($parsed === null) {
            return;
        }

        [$country, $number] = $parsed;
        $stored = $this->storedResult($order);

        echo '<div class="polski-vies-panel"><p><strong>' . esc_html__('EU VAT ID (VIES)', 'polski') . '</strong><br>';
        echo esc_html($country . $number) . '<br>';

        if ($stored === null) {
            echo esc_html__('Not checked yet.', 'polski');
        } elseif (! empty($stored['valid'])) {
            printf(
                /* translators: 1: company name from VIES, 2: date of the check */
                esc_html__('Valid: %1$s, checked %2$s', 'polski'),
                esc_html((string) ($stored['name'] ?? '')),
                esc_html((string) ($stored['checked_at'] ?? '')),
            );
            if (! empty($stored['consultation'])) {
                echo '<br>' . esc_html__('Consultation number:', 'polski') . ' <code>'
                    . esc_html((string) $stored['consultation']) . '</code>';
            }
        } elseif (in_array((string) ($stored['error'] ?? ''), ['INVALID', 'INVALID_INPUT', 'invalid_input'], true)) {
            printf(
                /* translators: %s: reason reported by VIES */
                esc_html__('Not valid in VIES (%s)', 'polski'),
                esc_html((string) ($stored['error'] ?? '')),
            );
        } else {
            // No answer (network, a member state's registry down) says nothing
            // about the number, so it must not read as "not valid".
            printf(
                /* translators: %s: technical reason, e.g. unreachable or MS_UNAVAILABLE */
                esc_html__('Could not check: VIES did not answer (%s). Try again later.', 'polski'),
                esc_html((string) ($stored['error'] ?? '')),
            );
        }

        echo '</p><p>';
        printf(
            '<a href="%s" class="button">%s</a>',
            esc_url(wp_nonce_url(
                admin_url('admin-post.php?action=polski_vies_check&order_id=' . $order->get_id()),
                'polski_vies_check_' . $order->get_id(),
            )),
            esc_html__('Check in VIES', 'polski'),
        );
        echo '</p></div>';
    }

    public function handleOrderCheck(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified immediately below.
        $orderId = isset($_GET['order_id']) ? absint(wp_unslash($_GET['order_id'])) : 0;

        check_admin_referer('polski_vies_check_' . $orderId);

        if (! current_user_can('edit_shop_orders')) {
            wp_die(esc_html__('Unauthorized', 'polski'));
        }

        $order = wc_get_order($orderId);

        if (! $order instanceof \WC_Order) {
            wp_die(esc_html__('Order not found', 'polski'));
        }

        $parsed = $this->vatIdForOrder($order);

        if ($parsed !== null) {
            // Recorded as evidence, so the request must be fresh.
            $result = $this->check($parsed[0], $parsed[1], true);
            $order->update_meta_data(self::META_RESULT, $result);
            $order->save();
        }

        wp_safe_redirect($order->get_edit_order_url());
        exit;
    }
}
