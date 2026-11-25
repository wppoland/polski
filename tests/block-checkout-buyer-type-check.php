<?php

// NOTE: no `declare(strict_types=1)` here on purpose - `wp eval-file` wraps this
// file in eval(), where a declare() can't be the first statement.

/**
 * existing-checked: tests/nip-field-consistency-check.php - that check covers
 * the NIP field's registration and validation; this one runs the "Buying as"
 * rule through WooCommerce's own rule engine, which a unit test cannot load.
 *
 * The block checkout's "Buying as" choice hides and requires the company name
 * and NIP with a JSON-schema rule. WooCommerce evaluates it against a document
 * built from the request, so a rule that reads well can still never match:
 * an empty field list arrives as a JSON array, not an object, and a missed
 * case there required a company name on My Account that the form never showed.
 *
 * Run: wp eval-file tests/block-checkout-buyer-type-check.php
 */

use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFieldsSchema\DocumentObject;
use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFieldsSchema\Validation;
use Polski\Service\B2BCheckoutService;

if (! class_exists(Validation::class) || ! class_exists(DocumentObject::class)) {
    echo "SKIP  WooCommerce checkout field rules not available\n";
    return;
}

$polski_failures = [];
$polski_matches = static function (array $additional, bool $forHidden): bool {
    $document = new DocumentObject(['customer' => ['additional_fields' => $additional]]);
    $document->set_context('contact');

    return true === Validation::validate_document_object($document, B2BCheckoutService::companyOnlyRule($forHidden));
};

$polski_cases = [
    'Company' => [[B2BCheckoutService::BUYER_TYPE_FIELD => 'company'], false],
    'Personal' => [[B2BCheckoutService::BUYER_TYPE_FIELD => 'personal'], true],
    'no choice yet' => [[B2BCheckoutService::BUYER_TYPE_FIELD => ''], true],
];

// My Account builds its document with no field values: show and keep what is
// stored, require nothing.
$polski_none = [
    'no contact fields at all' => [],
];

foreach ($polski_cases as $polski_label => [$polski_fields, $polski_hidden]) {
    if ($polski_matches($polski_fields, true) !== $polski_hidden) {
        $polski_failures[] = "{$polski_label}: company fields should be " . ($polski_hidden ? 'hidden' : 'shown');
    }
    if ($polski_matches($polski_fields, false) === $polski_hidden) {
        $polski_failures[] = "{$polski_label}: company fields should " . ($polski_hidden ? 'not ' : '') . 'be required';
    }
}

foreach ($polski_none as $polski_label => $polski_fields) {
    if ($polski_matches($polski_fields, true) || $polski_matches($polski_fields, false)) {
        $polski_failures[] = "{$polski_label}: company fields should be shown and optional";
    }
}

if ($polski_failures !== []) {
    echo "FAIL  block-checkout-buyer-type\n  - " . implode("\n  - ", $polski_failures) . "\n";
    exit(1);
}

echo "OK: Buying as hides and requires the company fields as WooCommerce reads the rule.\n";
