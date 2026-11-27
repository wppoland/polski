<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}

/**
 * Checks KSeFCrypto against the Ministry's real public keys.
 *
 * Reaches the network but needs no credentials: /auth/challenge and
 * /security/public-key-certificates are both unauthenticated. That is enough to
 * confirm the base URL is right, the certificates parse, and our RSA-OAEP output
 * is the size the key demands. It cannot confirm the Ministry accepts the
 * ciphertext, because only they hold the private key; the keypair round trip in
 * tests/ksef-crypto-test.php covers that side.
 *
 *   php tools/ksef-live-probe.php
 */

define('ABSPATH', __DIR__);

const KSEF_TEST_BASE = 'https://api-test.ksef.mf.gov.pl/v2';

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
require $root . 'src/KSeF/V2/KSeFCrypto.php';

use Polski\KSeF\V2\KSeFCrypto;

function get(string $url, string $method = 'GET'): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
    }
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$code, json_decode((string) $body, true)];
}

[$code, $challenge] = get(KSEF_TEST_BASE . '/auth/challenge', 'POST');
echo "POST /auth/challenge -> HTTP {$code}\n";
if ($code !== 200) {
    fwrite(STDERR, "challenge failed\n");
    exit(1);
}
echo "  challenge:   {$challenge['challenge']}\n";
echo "  timestampMs: {$challenge['timestampMs']}\n";

[$code, $certs] = get(KSEF_TEST_BASE . '/security/public-key-certificates');
echo "GET /security/public-key-certificates -> HTTP {$code}, " . count($certs) . " certificates\n";

$failures = 0;
foreach ($certs as $cert) {
    $usage = $cert['usage'][0] ?? '?';
    $pem = KSeFCrypto::toPem($cert['certificate']);
    $parsed = openssl_x509_parse($pem);
    $details = openssl_pkey_get_details(openssl_pkey_get_public($pem));

    if ($parsed === false || $details === false) {
        echo "  {$usage}: FAILED to parse\n";
        $failures++;
        continue;
    }

    $expected = (int) ($details['bits'] / 8);
    $ciphertext = $usage === 'KsefTokenEncryption'
        ? KSeFCrypto::encryptToken('PROBE-NOT-A-REAL-TOKEN', (int) $challenge['timestampMs'], $pem)
        : KSeFCrypto::encryptSymmetricKey(KSeFCrypto::newSymmetricKey()['key'], $pem);
    $actual = strlen((string) base64_decode($ciphertext, true));

    printf(
        "  %-24s %s | RSA-%d | keyId %d chars | ciphertext %d bytes %s\n",
        $usage,
        $parsed['subject']['CN'] ?? '?',
        $details['bits'],
        strlen($cert['publicKeyId'] ?? ''),
        $actual,
        $actual === $expected && strlen($cert['publicKeyId'] ?? '') === 44 ? 'ok' : 'MISMATCH',
    );

    if ($actual !== $expected) {
        $failures++;
    }
}

echo $failures === 0 ? "\nall good\n" : "\n{$failures} problem(s)\n";
exit($failures === 0 ? 0 : 1);
