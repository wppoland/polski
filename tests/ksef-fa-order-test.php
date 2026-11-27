<?php

declare(strict_types=1);

/**
 * Element order inside an FA(3) invoice.
 *
 *   php tests/ksef-fa-order-test.php
 *
 * The FA schema is one long xsd:sequence, so order is correctness, not style,
 * and getting it wrong produces "This element is not expected" rather than
 * anything that hints at the real cause.
 *
 * It reads the order straight out of schemat_FA(3)_v1-0E.xsd and checks every
 * block V2\FaMapper emits against it, so a reordered or renamed element in a
 * future schema shows up here by name, not as a schema dump. The legacy KSeF
 * 1.0 mapper this file also used to guard by source is deleted.
 */

define('ABSPATH', __DIR__);

if (! function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return $text;
    }
}

$root = dirname(__DIR__) . '/';

foreach (['InvoiceType', 'InvoiceStatus', 'InvoiceItem', 'Invoice'] as $class) {
    require $root . "src/KSeF/Model/{$class}.php";
}
require $root . 'src/KSeF/V2/FaParty.php';
require $root . 'src/KSeF/V2/VerificationLink.php';
require $root . 'src/KSeF/V2/FaMapper.php';

use Polski\KSeF\V2\FaMapper;
use Polski\KSeF\V2\FaParty;
use Polski\KSeF\Model\Invoice;
use Polski\KSeF\Model\InvoiceItem;
use Polski\KSeF\Model\InvoiceStatus;
use Polski\KSeF\Model\InvoiceType;

$failures = 0;
$checks = 0;

function check(string $label, bool $ok): void
{
    global $failures, $checks;
    $checks++;
    echo ($ok ? 'ok    ' : 'FAIL  ') . $label . "\n";
    if (! $ok) {
        $failures++;
    }
}

/**
 * Names an xsd:element may contain directly, in declaration order.
 *
 * Walks sequences and choices but not into child elements, so a nested block
 * contributes its own name and nothing below it.
 *
 * @return list<string>
 */
function declared(DOMElement $node): array
{
    $names = [];

    foreach ($node->childNodes as $child) {
        if (! $child instanceof DOMElement || $child->namespaceURI !== 'http://www.w3.org/2001/XMLSchema') {
            continue;
        }

        if ($child->localName === 'element') {
            $names[] = $child->getAttribute('name');
        } elseif (in_array($child->localName, ['complexType', 'sequence', 'choice'], true)) {
            array_push($names, ...declared($child));
        }
    }

    return $names;
}

/** Whether the children of $emitted appear in the order $order declares, all of them declared. */
function inOrder(DOMElement $emitted, array $order): bool
{
    $last = -1;

    foreach ($emitted->childNodes as $child) {
        if (! $child instanceof DOMElement) {
            continue;
        }

        $at = array_search($child->localName, $order, true);

        // Repeated elements (FaWiersz) share a position, hence >= and not >.
        if ($at === false || $at < $last) {
            echo "      {$emitted->localName}: {$child->localName} is " . ($at === false ? 'not declared' : 'out of order') . "\n";

            return false;
        }

        $last = $at;
    }

    return true;
}

$xsd = new DOMDocument();
$xsd->load($root . 'src/KSeF/V2/schema/FA_v3_1-0E.xsd');
$xp = new DOMXPath($xsd);
$xp->registerNamespace('xsd', 'http://www.w3.org/2001/XMLSchema');

$schemaNode = static function (string $path) use ($xp): DOMElement {
    $node = $xp->query($path)->item(0);
    if (! $node instanceof DOMElement) {
        echo "FAIL  schema path not found: {$path}\n";
        exit(1);
    }

    return $node;
};

$faktura = '/xsd:schema/xsd:element[@name="Faktura"]/xsd:complexType/xsd:sequence';
$order = [
    // Naglowek is the only block declared by a named type rather than inline.
    'Naglowek' => declared($schemaNode('/xsd:schema/xsd:complexType[@name="TNaglowek"]')),
    'Podmiot1' => declared($schemaNode("{$faktura}/xsd:element[@name=\"Podmiot1\"]")),
    'Podmiot2' => declared($schemaNode("{$faktura}/xsd:element[@name=\"Podmiot2\"]")),
    'Fa' => declared($schemaNode("{$faktura}/xsd:element[@name=\"Fa\"]")),
    'Adnotacje' => declared($schemaNode("{$faktura}/xsd:element[@name=\"Fa\"]//xsd:element[@name=\"Adnotacje\"]")),
    'DaneFaKorygowanej' => declared($schemaNode("{$faktura}/xsd:element[@name=\"Fa\"]//xsd:element[@name=\"DaneFaKorygowanej\"]")),
    'FaWiersz' => declared($schemaNode("{$faktura}/xsd:element[@name=\"Fa\"]//xsd:element[@name=\"FaWiersz\"]")),
];

check('the FA(3) schema declares GTU after P_12 and KursWaluty after GTU in FaWiersz', array_search('P_12', $order['FaWiersz'], true) < array_search('GTU', $order['FaWiersz'], true)
    && array_search('GTU', $order['FaWiersz'], true) < array_search('KursWaluty', $order['FaWiersz'], true));
check('and P_14_1W right after P_14_1 in Fa', array_search('P_14_1W', $order['Fa'], true) === array_search('P_14_1', $order['Fa'], true) + 1);

check('the FA(3) schema declares Adnotacje between P_15 and RodzajFaktury', array_search('P_15', $order['Fa'], true) < array_search('Adnotacje', $order['Fa'], true)
    && array_search('Adnotacje', $order['Fa'], true) < array_search('RodzajFaktury', $order['Fa'], true));
check('and JST and GV as the last two children of Podmiot2', array_slice($order['Podmiot2'], -2) === ['JST', 'GV']);

$item = static fn (float $qty, float $net, float $rate): InvoiceItem => new InvoiceItem(
    id: 1,
    invoiceId: 1,
    productId: null,
    name: 'Kubek',
    quantity: $qty,
    unit: 'szt',
    netPrice: $net,
    vatRate: $rate,
    vatAmount: round($net * $rate / 100, 2),
    grossPrice: round($net * (1 + $rate / 100), 2),
    sortOrder: 1,
);

$invoice = static fn (InvoiceType $type, string $number, array $items, ?string $ksef = null, string $currency = 'PLN'): Invoice => new Invoice(
    id: 1,
    orderId: 42,
    originalInvoiceId: null,
    sourceRefundId: null,
    type: $type,
    number: $number,
    status: InvoiceStatus::from('issued'),
    nipSeller: '5260250274',
    nipBuyer: null,
    correctionReason: 'Zwrot towaru',
    netTotal: 0.0,
    vatTotal: 0.0,
    grossTotal: 0.0,
    currency: $currency,
    pdfPath: null,
    ksefReference: $ksef,
    ksefStatus: null,
    issuedAt: new DateTimeImmutable('2026-08-11 10:15:00'),
    createdAt: new DateTimeImmutable('2026-08-11 10:15:00'),
    items: $items,
);

$mapper = new FaMapper();
$seller = new FaParty('5260250274', 'Sklep Testowy sp. z o.o.', 'PL', 'ul. Prosta 1', '00-001 Warszawa');
$buyer = new FaParty('', 'Jan Kowalski', 'PL', 'ul. Krotka 3', '80-001 Gdansk');

$documents = [
    'plain' => $mapper->toXml(
        $invoice(InvoiceType::FakturaVAT, 'FV/1', [$item(1.0, 100.0, 23.0), $item(2.0, 50.0, 8.0), $item(1.0, 10.0, 0.0)]),
        $seller,
        $buyer,
    ),
    'corrective' => $mapper->toXml(
        $invoice(InvoiceType::Korygujaca, 'FK/1', [$item(-1.0, -100.0, 23.0)]),
        $seller,
        $buyer,
        $invoice(InvoiceType::FakturaVAT, 'FV/1', [$item(1.0, 100.0, 23.0)], '5252445716-20260731-0A1B2C-3D4E5F-A1'),
    ),
    // GTU and a foreign currency fill the optional tail of FaWiersz and the
    // P_14_xW fields of Fa, the positions most easily got wrong.
    'EUR with GTU' => (new FaMapper('Polski Pro', static fn (?int $id): string => 'GTU_12', static fn (string $c, DateTimeImmutable $d): ?float => 4.2575))->toXml(
        $invoice(InvoiceType::FakturaVAT, 'FV/2', [$item(1.0, 100.0, 23.0), $item(2.0, 50.0, 8.0)], null, 'EUR'),
        $seller,
        $buyer,
    ),
];

check('the EUR document really carries GTU, KursWaluty and P_14_1W', str_contains($documents['EUR with GTU'], '<GTU>GTU_12</GTU>')
    && str_contains($documents['EUR with GTU'], '<KursWaluty>') && str_contains($documents['EUR with GTU'], '<P_14_1W>'));

foreach ($documents as $label => $xml) {
    $doc = new DOMDocument();
    $doc->loadXML($xml);

    foreach ($order as $block => $declaredOrder) {
        $emitted = $doc->getElementsByTagName($block)->item(0);

        if ($emitted === null) {
            // Only the corrective document has a DaneFaKorygowanej to check.
            check("the {$label} document has no {$block}, which is expected", $block === 'DaneFaKorygowanej' && $label !== 'corrective');
            continue;
        }

        check("the {$label} document emits {$block} in FA(3) schema order", inOrder($emitted, $declaredOrder));
    }
}

echo "\n{$checks} checks, {$failures} failed\n";

exit($failures === 0 ? 0 : 1);
