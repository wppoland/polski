<?php

declare(strict_types=1);

namespace Polski\Service;

defined('ABSPATH') || exit;

/**
 * Shipping and return policy nodes for Google merchant listings.
 *
 * Builds OfferShippingDetails (rate, destination, handling and transit time)
 * and MerchantReturnPolicy from the Structured Data module settings. Google
 * treats shippingRate, shippingDestination and deliveryTime as one required
 * set, so shipping details are left out when no numeric rate is known rather
 * than published half-filled. Both are claims the merchant makes, so both
 * are off until switched on in the settings.
 *
 * existing-checked: ProductHooks::enrichStructuredData - it built a rate-less
 * shippingDetails inline for the product offer only; variant offers and the
 * organization node need the same nodes, so the logic moved here and
 * ProductHooks no longer writes shippingDetails.
 *
 * @see https://developers.google.com/search/docs/appearance/structured-data/merchant-listing
 * @see https://developers.google.com/search/docs/appearance/structured-data/return-policy
 */
final class MerchantPolicySchema
{
    private const OPTION = 'polski_seo';

    public const RETURN_CATEGORIES = [
        'finite' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
        'unlimited' => 'https://schema.org/MerchantReturnUnlimitedWindow',
        'not_permitted' => 'https://schema.org/MerchantReturnNotPermitted',
    ];

    public const RETURN_METHODS = [
        'mail' => 'https://schema.org/ReturnByMail',
        'store' => 'https://schema.org/ReturnInStore',
        'kiosk' => 'https://schema.org/ReturnAtKiosk',
    ];

    public const RETURN_FEES = [
        'customer' => 'https://schema.org/ReturnFeesCustomerResponsibility',
        'free' => 'https://schema.org/FreeReturn',
        'shipping_fee' => 'https://schema.org/ReturnShippingFees',
    ];

    private ?float $wooRate = null;
    private bool $wooRateLoaded = false;

    public function __construct(
        private readonly DeliveryTimeService $deliveryTime,
    ) {
    }

    /**
     * MerchantReturnPolicy for the shop, or null when switched off.
     *
     * @return array<string, mixed>|null
     */
    public function returnPolicy(): ?array
    {
        $settings = $this->settings();

        if (! ($settings['schema_return_policy'] ?? false)) {
            return null;
        }

        $policy = self::buildReturnPolicy($settings, $this->baseCountry(), get_woocommerce_currency());

        if ($policy !== null) {
            $policy = ['@id' => home_url('/#merchant-return-policy')] + $policy;
        }

        /**
         * Filters the MerchantReturnPolicy node used on offers and on the organization.
         *
         * @param array<string, mixed>|null $policy
         */
        $policy = apply_filters('polski/structured_data/return_policy', $policy);

        return is_array($policy) ? $policy : null;
    }

    /**
     * OfferShippingDetails for a product, or null when no rate is known.
     *
     * @return array<string, mixed>|null
     */
    public function shippingDetails(\WC_Product $product): ?array
    {
        $settings = $this->settings();

        if (! ($settings['schema_shipping'] ?? false)) {
            return null;
        }

        $country = self::countries((string) ($settings['shipping_country'] ?? ''), $this->baseCountry())[0] ?? '';
        $rate = self::parseAmount((string) ($settings['shipping_rate'] ?? ''));
        $rate ??= $this->wooCommerceRate($country);

        // The delivery-time text ("2-3 dni robocze") still feeds the transit
        // time when no explicit transit days are set.
        $textDays = [];
        if ($settings['schema_delivery_time'] ?? true) {
            preg_match_all('/\d+/', $this->deliveryTime->getDeliveryTimeText($product), $found);
            $textDays = array_map('intval', $found[0]);
        }

        $details = self::buildShippingDetails($settings, $rate, $country, get_woocommerce_currency(), $textDays);

        /**
         * Filters the OfferShippingDetails node of one product offer.
         *
         * @param array<string, mixed>|null $details
         * @param \WC_Product               $product
         */
        $details = apply_filters('polski/structured_data/shipping_details', $details, $product);

        return is_array($details) ? $details : null;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>|null
     */
    public static function buildReturnPolicy(array $settings, string $baseCountry, string $currency): ?array
    {
        $category = self::RETURN_CATEGORIES[(string) ($settings['return_category'] ?? 'finite')] ?? self::RETURN_CATEGORIES['finite'];
        $countries = self::countries((string) ($settings['return_country'] ?? ''), $baseCountry);

        if ($countries === []) {
            return null;
        }

        $policy = [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => count($countries) === 1 ? $countries[0] : $countries,
            'returnPolicyCategory' => $category,
        ];

        if ($category === self::RETURN_CATEGORIES['not_permitted']) {
            return $policy;
        }

        if ($category === self::RETURN_CATEGORIES['finite']) {
            $policy['merchantReturnDays'] = max(1, (int) ($settings['return_days'] ?? 14));
        }

        $policy['returnMethod'] = self::RETURN_METHODS[(string) ($settings['return_method'] ?? 'mail')] ?? self::RETURN_METHODS['mail'];
        $fees = self::RETURN_FEES[(string) ($settings['return_fees'] ?? 'customer')] ?? self::RETURN_FEES['customer'];
        $amount = self::parseAmount((string) ($settings['return_shipping_fee'] ?? ''));

        // ReturnShippingFees without the amount says nothing, so it falls back
        // to the honest generic: the customer pays for the return.
        if ($fees === self::RETURN_FEES['shipping_fee'] && $amount === null) {
            $fees = self::RETURN_FEES['customer'];
        }

        $policy['returnFees'] = $fees;

        if ($fees === self::RETURN_FEES['shipping_fee']) {
            $policy['returnShippingFeesAmount'] = [
                '@type' => 'MonetaryAmount',
                'value' => $amount,
                'currency' => $currency,
            ];
        }

        return $policy;
    }

    /**
     * @param array<string, mixed> $settings
     * @param list<int>            $textDays Numbers read from the delivery-time text.
     * @return array<string, mixed>|null
     */
    public static function buildShippingDetails(array $settings, ?float $rate, string $country, string $currency, array $textDays = []): ?array
    {
        if ($rate === null || $country === '') {
            return null;
        }

        $handling = self::range($settings['handling_min'] ?? '', $settings['handling_max'] ?? '') ?? [0, 1];
        $transit = self::range($settings['transit_min'] ?? '', $settings['transit_max'] ?? '');

        if ($transit === null && $textDays !== []) {
            // "2-3 dni robocze" is a range, not 23: first and last number.
            $first = $textDays[0];
            $last = $textDays[count($textDays) - 1];
            $transit = count($textDays) > 1 ? [min($first, $last), max($first, $last)] : [min(1, $first), $first];
        }

        $transit ??= [1, 3];

        return [
            '@type' => 'OfferShippingDetails',
            'shippingRate' => [
                '@type' => 'MonetaryAmount',
                'value' => $rate,
                'currency' => $currency,
            ],
            'shippingDestination' => [
                '@type' => 'DefinedRegion',
                'addressCountry' => $country,
            ],
            'deliveryTime' => [
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => self::days($handling),
                'transitTime' => self::days($transit),
            ],
        ];
    }

    /**
     * A money amount from a settings field: "49", "49,90", "1 299.00". Empty or junk is null.
     */
    public static function parseAmount(string $raw): ?float
    {
        $raw = str_replace([' ', ','], ['', '.'], trim($raw));

        return is_numeric($raw) && (float) $raw >= 0 ? round((float) $raw, 2) : null;
    }

    /**
     * Two-letter country codes from a comma separated field, or the shop's base country.
     *
     * @return list<string>
     */
    public static function countries(string $raw, string $fallback): array
    {
        $codes = array_filter(array_map(
            static fn (string $code): string => strtoupper(trim($code)),
            explode(',', $raw),
        ), static fn (string $code): bool => (bool) preg_match('/^[A-Z]{2}$/', $code));

        if ($codes === [] && preg_match('/^[A-Z]{2}$/', $fallback)) {
            $codes = [$fallback];
        }

        return array_values(array_slice(array_unique($codes), 0, 50));
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private static function range(mixed $min, mixed $max): ?array
    {
        if (! is_numeric($min) && ! is_numeric($max)) {
            return null;
        }

        $low = max(0, (int) (is_numeric($min) ? $min : $max));
        $high = is_numeric($max) ? max(0, (int) $max) : $low;

        return [min($low, $high), max($low, $high)];
    }

    /**
     * @param array{0: int, 1: int} $range
     * @return array<string, mixed>
     */
    private static function days(array $range): array
    {
        return [
            '@type' => 'QuantitativeValue',
            'minValue' => $range[0],
            'maxValue' => $range[1],
            'unitCode' => 'DAY',
        ];
    }

    /**
     * Cheapest fixed rate the WooCommerce zone for the country offers: a free
     * shipping method without conditions is 0, a flat rate with a plain number
     * is its cost. Formulas and conditional methods say nothing certain about
     * the rate, so they are skipped.
     */
    private function wooCommerceRate(string $country): ?float
    {
        if ($this->wooRateLoaded) {
            return $this->wooRate;
        }

        $this->wooRateLoaded = true;

        if ($country === '' || ! class_exists(\WC_Shipping_Zones::class)) {
            return null;
        }

        $zone = \WC_Shipping_Zones::get_zone_matching_package([
            'destination' => ['country' => $country, 'state' => '', 'postcode' => '', 'city' => ''],
        ]);

        $rates = [];

        foreach ($zone->get_shipping_methods(true) as $method) {
            if ($method->id === 'free_shipping' && (string) $method->get_option('requires', '') === '') {
                $rates[] = 0.0;
            } elseif ($method->id === 'flat_rate') {
                $cost = self::parseAmount((string) $method->get_option('cost', ''));
                if ($cost !== null) {
                    $rates[] = $cost;
                }
            }
        }

        $this->wooRate = $rates === [] ? null : min($rates);

        return $this->wooRate;
    }

    private function baseCountry(): string
    {
        $base = (string) get_option('woocommerce_default_country', '');

        return strtoupper(explode(':', $base)[0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $settings = get_option(self::OPTION, []);

        return is_array($settings) ? $settings : [];
    }
}
