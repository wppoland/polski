<?php

declare(strict_types=1);

/**
 * AuthTokenRequest checks, validated against the Ministry's own XSD.
 *
 * Run with `php tests/ksef-auth-request-test.php`. No network: the schema is
 * vendored at tests/fixtures/ksef/schemat_auth_v2-1.xsd, taken verbatim from
 * https://github.com/CIRFMF/ksef-api. Refresh that copy if the Ministry
 * publishes a new revision, and expect these tests to tell you what moved.
 *
 * Validating against the real schema rather than against our own expectations
 * is the point. It catches the failure this integration is most likely to hit:
 * a document that looks right, posts fine, and is rejected server side for an
 * element order or a pattern nobody re-read.
 */

define('ABSPATH', __DIR__);

require __DIR__ . '/../src/KSeF/V2/AuthTokenRequest.php';

use Polski\KSeF\V2\AuthTokenRequest;

const XSD = __DIR__ . '/fixtures/ksef/schemat_auth_v2-1.xsd';

/** A real challenge, captured from POST /auth/challenge on 2026-08-10. */
const CHALLENGE = '20260810-CR-22437FD000-36A7B3A75B-76';

$failures = 0;
$checks = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    if ($ok) {
        echo "ok    {$label}\n";
        return;
    }
    $failures++;
    echo "FAIL  {$label}" . ($detail !== '' ? "  ({$detail})" : '') . "\n";
}

function validates(string $xml): array
{
    libxml_clear_errors();
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $ok = $dom->schemaValidate(XSD);
    $messages = array_map(static fn ($e) => trim($e->message), libxml_get_errors());
    libxml_clear_errors();

    return [$ok, implode('; ', $messages)];
}

// --- The happy path ---------------------------------------------------------

$xml = AuthTokenRequest::build(CHALLENGE, '9999999999');
[$ok, $why] = validates($xml);
check('a built document validates against the official schema', $ok, $why);

check(
    'the namespace is the one the schema declares',
    str_contains($xml, 'xmlns="http://ksef.mf.gov.pl/auth/token/2.1"'),
);
check('the challenge is carried through unchanged', str_contains($xml, '<Challenge>' . CHALLENGE . '</Challenge>'));
check('the NIP sits inside ContextIdentifier', str_contains($xml, '<ContextIdentifier><Nip>9999999999</Nip></ContextIdentifier>'));
check(
    'element order follows the schema sequence',
    (bool) preg_match('/Challenge>.*ContextIdentifier>.*SubjectIdentifierType>/s', $xml),
);
check(
    'AuthorizationPolicy is omitted, so no IP allow list is imposed on the shop',
    ! str_contains($xml, 'AuthorizationPolicy'),
);

// --- The other subject type -------------------------------------------------

[$ok, $why] = validates(AuthTokenRequest::build(CHALLENGE, '9999999999', 'certificateFingerprint'));
check('certificateFingerprint is accepted by the schema too', $ok, $why);

// --- Rejections, before anything reaches the Ministry -----------------------

$rejects = static function (callable $fn): bool {
    try {
        $fn();
        return false;
    } catch (InvalidArgumentException) {
        return true;
    }
};

check('a malformed challenge is refused', $rejects(static fn () => AuthTokenRequest::build('NOT-A-CHALLENGE', '9999999999')));
check(
    'a challenge with lowercase hex is refused, since the schema demands A-F',
    $rejects(static fn () => AuthTokenRequest::build('20260810-cr-22437fd000-36a7b3a75b-76', '9999999999')),
);
check('a NIP starting with zero is refused', $rejects(static fn () => AuthTokenRequest::build(CHALLENGE, '0123456789')));
check('a nine digit NIP is refused', $rejects(static fn () => AuthTokenRequest::build(CHALLENGE, '999999999')));
check('a NIP with separators is refused', $rejects(static fn () => AuthTokenRequest::build(CHALLENGE, '999-999-99-99')));
check('an unknown subject identifier type is refused', $rejects(static fn () => AuthTokenRequest::build(CHALLENGE, '9999999999', 'whatever')));

// --- The guard earns its keep ----------------------------------------------
// If the validation were dropped, this is what would go to the Ministry.

$handBuilt = str_replace('<Nip>9999999999</Nip>', '<Nip>0123456789</Nip>', $xml);
[$ok] = validates($handBuilt);
check('proof the NIP pattern matters: a leading zero fails the schema', ! $ok);

echo "\n{$checks} checks, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
