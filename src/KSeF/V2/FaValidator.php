<?php

declare(strict_types=1);

namespace Polski\KSeF\V2;

defined('ABSPATH') || exit;

/**
 * Checks a document against schemat_FA(3)_v1-0E.xsd before it is sent.
 *
 * KSeF accepts an invoice into a session and only later reports that it was
 * rejected, with a message that names an element and not a reason. Failing here
 * costs nothing and says exactly which line is wrong, so nothing goes on the
 * wire unvalidated.
 *
 * Two rules the schema cannot express are checked on top: every NIP must pass
 * its checksum (TNrNIP is only a pattern), and a foreign-currency document must
 * carry the PLN exchange rate and VAT (art. 106e ust. 11), which the schema
 * leaves optional because it cannot tie them to KodWaluty.
 *
 * The schema and the three it pulls in are vendored beside this file with their
 * schemaLocation attributes rewritten to relative paths. Left as published they
 * point at crd.gov.pl over plain http, which would mean a network call, and a
 * silent pass whenever that host is slow or blocked.
 */
final class FaValidator
{
    private const SCHEMA = __DIR__ . '/schema/FA_v3_1-0E.xsd';

    /**
     * @return list<string> Empty when the document is valid.
     */
    public function errors(string $xml): array
    {
        if (! is_readable(self::SCHEMA)) {
            return ['The FA(3) schema is missing from the plugin: ' . self::SCHEMA];
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();

        if (! $dom->loadXML($xml)) {
            $messages = $this->collect();
            libxml_use_internal_errors($previous);

            return $messages === [] ? ['The document is not well-formed XML.'] : $messages;
        }

        $dom->schemaValidate(self::SCHEMA);
        $messages = $this->collect();

        libxml_use_internal_errors($previous);

        return [...$messages, ...$this->businessErrors($dom)];
    }

    /**
     * @return list<string>
     */
    private function businessErrors(\DOMDocument $dom): array
    {
        $messages = [];

        foreach ($dom->getElementsByTagName('NIP') as $nip) {
            // NIP sits in DaneIdentyfikacyjne under Podmiot1, 2 or 3.
            $party = (string) $nip->parentNode?->parentNode?->nodeName;

            if (! FaParty::isValidNip(trim($nip->textContent))) {
                $messages[] = sprintf(
                    '%s: NIP %s fails the NIP checksum. Correct it on the invoice; it is not sent as a consumer invoice instead.',
                    $party === 'Podmiot2' ? 'Buyer' : ($party === 'Podmiot1' ? 'Seller' : $party),
                    trim($nip->textContent)
                );
            }
        }

        $currency = trim((string) $dom->getElementsByTagName('KodWaluty')->item(0)?->textContent);

        if ($currency === '' || $currency === 'PLN') {
            return $messages;
        }

        $missing = false;

        foreach ($dom->getElementsByTagName('FaWiersz') as $wiersz) {
            $missing = $missing || $wiersz->getElementsByTagName('KursWaluty')->length === 0;
        }

        foreach (['P_14_1', 'P_14_2', 'P_14_3', 'P_14_4'] as $vat) {
            $missing = $missing || ($dom->getElementsByTagName($vat)->length > 0 && $dom->getElementsByTagName($vat . 'W')->length === 0);
        }

        if ($missing) {
            $messages[] = sprintf(
                'The invoice is in %s but has no PLN exchange rate: KSeF needs KursWaluty on each line and the VAT converted to PLN (P_14_xW), at the NBP rate from the last working day before the tax point (art. 31a). The NBP rate lookup returned nothing; retry later or supply one through the polski/pro/invoice/exchange_rate filter.',
                $currency
            );
        }

        return $messages;
    }

    public function isValid(string $xml): bool
    {
        return $this->errors($xml) === [];
    }

    /**
     * @return list<string>
     */
    private function collect(): array
    {
        $messages = [];

        foreach (libxml_get_errors() as $error) {
            $messages[] = sprintf('line %d: %s', $error->line, trim($error->message));
        }

        libxml_clear_errors();

        return $messages;
    }
}
