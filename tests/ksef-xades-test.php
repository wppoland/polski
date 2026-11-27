<?php

declare(strict_types=1);

/**
 * Verifies the XAdES signature we produce, correctly.
 *
 *   php tools/ksef-make-test-cert.php     # once
 *   php tests/ksef-xades-test.php
 *
 * Why this does not just call xmlseclibs' validateReference: xmlseclibs does not
 * implement the enveloped-signature transform. Its processTransforms handles
 * exclusive c14n, inclusive c14n and XPath and nothing else, which is also why
 * it has no ENVELOPED constant. Handed an enveloped signature it never removes
 * the ds:Signature element, canonicalises the document with the signature still
 * in it, and reports the reference as invalid.
 *
 * That produced a genuinely alarming false negative while this signer was being
 * written: the Ministry accepted the document and our own verifier called it
 * broken. The digests below settle it. Reference 0 is checked by doing what the
 * transform says, removing the signature and canonicalising, and reference 1 by
 * canonicalising the SignedProperties node. Both are then compared against the
 * DigestValue actually in the document, and the SignedInfo signature is checked
 * against the certificate's public key.
 */

define('ABSPATH', __DIR__);

const DS_NS = 'http://www.w3.org/2000/09/xmldsig#';

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

if (! is_file($keyFile)) {
    fwrite(STDERR, "Run tools/ksef-make-test-cert.php first.\n");
    exit(1);
}

$failures = 0;
$checks = 0;

function check(string $label, bool $ok): void
{
    global $failures, $checks;
    $checks++;
    echo ($ok ? 'ok    ' : 'FAIL  ') . $label . "\n";
    if (! $ok) {
        $failures++;
    }
}

$plain = AuthTokenRequest::build('20260810-CR-22437FD000-36A7B3A75B-76', '9999999999');
$signed = XadesSigner::sign(
    $plain,
    (string) file_get_contents($keyFile),
    (string) file_get_contents($certFile),
    new DateTimeImmutable('2026-08-10T09:00:00Z'),
);

$doc = new DOMDocument();
$doc->loadXML($signed);
$xp = new DOMXPath($doc);
$xp->registerNamespace('ds', DS_NS);
$xp->registerNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');

// --- Shape ------------------------------------------------------------------

$refs = $xp->query('//ds:SignedInfo/ds:Reference');
check('SignedInfo carries two references', $refs !== false && $refs->length === 2);
check(
    'one reference covers the document and one the signed properties',
    $xp->query('//ds:SignedInfo/ds:Reference[@URI=""]')->length === 1
        && $xp->query('//ds:SignedInfo/ds:Reference[@URI="#polski-ksef-signedprops"]')->length === 1,
);
check(
    'the properties reference declares the XAdES type',
    $xp->query('//ds:Reference[@Type="http://uri.etsi.org/01903#SignedProperties"]')->length === 1,
);
check('SigningTime is present', $xp->query('//xades:SigningTime')->length === 1);
check('SigningCertificateV2 is present', $xp->query('//xades:SigningCertificateV2')->length === 1);
check(
    'QualifyingProperties targets the signature by id',
    $xp->query('//xades:QualifyingProperties[@Target="#polski-ksef-sig"]')->length === 1,
);
check('the certificate travels with the signature', $xp->query('//ds:X509Certificate')->length === 1);

// --- Reference 0: the document, enveloped transform applied by hand ---------

$stripped = new DOMDocument();
$stripped->loadXML($signed);
$sigNode = $stripped->getElementsByTagNameNS(DS_NS, 'Signature')->item(0);
$sigNode?->parentNode?->removeChild($sigNode);

$storedDocDigest = $xp->query('//ds:SignedInfo/ds:Reference[@URI=""]/ds:DigestValue')->item(0)?->nodeValue;
$recomputed = base64_encode(hash('sha256', $stripped->C14N(true, false), true));

check('the document digest matches once the signature is removed', $storedDocDigest === $recomputed);

$originalDoc = new DOMDocument();
$originalDoc->loadXML($plain);
check(
    'and it equals the digest of the unsigned document, so the signature covers what we sent',
    $storedDocDigest === base64_encode(hash('sha256', $originalDoc->C14N(true, false), true)),
);

// --- Reference 1: the signed properties -------------------------------------

$props = $xp->query('//xades:SignedProperties[@Id="polski-ksef-signedprops"]')->item(0);
$storedPropsDigest = $xp
    ->query('//ds:SignedInfo/ds:Reference[@URI="#polski-ksef-signedprops"]/ds:DigestValue')
    ->item(0)?->nodeValue;

check(
    'the signed properties digest matches the node',
    $props instanceof DOMElement
        && $storedPropsDigest === base64_encode(hash('sha256', $props->C14N(true, false), true)),
);

// --- The signature over SignedInfo ------------------------------------------

$signedInfo = $xp->query('//ds:SignedInfo')->item(0);
$signatureValue = (string) $xp->query('//ds:SignatureValue')->item(0)?->nodeValue;
$publicKey = openssl_pkey_get_public((string) file_get_contents($certFile));

check(
    'SignedInfo verifies against the certificate public key',
    $signedInfo instanceof DOMElement
        && openssl_verify(
            $signedInfo->C14N(true, false),
            (string) base64_decode($signatureValue, true),
            $publicKey,
            OPENSSL_ALGO_SHA256,
        ) === 1,
);

// --- Tamper detection -------------------------------------------------------

$tampered = str_replace('<Nip>9999999999</Nip>', '<Nip>1111111128</Nip>', $signed);
$t = new DOMDocument();
$t->loadXML($tampered);
$tSig = $t->getElementsByTagNameNS(DS_NS, 'Signature')->item(0);
$tSig?->parentNode?->removeChild($tSig);

check(
    'changing the NIP breaks the document digest',
    $storedDocDigest !== base64_encode(hash('sha256', $t->C14N(true, false), true)),
);

$tamperedTime = str_replace('2026-08-10T09:00:00Z', '2026-08-10T10:00:00Z', $signed);
$tt = new DOMDocument();
$tt->loadXML($tamperedTime);
$ttXp = new DOMXPath($tt);
$ttXp->registerNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');
$ttProps = $ttXp->query('//xades:SignedProperties[@Id="polski-ksef-signedprops"]')->item(0);

check(
    'changing the signing time breaks the properties digest, so the XAdES half is really covered',
    $ttProps instanceof DOMElement
        && $storedPropsDigest !== base64_encode(hash('sha256', $ttProps->C14N(true, false), true)),
);

echo "\n{$checks} checks, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
