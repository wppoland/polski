<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}

// existing-checked: ../polski-pro-ksef/tools/ksef-correction-e2e.php - that one sends a refund KOR from a stale branch; this sends the free kinds through InvoiceDataBuilder.

/**
 * Corrections without a refund (issue #63) on the KSeF test environment:
 * invoice A at 0% and its rate correction to 23% at the same gross, then
 * invoice B and its buyer correction (Podmiot2K). Reports all KSeF numbers.
 *
 *   php tools/ksef-make-test-cert.php     # once
 *   php tools/ksef-free-correction-e2e.php
 *
 * Test environment only.
 */

define('ABSPATH', __DIR__);

const BASE = 'https://api-test.ksef.mf.gov.pl/v2';
const CONTEXT_NIP = '9999999999';

$root = dirname(__DIR__) . '/';

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

require $root . 'vendor/phpseclib/phpseclib/phpseclib/bootstrap.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (! str_starts_with($class, 'phpseclib3\\')) {
        return;
    }
    $path = $root . 'vendor/phpseclib/phpseclib/phpseclib/'
        . str_replace('\\', '/', substr($class, strlen('phpseclib3\\'))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
foreach (['XMLSecurityDSig', 'XMLSecurityKey', 'XMLSecEnc'] as $class) {
    require $root . "vendor/robrichards/xmlseclibs/src/{$class}.php";
}
require $root . 'vendor/robrichards/xmlseclibs/src/Utils/XPath.php';
foreach (['InvoiceType', 'InvoiceStatus', 'InvoiceItem', 'Invoice'] as $class) {
    require $root . "src/KSeF/Model/{$class}.php";
}
require $root . 'src/Invoice/InvoiceDataBuilder.php';
foreach (['KSeFCrypto', 'AuthTokenRequest', 'XadesSigner', 'SessionRequest', 'FaParty', 'VerificationLink', 'FaMapper', 'FaValidator'] as $class) {
    require $root . "src/KSeF/V2/{$class}.php";
}

use Polski\Pro\Invoice\InvoiceDataBuilder;
use Polski\KSeF\V2\AuthTokenRequest;
use Polski\KSeF\V2\FaMapper;
use Polski\KSeF\V2\FaParty;
use Polski\KSeF\V2\FaValidator;
use Polski\KSeF\V2\KSeFCrypto;
use Polski\KSeF\V2\SessionRequest;
use Polski\KSeF\V2\XadesSigner;
use Polski\KSeF\Model\Invoice;
use Polski\KSeF\Model\InvoiceItem;
use Polski\KSeF\Model\InvoiceStatus;
use Polski\KSeF\Model\InvoiceType;

/**
 * @param list<string> $headers
 * @return array{0: int, 1: string}
 */
function call(string $method, string $path, ?string $body = null, string $contentType = 'application/json', array $headers = []): array
{
    $ch = curl_init(BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_HTTPHEADER => array_merge(["Content-Type: {$contentType}"], $headers),
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $out = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$code, $out];
}

function fail(string $message, string $body = ''): never
{
    fwrite(STDERR, $message . "\n");
    if ($body !== '') {
        fwrite(STDERR, substr($body, 0, 800) . "\n");
    }
    exit(1);
}


if (! function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = ''): string
    {
        return $text;
    }
}

$now = new DateTimeImmutable('now');
$stamp = $now->format('His');
$seller = new FaParty(CONTEXT_NIP, 'Sklep Testowy sp. z o.o.', 'PL', 'ul. Prosta 1', '00-001 Warszawa');
$mapper = new FaMapper('Polski Pro e2e');
$validator = new FaValidator();

function vat(string $number, float $net, float $rate, string $nip, string $buyer, DateTimeImmutable $at, ?string $ksef = null): Invoice
{
    $vat = round($net * $rate / 100, 2);
    $item = new InvoiceItem(1, 1, null, 'Bilet konferencyjny', 1.0, 'szt', $net, $rate, $vat, round($net + $vat, 2), 1);

    return new Invoice(1, 1, null, null, InvoiceType::FakturaVAT, $number, InvoiceStatus::from('issued'), CONTEXT_NIP, $nip, null,
        $net, $vat, round($net + $vat, 2), 'PLN', null, $ksef, $ksef !== null ? 'accepted' : null, $at, $at, [$item], $buyer, 'ul. Stara 1', 'Warszawa', '00-950');
}

/** @param array<string, mixed> $changes */
function correction(Invoice $original, array $changes, string $number, DateTimeImmutable $at): Invoice
{
    $p = (new InvoiceDataBuilder())->buildFreeCorrection($original, $changes);
    $items = [];
    foreach ($p['items'] as $i => $row) {
        $items[] = InvoiceItem::fromRow((object) ($row + ['id' => $i + 1, 'invoice_id' => 2, 'sort_order' => $i]));
    }

    return new Invoice(2, 1, $original->id, null, InvoiceType::Korygujaca, $number, InvoiceStatus::from('issued'), CONTEXT_NIP,
        $p['buyer']['nip'] !== '' ? $p['buyer']['nip'] : null, $p['reason'], $p['net_total'], $p['vat_total'], $p['gross_total'], 'PLN',
        null, null, null, $at, $at, $items, $p['buyer']['name'], $p['buyer']['address'], $p['buyer']['city'], $p['buyer']['postcode'],
        soldAt: new DateTimeImmutable((string) $p['sold_at']), correctionKind: $p['kind'], correctionType: $p['correction_type'], corrected: $original);
}

$party = static fn (Invoice $i): FaParty => new FaParty((string) $i->nipBuyer, (string) $i->buyerName, 'PL', (string) $i->buyerAddress, trim($i->buyerPostcode . ' ' . $i->buyerCity));
$document = static function (string $label, Invoice $invoice, ?Invoice $corrected = null, bool $buyerBefore = false) use ($mapper, $validator, $seller, $party): string {
    $xml = $mapper->toXml($invoice, $seller, $party($invoice), $corrected, $buyerBefore && $corrected !== null ? $party($corrected) : null);
    $errors = $validator->errors($xml);
    if ($errors !== []) {
        fail("{$label} INVALID, not sending:\n   " . implode("\n   ", $errors));
    }
    file_put_contents(__DIR__ . '/ksef-free-correction-' . trim($label) . '.xml', $xml);
    echo "0. {$label} " . strlen($xml) . " bytes, schema valid\n";

    return $xml;
};

$invoiceA = vat("FV/E2E-A-{$stamp}", 1230.00, 0.0, '5261040828', 'Firma Testowa sp. z o.o.', $now);
$invoiceB = vat("FV/E2E-B-{$stamp}", 1000.00, 23.0, '5261040828', 'Firma Stara sp. z o.o.', $now);
$xmlA = $document('invoice-A', $invoiceA);
$xmlB = $document('invoice-B', $invoiceB);

// 1. Authenticate with the self-signed seal certificate.
[, $body] = call('POST', '/auth/challenge', '{}');
$challenge = json_decode($body, true);

$signed = XadesSigner::sign(
    AuthTokenRequest::build($challenge['challenge'], CONTEXT_NIP),
    (string) file_get_contents($root . 'tools/ksef-test-key.pem'),
    (string) file_get_contents($root . 'tools/ksef-test-cert.pem'),
);

[, $body] = call('POST', '/auth/xades-signature', $signed, 'application/xml');
$init = json_decode($body, true);
$authHeader = ['Authorization: Bearer ' . ($init['authenticationToken']['token'] ?? '')];

$status = null;
for ($i = 0; $i < 6; $i++) {
    [, $body] = call('GET', '/auth/' . ($init['referenceNumber'] ?? ''), null, 'application/json', $authHeader);
    $status = json_decode($body, true);
    if (($status['status']['code'] ?? 0) !== 100) {
        break;
    }
    usleep(1_200_000);
}
echo '1. authentication                  code=' . ($status['status']['code'] ?? '?')
    . '  ' . ($status['status']['description'] ?? '') . "\n";

[, $body] = call('POST', '/auth/token/redeem', null, 'application/json', $authHeader);
$access = json_decode($body, true)['accessToken']['token'] ?? null;
if ($access === null) {
    fail('No access token.', $body);
}
$bearer = ["Authorization: Bearer {$access}"];

// 2. Open a session under the Ministry's symmetric-key certificate.
[, $body] = call('GET', '/security/public-key-certificates');
$certificate = null;
foreach (json_decode($body, true) ?? [] as $candidate) {
    if (in_array('SymmetricKeyEncryption', $candidate['usage'] ?? [], true)) {
        $certificate = $candidate;
    }
}
if ($certificate === null) {
    fail('No SymmetricKeyEncryption certificate offered.');
}

$material = KSeFCrypto::newSymmetricKey();
$open = SessionRequest::openOnline(
    KSeFCrypto::encryptSymmetricKey($material['key'], KSeFCrypto::toPem($certificate['certificate'])),
    $material['iv'],
    (string) $certificate['publicKeyId'],
    'FA(3)',
);

[$code, $body] = call('POST', '/sessions/online', (string) json_encode($open), 'application/json',
    array_merge($bearer, ['X-Error-Format: problem-details']));
$session = json_decode($body, true)['referenceNumber'] ?? null;
if ($session === null) {
    fail("2. POST /sessions/online           HTTP {$code}", $body);
}
echo "2. session opened                  {$session}\n";

// 3. Send a document and poll until KSeF has numbered or refused it.
$send = static function (string $label, string $document) use ($session, $material, $bearer): array {
    $encrypted = KSeFCrypto::encryptInvoice($document, $material['key'], $material['iv']);
    $payload = SessionRequest::sendInvoice($document, $encrypted);

    [$code, $body] = call('POST', "/sessions/online/{$session}/invoices", (string) json_encode($payload), 'application/json',
        array_merge($bearer, ['X-Error-Format: problem-details']));
    $element = json_decode($body, true)['referenceNumber'] ?? null;
    if ($element === null) {
        call('POST', "/sessions/online/{$session}/close", null, 'application/json', $bearer);
        fail("3. POST .../invoices ({$label})    HTTP {$code}", $body);
    }
    echo "3. {$label} accepted  {$element}\n";

    $last = [];
    for ($i = 0; $i < 20; $i++) {
        [, $body] = call('GET', "/sessions/{$session}/invoices/{$element}", null, 'application/json', $bearer);
        $last = json_decode($body, true) ?? [];
        if (isset($last['ksefNumber']) || (int) ($last['status']['code'] ?? 100) >= 400) {
            break;
        }
        sleep(3);
    }

    if (! isset($last['ksefNumber'])) {
        call('POST', "/sessions/online/{$session}/close", null, 'application/json', $bearer);
        fail("4. {$label} was not numbered  code=" . ($last['status']['code'] ?? '?')
            . '  ' . ($last['status']['description'] ?? ''), (string) json_encode($last['status'] ?? $last));
    }
    echo "4. {$label} KSeF number  {$last['ksefNumber']}\n";

    return $last;
};


$results = [];
$results['invoice A'] = $send('invoice A ', $xmlA)['ksefNumber'];
$results['invoice B'] = $send('invoice B ', $xmlB)['ksefNumber'];

$sentA = vat($invoiceA->number, 1230.00, 0.0, '5261040828', 'Firma Testowa sp. z o.o.', $now, $results['invoice A']);
$sentB = vat($invoiceB->number, 1000.00, 23.0, '5261040828', 'Firma Stara sp. z o.o.', $now, $results['invoice B']);

$rate = correction($sentA, ['kind' => 'rate', 'reason' => 'Stawka 23% zamiast 0%', 'items' => [['name' => 'Bilet konferencyjny', 'vat_rate' => 23]]], "FK/E2E-RATE-{$stamp}", $now);
$buyer = correction($sentB, ['kind' => 'buyer', 'reason' => 'Nowa nazwa nabywcy', 'buyer' => ['name' => 'Firma Nowa sp. z o.o.', 'address' => 'ul. Nowa 2']], "FK/E2E-BUYER-{$stamp}", $now);
echo "5. rate correction  net {$rate->netTotal} vat {$rate->vatTotal} gross {$rate->grossTotal}\n";

$results['rate KOR'] = $send('rate KOR  ', $document('kor-rate', $rate, $sentA))['ksefNumber'];
$results['buyer KOR'] = $send('buyer KOR ', $document('kor-buyer', $buyer, $sentB, true))['ksefNumber'];

[$code] = call('POST', "/sessions/online/{$session}/close", null, 'application/json', $bearer);
echo "6. session closed                  HTTP {$code}\n\n";

foreach ($results as $label => $number) {
    echo str_pad($label, 12) . $number . "\n";
}
