<?php

// NOTE: no `declare(strict_types=1)` here on purpose - `wp eval-file` wraps this
// file in eval(), where a declare() can't be the first statement.

/**
 * The guest withdrawal form finds an order by its custom order number.
 *
 * The lookup used to pass a `meta_query` to wc_get_orders(). The CPT order
 * datastore drops that argument (it only logs "not supported"), so the query
 * came back unfiltered: any number found the newest order in the shop, and a
 * number that matched nothing still returned an order. The check runs on
 * whichever datastore the site uses.
 *
 * Run: wp eval-file wp-content/plugins/<dir>/tests/withdrawal-order-number-lookup-check.php
 */

$polski_failures = [];
$polski_check = static function (string $label, bool $ok) use (&$polski_failures): void {
    if (! $ok) {
        $polski_failures[] = $label;
    }
};

$polski_number = 'PLK-' . wp_generate_password(8, false);

$polski_match = wc_create_order();
$polski_match->update_meta_data('_sequential_order_number', $polski_number);
$polski_match->set_date_created(time() - DAY_IN_SECONDS);
$polski_match->save();

// A newer order without the number: an unfiltered query returns this one.
$polski_newer = wc_create_order();
$polski_newer->save();

$polski_ref = new ReflectionClass(\Polski\Service\GuestWithdrawalService::class);
$polski_service = $polski_ref->newInstanceWithoutConstructor();
$polski_locate = $polski_ref->getMethod('locateOrder');
$polski_locate->setAccessible(true);

$polski_found = $polski_locate->invoke($polski_service, $polski_number);
$polski_check(
    'custom order number finds its own order',
    $polski_found instanceof WC_Order && $polski_found->get_id() === $polski_match->get_id()
);

$polski_found = $polski_locate->invoke($polski_service, '#' . $polski_number);
$polski_check(
    'leading # is ignored',
    $polski_found instanceof WC_Order && $polski_found->get_id() === $polski_match->get_id()
);

$polski_found = $polski_locate->invoke($polski_service, 'PLK-no-such-order-' . wp_generate_password(8, false));
$polski_check('unknown order number finds nothing', $polski_found === null);

$polski_match->delete(true);
$polski_newer->delete(true);

if ($polski_failures !== []) {
    echo "FAIL  withdrawal-order-number-lookup\n  - " . implode("\n  - ", $polski_failures) . "\n";
    exit(1);
}

echo "ok    withdrawal-order-number-lookup\n";
