<?php

declare(strict_types=1);

namespace Polski\KSeF\V2;

defined('ABSPATH') || exit;

use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

/**
 * Signs the AuthTokenRequest with an enveloped XAdES-BES signature.
 *
 * KSeF accepts every XAdES level from BES upwards but rejects detached
 * signatures, so this produces the smallest thing it will take: an enveloped
 * signature carrying the qualifying properties BES requires.
 *
 * xmlseclibs gives the XML-DSig half, which is the part worth not writing by
 * hand: canonicalisation, the digest over the transformed document, and the
 * SignedInfo signature. XAdES itself is a second Reference pointing at a
 * SignedProperties block, and that block is assembled here because no
 * maintained PHP library does it.
 *
 * Choices, all within what the Ministry documents as acceptable:
 * exclusive canonicalisation, SHA-256 digests, RSA PKCS#1 v1.5 with SHA-256,
 * minimum 2048 bit key.
 */
final class XadesSigner
{
    private const XADES_NS = 'http://uri.etsi.org/01903/v1.3.2#';
    private const DS_NS = 'http://www.w3.org/2000/09/xmldsig#';
    private const SIG_ID = 'polski-ksef-sig';
    private const PROPS_ID = 'polski-ksef-signedprops';

    /** xmlseclibs takes transforms as raw URIs and defines no constant for this one. */
    private const ENVELOPED = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';

    /**
     * @param string $xml            The AuthTokenRequest document.
     * @param string $privateKeyPem  Signing key, 2048 bit RSA or better.
     * @param string $certificatePem Matching certificate, PEM.
     * @param \DateTimeImmutable|null $signingTime Defaults to now, injectable so tests are deterministic.
     */
    public static function sign(
        string $xml,
        string $privateKeyPem,
        string $certificatePem,
        ?\DateTimeImmutable $signingTime = null,
    ): string {
        $doc = new \DOMDocument();
        $doc->preserveWhiteSpace = true;
        $doc->formatOutput = false;

        if ($doc->loadXML($xml) === false || $doc->documentElement === null) {
            throw new \InvalidArgumentException('The document to sign is not well formed XML.');
        }

        $signingTime ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $dsig = new XMLSecurityDSig();
        $dsig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);

        // Reference 1: the document itself, with the enveloped transform so the
        // signature does not try to digest itself.
        $dsig->addReference(
            $doc,
            XMLSecurityDSig::SHA256,
            [self::ENVELOPED, XMLSecurityDSig::EXC_C14N],
            ['force_uri' => true],
        );

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($privateKeyPem, false);

        // The signature element has to exist before the XAdES object can be
        // hung off it, so append first and fill in afterwards.
        $dsig->sign($key);
        $dsig->add509Cert(self::stripPem($certificatePem), false, false);
        $dsig->appendSignature($doc->documentElement);

        $signature = self::signatureElement($doc);
        $signature->setAttribute('Id', self::SIG_ID);

        self::appendQualifyingProperties($doc, $signature, $certificatePem, $signingTime);
        self::addSignedPropertiesReference($doc, $signature, $key);

        return (string) $doc->saveXML();
    }

    private static function signatureElement(\DOMDocument $doc): \DOMElement
    {
        $nodes = $doc->getElementsByTagNameNS(self::DS_NS, 'Signature');
        $signature = $nodes->item(0);

        if (! $signature instanceof \DOMElement) {
            throw new \RuntimeException('The signature element was not produced.');
        }

        return $signature;
    }

    /**
     * The XAdES half: SigningTime and SigningCertificateV2, which is what makes
     * this BES rather than plain XML-DSig.
     */
    private static function appendQualifyingProperties(
        \DOMDocument $doc,
        \DOMElement $signature,
        string $certificatePem,
        \DateTimeImmutable $signingTime,
    ): void {
        $object = $doc->createElementNS(self::DS_NS, 'ds:Object');
        $signature->appendChild($object);

        $qualifying = $doc->createElementNS(self::XADES_NS, 'xades:QualifyingProperties');
        $qualifying->setAttribute('Target', '#' . self::SIG_ID);
        $object->appendChild($qualifying);

        $signed = $doc->createElementNS(self::XADES_NS, 'xades:SignedProperties');
        $signed->setAttribute('Id', self::PROPS_ID);
        $qualifying->appendChild($signed);

        $sigProps = $doc->createElementNS(self::XADES_NS, 'xades:SignedSignatureProperties');
        $signed->appendChild($sigProps);

        $sigProps->appendChild(
            $doc->createElementNS(self::XADES_NS, 'xades:SigningTime', $signingTime->format('Y-m-d\TH:i:s\Z')),
        );

        $der = (string) base64_decode(self::stripPem($certificatePem), true);

        $signingCert = $doc->createElementNS(self::XADES_NS, 'xades:SigningCertificateV2');
        $sigProps->appendChild($signingCert);

        $cert = $doc->createElementNS(self::XADES_NS, 'xades:Cert');
        $signingCert->appendChild($cert);

        $certDigest = $doc->createElementNS(self::XADES_NS, 'xades:CertDigest');
        $cert->appendChild($certDigest);

        $method = $doc->createElementNS(self::DS_NS, 'ds:DigestMethod');
        $method->setAttribute('Algorithm', XMLSecurityDSig::SHA256);
        $certDigest->appendChild($method);

        $certDigest->appendChild(
            $doc->createElementNS(self::DS_NS, 'ds:DigestValue', base64_encode(hash('sha256', $der, true))),
        );
    }

    /**
     * Reference 2: the SignedProperties block, digested and covered by the same
     * signature. Without it the qualifying properties would be decoration that
     * anybody could rewrite.
     */
    private static function addSignedPropertiesReference(
        \DOMDocument $doc,
        \DOMElement $signature,
        XMLSecurityKey $key,
    ): void {
        $signedInfo = $doc->getElementsByTagNameNS(self::DS_NS, 'SignedInfo')->item(0);
        $properties = $doc->getElementById(self::PROPS_ID)
            ?? self::findById($doc, self::PROPS_ID);

        if (! $signedInfo instanceof \DOMElement || ! $properties instanceof \DOMElement) {
            throw new \RuntimeException('Could not attach the SignedProperties reference.');
        }

        $canonical = $properties->C14N(true, false);

        $reference = $doc->createElementNS(self::DS_NS, 'ds:Reference');
        $reference->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');
        $reference->setAttribute('URI', '#' . self::PROPS_ID);
        $signedInfo->appendChild($reference);

        $transforms = $doc->createElementNS(self::DS_NS, 'ds:Transforms');
        $reference->appendChild($transforms);
        $transform = $doc->createElementNS(self::DS_NS, 'ds:Transform');
        $transform->setAttribute('Algorithm', XMLSecurityDSig::EXC_C14N);
        $transforms->appendChild($transform);

        $digestMethod = $doc->createElementNS(self::DS_NS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', XMLSecurityDSig::SHA256);
        $reference->appendChild($digestMethod);

        $reference->appendChild(
            $doc->createElementNS(self::DS_NS, 'ds:DigestValue', base64_encode(hash('sha256', $canonical, true))),
        );

        // SignedInfo changed, so the signature over it has to be recomputed.
        $signedInfoCanonical = $signedInfo->C14N(true, false);
        $signatureValue = $doc->getElementsByTagNameNS(self::DS_NS, 'SignatureValue')->item(0);

        if (! $signatureValue instanceof \DOMElement) {
            throw new \RuntimeException('No SignatureValue element to update.');
        }

        $signatureValue->nodeValue = base64_encode($key->signData($signedInfoCanonical));
    }

    private static function findById(\DOMDocument $doc, string $id): ?\DOMElement
    {
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('xades', self::XADES_NS);
        $found = $xpath->query('//xades:SignedProperties[@Id="' . $id . '"]');

        if ($found === false) {
            return null;
        }

        $node = $found->item(0);

        return $node instanceof \DOMElement ? $node : null;
    }

    private static function stripPem(string $pem): string
    {
        return trim((string) preg_replace('/-----[A-Z ]+-----|\s+/', '', $pem));
    }
}
