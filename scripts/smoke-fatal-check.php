<?php

// NOTE: no `declare(strict_types=1)` here on purpose - `wp eval-file` wraps this
// file in eval(), where a declare() can't be the first statement.

/**
 * Runtime fatal smoke test. Run inside wp-env with the plugin active:
 *
 *   npx wp-env run cli wp eval-file scripts/smoke-fatal-check.php
 *
 * It exercises the code paths that have historically thrown a *runtime* fatal
 * that static analysis (phpstan) does NOT catch - notably the 1.22.4 regression
 * where WithdrawalQueryHelper was hooked to `woocommerce_order_query` with a
 * strict `array` type hint and fataled the moment any code ran a paginated order
 * query (the WooCommerce admin Orders screen). Each check catches Throwable, so a
 * TypeError of that class fails the release instead of reaching production.
 *
 * Exit code is non-zero if any check fails - wire it into the release preflight.
 */

$failures = [];

$check = static function (string $name, callable $fn) use (&$failures): void {
    try {
        $fn();
        echo "PASS  {$name}\n";
    } catch (\Throwable $e) {
        $msg = get_class($e) . ': ' . $e->getMessage();
        $failures[] = "{$name} -> {$msg}";
        echo "FAIL  {$name} -> {$msg}\n";
    }
};

if (! function_exists('wc_get_orders')) {
    fwrite(STDERR, "WooCommerce not active - cannot smoke test.\n");
    exit(2);
}

// 1. The exact class of bug that hit production: a paginated order query runs
//    every `woocommerce_order_query` filter (incl. WithdrawalQueryHelper) and
//    returns a stdClass, not an array.
$check('wc_get_orders(paginate=true)', static function (): void {
    wc_get_orders(['paginate' => true, 'limit' => 5]);
});
$check('wc_get_orders(plain array)', static function (): void {
    wc_get_orders(['limit' => 5]);
});
// 2. Same path, but with our custom query args active (forces the filter body).
$check('wc_get_orders(paginate + polski_has_withdrawal)', static function (): void {
    wc_get_orders(['paginate' => true, 'limit' => 5, 'polski_has_withdrawal' => true]);
});
$check('wc_get_orders(polski_withdrawal_status)', static function (): void {
    wc_get_orders(['limit' => 5, 'polski_withdrawal_status' => 'requested']);
});

// 3. Render the admin pages - catches render-time fatals (output buffered).
$check('ModulesPage::render()', static function (): void {
    $page = \Polski\Plugin::instance()->container()->get(\Polski\Admin\ModulesPage::class);
    ob_start();
    try {
        $page->render();
    } finally {
        ob_end_clean();
    }
});
$check('SetupWizard::render()', static function (): void {
    $page = \Polski\Plugin::instance()->container()->get(\Polski\Admin\SetupWizard::class);
    ob_start();
    try {
        $page->render();
    } finally {
        ob_end_clean();
    }
});

if ($failures !== []) {
    fwrite(STDERR, "\nSMOKE FAILED (" . count($failures) . "):\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "\nSMOKE OK - no runtime fatals.\n";
exit(0);
