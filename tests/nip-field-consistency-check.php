<?php

// NOTE: no `declare(strict_types=1)` here on purpose - `wp eval-file` wraps this
// file in eval(), where a declare() can't be the first statement.

/**
 * The NIP field must exist once, reject nonsense, and mean one number.
 *
 * Reported on the support forum against 1.38.1: letters saved without a word,
 * the field appeared in the shipping address as well as the billing one, and a
 * number entered in My Account had to be typed again at checkout. Each of those
 * is a separate defect and each one is asserted here.
 *
 * Run: wp eval-file tests/nip-field-consistency-check.php
 */

$polski_failures = [];

$polski_check = static function (string $label, bool $ok) use (&$polski_failures): void {
    if (! $ok) {
        $polski_failures[] = $label;
    }
};

// Both modules on: the combination that used to leave the field unregistered.
update_option('polski_modules', array_merge(
    (array) get_option('polski_modules', []),
    ['nip_lookup' => 1, 'b2b_checkout' => 1],
));

if (! class_exists(\Automattic\WooCommerce\Blocks\Package::class)) {
    echo "SKIP  WooCommerce Blocks not available\n";
    return;
}

$polski_fields = \Automattic\WooCommerce\Blocks\Package::container()->get(
    \Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class
);

// Boot may already have registered it; registering twice is a doing_it_wrong.
if (! isset($polski_fields->get_additional_fields()['polski/nip'])) {
    Polski\Plugin::instance()->container()
        ->get(Polski\Service\NipLookupService::class)
        ->registerAdditionalCheckoutFields();
}

$polski_all = $polski_fields->get_additional_fields();
$polski_def = $polski_all['polski/nip'] ?? null;

$polski_check('the NIP field is registered while the B2B module is on', $polski_def !== null);

if ($polski_def === null) {
    echo "FAIL  nip-field-consistency\n  - " . implode("\n  - ", $polski_failures) . "\n";
    exit(1);
}

$polski_check('the field is not an address field', ($polski_def['location'] ?? '') === 'contact');
$polski_check(
    'the field does not render in the billing address form',
    ! isset($polski_fields->get_fields_for_group('billing')['polski/nip']),
);
$polski_check(
    'the field does not render in the shipping address form',
    ! isset($polski_fields->get_fields_for_group('shipping')['polski/nip']),
);

// Sanitising must strip formatting only. Stripping every non-digit turns
// "letters" into "", and "" reads as "left blank", so nonsense passed.
$polski_cases = [
    'abcdef' => true,
    'nie podam' => true,
    '123' => true,
    '12345632190' => true,
    '1234563219' => true,   // bad checksum
    '1234563218' => false,  // valid
    '123-456-32-18' => false,
    '' => false,
];

foreach ($polski_cases as $polski_raw => $polski_should_reject) {
    $polski_clean = $polski_fields->sanitize_field('polski/nip', (string) $polski_raw);
    $polski_result = call_user_func($polski_def['validate_callback'], $polski_clean);
    $polski_check(
        sprintf('"%s" is %s', $polski_raw, $polski_should_reject ? 'rejected' : 'accepted'),
        is_wp_error($polski_result) === $polski_should_reject,
    );
}

// My Account > Addresses: an invalid number must stop the save, and a valid one
// must land in every key the other screens read.
$polski_service = Polski\Plugin::instance()->container()->get(Polski\Service\NipLookupService::class);
$polski_user_id = username_exists('polski_nip_probe') ?: wp_create_user('polski_nip_probe', wp_generate_password(16), 'polski_nip_probe@example.test');

wc_clear_notices();
$polski_service->validateSavedAddress((int) $polski_user_id, 'billing', ['billing_nip' => 'abcdef'], null);
$polski_check('My Account refuses an invalid NIP', wc_notice_count('error') === 1);

wc_clear_notices();
$polski_service->validateSavedAddress((int) $polski_user_id, 'billing', ['billing_nip' => '1234563218'], null);
$polski_check('My Account accepts a valid NIP', wc_notice_count('error') === 0);
wc_clear_notices();

$polski_customer = new WC_Customer((int) $polski_user_id);
$polski_customer->update_meta_data('billing_nip', '1234563218');
$polski_customer->update_meta_data('_wc_billing/polski/nip', '9999999999');
$polski_customer->save();

$polski_service->syncCustomerAfterAddressSave((int) $polski_user_id, 'billing');

$polski_fresh = new WC_Customer((int) $polski_user_id);

foreach (['billing_nip', '_billing_nip', '_polski_billing_nip', '_wc_other/polski/nip'] as $polski_key) {
    $polski_check(
        sprintf('customer meta %s carries the number', $polski_key),
        (string) $polski_fresh->get_meta($polski_key, true) === '1234563218',
    );
}

$polski_check(
    'the stale address-group copy is cleared',
    (string) $polski_fresh->get_meta('_wc_billing/polski/nip', true) === '',
);

wp_delete_user((int) $polski_user_id);

if ($polski_failures !== []) {
    echo "FAIL  nip-field-consistency\n  - " . implode("\n  - ", $polski_failures) . "\n";
    exit(1);
}

echo "OK: NIP field registers once, rejects nonsense, and means one number.\n";
