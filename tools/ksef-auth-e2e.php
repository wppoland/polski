<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}

/**
 * Runs the whole KSeF 2.0 authentication chain against the TEST environment.
 *
 *   php tools/ksef-make-test-cert.php     # once, generates the self-signed pair
 *   php tools/ksef-auth-e2e.php
 *
 * challenge -> signed AuthTokenRequest -> status poll -> redeem.
 *
 * Test environment only, with a self-signed certificate the Ministry permits
 * there, and the plainly fake NIP 9999999999. Nothing here touches production
 * and nothing here uses anybody's real identity.
 */

define('ABSPATH', __DIR__);

const BASE = 'https://api-test.ksef.mf.gov.pl/v2';
const CONTEXT_NIP = '9999999999';

$root = dirname(__DIR__) . '/';

foreach (['XMLSecurityDSig', 'XMLSecurityKey', 'XMLSecEnc'] as $class) {
    require $root . "vendor/robrichards/xmlseclibs/src/{$class}.php";
}
require $root . 'vendor/robrichards/xmlseclibs/src/Utils/XPath.php';
require $root . 'src/KSeF/V2/AuthTokenRequest.php';
require $root . 'src/KSeF/V2/XadesSigner.php';

use Polski\KSeF\V2\AuthTokenRequest;
use Polski\KSeF\V2\XadesSigner;

$keyFile = $root . 'tools/ksef-test-key.pem';
$certFile = $root . 'tools/ksef-test-cert.pem';

if (! is_file($keyFile) || ! is_file($certFile)) {
    fwrite(STDERR, "Run tools/ksef-make-test-cert.php first.\n");
    exit(1);
}

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

[$code, $body] = call('POST', '/auth/challenge', '{}');
$challenge = json_decode($body, true);
echo "1. POST /auth/challenge            HTTP {$code}  {$challenge['challenge']}\n";
if ($code !== 200) {
    exit(1);
}

$signed = XadesSigner::sign(
    AuthTokenRequest::build($challenge['challenge'], CONTEXT_NIP),
    (string) file_get_contents($keyFile),
    (string) file_get_contents($certFile),
);
echo "2. signed AuthTokenRequest         " . strlen($signed) . " bytes\n";

[$code, $body] = call('POST', '/auth/xades-signature', $signed, 'application/xml', ['X-Error-Format: problem-details']);
echo "3. POST /auth/xades-signature      HTTP {$code}\n";
$init = json_decode($body, true);
$reference = $init['referenceNumber'] ?? null;
$authToken = $init['authenticationToken']['token'] ?? null;

if ($reference === null || $authToken === null) {
    echo substr($body, 0, 800) . "\n";
    exit(1);
}

$authHeader = ["Authorization: Bearer {$authToken}"];
$status = null;

for ($attempt = 1; $attempt <= 8; $attempt++) {
    [$code, $body] = call('GET', '/auth/' . $reference, null, 'application/json', $authHeader);
    $status = json_decode($body, true);
    $statusCode = $status['status']['code'] ?? 0;
    echo "4. GET /auth/{ref} attempt {$attempt}        HTTP {$code}  code={$statusCode}  "
        . ($status['status']['description'] ?? '') . "\n";
    if ($statusCode !== 100) {
        break;
    }
    usleep(1_500_000);
}

if (($status['status']['code'] ?? 0) !== 200) {
    fwrite(STDERR, "Authentication did not succeed.\n");
    exit(1);
}

[$code, $body] = call('POST', '/auth/token/redeem', null, 'application/json', $authHeader);
echo "5. POST /auth/token/redeem         HTTP {$code}\n";
$tokens = json_decode($body, true);

foreach (['accessToken', 'refreshToken'] as $name) {
    $jwt = $tokens[$name]['token'] ?? null;
    if ($jwt === null) {
        echo "   {$name}: absent\n";
        continue;
    }
    $claims = json_decode((string) base64_decode(strtr(explode('.', $jwt)[1], '-_', '+/')), true);
    echo "   {$name}: " . strlen($jwt) . " chars, expires " . date('c', $claims['exp'] ?? 0) . "\n";
}

echo "\nauthentication chain complete\n";
