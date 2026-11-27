<?php

declare(strict_types=1);

namespace Polski\KSeF\V2;

defined('ABSPATH') || exit;

/**
 * KOD I: the link printed as a QR code on an invoice already in KSeF.
 *
 * Source: github.com/CIRFMF/ksef-docs, kody-qr.md, "1. KOD I" (21.08.2025).
 * The link is the environment's QR host, then /invoice/, the seller NIP, the
 * P_1 issue date as DD-MM-RRRR, and the SHA-256 of the invoice file in
 * Base64URL. The prose lists the date before the NIP, but the spec's own
 * example URL puts the NIP first, and the example is what the test pins.
 *
 * The hash must be taken over the exact bytes that were sent, which is the
 * same digest SessionRequest sends as invoiceHash, only in the URL-safe
 * alphabet without padding. FaMapper stamps DataWytworzeniaFa, so rebuilding
 * the document later gives a different hash: KSeFSyncService builds url() at
 * submission time and stores it in ksef_qr_url, and InvoiceGenerator prints
 * it once the invoice has its KSeF number.
 */
final class VerificationLink
{
    /** Context types a KOD II link may name, from kody-qr.md "2. KOD II". */
    public const CONTEXT_TYPES = ['Nip', 'InternalId', 'NipVatUe', 'PeppolId'];

    /** QR hosts per environment, from kody-qr.md "Srodowiska"; not the API hosts. */
    private const HOSTS = [
        'test' => 'https://qr-test.ksef.mf.gov.pl',
        'demo' => 'https://qr-demo.ksef.mf.gov.pl',
        'production' => 'https://qr.ksef.mf.gov.pl',
    ];

    /** TNumerKSeF from the FA(3) schema; FaMapper and KSeFSyncService call isKsefNumber() rather than copy it. */
    private const KSEF_NUMBER = '/^([1-9]((\d[1-9])|([1-9]\d))\d{7}|M\d{9}|[A-Z]{3}\d{7})-(20[2-9][0-9]|2[1-9][0-9]{2}|[3-9][0-9]{3})(0[1-9]|1[0-2])(0[1-9]|[1-2][0-9]|3[0-1])-([0-9A-F]{6})-?([0-9A-F]{6})-([0-9A-F]{2})$/';

    /**
     * SHA-256 of the invoice XML bytes, Base64URL, no padding.
     */
    public static function hash(string $xml): string
    {
        return self::base64Url(hash('sha256', $xml, true));
    }

    /**
     * Build the KOD I link.
     *
     * Like ApiClient, only an exact "production" (or "demo") leaves the test
     * host, so an empty or unexpected setting can never point a code at the
     * production register. Pass ApiClient::environment() of the client that
     * sent the document.
     *
     * @param string $hash hash() of the XML that was sent.
     */
    public static function url(string $environment, \DateTimeInterface $issueDate, string $sellerNip, string $hash): string
    {
        return sprintf(
            '%s/invoice/%s/%s/%s',
            self::HOSTS[$environment] ?? self::HOSTS['test'],
            $sellerNip,
            $issueDate->format('d-m-Y'),
            $hash,
        );
    }

    /**
     * KOD II: the string the Offline certificate signs (kody-qr.md "2. KOD II").
     *
     * The link path without "https://" and without a trailing slash: QR host,
     * /certificate/, context type and value, seller NIP, the Offline
     * certificate serial number and the Base64URL SHA-256 of the invoice file.
     *
     * @param string $hash hash() of the frozen XML.
     */
    public static function certificatePath(string $environment, string $contextType, string $contextValue, string $sellerNip, string $serial, string $hash): string
    {
        if (! in_array($contextType, self::CONTEXT_TYPES, true)) {
            throw new \InvalidArgumentException("Unsupported KSeF context type: {$contextType}"); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer error, never rendered unescaped.
        }

        return sprintf(
            '%s/certificate/%s/%s/%s/%s/%s',
            substr(self::HOSTS[$environment] ?? self::HOSTS['test'], strlen('https://')),
            $contextType,
            $contextValue,
            $sellerNip,
            $serial,
            $hash,
        );
    }

    /**
     * KOD II link: "https://", the signed path, "/" and the signature.
     *
     * RSA keys sign with RSASSA-PSS, SHA-256, MGF1 with SHA-256 and a 32 byte
     * salt; EC keys (P-256) with ECDSA SHA-256 in IEEE P1363 form (R || S,
     * 64 bytes), the format the documentation recommends. Base64URL, no padding.
     *
     * @param string $privateKeyPem Unencrypted key of the Offline certificate (KSeFCredentials::load('offline')).
     */
    public static function certificateUrl(string $environment, string $contextType, string $contextValue, string $sellerNip, string $serial, string $hash, string $privateKeyPem): string
    {
        $path = self::certificatePath($environment, $contextType, $contextValue, $sellerNip, $serial, $hash);

        return 'https://' . $path . '/' . self::base64Url(self::sign($path, $privateKeyPem));
    }

    private static function sign(string $data, string $privateKeyPem): string
    {
        $key = \phpseclib3\Crypt\PublicKeyLoader::loadPrivateKey($privateKeyPem);

        if ($key instanceof \phpseclib3\Crypt\RSA\PrivateKey) {
            $key = $key->withPadding(\phpseclib3\Crypt\RSA::SIGNATURE_PSS)->withHash('sha256')->withMGFHash('sha256')->withSaltLength(32);
        } elseif ($key instanceof \phpseclib3\Crypt\EC\PrivateKey) {
            $key = $key->withHash('sha256')->withSignatureFormat('IEEE');
        } else {
            throw new \InvalidArgumentException('The Offline certificate key must be RSA or EC P-256.');
        }

        return (string) $key->sign($data);
    }

    private static function base64Url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * Whether a stored value is a KSeF number rather than a session reference.
     */
    public static function isKsefNumber(string $value): bool
    {
        return preg_match(self::KSEF_NUMBER, $value) === 1;
    }

    /**
     * The KSeF QR codes an invoice carries, with their captions and side in mm.
     *
     * KOD I once the invoice holds a KSeF number, captioned with it (before
     * that the column holds a session reference). An offline invoice has both
     * codes from the moment it is issued: KOD I captioned OFFLINE until KSeF
     * numbers it, and KOD II captioned CERTYFIKAT. KOD II signed with RSA is
     * about 470 characters, so it gets 40 mm (spec minimum 35).
     *
     * @return list<array{url: string, label: string, size: float}>
     */
    public static function codes(\Polski\KSeF\Model\Invoice $invoice): array
    {
        $number = (string) ($invoice->ksefReference ?? '');
        $url = (string) ($invoice->ksefQrUrl ?? '');
        $cert = (string) ($invoice->ksefCertUrl ?? '');
        $numbered = self::isKsefNumber($number);
        $codes = [];

        if ($url !== '' && ($numbered || $cert !== '')) {
            $codes[] = ['url' => $url, 'label' => $numbered ? $number : 'OFFLINE', 'size' => 30.0];
        }

        if ($url !== '' && $cert !== '') {
            $codes[] = ['url' => $cert, 'label' => 'CERTYFIKAT', 'size' => strlen($cert) > 300 ? 40.0 : 30.0];
        }

        return $codes;
    }

    /** A QR code as inline SVG, for the HTML invoice (the PDF in Polski PRO draws its own). */
    public static function svg(string $data): string
    {
        $options = new \chillerlan\QRCode\QROptions([
            'outputType' => \chillerlan\QRCode\Output\QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M,
            'drawLightModules' => false,
            'connectPaths' => true,
            'svgAddXmlHeader' => false,
        ]);

        return (string) (new \chillerlan\QRCode\QRCode($options))->render($data);
    }
}
