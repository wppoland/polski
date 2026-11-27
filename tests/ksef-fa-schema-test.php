<?php

declare(strict_types=1);

/**
 * FA(3) documents validated against the Ministry schema.
 *
 *   php tests/ksef-fa-schema-test.php
 *
 * This is the check the KSeF 1.0 integration never had. Its mapper produced a
 * document that no schema had ever seen, so the first thing a working transport
 * would have earned was a rejection. Every document the plugin can build gets
 * run through schemat_FA(3)_v1-0E.xsd here, offline. FA(3) is mandatory for
 * invoices issued from 2026-02-01.
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
require $root . 'src/KSeF/V2/KSeFCrypto.php';
require $root . 'src/KSeF/V2/SessionRequest.php';

use Polski\KSeF\V2\FaMapper;
use Polski\KSeF\V2\FaParty;
use Polski\KSeF\V2\FaValidator;
use Polski\KSeF\V2\SessionRequest;
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

function item(int $id, string $name, float $qty, float $net, float $rate, ?int $productId = null): InvoiceItem
{
    return new InvoiceItem(
        id: $id,
        invoiceId: 1,
        productId: $productId,
        name: $name,
        quantity: $qty,
        unit: 'szt',
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
function invoice(
    InvoiceType $type,
    string $number,
    array $items,
    ?string $ksefReference = null,
    ?string $correctionReason = null,
    string $currency = 'PLN',
): Invoice {
    $net = array_sum(array_map(static fn (InvoiceItem $i) => $i->netPrice, $items));
    $vat = array_sum(array_map(static fn (InvoiceItem $i) => $i->vatAmount, $items));

    return new Invoice(
        id: 1,
        orderId: 42,
        originalInvoiceId: null,
        sourceRefundId: null,
        type: $type,
        number: $number,
        status: InvoiceStatus::from('issued'),
        nipSeller: '5260250274',
        nipBuyer: null,
        correctionReason: $correctionReason,
        netTotal: $net,
        vatTotal: $vat,
        grossTotal: $net + $vat,
        currency: $currency,
        pdfPath: null,
        ksefReference: $ksefReference,
        ksefStatus: null,
        issuedAt: new DateTimeImmutable('2026-08-11 10:15:00'),
        createdAt: new DateTimeImmutable('2026-08-11 10:15:00'),
        items: $items,
    );
}

$mapper = new FaMapper('Polski Pro 1.2.3');
$validator = new FaValidator();

$seller = new FaParty('5260250274', 'Sklep Testowy sp. z o.o.', 'PL', 'ul. Prosta 1', '00-001 Warszawa');
$company = new FaParty('9999999999', 'Nabywca sp. z o.o.', 'PL', 'ul. Dluga 7', '30-001 Krakow');
$consumer = new FaParty('', 'Jan Kowalski', 'PL', 'ul. Krotka 3', '80-001 Gdansk');

// 1. A plain business-to-business sale across three VAT rates.
$plain = $mapper->toXml(
    invoice(InvoiceType::FakturaVAT, 'FV/2026/08/1', [
        item(1, 'Hosting roczny', 1.0, 1200.00, 23.0),
        item(2, 'Ksiazka drukowana', 3.0, 90.00, 5.0),
        item(3, 'Uslugi hotelowe', 2.0, 400.00, 8.0),
    ]),
    $seller,
    $company,
);
check('a plain VAT invoice validates against schemat_FA(3)_v1-0E.xsd', $validator->isValid($plain), implode("\n", $validator->errors($plain)));

// 1a. A VAT-active seller with an art. 43 exempt line and cash accounting: the
// line is "zw" (P_13_7), never "0 KR", Zwolnienie states the basis in P_19A, P_16 is 1.
$exemptMapper = new FaMapper(
    'Polski Pro 1.2.3',
    lineExemption: static fn (?int $id): string => $id === 77 ? 'art. 43 ust. 1 pkt 29 lit. c ustawy o VAT' : '',
    cashAccounting: static fn (): bool => true,
);
$exempt = $exemptMapper->toXml(
    invoice(InvoiceType::FakturaVAT, 'FV/2026/08/1A', [
        item(1, 'Konsultacja', 1.0, 500.00, 23.0),
        item(2, 'Szkolenie zawodowe', 1.0, 1000.00, 0.0, 77),
    ]),
    $seller,
    $company,
);
check('an invoice with an exempt line validates against the schema', $validator->isValid($exempt), implode("\n", $validator->errors($exempt)));
check('the exempt line is zw and lands in P_13_7, not 0 KR', str_contains($exempt, '<P_12>zw</P_12>') && str_contains($exempt, '<P_13_7>1000.00</P_13_7>') && ! str_contains($exempt, '0 KR'));
check('Zwolnienie states the basis in P_19A', str_contains($exempt, '<P_19>1</P_19>') && str_contains($exempt, '<P_19A>art. 43 ust. 1 pkt 29 lit. c ustawy o VAT</P_19A>'));
check('cash accounting sets P_16 to 1', str_contains($exempt, '<P_16>1</P_16>'));
check('a plain invoice keeps P_16 at 2 and P_19N', str_contains($plain, '<P_16>2</P_16>') && str_contains($plain, '<P_19N>1</P_19N>'));

// 1b. Payment block and order number: paid by card, unpaid by transfer with a
// due date and account, and a gateway without a TFormaPlatnosci code.
$terms = static fn (array $t): Closure => static fn (Invoice $i): array => $t + ['paid' => null, 'due' => null, 'method' => '', 'title' => '', 'account' => '', 'order' => '', 'ordered' => null];
$lines = [item(1, 'Bilet', 1.0, 150.00, 23.0)];
$paidXml = (new FaMapper('Polski Pro 1.2.3', paymentTerms: $terms(['paid' => '2026-08-10', 'method' => 'stripe_cc', 'title' => 'Karta', 'account' => 'PL61109010140000071219812874', 'order' => '1043', 'ordered' => '2026-08-09'])))
    ->toXml(invoice(InvoiceType::FakturaVAT, 'FV/2026/08/1B', $lines), $seller, $company);
check('a paid invoice with an order number validates', $validator->isValid($paidXml), implode("\n", $validator->errors($paidXml)));
check('and says paid, on which day, by card, with no account to pay into', str_contains($paidXml, '<Zaplacono>1</Zaplacono>') && str_contains($paidXml, '<DataZaplaty>2026-08-10</DataZaplaty>') && str_contains($paidXml, '<FormaPlatnosci>2</FormaPlatnosci>') && ! str_contains($paidXml, 'NrRB'));
check('and carries the order number and date', str_contains($paidXml, '<NrZamowienia>1043</NrZamowienia>') && str_contains($paidXml, '<DataZamowienia>2026-08-09</DataZamowienia>'));
$dueXml = (new FaMapper('Polski Pro 1.2.3', paymentTerms: $terms(['due' => '2026-08-25', 'method' => 'bacs', 'title' => 'Przelew', 'account' => 'PL61 1090 1014 0000 0712 1981 2874'])))
    ->toXml(invoice(InvoiceType::FakturaVAT, 'FV/2026/08/1C', $lines), $seller, $company);
check('an unpaid transfer validates', $validator->isValid($dueXml), implode("\n", $validator->errors($dueXml)));
check('and gives the due date, form 6 and the account without spaces', str_contains($dueXml, '<Termin>2026-08-25</Termin>') && str_contains($dueXml, '<FormaPlatnosci>6</FormaPlatnosci>') && str_contains($dueXml, '<NrRB>PL61109010140000071219812874</NrRB>'));
$otherXml = (new FaMapper('Polski Pro 1.2.3', paymentTerms: $terms(['paid' => '2026-08-10', 'method' => 'payu', 'title' => 'PayU (BLIK)'])))
    ->toXml(invoice(InvoiceType::FakturaVAT, 'FV/2026/08/1D', $lines), $seller, $company);
check('another gateway validates as PlatnoscInna with its title', $validator->isValid($otherXml) && str_contains($otherXml, '<OpisPlatnosci>PayU (BLIK)</OpisPlatnosci>'), implode("\n", $validator->errors($otherXml)));
check('without an order there is no Platnosc or WarunkiTransakcji', ! str_contains($plain, '<Platnosc>') && ! str_contains($plain, 'WarunkiTransakcji'));

// 2. The same sale to a consumer, who has no tax id at all.
$toConsumer = $mapper->toXml(
    invoice(InvoiceType::FakturaVAT, 'FV/2026/08/2', [item(1, 'Kubek', 1.0, 40.00, 23.0)]),
    $seller,
    $consumer,
);
check('an invoice to a buyer with no NIP validates', $validator->isValid($toConsumer), implode("\n", $validator->errors($toConsumer)));
check('and declares BrakID rather than omitting the identifier', str_contains($toConsumer, '<BrakID>1</BrakID>'));

// 3. A correction whose original never reached KSeF.
$correctedWithout = invoice(InvoiceType::FakturaVAT, 'FV/2026/07/9', [item(1, 'Kubek', 1.0, 40.00, 23.0)]);
$correction = $mapper->toXml(
    invoice(InvoiceType::Korygujaca, 'FK/2026/08/1', [item(1, 'Kubek', -1.0, -40.00, 23.0)], null, 'Zwrot towaru'),
    $seller,
    $company,
    $correctedWithout,
);
check('a corrective invoice validates', $validator->isValid($correction), implode("\n", $validator->errors($correction)));
check('and says NrKSeFN when the original has no KSeF number', str_contains($correction, '<NrKSeFN>1</NrKSeFN>'));

// 4. A correction whose original does have one. A session reference is not a
//    KSeF number and must not be presented as one.
$correctedWith = invoice(
    InvoiceType::FakturaVAT,
    'FV/2026/07/10',
    [item(1, 'Kubek', 1.0, 40.00, 23.0)],
    '5252445716-20260731-0A1B2C-3D4E5F-A1',
);
$correctionWithKsef = $mapper->toXml(
    invoice(InvoiceType::Korygujaca, 'FK/2026/08/2', [item(1, 'Kubek', -1.0, -40.00, 23.0)], null, 'Zwrot towaru'),
    $seller,
    $company,
    $correctedWith,
);
check('a correction referencing a real KSeF number validates', $validator->isValid($correctionWithKsef), implode("\n", $validator->errors($correctionWithKsef)));
check('and carries it in NrKSeFFaKorygowanej', str_contains($correctionWithKsef, '<NrKSeFFaKorygowanej>5252445716-20260731-0A1B2C-3D4E5F-A1</NrKSeFFaKorygowanej>'));

$sessionReference = invoice(InvoiceType::FakturaVAT, 'FV/2026/07/11', [item(1, 'Kubek', 1.0, 40.00, 23.0)], '20260731-SE-9F2A11B000-9F2A11B000-8C');
$correctionSessionRef = $mapper->toXml(
    invoice(InvoiceType::Korygujaca, 'FK/2026/08/3', [item(1, 'Kubek', -1.0, -40.00, 23.0)], null, 'Zwrot towaru'),
    $seller,
    $company,
    $sessionReference,
);
check('a session reference is not passed off as a KSeF number', str_contains($correctionSessionRef, '<NrKSeFN>1</NrKSeFN>'));
check('and that document still validates', $validator->isValid($correctionSessionRef), implode("\n", $validator->errors($correctionSessionRef)));

// 5. The Adnotacje block, which is where the old mapper failed outright.
foreach (['P_16', 'P_17', 'P_18', 'P_18A', 'P_19N', 'P_22N', 'P_23', 'P_PMarzyN'] as $element) {
    check("Adnotacje answers {$element}", str_contains($plain, "<{$element}>"));
}

// 6. Rules the schema enforces that the old mapper broke.
check('the VAT rate is a bare number, not "23%"', str_contains($plain, '<P_12>23</P_12>') && ! str_contains($plain, '%'));
check('lines are FaWiersz children of Fa, with no invented FaWiersze wrapper', ! str_contains($plain, '<FaWiersze>'));
check('rate totals are summed into P_13_1, P_13_2 and P_13_3', str_contains($plain, '<P_13_1>1200.00</P_13_1>')
    && str_contains($plain, '<P_13_2>400.00</P_13_2>')
    && str_contains($plain, '<P_13_3>90.00</P_13_3>'));
check('P_9A divides the line total back to a unit price', str_contains($plain, '<P_9A>200.00</P_9A>'));

// 7. Guards. A document that cannot be built must not be built quietly.
$rejected = static function (callable $build): bool {
    try {
        $build();
    } catch (InvalidArgumentException) {
        return true;
    }

    return false;
};

check('a proforma is refused', $rejected(static fn () => $mapper->toXml(
    invoice(InvoiceType::Proforma, 'PF/1', [item(1, 'Kubek', 1.0, 40.00, 23.0)]),
    $seller,
    $company,
)));
check('a seller without a NIP is refused', $rejected(static fn () => $mapper->toXml(
    invoice(InvoiceType::FakturaVAT, 'FV/1', [item(1, 'Kubek', 1.0, 40.00, 23.0)]),
    new FaParty('', 'Sklep', 'PL', 'ul. Prosta 1'),
    $company,
)));
check('a correction without its original is refused', $rejected(static fn () => $mapper->toXml(
    invoice(InvoiceType::Korygujaca, 'FK/1', [item(1, 'Kubek', -1.0, -40.00, 23.0)]),
    $seller,
    $company,
)));
check('a VAT rate FA(3) has no bucket for is refused', $rejected(static fn () => $mapper->toXml(
    invoice(InvoiceType::FakturaVAT, 'FV/1', [item(1, 'Kubek', 1.0, 40.00, 19.0)]),
    $seller,
    $company,
)));
check('a blank shop address is refused at the party, not at the Ministry', $rejected(
    static fn () => new FaParty('5260250274', 'Sklep', 'PL', '')
));

// 8. The validator has to be able to fail, or none of the above means anything.
check('the validator rejects a document with Adnotacje removed', ! $validator->isValid(
    (string) preg_replace('#<Adnotacje>.*?</Adnotacje>#s', '', $plain)
));

// 9. What FA(3) changed against FA(2).
check('the document is in the FA(3) namespace', str_contains($plain, 'xmlns="http://crd.gov.pl/wzor/2025/06/25/13775/"'));
check('KodFormularza declares FA (3) and WariantFormularza is 3', str_contains($plain, 'kodSystemowy="FA (3)" wersjaSchemy="1-0E">FA</KodFormularza>')
    && str_contains($plain, '<WariantFormularza>3</WariantFormularza>'));
check('Podmiot2 answers the mandatory JST and GV flags with 2', str_contains($plain, "<JST>2</JST>")
    && str_contains($plain, "<GV>2</GV>"));
check('the validator rejects a buyer without JST, so it is the FA(3) schema doing the checking', ! $validator->isValid(
    str_replace('<JST>2</JST>', '', $plain)
));

$zeroRated = $mapper->toXml(
    invoice(InvoiceType::FakturaVAT, 'FV/2026/08/4', [
        item(1, 'Kubek', 1.0, 40.00, 23.0),
        item(2, 'Podrecznik szkolny', 2.0, 60.00, 0.0),
    ]),
    $seller,
    $consumer,
);
check('a domestic 0% line validates', $validator->isValid($zeroRated), implode("\n", $validator->errors($zeroRated)));
check('and is written "0 KR", since a bare 0 is no longer in TStawkaPodatku', str_contains($zeroRated, '<P_12>0 KR</P_12>')
    && ! str_contains($zeroRated, '<P_12>0</P_12>'));
check('and its net total lands in P_13_6_1', str_contains($zeroRated, '<P_13_6_1>60.00</P_13_6_1>'));

$longName = str_repeat('Dlugi opis pozycji ', 20);
$longLine = $mapper->toXml(
    invoice(InvoiceType::FakturaVAT, 'FV/2026/08/5', [item(1, $longName, 1.0, 40.00, 23.0)]),
    $seller,
    $company,
);
check('a line name past 256 characters validates, P_7 is TZnakowy512 now', $validator->isValid($longLine), implode("\n", $validator->errors($longLine)));
check('and keeps up to 512 characters instead of the FA(2) 256', str_contains($longLine, '<P_7>' . mb_substr(trim($longName), 0, 300)));

// 10. GTU, recovered from the KSeF 1.0 mapper: written after P_12, and only
//     when it is one of the thirteen codes TGTU enumerates.
$gtuMapper = new FaMapper('Polski Pro 1.2.3', static fn (?int $productId): string => match ($productId) {
    7 => 'GTU_01',
    8 => 'GTU_99',
    9 => ' ',
    default => '',
});
$withGtu = $gtuMapper->toXml(
    invoice(InvoiceType::FakturaVAT, 'FV/2026/08/6', [
        item(1, 'Wino', 1.0, 100.00, 23.0, 7),
        item(2, 'Towar z blednym kodem', 1.0, 10.00, 23.0, 8),
        item(3, 'Towar bez kodu', 1.0, 10.00, 23.0, 9),
    ]),
    $seller,
    $company,
);
check('an invoice with a GTU line validates', $validator->isValid($withGtu), implode("\n", $validator->errors($withGtu)));
check('and declares GTU_01 right after P_12 on that line', str_contains($withGtu, "<P_12>23</P_12>\n      <GTU>GTU_01</GTU>"));
check('an unknown or blank GTU code is left out, not sent', substr_count($withGtu, '<GTU>') === 1 && ! str_contains($withGtu, 'GTU_99'));

// 11. Foreign currency: VAT in PLN per band and the rate on every line.
$eur = static fn (?float $rate): FaMapper => new FaMapper('Polski Pro 1.2.3', null, static fn (string $currency, DateTimeImmutable $taxPoint): ?float => $rate);
$eurItems = [item(1, 'Hosting roczny', 1.0, 100.00, 23.0), item(2, 'Uslugi hotelowe', 1.0, 50.00, 8.0), item(3, 'Podrecznik', 1.0, 10.00, 0.0)];
$inEur = $eur(4.2575)->toXml(invoice(InvoiceType::FakturaVAT, 'FV/2026/08/7', $eurItems, null, null, 'EUR'), $seller, $company);
check('a EUR invoice with an NBP rate validates', $validator->isValid($inEur), implode("\n", $validator->errors($inEur)));
check('and states the VAT in PLN per band (23.00 EUR and 4.00 EUR at 4.2575)', str_contains($inEur, "<P_14_1>23.00</P_14_1>\n    <P_14_1W>97.92</P_14_1W>")
    && str_contains($inEur, "<P_14_2>4.00</P_14_2>\n    <P_14_2W>17.03</P_14_2W>"));
check('and carries KursWaluty on every line', substr_count($inEur, '<KursWaluty>4.257500</KursWaluty>') === 3);
check('a PLN invoice carries neither', ! str_contains($plain, 'KursWaluty') && ! str_contains($plain, 'P_14_1W'));

$noRate = $eur(null)->toXml(invoice(InvoiceType::FakturaVAT, 'FV/2026/08/8', $eurItems, null, null, 'EUR'), $seller, $company);
$noRateErrors = $validator->errors($noRate);
check('a EUR invoice without an exchange rate is refused', $noRateErrors !== [] && str_contains(implode(' ', $noRateErrors), 'no PLN exchange rate'), implode("\n", $noRateErrors));
check('and only by the rate check, the schema itself lets it through', count($noRateErrors) === 1);

$rateLookupDate = null;
$correctionRate = new FaMapper('Polski Pro 1.2.3', null, static function (string $currency, DateTimeImmutable $taxPoint) use (&$rateLookupDate): ?float {
    $rateLookupDate = $taxPoint->format('Y-m-d');

    return 4.30;
});
$eurOriginal = new Invoice(1, 42, null, null, InvoiceType::FakturaVAT, 'FV/2026/07/20', InvoiceStatus::from('issued'), '5260250274', null, null, 100.0, 23.0, 123.0, 'EUR', null, null, null, new DateTimeImmutable('2026-07-02 10:00:00'), new DateTimeImmutable('2026-07-02 10:00:00'), [item(1, 'Kubek', 1.0, 100.00, 23.0)]);
$eurCorrection = $correctionRate->toXml(
    invoice(InvoiceType::Korygujaca, 'FK/2026/08/9', [item(1, 'Kubek', -1.0, -100.00, 23.0)], null, 'Zwrot towaru', 'EUR'),
    $seller,
    $company,
    $eurOriginal,
);
check('a EUR correction validates with its rate', $validator->isValid($eurCorrection), implode("\n", $validator->errors($eurCorrection)));
check('and converts at the corrected invoice\'s tax point (art. 31a ust. 2a)', $rateLookupDate === '2026-07-02');

// 12. NIP checksum. A mistyped buyer NIP must not turn a business sale into a
//     consumer one, so it is refused rather than sent as BrakID.
$badNip = $mapper->toXml(
    invoice(InvoiceType::FakturaVAT, 'FV/2026/08/10', [item(1, 'Kubek', 1.0, 40.00, 23.0)]),
    $seller,
    new FaParty('5252445716', 'Nabywca sp. z o.o.', 'PL', 'ul. Dluga 7', '30-001 Krakow'),
);
check('a buyer NIP with a bad checksum is not declared as BrakID', ! str_contains($badNip, '<BrakID>'));
check('and the validator refuses it, naming the buyer', str_contains(implode(' ', $validator->errors($badNip)), 'Buyer: NIP 5252445716 fails the NIP checksum'), implode("\n", $validator->errors($badNip)));
check('a seller NIP with a bad checksum is refused by the mapper', $rejected(static fn () => $mapper->toXml(
    invoice(InvoiceType::FakturaVAT, 'FV/1', [item(1, 'Kubek', 1.0, 40.00, 23.0)]),
    new FaParty('5252445716', 'Sklep', 'PL', 'ul. Prosta 1'),
    $company,
)));
check('FaParty::isValidNip accepts valid NIPs and rejects near misses', FaParty::isValidNip('5260250274') && FaParty::isValidNip('9999999999')
    && ! FaParty::isValidNip('5260250275') && ! FaParty::isValidNip('0260250274') && ! FaParty::isValidNip('526025027'));

// 13. The session has to announce the schema the document is actually in.
$session = SessionRequest::openOnline('WRAPPED==', random_bytes(16), str_repeat('k', 44));
check('the default session formCode is the FA (3) the mapper writes', $session['formCode']['systemCode'] === 'FA (3)'
    && str_contains($plain, 'kodSystemowy="' . $session['formCode']['systemCode'] . '"'));

// 12. A seller exempt under art. 113: "zw" lines, P_13_7, P_19 with the basis.
$exempt = new FaMapper('Polski Pro 1.2.3', null, null, static fn (): string => 'art. 113 ust. 1 ustawy o VAT');
$zwItems = [item(1, 'Kubek', 2.0, 40.00, 0.0), item(2, 'Plakat', 1.0, 25.00, 0.0)];
$zw = $exempt->toXml(invoice(InvoiceType::FakturaVAT, 'FV/2026/08/9', $zwItems), $seller, $consumer);
check('an art. 113 invoice validates', $validator->isValid($zw), implode("\n", $validator->errors($zw)));
check('and marks every line "zw", not the domestic 0% rate', substr_count($zw, '<P_12>zw</P_12>') === 2 && ! str_contains($zw, '0 KR'));
check('and reports the net as exempt sales in P_13_7', str_contains($zw, '<P_13_7>65.00</P_13_7>') && ! str_contains($zw, 'P_13_6_1'));
check('and states the exemption with its legal basis', str_contains($zw, "<P_19>1</P_19>\n        <P_19A>art. 113 ust. 1 ustawy o VAT</P_19A>") && ! str_contains($zw, 'P_19N'));
check('a seller that is not exempt still declares nothing exempt', str_contains($plain, '<P_19N>1</P_19N>') && ! str_contains($plain, '<P_12>zw</P_12>'));
try {
    $exempt->toXml(invoice(InvoiceType::FakturaVAT, 'FV/2026/08/10', [item(1, 'Kubek', 1.0, 40.00, 23.0)]), $seller, $consumer);
    check('an exempt seller charging VAT is refused', false);
} catch (InvalidArgumentException $e) {
    check('an exempt seller charging VAT is refused', str_contains($e->getMessage(), 'Kubek'), $e->getMessage());
}

// 13. Zero rate abroad: WDT, export, and services outside Poland.
// Product 8 is a service (virtual); 7 is goods; a line without a product
// (shipping) follows the goods on the invoice.
$abroad = new FaMapper('Polski Pro 1.2.3', null, null, static fn (): string => '', static fn (?int $productId): bool => $productId === 8);
$euBusiness = new FaParty('DE123456789', 'Kunde GmbH', 'DE', 'Hauptstr. 1', '10115 Berlin');
$wdt = $abroad->toXml(invoice(InvoiceType::FakturaVAT, 'FV/2026/08/11', [item(1, 'Kubek', 2.0, 40.00, 0.0, 7), item(2, 'Wysylka', 1.0, 15.00, 0.0)]), $seller, $euBusiness);
check('an intra-EU supply of goods validates', $validator->isValid($wdt), implode("\n", $validator->errors($wdt)));
check('and is "0 WDT" on every line, shipping included', substr_count($wdt, '<P_12>0 WDT</P_12>') === 2 && str_contains($wdt, '<P_13_6_2>55.00</P_13_6_2>'));
check('and names the buyer by its EU VAT number', str_contains($wdt, "<KodUE>DE</KodUE>\n      <NrVatUE>123456789</NrVatUE>") && ! str_contains($wdt, 'BrakID'));
check('and is not a reverse charge', str_contains($wdt, '<P_18>2</P_18>'));

$npII = $abroad->toXml(invoice(InvoiceType::FakturaVAT, 'FV/2026/08/12', [item(1, 'Licencja', 1.0, 100.00, 0.0, 8)]), $seller, $euBusiness);
check('a service to an EU business validates', $validator->isValid($npII), implode("\n", $validator->errors($npII)));
check('and is "np II" in P_13_9 with the reverse charge declared', str_contains($npII, '<P_12>np II</P_12>') && str_contains($npII, '<P_13_9>100.00</P_13_9>') && str_contains($npII, '<P_18>1</P_18>'));

$usBuyer = new FaParty('', 'Customer Inc.', 'US', '1 Main St', '10001 New York');
$ex = $abroad->toXml(invoice(InvoiceType::FakturaVAT, 'FV/2026/08/13', [item(1, 'Kubek', 1.0, 40.00, 0.0, 7), item(2, 'Licencja', 1.0, 100.00, 0.0, 8)]), $seller, $usBuyer);
check('an export with a service validates', $validator->isValid($ex), implode("\n", $validator->errors($ex)));
check('goods leave as "0 EX", the service as "np I"', str_contains($ex, '<P_12>0 EX</P_12>') && str_contains($ex, '<P_13_6_3>40.00</P_13_6_3>')
    && str_contains($ex, '<P_12>np I</P_12>') && str_contains($ex, '<P_13_8>100.00</P_13_8>'));

$greek = $abroad->toXml(invoice(InvoiceType::FakturaVAT, 'FV/2026/08/14', [item(1, 'Kubek', 1.0, 40.00, 0.0, 7)]), $seller, new FaParty('123456789', 'Pelatis AE', 'GR', 'Odos 1', '10431 Athina'));
check('a VAT number typed without its prefix takes the country, Greece as EL', str_contains($greek, "<KodUE>EL</KodUE>\n      <NrVatUE>123456789</NrVatUE>"), $greek);

try {
    $abroad->toXml(invoice(InvoiceType::FakturaVAT, 'FV/2026/08/15', [item(1, 'Kubek', 1.0, 40.00, 0.0, 7)]), $seller, new FaParty('', 'Jean Dupont', 'FR', 'Rue 1', '75001 Paris'));
    check('a 0% sale to an EU consumer is refused', false);
} catch (InvalidArgumentException $e) {
    check('a 0% sale to an EU consumer is refused', str_contains($e->getMessage(), 'Kubek'), $e->getMessage());
}

$ch = $abroad->toXml(invoice(InvoiceType::FakturaVAT, 'FV/2026/08/17', [item(1, 'Kubek', 1.0, 40.00, 0.0, 7)]), $seller, new FaParty('CHE-123.456.788', 'Kunde AG', 'CH', 'Bahnhofstr. 1', '8001 Zurich'));
check('a non-EU tax id validates', $validator->isValid($ch), implode("\n", $validator->errors($ch)));
check('and goes out as KodKraju plus NrID, not BrakID', str_contains($ch, "<KodKraju>CH</KodKraju>\n      <NrID>CHE-123.456.788</NrID>") && ! str_contains($ch, 'BrakID'), $ch);
check('a US consumer without an id stays BrakID', str_contains($ex, '<BrakID>1</BrakID>'));

// 14. The PDF reads the same classification, so paper and KSeF agree.
$mixed = invoice(InvoiceType::FakturaVAT, 'FV/2026/08/16', [item(1, 'Kubek', 1.0, 40.00, 0.0, 7), item(2, 'Licencja', 1.0, 100.00, 0.0, 8), item(3, 'Kubek', 1.0, 40.00, 23.0, 7)]);
check('lineRates names each line as KSeF files it', $abroad->lineRates($mixed, $euBusiness) === ['0 WDT', 'np II', '23'], var_export($abroad->lineRates($mixed, $euBusiness), true));
check('and null for a line KSeF would refuse', $abroad->lineRates($mixed, new FaParty('', 'Jean Dupont', 'FR', 'Rue 1', '75001 Paris')) === [null, null, '23']);

echo "\n{$checks} checks, {$failures} failed\n";

exit($failures === 0 ? 0 : 1);
