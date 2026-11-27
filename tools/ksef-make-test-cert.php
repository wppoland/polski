<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}

/**
 * Generates a self-signed certificate for the KSeF TEST environment only.
 *
 * The Ministry allows self-signed certificates on the test environment, treated
 * as the equivalent of a qualified one, which is what lets this integration be
 * exercised end to end without anybody's real identity.
 *
 * The subject follows what the Ministry documents for a qualified SEAL, because
 * a shop authenticates as an organisation and not as a person:
 *   organizationIdentifier  VATPL-<NIP>   (the pattern it matches is VATPL then ten digits)
 *   organizationName, commonName, countryName
 * givenName and surname are explicitly prohibited for a seal, so they are absent.
 *
 *   php tools/ksef-make-test-cert.php [NIP]
 *
 * Writes ksef-test-key.pem and ksef-test-cert.pem next to this script. Both are
 * gitignored. Never use either against production.
 */

$nip = $argv[1] ?? '9999999999';

if (preg_match('/^[1-9]((\d[1-9])|([1-9]\d))\d{7}$/', $nip) !== 1) {
    fwrite(STDERR, "Not a NIP the KSeF auth schema accepts: {$nip}\n");
    exit(1);
}

$subject = [
    'countryName' => 'PL',
    'organizationName' => 'Polski PRO test seal',
    'commonName' => 'Polski PRO test seal',
    'organizationIdentifier' => 'VATPL-' . $nip,
];

$config = [
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'digest_alg' => 'sha256',
];

$key = openssl_pkey_new($config);

if ($key === false) {
    fwrite(STDERR, "Could not generate a key: " . openssl_error_string() . "\n");
    exit(1);
}

$csr = openssl_csr_new($subject, $key, $config);

if ($csr === false) {
    fwrite(STDERR, "Could not build the request: " . openssl_error_string() . "\n");
    fwrite(STDERR, "If this complains about organizationIdentifier, the local openssl.cnf lacks that OID.\n");
    exit(1);
}

$cert = openssl_csr_sign($csr, null, $key, 365, $config);

if ($cert === false) {
    fwrite(STDERR, "Could not self-sign: " . openssl_error_string() . "\n");
    exit(1);
}

openssl_pkey_export($key, $privatePem);
openssl_x509_export($cert, $certPem);

file_put_contents(__DIR__ . '/ksef-test-key.pem', $privatePem);
file_put_contents(__DIR__ . '/ksef-test-cert.pem', $certPem);

$parsed = openssl_x509_parse($certPem);

echo "wrote tools/ksef-test-key.pem and tools/ksef-test-cert.pem\n";
echo "subject: " . ($parsed['name'] ?? '?') . "\n";
echo "valid to: " . date('c', $parsed['validTo_time_t'] ?? 0) . "\n";
echo "fingerprint sha256: " . openssl_x509_fingerprint($certPem, 'sha256') . "\n";
