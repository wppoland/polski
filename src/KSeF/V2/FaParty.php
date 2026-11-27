<?php

declare(strict_types=1);

namespace Polski\KSeF\V2;

defined('ABSPATH') || exit;

/**
 * Seller or buyer, flattened to exactly what an FA(3) Podmiot block needs.
 *
 * The mapper takes these instead of a WC_Order so a document can be built and
 * schema checked without WordPress loaded, which is the only way the validation
 * test can run at all.
 */
final class FaParty
{
    /**
     * @param string $nip       Ten digits, or '' when the buyer has none (a consumer).
     * @param string $addressL2 Postcode and town, optional per TAdres.
     *
     * @throws \InvalidArgumentException When a field the schema declares mandatory is blank.
     */
    public function __construct(
        public readonly string $nip,
        public readonly string $name,
        public readonly string $countryCode,
        public readonly string $addressL1,
        public readonly string $addressL2 = '',
    ) {
        // TZnakowy512 has minLength 1 and TKodKraju is an enumeration, so an
        // unset shop address does not produce a slightly worse invoice, it
        // produces one KSeF refuses. Say which field, here, not in a schema dump.
        foreach (['name' => $name, 'countryCode' => $countryCode, 'addressL1' => $addressL1] as $field => $value) {
            if (trim($value) === '') {
                throw new \InvalidArgumentException(
                    esc_html("FaParty needs a non-empty {$field}; FA(3) rejects a blank one.")
                );
            }
        }
    }

    /** TNrNIP from the FA(3) schema. */
    private const NIP_PATTERN = '/^[1-9]((\d[1-9])|([1-9]\d))\d{7}$/';

    /** Weights of the NIP check digit, digits one to nine, mod 11. */
    private const NIP_WEIGHTS = [6, 5, 7, 2, 3, 4, 5, 6, 7];

    /** Whether the NIP is a valid Polish tax identifier: TNrNIP shape and checksum. */
    public function hasPolishNip(): bool
    {
        return self::isValidNip($this->nip);
    }

    /**
     * Whether the NIP has the TNrNIP shape, checksum aside.
     *
     * The mapper writes such a value as NIP and lets FaValidator refuse a bad
     * checksum. An EU VAT number goes to KodUE/NrVatUE (see euVat()); anything
     * else becomes BrakID on the buyer side.
     */
    public function claimsPolishNip(): bool
    {
        return preg_match(self::NIP_PATTERN, $this->nip) === 1;
    }

    /** Member states by ISO code, as WooCommerce stores the billing country. */
    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU',
        'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
    ];

    /** Whether the party sits in another member state. */
    public function inOtherEuCountry(): bool
    {
        $country = strtoupper($this->countryCode);

        return $country !== 'PL' && in_array($country, self::EU_COUNTRIES, true);
    }

    /**
     * The EU VAT number as TKodyKrajowUE prefix and TNrVatUE body, or null.
     *
     * Only for a party in another member state. A number typed without its
     * prefix takes the billing country's, which is how VIES spells Greece: EL.
     *
     * @return array{0: string, 1: string}|null
     */
    public function euVat(): ?array
    {
        if (! $this->inOtherEuCountry()) {
            return null;
        }

        $id = strtoupper($this->nip);
        $prefix = strtoupper($this->countryCode) === 'GR' ? 'EL' : strtoupper($this->countryCode);

        if (str_starts_with($id, $prefix)) {
            $id = substr($id, 2);
        }

        return preg_match('/^[0-9A-Z+*]{1,12}$/', $id) === 1 ? [$prefix, $id] : null;
    }

    public static function isValidNip(string $nip): bool
    {
        if (preg_match(self::NIP_PATTERN, $nip) !== 1) {
            return false;
        }

        $sum = 0;

        foreach (self::NIP_WEIGHTS as $i => $weight) {
            $sum += (int) $nip[$i] * $weight;
        }

        // A remainder of 10 has no check digit, so no NIP is issued with one.
        return $sum % 11 === (int) $nip[9];
    }
}
