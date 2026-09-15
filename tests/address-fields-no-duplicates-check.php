<?php

// NOTE: no `declare(strict_types=1)` here on purpose - `wp eval-file` wraps this
// file in eval(), where a declare() can't be the first statement.

/**
 * My Account > Addresses must not show the same field twice.
 *
 * A field can reach that form two ways: the classic `woocommerce_billing_fields`
 * filter, and an additional checkout field registered with `location: address`,
 * which WooCommerce itself appends through `woocommerce_address_to_edit`. Both
 * registrations are needed (classic checkout renders only the first, block
 * checkout only the second), and nothing in WooCommerce notices they are the
 * same field. That is what put two NIP inputs on one form in 1.37.9.
 *
 * Run inside wp-env with the module switched on:
 *
 *   npx wp-env run cli wp option patch insert polski_modules nip_lookup 1
 *   npx wp-env run cli wp eval-file tests/address-fields-no-duplicates-check.php
 *
 * Exit code is non-zero when a duplicate survives - wire it into preflight.
 */

if (! function_exists('WC') || ! WC()->countries) {
    fwrite(STDERR, "WooCommerce not active - cannot check the address form.\n");
    exit(2);
}

foreach (['nip_lookup', 'b2b_checkout', 'custom_checkout_fields', 'withdrawal'] as $module) {
    if (! \Polski\Admin\ModulesPage::isModuleEnabled($module)) {
        fwrite(STDERR, "Module {$module} is off - enable it before running this check.\n");
        exit(2);
    }
}

$failures = [];

foreach (['billing', 'shipping'] as $addressType) {
    $fields = WC()->countries->get_address_fields('PL', $addressType . '_');
    /** @var array<string, array<string, mixed>> $fields */
    $fields = apply_filters('woocommerce_address_to_edit', $fields, $addressType);

    // Two keys are the same field when the tail of one is the other's id, e.g.
    // `billing_nip` and `_wc_billing/polski/nip`.
    $ids = [];

    foreach (array_keys($fields) as $key) {
        $id = strtolower((string) $key);
        $id = (string) preg_replace('#^_wc_(billing|shipping|other)/#', '', $id);
        $id = (string) preg_replace('#^(polski/|' . $addressType . '_)#', '', $id);
        $id = str_replace('/', '_', $id);

        $ids[$id][] = $key;
    }

    foreach ($ids as $id => $keys) {
        if (count($keys) > 1) {
            $failures[] = "{$addressType}: '{$id}' rendered " . count($keys) . ' times (' . implode(', ', $keys) . ')';
        }
    }

    echo "checked {$addressType}: " . count($fields) . " fields\n";
}

// The live form above only proves what this shop's module mix happens to
// render: with the B2B module on, for instance, the NIP additional field is not
// registered at all. Exercise the dedupe itself on a synthetic form so the
// check cannot pass by accident.
$synthetic = [
    'billing_nip' => [],
    'billing_regon' => [],
    'billing_iban' => [],
    '_wc_billing/polski/nip' => [],
    '_wc_billing/polski/regon' => [],
    '_wc_billing/polski/iban' => [],
    '_wc_billing/polski/unrelated' => [],
];

$deduped = apply_filters('woocommerce_address_to_edit', $synthetic, 'billing');

foreach (['_wc_billing/polski/nip', '_wc_billing/polski/regon', '_wc_billing/polski/iban'] as $shouldGo) {
    if (isset($deduped[$shouldGo])) {
        $failures[] = "dedupe: '{$shouldGo}' survived next to its classic twin";
    }
}

// A field with no classic twin must be left alone, or the block checkout would
// lose the only copy it has.
if (! isset($deduped['_wc_billing/polski/unrelated'])) {
    $failures[] = "dedupe: removed '_wc_billing/polski/unrelated', which has no classic twin";
}

echo "checked dedupe on a synthetic form: 4\n";

// The other half of the same defect: a module that registers both ways must
// keep the CLASSIC one. WooCommerce renders additional fields on the block
// checkout, the order confirmation and My Account, never on the shortcode
// checkout, and three services used to drop their classic path the moment the
// additional-fields API existed, which emptied the classic checkout.
$classic = apply_filters('woocommerce_billing_fields', WC()->countries->get_address_fields('PL', 'billing_'));

// Only the fields that do not depend on a per-shop setting: REGON and IBAN are
// optional in the B2B module, so their absence here says nothing.
foreach (['billing_nip', 'polski_buying_as_company'] as $expected) {
    if (! isset($classic[$expected])) {
        $failures[] = "classic checkout: '{$expected}' missing from woocommerce_billing_fields";
    }
}

$classicOwners = [
    'woocommerce_checkout_fields' => \Polski\Service\CustomCheckoutFieldsService::class,
    'woocommerce_review_order_before_submit' => \Polski\Service\DigitalConsentService::class,
    'woocommerce_checkout_process' => \Polski\Service\DigitalConsentService::class,
];

foreach ($classicOwners as $hook => $owner) {
    $found = false;

    foreach ($GLOBALS['wp_filter'][$hook]->callbacks ?? [] as $callbacks) {
        foreach ($callbacks as $callback) {
            $object = $callback['function'][0] ?? null;

            if ($object instanceof $owner) {
                $found = true;
                break 2;
            }
        }
    }

    if (! $found) {
        $failures[] = "classic checkout: {$owner} has no callback on {$hook}";
    }
}

echo 'checked classic registrations: ' . (count($classicOwners) + 2) . "\n";

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo "FAIL  {$failure}\n";
    }

    fwrite(STDERR, "\nAddress/checkout field checks failed: " . count($failures) . "\n");
    exit(1);
}

echo "PASS  no duplicate on the edit-address form, classic checkout registrations intact\n";
