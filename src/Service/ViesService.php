<?php

declare(strict_types=1);

namespace Polski\Service;

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

    /** Member state codes VIES accepts. EL is Greece; XI is Northern Ireland. */
    private const MEMBER_STATES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES', 'FI', 'FR',
        'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO',
        'SE', 'SI', 'SK', 'XI',
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

        if (in_array($prefix, self::MEMBER_STATES, true) && strlen($clean) > 2) {
            return [$prefix, substr($clean, 2)];
        }

        $country = strtoupper(trim($fallbackCountry));
        // VIES calls Greece EL, while ISO country codes and WooCommerce use GR.
        if ($country === 'GR') {
            $country = 'EL';
        }

        if (! in_array($country, self::MEMBER_STATES, true)) {
            return null;
        }

        return [$country, $clean];
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

        if (! in_array($countryCode, self::MEMBER_STATES, true) || $number === '') {
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

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/json'],
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
        } else {
            printf(
                /* translators: %s: reason reported by VIES */
                esc_html__('Not valid in VIES (%s)', 'polski'),
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
