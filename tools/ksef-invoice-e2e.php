<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}

/**
 * Sends one real FA(3) invoice to the KSeF test environment and reports its
 * KSeF number.
 *
 *   php tools/ksef-make-test-cert.php     # once
 *   php tools/ksef-invoice-e2e.php
 *
 * Everything before this proved a piece: the signature, the wrapped key, the
 * session. This is the whole chain with an actual document in it, built by
 * FaMapper and refused locally by FaValidator if it is not schema valid, so a
 * rejection here can only come from the Ministry's own business rules.
 *
 * Test environment only. The seller NIP has to be the one the session
 * authenticated as, or KSeF rejects the document as belonging to someone else.
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
foreach (['KSeFCrypto', 'AuthTokenRequest', 'XadesSigner', 'SessionRequest', 'FaParty', 'FaMapper', 'FaValidator'] as $class) {
    require $root . "src/KSeF/V2/{$class}.php";
}

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

// 0. Build the document first. There is no point authenticating to send
//    something that would be rejected on arrival.
$issuedAt = new DateTimeImmutable('now');
$items = [
    new InvoiceItem(
        id: 1,
        invoiceId: 1,
        productId: 1,
        name: 'Licencja Polski Pro, 12 miesiecy',
        quantity: 1.0,
        unit: 'szt',
        netPrice: 299.00,
        vatRate: 23.0,
        vatAmount: 68.77,
        grossPrice: 367.77,
        sortOrder: 1,
    ),
];

$invoice = new Invoice(
    id: 1,
    orderId: 1,
    originalInvoiceId: null,
    sourceRefundId: null,
    type: InvoiceType::FakturaVAT,
    number: 'FV/' . $issuedAt->format('Y/m') . '/E2E-' . $issuedAt->format('His'),
    status: InvoiceStatus::from('issued'),
    nipSeller: CONTEXT_NIP,
    nipBuyer: null,
    correctionReason: null,
    netTotal: 299.00,
    vatTotal: 68.77,
    grossTotal: 367.77,
    currency: 'PLN',
    pdfPath: null,
    ksefReference: null,
    ksefStatus: null,
    issuedAt: $issuedAt,
    createdAt: $issuedAt,
    items: $items,
);

// No WooCommerce here, so the product's GTU marking is supplied directly.
$xml = (new FaMapper('Polski Pro e2e', static fn (?int $productId): string => $productId === 1 ? 'GTU_12' : ''))->toXml(
    $invoice,
    new FaParty(CONTEXT_NIP, 'Sklep Testowy sp. z o.o.', 'PL', 'ul. Prosta 1', '00-001 Warszawa'),
    new FaParty('', 'Jan Kowalski', 'PL', 'ul. Krotka 3', '80-001 Gdansk'),
);

$errors = (new FaValidator())->errors($xml);
if (! str_contains($xml, '<GTU>GTU_12</GTU>')) {
    fail('The document carries no GTU line.');
}
if ($errors !== []) {
    fail("0. document                        INVALID, not sending:\n   " . implode("\n   ", $errors));
}
echo '0. document                        ' . strlen($xml) . " bytes, schema valid\n";

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

// 3. Send the invoice, encrypted with this session's key.
$encrypted = KSeFCrypto::encryptInvoice($xml, $material['key'], $material['iv']);
$send = SessionRequest::sendInvoice($xml, $encrypted);

[$code, $body] = call('POST', "/sessions/online/{$session}/invoices", (string) json_encode($send), 'application/json',
    array_merge($bearer, ['X-Error-Format: problem-details']));
$element = json_decode($body, true)['referenceNumber'] ?? null;
if ($element === null) {
    call('POST', "/sessions/online/{$session}/close", null, 'application/json', $bearer);
    fail("3. POST .../invoices               HTTP {$code}", $body);
}
echo "3. invoice accepted for processing {$element}\n";

// 4. Poll until KSeF has either numbered it or refused it.
$ksefNumber = null;
$last = [];
for ($i = 0; $i < 20; $i++) {
    [, $body] = call('GET', "/sessions/{$session}/invoices/{$element}", null, 'application/json', $bearer);
    $last = json_decode($body, true) ?? [];
    $ksefNumber = $last['ksefNumber'] ?? null;
    if ($ksefNumber !== null || (int) ($last['status']['code'] ?? 100) >= 400) {
        break;
    }
    sleep(3);
}

[$code] = call('POST', "/sessions/online/{$session}/close", null, 'application/json', $bearer);
echo "5. session closed                  HTTP {$code}\n";

if ($ksefNumber === null) {
    fail('4. invoice was not numbered       code=' . ($last['status']['code'] ?? '?')
        . '  ' . ($last['status']['description'] ?? ''), (string) json_encode($last['status'] ?? $last));
}

echo "4. KSeF number                     {$ksefNumber}\n";
echo "\ninvoice {$invoice->number} is in KSeF\n";

if (isset($last['upoDownloadUrl'])) {
    echo "UPO: {$last['upoDownloadUrl']}\n";
}
