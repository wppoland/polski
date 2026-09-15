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

if (! \Polski\Admin\ModulesPage::isModuleEnabled('nip_lookup')) {
    fwrite(STDERR, "Module nip_lookup is off - enable it before running this check.\n");
    exit(2);
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

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo "FAIL  {$failure}\n";
    }

    fwrite(STDERR, "\nDuplicate fields on the edit-address form: " . count($failures) . "\n");
    exit(1);
}

echo "PASS  no field renders twice on the edit-address form\n";
