<?php

declare(strict_types=1);

namespace Polski\KSeF\V2;

defined('ABSPATH') || exit;

/**
 * Bodies for the interactive session endpoints.
 *
 * Kept apart from the HTTP client so the exact shapes KSeF expects can be
 * asserted in tests without a network or a token, which is where most of the
 * risk lives: a wrong field name here is a 400 that says very little.
 */
final class SessionRequest
{
    /**
     * Schemas the online session accepts, from the OpenAPI table on
     * POST /sessions/online. systemCode, schemaVersion, value. FaMapper builds
     * FA(3), mandatory for invoices issued from 2026-02-01. The OpenAPI table
     * still lists FA(2), so it stays accepted here for a caller that names it;
     * nothing in the plugin sends it.
     */
    public const FORM_CODES = [
        'FA(2)' => ['systemCode' => 'FA (2)', 'schemaVersion' => '1-0E', 'value' => 'FA'],
        'FA(3)' => ['systemCode' => 'FA (3)', 'schemaVersion' => '1-0E', 'value' => 'FA'],
    ];

    /**
     * Body for POST /sessions/online.
     *
     * The symmetric key travels wrapped in the Ministry's public key and the IV
     * travels in the clear, which is what an IV is for. Both base64.
     *
     * @param string $wrappedKey Base64 RSA-OAEP-SHA256 of the raw 32 byte key.
     * @param string $iv         Raw 16 byte initialisation vector.
     * @param string $publicKeyId The Ministry key id the wrapping used, so they know which private half to try.
     * @return array<string, mixed>
     */
    public static function openOnline(
        string $wrappedKey,
        string $iv,
        string $publicKeyId,
        string $formCode = 'FA(3)',
    ): array {
        if (! isset(self::FORM_CODES[$formCode])) {
            throw new \InvalidArgumentException("Unsupported KSeF form code: {$formCode}"); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer error, never rendered unescaped.
        }

        if (strlen($iv) !== KSeFCrypto::IV_BYTES) {
            throw new \InvalidArgumentException('The initialisation vector must be exactly 16 raw bytes.');
        }

        return [
            'formCode' => self::FORM_CODES[$formCode],
            'encryption' => [
                'encryptedSymmetricKey' => $wrappedKey,
                'initializationVector' => base64_encode($iv),
                'publicKeyId' => $publicKeyId,
            ],
        ];
    }

    /**
     * Body for POST /sessions/online/{referenceNumber}/invoices.
     *
     * Four measurements of the same document, two before encryption and two
     * after. KSeF checks all of them, so they are computed here in one place
     * from the two byte strings rather than passed in and trusted.
     *
     * @param string      $xml                    The invoice document, as it will be after decryption.
     * @param string      $encrypted              The same document under AES-256-CBC.
     * @param string|null $hashOfCorrectedInvoice Technical correction of a rejected offline invoice
     *        (offline/korekta-techniczna.md): SHA-256 of the rejected file, plain Base64.
     * @return array<string, mixed>
     */
    public static function sendInvoice(string $xml, string $encrypted, bool $offlineMode = false, ?string $hashOfCorrectedInvoice = null): array
    {
        $body = [
            'invoiceHash' => KSeFCrypto::hash($xml),
            'invoiceSize' => strlen($xml),
            'encryptedInvoiceHash' => KSeFCrypto::hash($encrypted),
            'encryptedInvoiceSize' => strlen($encrypted),
            'encryptedInvoiceContent' => base64_encode($encrypted),
            'offlineMode' => $offlineMode,
        ];

        if ((string) $hashOfCorrectedInvoice !== '') {
            $body['hashOfCorrectedInvoice'] = $hashOfCorrectedInvoice;
        }

        return $body;
    }
}
