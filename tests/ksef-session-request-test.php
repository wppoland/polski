<?php

declare(strict_types=1);

/**
 * Shapes of the interactive session bodies.
 *
 *   php tests/ksef-session-request-test.php
 *
 * These are the payloads the Ministry answers with a bare 400 when a field name
 * is wrong, so they are pinned here rather than discovered on the wire. The
 * live proof that they are accepted is tools/ksef-session-e2e.php, which opened
 * a real session (HTTP 201) and closed it (204) on the test environment.
 */

define('ABSPATH', __DIR__);

$root = dirname(__DIR__) . '/';
require $root . 'vendor/phpseclib/phpseclib/phpseclib/bootstrap.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (! str_starts_with($class, 'phpseclib3\\')) {
        return;
    }
    $path = $root . 'vendor/phpseclib/phpseclib/phpseclib/'
        . str_replace('\\', '/', substr($class, strlen('phpseclib3\\'))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
require $root . 'src/KSeF/V2/KSeFCrypto.php';
require $root . 'src/KSeF/V2/SessionRequest.php';

use Polski\KSeF\V2\KSeFCrypto;
use Polski\KSeF\V2\SessionRequest;

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

// --- Opening a session ------------------------------------------------------

$material = KSeFCrypto::newSymmetricKey();
$body = SessionRequest::openOnline('WRAPPED==', $material['iv'], str_repeat('k', 44), 'FA(2)');

check('formCode carries the three fields the schema requires', array_keys($body['formCode']) === ['systemCode', 'schemaVersion', 'value']);
check('FA(2) maps to the documented triple', $body['formCode'] === ['systemCode' => 'FA (2)', 'schemaVersion' => '1-0E', 'value' => 'FA']);
check('FA(3) is available too', SessionRequest::openOnline('X', $material['iv'], 'k', 'FA(3)')['formCode']['systemCode'] === 'FA (3)');
check(
    'encryption carries exactly the three documented keys',
    array_keys($body['encryption']) === ['encryptedSymmetricKey', 'initializationVector', 'publicKeyId'],
);
check('the IV is base64 of sixteen raw bytes', strlen((string) base64_decode($body['encryption']['initializationVector'], true)) === 16);
check('the whole body is JSON serialisable', json_encode($body) !== false);

$rejects = static function (callable $fn): bool {
    try {
        $fn();
        return false;
    } catch (InvalidArgumentException) {
        return true;
    }
};

check('an unknown form code is refused', $rejects(static fn () => SessionRequest::openOnline('X', $material['iv'], 'k', 'FA(9)')));
check('a short initialisation vector is refused', $rejects(static fn () => SessionRequest::openOnline('X', 'too short', 'k')));

// --- Sending an invoice -----------------------------------------------------

$xml = '<?xml version="1.0" encoding="UTF-8"?><Faktura><P_1>2026-08-10</P_1></Faktura>';
$encrypted = KSeFCrypto::encryptInvoice($xml, $material['key'], $material['iv']);
$send = SessionRequest::sendInvoice($xml, $encrypted);

check(
    'the send body carries all six documented fields',
    array_keys($send) === ['invoiceHash', 'invoiceSize', 'encryptedInvoiceHash', 'encryptedInvoiceSize', 'encryptedInvoiceContent', 'offlineMode'],
);
check('invoiceSize is the plaintext byte count', $send['invoiceSize'] === strlen($xml));
check('encryptedInvoiceSize is the ciphertext byte count, not the base64 length', $send['encryptedInvoiceSize'] === strlen($encrypted));
check('the two sizes differ, so nothing measured the wrong buffer', $send['invoiceSize'] !== $send['encryptedInvoiceSize']);
check('invoiceHash is the base64 SHA-256 of the plaintext', $send['invoiceHash'] === base64_encode(hash('sha256', $xml, true)));
check('encryptedInvoiceHash is taken over the ciphertext, not the base64', $send['encryptedInvoiceHash'] === base64_encode(hash('sha256', $encrypted, true)));
check('the content is base64 of the ciphertext', base64_decode($send['encryptedInvoiceContent'], true) === $encrypted);
check(
    'the ciphertext decrypts back to the invoice, so the session key really opens it',
    openssl_decrypt($encrypted, 'aes-256-cbc', $material['key'], OPENSSL_RAW_DATA, $material['iv']) === $xml,
);
check('offlineMode defaults to false', $send['offlineMode'] === false);
check('no hashOfCorrectedInvoice key unless one is given', ! array_key_exists('hashOfCorrectedInvoice', $send)
    && ! array_key_exists('hashOfCorrectedInvoice', SessionRequest::sendInvoice($xml, $encrypted, true, '')));

// --- Offline and technical correction (tryby-offline.md, offline/korekta-techniczna.md)

$offline = SessionRequest::sendInvoice($xml, $encrypted, true);
check('offlineMode true is sent as a JSON boolean', $offline['offlineMode'] === true && str_contains((string) json_encode($offline), '"offlineMode":true'));
$rejectedHash = KSeFCrypto::hash('<Faktura>rejected</Faktura>');
$correction = SessionRequest::sendInvoice($xml, $encrypted, true, $rejectedHash);
check('a technical correction carries hashOfCorrectedInvoice', ($correction['hashOfCorrectedInvoice'] ?? null) === $rejectedHash);
check('hashOfCorrectedInvoice is plain Base64 of 32 bytes, not Base64URL', preg_match('#^[A-Za-z0-9+/]{43}=$#', $rejectedHash) === 1
    && strlen((string) base64_decode($rejectedHash, true)) === 32);
check('the corrected document hash is still its own', $correction['invoiceHash'] === base64_encode(hash('sha256', $xml, true)) && $correction['invoiceHash'] !== $rejectedHash);

echo "\n{$checks} checks, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
