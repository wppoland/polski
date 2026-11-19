<?php

declare(strict_types=1);

namespace Polski\Service;

defined('ABSPATH') || exit;

/**
 * LocalBusiness JSON-LD from the Local business module settings, with the
 * seller data from the setup wizard and the WooCommerce store address as
 * fallbacks.
 *
 * existing-checked: BusinessInfoService - it renders the seller block from
 * polski_general as HTML and keeps the address as one free-text line;
 * LocalBusiness needs a split PostalAddress, hours and coordinates, so it reads
 * its own option and only borrows name, phone and e-mail from polski_general.
 *
 * @see https://developers.google.com/search/docs/appearance/structured-data/local-business
 */
final class LocalBusinessSchema
{
    private const OPTION = 'polski_local_business';

    /** Selectable types: LocalBusiness and the subtypes a Polish shop is likely to be. */
    public const TYPES = [
        'LocalBusiness',
        'HomeAndConstructionBusiness',
        'GeneralContractor',
        'HousePainter',
        'Electrician',
        'Plumber',
        'RoofingContractor',
        'Store',
        'HardwareStore',
        'HomeGoodsStore',
        'FurnitureStore',
        'GardenStore',
        'ProfessionalService',
        'AutomotiveBusiness',
    ];

    private const DAYS = [
        'mo' => 'Monday', 'tu' => 'Tuesday', 'we' => 'Wednesday', 'th' => 'Thursday',
        'fr' => 'Friday', 'sa' => 'Saturday', 'su' => 'Sunday',
        'pn' => 'Monday', 'wt' => 'Tuesday', 'sr' => 'Wednesday', 'cz' => 'Thursday',
        'pt' => 'Friday', 'so' => 'Saturday', 'nd' => 'Sunday',
    ];

    /**
     * The node, or null when the business has no name or address to publish.
     *
     * @return array<string, mixed>|null
     */
    public function build(): ?array
    {
        $saved = get_option(self::OPTION, []);
        $general = get_option('polski_general', []);
        $general = is_array($general) ? $general : [];
        [$country, $region] = array_pad(explode(':', (string) get_option('woocommerce_default_country', '')), 2, '');

        $street = trim((string) get_option('woocommerce_store_address', '') . ' ' . (string) get_option('woocommerce_store_address_2', ''));

        $settings = array_merge([
            'name' => (string) ($general['company_name'] ?? ''),
            'street' => $street,
            'postal_code' => (string) get_option('woocommerce_store_postcode', ''),
            'city' => (string) get_option('woocommerce_store_city', ''),
            'region' => $region,
            'country' => $country,
            'telephone' => (string) ($general['company_phone'] ?? ''),
            'email' => (string) ($general['company_email'] ?? ''),
        ], array_filter(is_array($saved) ? $saved : [], static fn (mixed $v): bool => $v !== '' && $v !== null));

        if (trim((string) $settings['name']) === '') {
            $settings['name'] = (string) get_bloginfo('name');
        }

        $logo = (int) get_theme_mod('custom_logo');
        $image = $logo > 0 ? wp_get_attachment_url($logo) : get_site_icon_url();

        $node = self::buildNode($settings, home_url('/'), is_string($image) ? $image : '');

        /**
         * Filters the LocalBusiness node before it is printed or merged.
         *
         * @param array<string, mixed>|null $node
         */
        $node = apply_filters('polski/local_business/node', $node);

        return is_array($node) ? $node : null;
    }

    /**
     * @param array<string, mixed> $s
     * @return array<string, mixed>|null
     */
    public static function buildNode(array $s, string $homeUrl, string $image = ''): ?array
    {
        $name = trim((string) ($s['name'] ?? ''));
        $address = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => trim((string) ($s['street'] ?? '')),
            'postalCode' => trim((string) ($s['postal_code'] ?? '')),
            'addressLocality' => trim((string) ($s['city'] ?? '')),
            'addressRegion' => trim((string) ($s['region'] ?? '')),
            'addressCountry' => strtoupper(trim((string) ($s['country'] ?? ''))),
        ]);

        // Google requires both; an address without street or town locates nothing.
        if ($name === '' || (! isset($address['streetAddress']) && ! isset($address['addressLocality']))) {
            return null;
        }

        $type = in_array($s['type'] ?? '', self::TYPES, true) ? (string) $s['type'] : 'LocalBusiness';

        $node = [
            '@type' => $type,
            '@id' => $homeUrl . '#localbusiness',
            'name' => $name,
            'url' => $homeUrl,
            'address' => $address,
        ];

        $image = (string) (($s['image'] ?? '') ?: $image);
        if ($image !== '') {
            $node['image'] = $image;
        }

        foreach (['telephone' => 'telephone', 'email' => 'email', 'price_range' => 'priceRange'] as $key => $property) {
            $value = trim((string) ($s[$key] ?? ''));
            if ($value !== '') {
                $node[$property] = $property === 'priceRange' ? mb_substr($value, 0, 99) : $value;
            }
        }

        $lat = str_replace(',', '.', trim((string) ($s['latitude'] ?? '')));
        $lng = str_replace(',', '.', trim((string) ($s['longitude'] ?? '')));
        if (is_numeric($lat) && is_numeric($lng) && abs((float) $lat) <= 90 && abs((float) $lng) <= 180) {
            $node['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $lat,
                'longitude' => (float) $lng,
            ];
        }

        $hours = self::parseOpeningHours((string) ($s['opening_hours'] ?? ''));
        if ($hours !== []) {
            $node['openingHoursSpecification'] = $hours;
        }

        $areas = array_values(array_filter(array_map('trim', explode(',', (string) ($s['area_served'] ?? '')))));
        if ($areas !== []) {
            $node['areaServed'] = count($areas) === 1 ? $areas[0] : $areas;
        }

        $sameAs = array_values(array_filter(
            array_map('trim', preg_split('/[\s,]+/', (string) ($s['same_as'] ?? '')) ?: []),
            static fn (string $url): bool => (bool) preg_match('#^https?://\S+$#i', $url),
        ));
        if ($sameAs !== []) {
            $node['sameAs'] = $sameAs;
        }

        return $node;
    }

    /**
     * Opening hours, one rule per line: "Mo-Fr 08:00-16:00", "Sa 09:00-13:00",
     * "Pn,Śr 10:00-12:00". English (Mo..Su) and Polish (Pn..Nd) day codes.
     * Lines that do not parse are skipped rather than guessed.
     *
     * @return list<array<string, mixed>>
     */
    public static function parseOpeningHours(string $raw): array
    {
        $specs = [];
        $order = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = strtolower(remove_accents(trim($line)));

            if (! preg_match('/^([a-z,\s-]+?)\s+(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})$/', $line, $m)) {
                continue;
            }

            $days = [];
            foreach (explode(',', $m[1]) as $part) {
                $range = array_map('trim', explode('-', $part));
                $from = self::DAYS[$range[0]] ?? null;
                $to = self::DAYS[$range[1] ?? $range[0]] ?? null;

                if ($from === null || $to === null) {
                    continue 2;
                }

                $start = (int) array_search($from, $order, true);
                $end = (int) array_search($to, $order, true);
                for ($i = $start; $i <= ($end < $start ? $end + 7 : $end); $i++) {
                    $days[] = $order[$i % 7];
                }
            }

            $opens = sprintf('%02d:%s', (int) $m[2], $m[3]);
            $closes = sprintf('%02d:%s', (int) $m[4], $m[5]);

            if ($days === [] || (int) $m[2] > 23 || (int) $m[4] > 23 || (int) $m[3] > 59 || (int) $m[5] > 59) {
                continue;
            }

            $days = array_values(array_unique($days));
            $specs[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => count($days) === 1 ? $days[0] : $days,
                'opens' => $opens,
                'closes' => $closes,
            ];
        }

        return $specs;
    }

    /**
     * Merge the business into an existing Organization node (Yoast's), so the
     * page describes one organization instead of two. The organization keeps
     * its @id, name and logo; the business adds its type and local facts.
     *
     * @param array<string, mixed> $organization
     * @param array<string, mixed> $business
     * @return array<string, mixed>
     */
    public static function mergeInto(array $organization, array $business): array
    {
        $types = array_merge((array) ($organization['@type'] ?? 'Organization'), [(string) $business['@type']]);
        $organization['@type'] = array_values(array_unique($types));

        $sameAs = array_values(array_unique(array_merge(
            (array) ($organization['sameAs'] ?? []),
            (array) ($business['sameAs'] ?? []),
        )));

        unset($business['@type'], $business['@id'], $business['name'], $business['sameAs']);
        $organization += $business;

        if ($sameAs !== []) {
            $organization['sameAs'] = $sameAs;
        }

        return $organization;
    }
}
