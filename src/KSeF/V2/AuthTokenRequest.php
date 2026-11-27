<?php

declare(strict_types=1);

namespace Polski\KSeF\V2;

defined('ABSPATH') || exit;

/**
 * Builds the AuthTokenRequest document that POST /auth/xades-signature expects.
 *
 * Schema: http://ksef.mf.gov.pl/auth/token/2.1, published as
 * auth/schemy/schemat_auth_v2-1.xsd in the Ministry's ksef-api repository. The
 * element order below is the schema's xsd:sequence and is not negotiable: a
 * reordered document fails validation rather than being tolerated.
 *
 * The document is signed with XAdES afterwards and posted as the raw signed XML.
 * On the test environment the Ministry accepts a self-signed certificate as the
 * equivalent of a qualified one, which is what makes this path testable without
 * anybody's real identity.
 */
final class AuthTokenRequest
{
    public const NAMESPACE_URI = 'http://ksef.mf.gov.pl/auth/token/2.1';

    /** The challenge format the schema pins, checked before we build. */
    private const CHALLENGE_PATTERN = '/^\d{8}-CR-[A-F0-9]{10}-[A-F0-9]{10}-[A-F0-9]{2}$/';

    /** NIP as the schema defines it: ten digits, and not all of 2 and 3 zero. */
    private const NIP_PATTERN = '/^[1-9]((\d[1-9])|([1-9]\d))\d{7}$/';

    /**
     * How the signing certificate is matched to a KSeF identity.
     *
     * certificateSubject matches on the subject fields, certificateFingerprint
     * on the certificate thumbprint. Anything else is rejected by the schema.
     */
    public const SUBJECT_TYPES = ['certificateSubject', 'certificateFingerprint'];

    /**
     * @param string $challenge Value from POST /auth/challenge, used verbatim.
     * @param string $nip       Ten digit context NIP.
     * @param string $subjectIdentifierType One of SUBJECT_TYPES.
     */
    public static function build(
        string $challenge,
        string $nip,
        string $subjectIdentifierType = 'certificateSubject',
    ): string {
        if (preg_match(self::CHALLENGE_PATTERN, $challenge) !== 1) {
            throw new \InvalidArgumentException(
                'The challenge does not match the format KSeF issues; pass the value from /auth/challenge unchanged.',
            );
        }

        if (preg_match(self::NIP_PATTERN, $nip) !== 1) {
            throw new \InvalidArgumentException('The context NIP is not in the form the auth schema accepts.');
        }

        if (! in_array($subjectIdentifierType, self::SUBJECT_TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported subject identifier type.');
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $root = $dom->createElementNS(self::NAMESPACE_URI, 'AuthTokenRequest');
        $dom->appendChild($root);

        $root->appendChild($dom->createElementNS(self::NAMESPACE_URI, 'Challenge', $challenge));

        $context = $dom->createElementNS(self::NAMESPACE_URI, 'ContextIdentifier');
        $context->appendChild($dom->createElementNS(self::NAMESPACE_URI, 'Nip', $nip));
        $root->appendChild($context);

        $root->appendChild(
            $dom->createElementNS(self::NAMESPACE_URI, 'SubjectIdentifierType', $subjectIdentifierType),
        );

        // AuthorizationPolicy is optional and deliberately omitted. It pins the
        // access token to a list of IP addresses, which is a hardening step a
        // shop should opt into knowingly rather than something a plugin decides
        // on its behalf, and a wrong entry locks the shop out of its own token.

        return (string) $dom->saveXML();
    }
}
