<?php
/**
 * Fails when a wc_get_products() call asks for products by 'post__in'.
 *
 * 'post__in' is a WP_Query var, not a product query var, and the product query
 * does not pass it through. WC_Product_Data_Store_CPT::get_wp_query_args()
 * copies the 'include' query var over 'post__in' before WP_Query sees either,
 * and WC_Product_Query::get_default_query_vars() sets 'include' to an empty
 * array, so a 'post__in' handed to wc_get_products() is overwritten with
 * nothing and then dropped as an empty value. The query runs, returns products,
 * and honours only the remaining args: with a 'limit' it hands back that many
 * of the newest products instead of the ones that were asked for.
 *
 * That is silent. It cost the stock CSV export a release: batches of 200 were
 * hydrated by 'post__in', and every batch after the first wrote the same newest
 * 200 products into the file. A catalogue that fits in one batch cannot show it,
 * which is why a live run on 64 products passed.
 *
 * The unit test in tests/Unit/Service/UnboundedQueryBatchingTest.php pins the
 * export itself. This check covers the rest of src/, where there is no test.
 *
 * Usage: php tests/product-query-args-check.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$call = 'wc_get_products(';
$calls = 0;
$bad = [];

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src'));

foreach ($files as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $src = (string) file_get_contents($file->getPathname());
    $offset = 0;

    while (($pos = strpos($src, $call, $offset)) !== false) {
        $offset = $pos + strlen($call);

        // function_exists('wc_get_products') and the like are not calls.
        $before = substr($src, max(0, $pos - 16), min(16, $pos));

        if (str_contains($before, "'") || str_contains($before, '"')) {
            continue;
        }

        ++$calls;

        // Read to the matching closing parenthesis, so only this call's own
        // arguments are inspected.
        $depth = 1;
        $i = $offset;
        $len = strlen($src);

        while ($i < $len && $depth > 0) {
            $depth += match ($src[$i]) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };
            ++$i;
        }

        $args = substr($src, $offset, $i - $offset);

        if (preg_match("/['\"]post__in['\"]/", $args) === 1) {
            $line = substr_count(substr($src, 0, $pos), "\n") + 1;
            $bad[] = str_replace($root . '/', '', $file->getPathname()) . ':' . $line;
        }
    }
}

if ($calls === 0) {
    fwrite(STDERR, "FAIL: found no wc_get_products() calls in src/, the check is not reading what it thinks it is.\n");
    exit(1);
}

if ($bad !== []) {
    fwrite(STDERR, "FAIL: wc_get_products() called with 'post__in', which the product query drops:\n");

    foreach ($bad as $where) {
        fwrite(STDERR, "  - {$where}\n");
    }

    fwrite(STDERR, "\nUse 'include' => \$ids. 'post__in' is silently replaced by the empty 'include'\n");
    fwrite(STDERR, "default, and the query then returns the newest products the limit allows.\n");
    exit(1);
}

echo "OK: {$calls} wc_get_products() calls, none asking by 'post__in'.\n";
