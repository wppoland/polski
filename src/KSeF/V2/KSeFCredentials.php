<?php

declare(strict_types=1);

namespace Polski\KSeF\V2;

defined('ABSPATH') || exit;

/**
 * The KSeF certificate and its private key, as the merchant downloaded them
 * from Aplikacja Podatnika KSeF 2.0.
 *
 * Kept in an option of their own, not in polski_pro_ksef: ProAdminPage hands
 * that option to the admin JavaScript, and secrets have no business there.
 *
 * The private key and its passphrase are sealed with libsodium under a key
 * derived from wp_salt('secure_auth'). That keeps them out of a database dump
 * only when SECURE_AUTH_KEY and SECURE_AUTH_SALT are set in wp-config.php. When
 * either is missing or still the placeholder, WordPress generates the value and
 * stores it in the options table, next to the ciphertext, and a dump then holds
 * everything needed to open the key; KSeFSettingsPage warns about that case.
 * Rotating the salts makes the stored values unreadable, and the merchant
 * uploads the key again. Nothing here hands the stored key back to a
 * form or a log; load() only ever gives it to the signer, in memory.
 *
 * Two slots, because KSeF issues one type per certificate
 * (certyfikaty-KSeF.md): "auth" logs the shop in (type Authentication,
 * keyUsage Digital Signature) and "offline" signs KOD II on offline invoices
 * (type Offline, keyUsage Non-Repudiation). Each slot has its own option.
 */
final class KSeFCredentials
{
    public const OPTION = 'polski_pro_ksef_credentials';

    public const OFFLINE_OPTION = 'polski_pro_ksef_offline_credentials';

    public const SLOTS = ['auth' => self::OPTION, 'offline' => self::OFFLINE_OPTION];

    /** Marks the format, so a future change of scheme can tell old values apart. */
    private const PREFIX = 'sb1:';

    /**
     * Validate and store a certificate with its key.
     *
     * @param string|null $privateKeyPem Null keeps the stored key and passphrase, so the certificate can be renewed alone.
     * @param string      $slot          "auth" or "offline".
     *
     * @throws \InvalidArgumentException Saying which part is wrong; nothing is stored then.
     */
    public static function save(string $certificatePem, ?string $privateKeyPem, string $passphrase = '', string $slot = 'auth'): void
    {
        $certificatePem = self::normaliseCertificate($certificatePem);
        $certificate = openssl_x509_read($certificatePem);

        if ($certificate === false) {
            throw new \InvalidArgumentException(
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is stored raw and escaped where it is rendered (esc_html in KSeFSettingsPage).
                __('The certificate could not be read. Paste the PEM text from KSeF, including the BEGIN and END lines.', 'polski'),
            );
        }

        self::checkType($certificate, $slot);
        $stored = self::stored($slot);

        if ($privateKeyPem === null) {
            $keyPem = self::open((string) ($stored['private_key'] ?? ''));
            $passphrase = (string) self::open((string) ($stored['passphrase'] ?? ''));

            if ($keyPem === null) {
                throw new \InvalidArgumentException(__('Paste the private key that belongs to this certificate.', 'polski')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is stored raw and escaped where it is rendered.
            }
        } else {
            $keyPem = trim($privateKeyPem);
        }

        $key = openssl_pkey_get_private($keyPem, $passphrase);

        if ($key === false) {
            throw new \InvalidArgumentException(
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is stored raw and escaped where it is rendered (esc_html in KSeFSettingsPage).
                __('The private key could not be opened. Check the key and its passphrase.', 'polski'),
            );
        }

        if (! openssl_x509_check_private_key($certificate, $key)) {
            throw new \InvalidArgumentException(__('The private key does not belong to this certificate.', 'polski')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is stored raw and escaped where it is rendered.
        }

        self::checkKey($key);
        $parsed = (array) openssl_x509_parse($certificate);

        update_option(self::option($slot), [
            'certificate' => $certificatePem,
            'private_key' => self::seal($keyPem),
            'passphrase' => self::seal($passphrase),
            'serial' => self::serial((string) ($parsed['serialNumberHex'] ?? '')),
            'valid_to' => (int) ($parsed['validTo_time_t'] ?? 0),
        ], false);
    }

    /**
     * The certificate serial number as KOD II prints it: hex, upper case, an
     * even number of digits (kody-qr.md example 01F20A5D352AE590).
     */
    public static function serial(string $hex): string
    {
        $hex = strtoupper(ltrim($hex, '0'));

        return str_pad($hex, max(2, strlen($hex) + strlen($hex) % 2), '0', STR_PAD_LEFT);
    }

    /**
     * Serial number and expiry of the stored certificate, saved next to it.
     *
     * @return array{serial: string, valid_to: int}|null Null when the slot is empty.
     */
    public static function details(string $slot = 'auth'): ?array
    {
        $stored = self::stored($slot);

        if ((string) ($stored['certificate'] ?? '') === '') {
            return null;
        }

        return ['serial' => (string) ($stored['serial'] ?? ''), 'valid_to' => (int) ($stored['valid_to'] ?? 0)];
    }

    /**
     * Each KSeF certificate has one type, told apart by keyUsage. An Offline
     * certificate cannot log in and an Authentication one cannot sign KOD II.
     * A certificate without keyUsage (the self-signed TEST seal) is taken
     * for authentication only.
     *
     * @throws \InvalidArgumentException
     */
    private static function checkType(\OpenSSLCertificate $certificate, string $slot): void
    {
        $parsed = openssl_x509_parse($certificate);
        $usage = is_array($parsed) ? (string) ($parsed['extensions']['keyUsage'] ?? '') : '';
        $nonRepudiation = str_contains($usage, 'Non Repudiation') || str_contains($usage, 'Content Commitment');
        $digitalSignature = str_contains($usage, 'Digital Signature');

        if ($slot === 'offline' && ! $nonRepudiation) {
            throw new \InvalidArgumentException(__('This is not an Offline certificate (keyUsage Non-Repudiation). Paste the certificate of type Offline here; the Authentication certificate belongs in the field above.', 'polski')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is stored raw and escaped where it is rendered.
        }

        if ($slot === 'auth' && $nonRepudiation && ! $digitalSignature) {
            throw new \InvalidArgumentException(__('This is an Offline certificate, which cannot log in to KSeF. Paste the certificate of type Authentication here, and the Offline one under Offline mode.', 'polski')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is stored raw and escaped where it is rendered.
        }
    }

    /**
     * KSeF certificates use RSA of at least 2048 bits or EC P-256 (certyfikaty-KSeF.md, kody-qr.md).
     *
     * @throws \InvalidArgumentException
     */
    private static function checkKey(\OpenSSLAsymmetricKey $key): void
    {
        $details = openssl_pkey_get_details($key);
        $type = is_array($details) ? (int) ($details['type'] ?? -1) : -1;
        $ok = ($type === OPENSSL_KEYTYPE_RSA && (int) ($details['bits'] ?? 0) >= 2048)
            || ($type === OPENSSL_KEYTYPE_EC && ($details['ec']['curve_name'] ?? '') === 'prime256v1');

        if (! $ok) {
            throw new \InvalidArgumentException(__('KSeF certificates use an RSA key of at least 2048 bits or an EC P-256 key. This key is neither.', 'polski')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is stored raw and escaped where it is rendered.
        }
    }

    /**
     * Certificate and an unencrypted private key, for the signer only.
     *
     * @return array{certificate: string, private_key: string}|null Null when nothing usable is stored.
     */
    public static function load(string $slot = 'auth'): ?array
    {
        $stored = self::stored($slot);
        $certificate = (string) ($stored['certificate'] ?? '');
        $keyPem = self::open((string) ($stored['private_key'] ?? ''));
        $passphrase = self::open((string) ($stored['passphrase'] ?? ''));

        if ($certificate === '' || $keyPem === null || $passphrase === null) {
            return null;
        }

        $key = openssl_pkey_get_private($keyPem, $passphrase);
        $plain = '';

        if ($key === false || ! openssl_pkey_export($key, $plain)) {
            return null;
        }

        return ['certificate' => $certificate, 'private_key' => $plain];
    }

    /** A stored certificate that has not expired, with a key that still opens; an expired Offline one signs a KOD II KSeF reports invalid. */
    public static function usable(string $slot = 'auth'): bool
    {
        $details = self::details($slot);

        return $details !== null && $details['valid_to'] > time() && self::load($slot) !== null;
    }

    /** The stored certificate, public by nature, for display. */
    public static function certificate(string $slot = 'auth'): string
    {
        return (string) (self::stored($slot)['certificate'] ?? '');
    }

    public static function clear(string $slot = 'auth'): void
    {
        delete_option(self::option($slot));
    }

    public static function seal(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    /** Null when the value is not ours or no longer opens (salts rotated). */
    public static function open(string $sealed): ?string
    {
        if (! str_starts_with($sealed, self::PREFIX)) {
            return null;
        }

        $raw = base64_decode(substr($sealed, strlen(self::PREFIX)), true);

        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            self::key(),
        );

        return $plain === false ? null : $plain;
    }

    /**
     * The certificate is downloaded as a .crt file; accept its PEM text, or the
     * bare base64 body when someone pastes only that.
     */
    private static function normaliseCertificate(string $certificate): string
    {
        $certificate = trim($certificate);

        return str_contains($certificate, '-----BEGIN CERTIFICATE-----')
            ? $certificate . "\n"
            : KSeFCrypto::toPem($certificate);
    }

    private static function option(string $slot): string
    {
        return self::SLOTS[$slot] ?? throw new \InvalidArgumentException("Unknown KSeF certificate slot: {$slot}"); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer error, never rendered unescaped.
    }

    /** @return array<string, mixed> */
    private static function stored(string $slot = 'auth'): array
    {
        $stored = get_option(self::option($slot), []);

        return is_array($stored) ? $stored : [];
    }

    private static function key(): string
    {
        return hash_hmac('sha256', 'polski-pro-ksef-credentials', wp_salt('secure_auth'), true);
    }
}
