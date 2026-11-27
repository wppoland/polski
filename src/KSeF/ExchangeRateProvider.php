<?php

declare(strict_types=1);

namespace Polski\KSeF;

/**
 * NBP (Narodowy Bank Polski) exchange rates.
 *
 * A Polish VAT invoice issued in a foreign currency must also state the VAT
 * amount converted to PLN, using the NBP average rate published on the last
 * business day preceding the tax point (art. 31a ustawy o VAT). This provider
 * fetches table A rates from api.nbp.pl, caches them for a day, and exposes the
 * rate + its publication date so the PDF can print the legally required note.
 *
 * The whole lookup is overridable via the 'polski/pro/invoice/exchange_rate'
 * filter for merchants who source rates elsewhere.
 */
final class ExchangeRateProvider
{
    /**
     * Rate to convert one unit of $currency into PLN for the day before $date,
     * plus the NBP table publication date. Null when unavailable.
     *
     * @return array{rate: float, date: string}|null
     */
    public function plnRate(string $currency, \DateTimeImmutable $date): ?array
    {
        $currency = strtoupper($currency);

        /** @var array{rate: float, date: string}|null $override */
        $override = apply_filters('polski/pro/invoice/exchange_rate', null, $currency, $date);
        if (is_array($override) && isset($override['rate'], $override['date'])) {
            return ['rate' => (float) $override['rate'], 'date' => (string) $override['date']];
        }

        if ($currency === 'PLN' || $currency === '') {
            return null;
        }

        // NBP "last rate on or before the day preceding the tax point".
        $effective = $date->modify('-1 day')->format('Y-m-d');
        $cacheKey = 'polski_pro_nbp_' . $currency . '_' . $effective;

        $cached = get_transient($cacheKey);
        if (is_array($cached) && isset($cached['rate'], $cached['date'])) {
            return ['rate' => (float) $cached['rate'], 'date' => (string) $cached['date']];
        }

        // /last/1/ would return today's table whatever the date. Ask for a
        // window ending on the effective day instead and take its last table;
        // ten days covers the longest run of non-working days NBP skips.
        $url = sprintf(
            'https://api.nbp.pl/api/exchangerates/rates/a/%s/%s/%s/?format=json',
            rawurlencode(strtolower($currency)),
            $date->modify('-11 days')->format('Y-m-d'),
            $effective,
        );

        $response = wp_remote_get($url, ['timeout' => 8, 'headers' => ['Accept' => 'application/json']]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $rates = is_array($body['rates'] ?? null) ? $body['rates'] : [];
        $entry = $rates === [] ? null : end($rates);

        if (! is_array($entry) || ! isset($entry['mid'], $entry['effectiveDate'])) {
            return null;
        }

        $result = ['rate' => (float) $entry['mid'], 'date' => (string) $entry['effectiveDate']];
        set_transient($cacheKey, $result, DAY_IN_SECONDS);

        return $result;
    }
}
