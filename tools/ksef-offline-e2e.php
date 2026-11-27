<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}

/**
 * KSeF offline mode end to end on the test environment.
 *
 *   php tools/ksef-make-test-cert.php     # once
 *   php tools/ksef-offline-e2e.php
 *
 * 1. logs in with the self-signed TEST seal,
 * 2. requests a KSeF certificate of type Offline (EC P-256) through
 *    /certificates/enrollments, since a self-signed certificate is not in the
 *    KSeF register and so cannot back KOD II,
 * 3. issues an invoice the way KSeFSyncService::issueOffline() does: XML built
 *    once, KOD I and KOD II computed over it,
 * 4. sends those bytes with offlineMode true and reads invoicingMode back,
 * 5. opens KOD II and KOD I on qr-test and prints what answers,
 * 6. sends a document KSeF rejects, then its technical correction with
 *    hashOfCorrectedInvoice, and reports the number the correction gets.
 *
 * Test environment only. Writes tools/ksef-offline-key.pem and
 * tools/ksef-offline-cert.pem (gitignored).
 */

// existing-checked: tools/ksef-invoice-e2e.php - that script proves the online send; the offline run adds a certificate enrollment, two QR probes and a technical correction, too much to branch into it.

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
// KSeFCredentials::serial() only; the rest of the class needs WordPress.
if (! function_exists('wp_salt')) {
    function wp_salt(string $scheme = 'auth'): string
    {
        return 'e2e';
    }
}
require $root . 'src/KSeF/V2/KSeFCredentials.php';
foreach (['InvoiceType', 'InvoiceStatus', 'InvoiceItem', 'Invoice'] as $class) {
    require $root . "src/KSeF/Model/{$class}.php";
}
foreach (['KSeFCrypto', 'AuthTokenRequest', 'XadesSigner', 'SessionRequest', 'FaParty', 'FaMapper', 'FaValidator', 'VerificationLink'] as $class) {
    require $root . "src/KSeF/V2/{$class}.php";
}

use Polski\KSeF\V2\AuthTokenRequest;
use Polski\KSeF\V2\FaMapper;
use Polski\KSeF\V2\FaParty;
use Polski\KSeF\V2\FaValidator;
use Polski\KSeF\V2\KSeFCredentials;
use Polski\KSeF\V2\KSeFCrypto;
use Polski\KSeF\V2\SessionRequest;
use Polski\KSeF\V2\VerificationLink;
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


/** @return array{0: Invoice, 1: string} The invoice and its FA(3) XML. */
function document(string $suffix): array
{
    $issuedAt = new DateTimeImmutable('now', new DateTimeZone('Europe/Warsaw'));
    $items = [new InvoiceItem(1, 1, 1, 'Licencja Polski Pro, 12 miesiecy', 1.0, 'szt', 299.00, 23.0, 68.77, 367.77, 1)];
    $invoice = new Invoice(
        id: 1,
        orderId: 1,
        originalInvoiceId: null,
        sourceRefundId: null,
        type: InvoiceType::FakturaVAT,
        number: 'FV/' . $issuedAt->format('Y/m') . '/OFF-' . $issuedAt->format('His') . $suffix,
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
    $xml = (new FaMapper('Polski Pro offline e2e', static fn (?int $productId): string => ''))->toXml(
        $invoice,
        new FaParty(CONTEXT_NIP, 'Sklep Testowy sp. z o.o.', 'PL', 'ul. Prosta 1', '00-001 Warszawa'),
        new FaParty('', 'Jan Kowalski', 'PL', 'ul. Krotka 3', '80-001 Gdansk'),
    );
    $errors = (new FaValidator())->errors($xml);
    if ($errors !== []) {
        fail('document INVALID: ' . implode(' ', $errors));
    }

    return [$invoice, $xml];
}

/** GET a QR link and say what answered. */
function probe(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30]);
    $out = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    $text = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) preg_replace('#<(script|style)\b.*?</\1>#is', '', $out))));

    return "HTTP {$code} {$type} " . substr($text, 0, 160);
}

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
for ($i = 0; $i < 10; $i++) {
    [, $body] = call('GET', '/auth/' . ($init['referenceNumber'] ?? ''), null, 'application/json', $authHeader);
    if ((json_decode($body, true)['status']['code'] ?? 0) !== 100) {
        break;
    }
    usleep(1_200_000);
}
[, $body] = call('POST', '/auth/token/redeem', null, 'application/json', $authHeader);
$access = json_decode($body, true)['accessToken']['token'] ?? null;
if ($access === null) {
    fail('1. no access token', $body);
}
$bearer = ["Authorization: Bearer {$access}", 'X-Error-Format: problem-details'];
echo "1. authenticated (XAdES, self-signed TEST seal)\n";

// 2. Offline certificate: enrollment data, CSR with exactly those fields, enroll, retrieve.
$keyFile = $root . 'tools/ksef-offline-key.pem';
$certFile = $root . 'tools/ksef-offline-cert.pem';
if (! is_file($certFile) || ! is_file($keyFile)) {
    [$code, $body] = call('GET', '/certificates/enrollments/data', null, 'application/json', $bearer);
    $data = json_decode($body, true);
    if ($code !== 200 || ! is_array($data)) {
        fail("2. GET /certificates/enrollments/data HTTP {$code}", $body);
    }
    $subject = array_filter([
        'commonName' => $data['commonName'] ?? null,
        'countryName' => $data['countryName'] ?? null,
        'givenName' => $data['givenName'] ?? null,
        'surname' => $data['surname'] ?? null,
        'serialNumber' => $data['serialNumber'] ?? null,
        'uniqueIdentifier' => $data['uniqueIdentifier'] ?? null,
        'organizationName' => $data['organizationName'] ?? null,
        'organizationIdentifier' => $data['organizationIdentifier'] ?? null,
    ], static fn (mixed $v): bool => is_string($v) && $v !== '');
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    $csr = openssl_csr_new($subject, $key, ['digest_alg' => 'sha256']);
    if ($csr === false) {
        fail('2. CSR could not be built: ' . openssl_error_string());
    }
    openssl_csr_export($csr, $csrPem);
    $csrDer = base64_decode((string) preg_replace('/-----[A-Z ]+-----|\s/', '', $csrPem));
    [$code, $body] = call('POST', '/certificates/enrollments', (string) json_encode([
        'certificateName' => 'Polski PRO offline e2e',
        'certificateType' => 'Offline',
        'csr' => base64_encode($csrDer),
    ]), 'application/json', $bearer);
    $reference = json_decode($body, true)['referenceNumber'] ?? null;
    if ($reference === null) {
        fail("2. POST /certificates/enrollments HTTP {$code}", $body);
    }
    $serialNumber = null;
    for ($i = 0; $i < 30 && $serialNumber === null; $i++) {
        sleep(2);
        [, $body] = call('GET', '/certificates/enrollments/' . $reference, null, 'application/json', $bearer);
        $status = json_decode($body, true);
        $serialNumber = $status['certificateSerialNumber'] ?? null;
        if ((int) ($status['status']['code'] ?? 100) >= 400) {
            fail('2. enrollment refused', $body);
        }
    }
    if ($serialNumber === null) {
        fail('2. enrollment not finished in a minute', $body);
    }
    [$code, $body] = call('POST', '/certificates/retrieve', (string) json_encode(['certificateSerialNumbers' => [$serialNumber]]), 'application/json', $bearer);
    $der = json_decode($body, true)['certificates'][0]['certificate'] ?? null;
    if ($der === null) {
        fail("2. POST /certificates/retrieve HTTP {$code}", $body);
    }
    openssl_pkey_export($key, $keyPem);
    file_put_contents($keyFile, $keyPem);
    file_put_contents($certFile, KSeFCrypto::toPem($der));
    echo "2. Offline certificate issued      serial from API {$serialNumber}\n";
} else {
    echo "2. Offline certificate reused      tools/ksef-offline-cert.pem\n";
}
$offlineCert = (string) file_get_contents($certFile);
$offlineKey = (string) file_get_contents($keyFile);
$parsed = (array) openssl_x509_parse($offlineCert);
$serial = KSeFCredentials::serial((string) ($parsed['serialNumberHex'] ?? ''));
echo '   keyUsage: ' . ($parsed['extensions']['keyUsage'] ?? '(none)') . "\n";
echo "   serial as KSeFCredentials stores it: {$serial}\n";
[, $body] = call('POST', '/certificates/query', (string) json_encode(['certificateSerialNumber' => $serial]), 'application/json', $bearer);
echo '   /certificates/query by that serial: ' . substr($body, 0, 300) . "\n";

// 3. Issue offline: one build, both codes over it.
[$invoice, $xml] = document('');
$hash = VerificationLink::hash($xml);
$kod1 = VerificationLink::url('test', $invoice->issuedAt, CONTEXT_NIP, $hash);
$kod2 = VerificationLink::certificateUrl('test', 'Nip', CONTEXT_NIP, CONTEXT_NIP, $serial, $hash, $offlineKey);
echo "3. issued offline                  " . strlen($xml) . " bytes\n   KOD I  {$kod1}\n   KOD II {$kod2}\n";
echo '   KOD II before sending: ' . probe($kod2) . "\n";
echo '   KOD I before sending:  ' . probe($kod1) . "\n";

// 4. Session, send the frozen bytes with offlineMode.
[, $body] = call('GET', '/security/public-key-certificates');
$certificate = null;
foreach (json_decode($body, true) ?? [] as $candidate) {
    if (in_array('SymmetricKeyEncryption', $candidate['usage'] ?? [], true)) {
        $certificate = $candidate;
    }
}
$material = KSeFCrypto::newSymmetricKey();
$open = SessionRequest::openOnline(
    KSeFCrypto::encryptSymmetricKey($material['key'], KSeFCrypto::toPem($certificate['certificate'])),
    $material['iv'],
    (string) $certificate['publicKeyId'],
    'FA(3)',
);
[$code, $body] = call('POST', '/sessions/online', (string) json_encode($open), 'application/json', $bearer);
$session = json_decode($body, true)['referenceNumber'] ?? null;
if ($session === null) {
    fail("4. POST /sessions/online HTTP {$code}", $body);
}

/** @return array<string, mixed> Final status of one document. */
function sendAndWait(string $session, string $xml, array $material, array $bearer, ?string $corrected = null): array
{
    $encrypted = KSeFCrypto::encryptInvoice($xml, $material['key'], $material['iv']);
    [$code, $body] = call('POST', "/sessions/online/{$session}/invoices", (string) json_encode(SessionRequest::sendInvoice($xml, $encrypted, true, $corrected)), 'application/json', $bearer);
    $element = json_decode($body, true)['referenceNumber'] ?? null;
    if ($element === null) {
        return ['http' => $code, 'body' => $body];
    }
    $last = [];
    for ($i = 0; $i < 20; $i++) {
        sleep(3);
        [, $body] = call('GET', "/sessions/{$session}/invoices/{$element}", null, 'application/json', $bearer);
        $last = json_decode($body, true) ?? [];
        if (isset($last['ksefNumber']) || (int) ($last['status']['code'] ?? 100) >= 400) {
            break;
        }
    }

    return $last;
}

$result = sendAndWait($session, $xml, $material, $bearer);
echo '4. offline send                    status ' . ($result['status']['code'] ?? '?') . ' ' . ($result['status']['description'] ?? '')
    . "\n   ksefNumber " . ($result['ksefNumber'] ?? '-') . '  invoicingMode ' . ($result['invoicingMode'] ?? '-')
    . '  invoiceHash matches ' . (($result['invoiceHash'] ?? '') === KSeFCrypto::hash($xml) ? 'yes' : 'no') . "\n";

// 6. A rejected offline document and its technical correction.
[, $good] = document('T');
$bad = str_replace('<P_2>', '<P_2_bledne>', $good);
$bad = str_replace('</P_2>', '</P_2_bledne>', $bad);
$rejected = sendAndWait($session, $bad, $material, $bearer);
echo '6. broken document                 status ' . ($rejected['status']['code'] ?? $rejected['http'] ?? '?') . ' ' . ($rejected['status']['description'] ?? substr((string) ($rejected['body'] ?? ''), 0, 200)) . "\n";
$correction = sendAndWait($session, $good, $material, $bearer, KSeFCrypto::hash($bad));
echo '   technical correction              status ' . ($correction['status']['code'] ?? $correction['http'] ?? '?') . ' ' . ($correction['status']['description'] ?? substr((string) ($correction['body'] ?? ''), 0, 300))
    . "\n   ksefNumber " . ($correction['ksefNumber'] ?? '-') . '  invoicingMode ' . ($correction['invoicingMode'] ?? '-') . "\n";
if (isset($correction['status']['details'])) {
    echo '   details ' . json_encode($correction['status']['details'], JSON_UNESCAPED_UNICODE) . "\n";
}

[$code] = call('POST', "/sessions/online/{$session}/close", null, 'application/json', $bearer);
echo "   session closed HTTP {$code}\n";

// 5. The codes after KSeF took the invoice.
sleep(5);
echo '5. KOD II after sending: ' . probe($kod2) . "\n";
echo '   KOD I after sending:  ' . probe($kod1) . "\n";
echo '   KOD I of the rejected file after its correction: ' . probe(VerificationLink::url('test', $invoice->issuedAt, CONTEXT_NIP, VerificationLink::hash($bad))) . "\n";
echo isset($result['ksefNumber']) && isset($correction['ksefNumber']) ? "\nOK\n" : "\nNOT OK\n";
exit(isset($result['ksefNumber']) && isset($correction['ksefNumber']) ? 0 : 1);
