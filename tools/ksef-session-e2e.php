<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}

/**
 * Authenticates, opens an interactive KSeF session and closes it again.
 *
 *   php tools/ksef-make-test-cert.php     # once
 *   php tools/ksef-session-e2e.php
 *
 * This is the step that proves the symmetric key wrapping: the Ministry can
 * only accept the session if it decrypts the AES key we sent under its public
 * certificate. Test environment only.
 */

define('ABSPATH', __DIR__);

const BASE = 'https://api-test.ksef.mf.gov.pl/v2';
const CONTEXT_NIP = '9999999999';

$root = dirname(__DIR__) . '/';

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
foreach (['KSeFCrypto', 'AuthTokenRequest', 'XadesSigner', 'SessionRequest'] as $class) {
    require $root . "src/KSeF/V2/{$class}.php";
}

use Polski\KSeF\V2\AuthTokenRequest;
use Polski\KSeF\V2\KSeFCrypto;
use Polski\KSeF\V2\SessionRequest;
use Polski\KSeF\V2\XadesSigner;

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
    fwrite(STDERR, "No access token.\n");
    exit(1);
}
$bearer = ["Authorization: Bearer {$access}"];
echo "2. access token                    obtained\n";

[, $body] = call('GET', '/security/public-key-certificates');
$certificate = null;
foreach (json_decode($body, true) ?? [] as $candidate) {
    if (in_array('SymmetricKeyEncryption', $candidate['usage'] ?? [], true)) {
        $certificate = $candidate;
    }
}
if ($certificate === null) {
    fwrite(STDERR, "No SymmetricKeyEncryption certificate offered.\n");
    exit(1);
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
echo "3. POST /sessions/online           HTTP {$code}\n";

$session = json_decode($body, true)['referenceNumber'] ?? null;
if ($session === null) {
    echo substr($body, 0, 600) . "\n";
    exit(1);
}
echo "   session {$session}, valid until " . (json_decode($body, true)['validUntil'] ?? '?') . "\n";

[$code] = call('POST', "/sessions/online/{$session}/close", null, 'application/json', $bearer);
echo "4. POST /sessions/online/{ref}/close  HTTP {$code}\n";

echo "\nsession opened and closed, the Ministry accepted our wrapped key\n";
