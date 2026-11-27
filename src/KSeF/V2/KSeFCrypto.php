<?php

declare(strict_types=1);

namespace Polski\KSeF\V2;

defined('ABSPATH') || exit;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

/**
 * The cryptography KSeF 2.0 requires, and nothing else.
 *
 * Two things are encrypted with the Ministry's public key: the KSeF token during
 * authentication, and the AES key that protects each invoice. Both use RSA with
 * OAEP padding and SHA-256.
 *
 * That SHA-256 is why this class exists rather than a couple of calls to the
 * openssl extension. PHP's openssl_public_encrypt does support
 * OPENSSL_PKCS1_OAEP_PADDING, but it hard-codes SHA-1 for the OAEP and MGF1
 * hashes and exposes no way to change them, so its output is rejected. phpseclib
 * lets the hash be set, which is the whole reason for the dependency.
 *
 * The invoice itself is AES-256-CBC with PKCS#7 padding, which the openssl
 * extension does correctly by default, so that part stays on openssl.
 */
final class KSeFCrypto
{
    /** KSeF requires a 32 byte symmetric key and a 16 byte IV. */
    public const KEY_BYTES = 32;
    public const IV_BYTES = 16;

    /**
     * Encrypt the KSeF token for POST /auth/ksef-token.
     *
     * The plaintext is the token and the challenge timestamp joined by a pipe,
     * exactly "token|timestamp". The timestamp is the millisecond value the
     * challenge response returns as timestampMs, not the ISO string.
     *
     * @param string $certificatePem Ministry certificate carrying the public key.
     */
    public static function encryptToken(string $token, int $challengeTimestampMs, string $certificatePem): string
    {
        return base64_encode(
            self::rsaOaepSha256($token . '|' . $challengeTimestampMs, $certificatePem),
        );
    }

    /**
     * A fresh symmetric key and IV for one session.
     *
     * @return array{key: string, iv: string} Raw bytes, not encoded.
     */
    public static function newSymmetricKey(): array
    {
        return [
            'key' => random_bytes(self::KEY_BYTES),
            'iv' => random_bytes(self::IV_BYTES),
        ];
    }

    /**
     * Encrypt the symmetric key for the encryption block of POST /sessions/online.
     *
     * @param string $key Raw 32 byte key from newSymmetricKey().
     */
    public static function encryptSymmetricKey(string $key, string $certificatePem): string
    {
        return base64_encode(self::rsaOaepSha256($key, $certificatePem));
    }

    /**
     * Encrypt one invoice document.
     *
     * AES-256-CBC with PKCS#7 padding, which is what OPENSSL_RAW_DATA gives
     * without OPENSSL_ZERO_PADDING.
     *
     * @param string $key Raw 32 byte key.
     * @param string $iv  Raw 16 byte IV.
     */
    public static function encryptInvoice(string $xml, string $key, string $iv): string
    {
        $encrypted = openssl_encrypt($xml, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new \RuntimeException('Failed to encrypt the invoice document.');
        }

        return $encrypted;
    }

    /**
     * Base64 encoded SHA-256 digest, the form every hash field in the API wants.
     */
    public static function hash(string $data): string
    {
        return base64_encode(hash('sha256', $data, true));
    }

    /**
     * Wrap a bare base64 DER certificate body in PEM armour.
     *
     * GET /security/public-key-certificates returns the certificate as base64
     * with no header, which openssl and phpseclib both refuse to load.
     */
    public static function toPem(string $base64Certificate): string
    {
        $body = trim(preg_replace('/\s+/', '', $base64Certificate) ?? '');

        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split($body, 64, "\n")
            . "-----END CERTIFICATE-----\n";
    }

    private static function rsaOaepSha256(string $plaintext, string $certificatePem): string
    {
        $key = PublicKeyLoader::loadPublicKey($certificatePem);

        if (! $key instanceof RSA\PublicKey) {
            throw new \RuntimeException('The Ministry certificate did not yield an RSA public key.');
        }

        /** @var RSA\PublicKey $configured */
        $configured = $key
            ->withPadding(RSA::ENCRYPTION_OAEP)
            ->withHash('sha256')
            ->withMGFHash('sha256');

        $encrypted = $configured->encrypt($plaintext);
        if (! is_string($encrypted)) {
            throw new \RuntimeException('RSA-OAEP encryption failed.');
        }

        return $encrypted;
    }
}
