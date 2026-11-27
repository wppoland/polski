<?php

declare(strict_types=1);

/**
 * Which KSeF number a buyer sees in e-mails and My Account.
 *
 *   php tests/ksef-number-for-buyer-test.php
 *
 * From 1 January 2027 a VAT payer puts the KSeF number in the transfer title
 * (art. 108a and 108g of the Polish VAT act). Only a production number may
 * reach the buyer; a session reference or a test-register number may not.
 */

define('ABSPATH', __DIR__);

$root = dirname(__DIR__) . '/';
require $root . 'src/KSeF/V2/VerificationLink.php';
require $root . 'src/KSeF/Model/InvoiceType.php';
require $root . 'src/KSeF/Model/InvoiceStatus.php';
require $root . 'src/KSeF/Model/Invoice.php';

use Polski\KSeF\Model\Invoice;
use Polski\KSeF\Model\InvoiceStatus;
use Polski\KSeF\Model\InvoiceType;

$failures = 0;

function invoice(?string $reference, ?string $environment): Invoice
{
    $now = new DateTimeImmutable('2027-01-04');

    return new Invoice(
        id: 1, orderId: 10, originalInvoiceId: null, sourceRefundId: null, type: InvoiceType::FakturaVAT, number: 'FV/1/2027',
        status: InvoiceStatus::cases()[0], nipSeller: '5260250274', nipBuyer: '7393037445',
        correctionReason: null, netTotal: 100.0, vatTotal: 23.0, grossTotal: 123.0, currency: 'PLN',
        pdfPath: null, ksefReference: $reference, ksefStatus: 'accepted', issuedAt: $now, createdAt: $now,
        ksefEnvironment: $environment,
    );
}

$number = '9999999999-20260926-41D674000000-86';
$cases = [
    'production number is shown' => [invoice($number, 'production'), $number],
    'test-register number is hidden' => [invoice($number, 'test'), null],
    'session reference is hidden' => [invoice('20260926-SE-1234567890-ABCDEF1234-01', 'production'), null],
    'no reference is hidden' => [invoice(null, 'production'), null],
];

foreach ($cases as $label => [$invoice, $expected]) {
    $ok = $invoice->customerKsefNumber() === $expected;
    echo ($ok ? 'ok    ' : 'FAIL  ') . $label . "\n";
    $failures += $ok ? 0 : 1;
}

exit($failures === 0 ? 0 : 1);
