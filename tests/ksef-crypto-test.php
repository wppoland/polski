<?php

declare(strict_types=1);

/**
 * KSeF 2.0 cryptography checks.
 *
 * No framework, matching the other standalone tests in this directory. Run with
 * `php tests/ksef-crypto-test.php`.
 *
 * The point of these is that the crypto can be proved correct without a KSeF
 * token and without reaching the Ministry at all: generate a keypair here, let
 * KSeFCrypto encrypt with the public half, and decrypt with the private half
 * using the same OAEP parameters KSeF specifies. If the round trip holds with
 * SHA-256 on both the OAEP and the MGF1 hash, the wire format is right.
 */

define('ABSPATH', __DIR__);

// Not the Composer autoloader: it eagerly loads the Freemius SDK, which needs a
// booted WordPress. Register phpseclib alone, the way the other framework-free
// tests in this directory stub only what they need.
$phpseclibRoot = __DIR__ . '/../vendor/phpseclib/phpseclib/phpseclib/';
require $phpseclibRoot . 'bootstrap.php';
spl_autoload_register(static function (string $class) use ($phpseclibRoot): void {
    if (! str_starts_with($class, 'phpseclib3\\')) {
        return;
    }

    $path = $phpseclibRoot . str_replace('\\', '/', substr($class, strlen('phpseclib3\\'))) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

require __DIR__ . '/../src/KSeF/V2/KSeFCrypto.php';

use phpseclib3\Crypt\RSA;
use Polski\KSeF\V2\KSeFCrypto;

$failures = 0;
$checks = 0;

function check(string $label, bool $ok): void
{
    global $failures, $checks;
    $checks++;
    if (! $ok) {
        $failures++;
        echo "FAIL  {$label}\n";
        return;
    }
    echo "ok    {$label}\n";
}

// A stand-in for the Ministry keypair.
$private = RSA::createKey(2048);
$publicPem = (string) $private->getPublicKey();

$decrypt = static function (string $base64) use ($private): string {
    /** @var RSA\PrivateKey $configured */
    $configured = $private
        ->withPadding(RSA::ENCRYPTION_OAEP)
        ->withHash('sha256')
        ->withMGFHash('sha256');

    return $configured->decrypt(base64_decode($base64, true));
};

// --- Token encryption -------------------------------------------------------

$token = 'ABCDEF0123456789';
$timestampMs = 1786290000123;

$encryptedToken = KSeFCrypto::encryptToken($token, $timestampMs, $publicPem);

check('token ciphertext is valid base64', base64_decode($encryptedToken, true) !== false);
check(
    'token decrypts to the exact token|timestamp form KSeF specifies',
    $decrypt($encryptedToken) === $token . '|' . $timestampMs,
);
check(
    'the timestamp is the millisecond value, not an ISO string',
    str_ends_with($decrypt($encryptedToken), '|1786290000123'),
);
check(
    'OAEP is randomised, so the same input twice gives different ciphertext',
    KSeFCrypto::encryptToken($token, $timestampMs, $publicPem) !== $encryptedToken,
);

// --- Symmetric key ----------------------------------------------------------

$material = KSeFCrypto::newSymmetricKey();

check('symmetric key is 32 bytes', strlen($material['key']) === KSeFCrypto::KEY_BYTES);
check('initialisation vector is 16 bytes', strlen($material['iv']) === KSeFCrypto::IV_BYTES);
check('two calls do not return the same key', KSeFCrypto::newSymmetricKey()['key'] !== $material['key']);

$wrappedKey = KSeFCrypto::encryptSymmetricKey($material['key'], $publicPem);
check('the wrapped symmetric key decrypts back to the raw key', $decrypt($wrappedKey) === $material['key']);

// --- Invoice encryption -----------------------------------------------------

$xml = '<?xml version="1.0" encoding="UTF-8"?><Faktura><Naglowek>Zażółć gęślą jaźń</Naglowek></Faktura>';
$cipher = KSeFCrypto::encryptInvoice($xml, $material['key'], $material['iv']);

$plain = openssl_decrypt($cipher, 'aes-256-cbc', $material['key'], OPENSSL_RAW_DATA, $material['iv']);
check('invoice round trips through AES-256-CBC', $plain === $xml);
check('ciphertext is a whole number of AES blocks', strlen($cipher) % 16 === 0);
check(
    'PKCS#7 padding adds a full block when the input is block aligned',
    strlen(KSeFCrypto::encryptInvoice(str_repeat('A', 32), $material['key'], $material['iv'])) === 48,
);

// --- Hashes -----------------------------------------------------------------

check(
    'hash of an empty string matches the known SHA-256 digest',
    KSeFCrypto::hash('') === '47DEQpj8HBSa+/TImW+5JCeuQeRkm5NMpJWZG3hSuFU=',
);
check(
    'hash is the base64 of the raw digest, not of the hex string',
    KSeFCrypto::hash($xml) === base64_encode(hash('sha256', $xml, true)),
);

// --- Certificate armour -----------------------------------------------------

$bare = preg_replace('/-----[A-Z ]+-----|\s+/', '', $publicPem) ?? '';
$armoured = KSeFCrypto::toPem($bare);

check('armoured output opens with a PEM header', str_starts_with($armoured, '-----BEGIN CERTIFICATE-----'));
check('armoured output wraps at 64 characters', (bool) preg_match('/^.{64}$/m', $armoured));

echo "\n{$checks} checks, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
