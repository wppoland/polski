<?php

declare(strict_types=1);

/**
 * FA(3) corrective invoices (RodzajFaktury KOR) validated against the schema
 * and checked against the Ministry's information sheet.
 *
 *   php tests/ksef-fa-correction-test.php
 *
 * The schema accepts almost any signs on a correction line, so validity alone
 * proves little. The sheet says more: header totals by difference (Fa, "pola
 * dotyczace podstaw opodatkowania ... wypelnia sie poprzez roznice"), returned
 * goods as a negative quantity at the original price (Przyklad 23, method one),
 * and a price cut as a negative unit price (sample file Przyklad 3). These
 * checks pin both shapes and that P_8B times P_9A always equals P_11.
 */

define('ABSPATH', __DIR__);

if (! function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return $text;
    }
}

if (! function_exists('__')) {
    function __(string $text, string $domain = ''): string
    {
        return $text;
    }
}

$root = dirname(__DIR__) . '/';

require $root . 'src/KSeF/Model/InvoiceType.php';
require $root . 'src/KSeF/Model/InvoiceStatus.php';
require $root . 'src/KSeF/Model/InvoiceItem.php';
require $root . 'src/KSeF/Model/Invoice.php';
require $root . 'src/KSeF/V2/FaParty.php';
require $root . 'src/KSeF/V2/VerificationLink.php';
require $root . 'src/KSeF/V2/FaMapper.php';
require $root . 'src/KSeF/V2/FaValidator.php';

use Polski\KSeF\V2\FaMapper;
use Polski\KSeF\V2\FaParty;
use Polski\KSeF\V2\FaValidator;
use Polski\KSeF\Model\Invoice;
use Polski\KSeF\Model\InvoiceItem;
use Polski\KSeF\Model\InvoiceStatus;
use Polski\KSeF\Model\InvoiceType;

$failures = 0;
$checks = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    echo ($ok ? 'ok    ' : 'FAIL  ') . $label . "\n";
    if (! $ok) {
        $failures++;
        if ($detail !== '') {
            echo '      ' . str_replace("\n", "\n      ", trim($detail)) . "\n";
        }
    }
}

function item(int $id, ?int $productId, string $name, float $qty, float $net, float $rate): InvoiceItem
{
    return new InvoiceItem(
        id: $id,
        invoiceId: 1,
        productId: $productId,
        name: $name,
        quantity: $qty,
        unit: 'szt.',
        netPrice: $net,
        vatRate: $rate,
        vatAmount: round($net * $rate / 100, 2),
        grossPrice: round($net * (1 + $rate / 100), 2),
        sortOrder: $id,
    );
}

/**
 * @param list<InvoiceItem> $items
 */
function invoice(InvoiceType $type, string $number, string $issued, array $items, ?string $ksef = null, ?string $reason = null): Invoice
{
    $net = array_sum(array_map(static fn (InvoiceItem $i) => $i->netPrice, $items));
    $vat = array_sum(array_map(static fn (InvoiceItem $i) => $i->vatAmount, $items));

    return new Invoice(
        id: 1,
        orderId: 42,
        originalInvoiceId: null,
        sourceRefundId: $type === InvoiceType::Korygujaca ? 7 : null,
        type: $type,
        number: $number,
        status: InvoiceStatus::from('issued'),
        nipSeller: '5260250274',
        nipBuyer: null,
        correctionReason: $reason,
        netTotal: $net,
        vatTotal: $vat,
        grossTotal: $net + $vat,
        currency: 'PLN',
        pdfPath: null,
        ksefReference: $ksef,
        ksefStatus: null,
        issuedAt: new DateTimeImmutable($issued),
        createdAt: new DateTimeImmutable($issued),
        items: $items,
    );
}

/**
 * P_8B, P_9A and P_11 of every FaWiersz, in document order.
 *
 * @return list<array{0: string, 1: string, 2: string}>
 */
function lines(string $xml): array
{
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $out = [];
    foreach ($dom->getElementsByTagName('FaWiersz') as $wiersz) {
        $value = static fn (string $name): string => (string) $wiersz->getElementsByTagName($name)->item(0)?->textContent;
        $out[] = [$value('P_8B'), $value('P_9A'), $value('P_11')];
    }

    return $out;
}

function sumsUp(string $xml): bool
{
    foreach (lines($xml) as [$quantity, $unitPrice, $net]) {
        if (abs(round((float) $quantity * (float) $unitPrice, 2) - (float) $net) > 0.005) {
            return false;
        }
    }

    return true;
}

$mapper = new FaMapper('Polski Pro test');
$validator = new FaValidator();
$seller = new FaParty('5260250274', 'Sklep Testowy sp. z o.o.', 'PL', 'ul. Prosta 1', '00-001 Warszawa');
$buyer = new FaParty('', 'Jan Kowalski', 'PL', 'ul. Krotka 3', '80-001 Gdansk');
$ksefNumber = '5252445716-20260912-0A1B2C3D4E5F-A1';

// The information sheet's own case (Przyklad 22 and 23): 3 blankets at 90 and
// 5 sets of bedding at 120, then 1 blanket and 2 sets come back. Plus shipping
// at 8%, which has no product id and has to be matched by name.
$original = invoice(InvoiceType::FakturaVAT, 'FV/2026/09/12', '2026-09-14 12:00:00', [
    item(1, 101, 'Koc polarowy', 3.0, 270.00, 23.0),
    item(2, 102, 'Posciel bawelniana', 5.0, 600.00, 23.0),
    item(3, null, 'Wysylka', 1.0, 20.00, 8.0),
], $ksefNumber);

// Stored the way InvoiceDataBuilder::buildCorrectionFromRefund stores a refund:
// positive quantity, negative money.
$return = invoice(InvoiceType::Korygujaca, 'FK/2026/09/1', '2026-09-20 09:00:00', [
    item(1, 101, 'Koc polarowy', 1.0, -90.00, 23.0),
    item(2, 102, 'Posciel bawelniana', 2.0, -240.00, 23.0),
    item(3, null, 'Wysylka', 1.0, -20.00, 8.0),
], null, 'Zwrot towaru');

$xml = $mapper->toXml($return, $seller, $buyer, $original);

// 1. It is an FA(3) KOR the schema accepts.
check('a returned-goods correction validates against FA(3)', $validator->isValid($xml), implode("\n", $validator->errors($xml)));
check('RodzajFaktury is KOR', str_contains($xml, '<RodzajFaktury>KOR</RodzajFaktury>'));
check('PrzyczynaKorekty carries the refund reason', str_contains($xml, '<PrzyczynaKorekty>Zwrot towaru</PrzyczynaKorekty>'));
check('an in-minus correction says TypKorekty 2', str_contains($xml, '<TypKorekty>2</TypKorekty>'));
check('DaneFaKorygowanej has the original date and number', str_contains($xml, '<DataWystFaKorygowanej>2026-09-14</DataWystFaKorygowanej>')
    && str_contains($xml, '<NrFaKorygowanej>FV/2026/09/12</NrFaKorygowanej>'));
check('and its KSeF number behind the NrKSeF marker', preg_match('#<NrKSeF>1</NrKSeF>\s*<NrKSeFFaKorygowanej>' . preg_quote($ksefNumber, '#') . '</NrKSeFFaKorygowanej>#', $xml) === 1);

// 2. Header totals are the difference, per rate band.
check('P_13_1 and P_14_1 are the 23% difference', str_contains($xml, '<P_13_1>-330.00</P_13_1>') && str_contains($xml, '<P_14_1>-75.90</P_14_1>'));
check('P_13_2 and P_14_2 are the 8% difference', str_contains($xml, '<P_13_2>-20.00</P_13_2>') && str_contains($xml, '<P_14_2>-1.60</P_14_2>'));
check('P_15 is the gross difference', str_contains($xml, '<P_15>-427.50</P_15>'));

// 3. Lines as in Przyklad 23, method one: negative quantity, original price.
check('returned lines are written as the information sheet shows', lines($xml) === [
    ['-1.000000', '90.00', '-90.00'],
    ['-2.000000', '120.00', '-240.00'],
    ['-1.000000', '20.00', '-20.00'],
], (string) json_encode(lines($xml)));
check('every returned line multiplies out to its P_11', sumsUp($xml));

// 4. An amount-only refund: InvoiceDataBuilder falls back to the original
//    quantity, so the unit price no longer matches and it is a price cut, as
//    in sample file Przyklad 3.
$cut = invoice(InvoiceType::Korygujaca, 'FK/2026/09/2', '2026-09-20 09:00:00', [
    item(1, 102, 'Posciel bawelniana', 5.0, -100.00, 23.0),
], null, 'Rabat za uszkodzone opakowanie');
$cutXml = $mapper->toXml($cut, $seller, $buyer, $original);
check('a price-cut correction validates', $validator->isValid($cutXml), implode("\n", $validator->errors($cutXml)));
check('and keeps the quantity with a negative unit price', lines($cutXml) === [['5.000000', '-20.00', '-100.00']], (string) json_encode(lines($cutXml)));

// 5. The same product at another rate is not the same line.
$otherRate = invoice(InvoiceType::Korygujaca, 'FK/2026/09/3', '2026-09-20 09:00:00', [
    item(1, 101, 'Koc polarowy', 1.0, -90.00, 8.0),
]);
check('a line is only matched at the same rate', lines($mapper->toXml($otherRate, $seller, $buyer, $original)) === [['1.000000', '-90.00', '-90.00']]);

// 6. The shape older fixtures use, a negative stored quantity, used to come
//    out as P_8B -1 with P_9A -40, which multiplies to +40 against P_11 -40.
$legacy = invoice(InvoiceType::Korygujaca, 'FK/2026/09/4', '2026-09-20 09:00:00', [
    item(1, null, 'Kubek', -1.0, -40.00, 23.0),
]);
$legacyXml = $mapper->toXml($legacy, $seller, $buyer, invoice(InvoiceType::FakturaVAT, 'FV/2026/09/1', '2026-09-01 10:00:00', [
    item(1, null, 'Kubek', 1.0, 40.00, 23.0),
]));
check('a negative stored quantity no longer yields a line that multiplies to the wrong sign', sumsUp($legacyXml) && lines($legacyXml) === [['-1.000000', '40.00', '-40.00']], (string) json_encode(lines($legacyXml)));

// 7. An in-plus correction: TypKorekty depends on when its cause arose, which
//    the invoice cannot say, so it is left out.
$plus = invoice(InvoiceType::Korygujaca, 'FK/2026/09/5', '2026-09-20 09:00:00', [
    item(1, 102, 'Posciel bawelniana', 5.0, 50.00, 23.0),
], null, 'Doplata za wersje premium');
$plusXml = $mapper->toXml($plus, $seller, $buyer, $original);
check('an in-plus correction validates', $validator->isValid($plusXml), implode("\n", $validator->errors($plusXml)));
check('and carries no TypKorekty', ! str_contains($plusXml, '<TypKorekty>'));
check('and its price rise multiplies out', sumsUp($plusXml) && lines($plusXml) === [['5.000000', '10.00', '50.00']], (string) json_encode(lines($plusXml)));

// 8. A plain VAT invoice is untouched by any of this.
$plainXml = $mapper->toXml($original, $seller, $buyer);
check('a VAT invoice keeps positive quantities and prices', lines($plainXml)[0] === ['3.000000', '90.00', '270.00']);
check('and has no correction fields', ! str_contains($plainXml, '<TypKorekty>') && ! str_contains($plainXml, '<DaneFaKorygowanej>'));

// 9. The schema has to be live on the new element, or 1 proves nothing.
check('the validator rejects a TypKorekty outside 1, 2, 3', ! $validator->isValid(
    str_replace('<TypKorekty>2</TypKorekty>', '<TypKorekty>4</TypKorekty>', $xml)
));
check('and TypKorekty placed before PrzyczynaKorekty', ! $validator->isValid(
    str_replace(
        "<PrzyczynaKorekty>Zwrot towaru</PrzyczynaKorekty>\n    <TypKorekty>2</TypKorekty>",
        "<TypKorekty>2</TypKorekty>\n    <PrzyczynaKorekty>Zwrot towaru</PrzyczynaKorekty>",
        $xml
    )
));

// 10. A correction line keeps the GTU of its product.
$gtuXml = (new FaMapper('Polski Pro test', static fn (?int $productId): string => $productId === 101 ? 'GTU_06' : ''))->toXml($return, $seller, $buyer, $original);
check('a correction with a GTU line validates', $validator->isValid($gtuXml), implode("\n", $validator->errors($gtuXml)));
check('and declares GTU only on the line whose product has one', substr_count($gtuXml, '<GTU>GTU_06</GTU>') === 1);

echo "\n{$checks} checks, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
