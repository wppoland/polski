<?php
/**
 * Fails when a function that queries products anywhere in src/ mentions 'post__in'.
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
 * WHAT THIS CHECK LOOKS AT, AND WHY IT IS THE WHOLE FUNCTION
 *
 * The args are usually not written inside the call. The export builds an array
 * in a variable, adds to it, and hands the variable over; reading only the text
 * between the parentheses of wc_get_products( ... ) sees none of that, which is
 * exactly the shape the defect had. So the unit is the enclosing function: if a
 * function queries products, no 'post__in' string may appear in it. Both
 * wc_get_products() and a WC_Product_Query built by hand count as querying
 * products.
 *
 * The source is tokenised rather than grepped, so a 'post__in' inside a comment
 * is not a finding and the word wc_get_products inside a comment or a string is
 * not a call. hydrate() in StockExportService explains 'post__in' in a comment
 * directly above its query, and that function has to stay clean.
 *
 * The cost is a false positive on a function that queries products and, for a
 * different query, legitimately passes 'post__in' to WP_Query or to
 * wc_get_orders(), which does accept it. No function in src/ does both today.
 * Split it if one ever needs to.
 *
 * The unit tests in tests/Unit/Service/UnboundedQueryBatchingTest.php pin the
 * export itself. This check covers the rest of src/, where there is no test.
 *
 * Usage: php tests/product-query-args-check.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);

/** @var list<string> $bad */
$bad = [];
$calls = 0;

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src'));

foreach ($files as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = str_replace($root . '/', '', $file->getPathname());
    $tokens = token_get_all((string) file_get_contents($file->getPathname()));
    $count = count($tokens);

    $text = static fn (int $at): string => is_array($tokens[$at]) ? $tokens[$at][1] : $tokens[$at];

    // Index of every token that is not whitespace or a comment, so "the token
    // before this one" can skip over both.
    $significant = [];

    foreach ($tokens as $i => $token) {
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $significant[$i] = count($significant);
    }

    $order = array_keys($significant);

    $previous = static function (int $i) use ($significant, $order): ?int {
        $at = $significant[$i] ?? null;

        return $at === null || $at === 0 ? null : $order[$at - 1];
    };

    $next = static function (int $i) use ($significant, $order): ?int {
        $at = $significant[$i] ?? null;

        return $at === null || $at === count($order) - 1 ? null : $order[$at + 1];
    };

    // Every function body in the file as a token range. Ranges nest, so the
    // innermost one containing a call is the last one that contains it.
    $scopes = [];

    foreach ($tokens as $i => $token) {
        if (! is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }

        // Walk to this function's opening brace, past the signature and any
        // return type. An abstract or interface method ends at ';' instead.
        $j = $i;
        $depth = 0;

        while ($j < $count) {
            $at = $text($j);

            if ($at === '(') {
                ++$depth;
            } elseif ($at === ')') {
                --$depth;
            } elseif ($depth === 0 && ($at === '{' || $at === ';')) {
                break;
            }

            ++$j;
        }

        if ($j >= $count || $text($j) !== '{') {
            continue;
        }

        $start = $j;
        $braces = 0;

        while ($j < $count) {
            $at = $text($j);
            $isOpen = $at === '{'
                || (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true));

            if ($isOpen) {
                ++$braces;
            } elseif ($at === '}') {
                --$braces;

                if ($braces === 0) {
                    break;
                }
            }

            ++$j;
        }

        $scopes[] = ['start' => $start, 'end' => min($j, $count - 1)];
    }

    foreach ($tokens as $i => $token) {
        if (! is_array($token) || $token[0] !== T_STRING) {
            continue;
        }

        $isQuery = false;

        if ($token[1] === 'wc_get_products') {
            $before = $previous($i);
            $after = $next($i);
            $beforeId = $before !== null && is_array($tokens[$before]) ? $tokens[$before][0] : null;

            // A method or a declaration of the same name is not this call.
            $isQuery = $after !== null
                && $text($after) === '('
                && ! in_array($beforeId, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true);
        } elseif ($token[1] === 'WC_Product_Query') {
            $before = $previous($i);

            if ($before !== null && is_array($tokens[$before]) && $tokens[$before][0] === T_NS_SEPARATOR) {
                $before = $previous($before);
            }

            $isQuery = $before !== null && is_array($tokens[$before]) && $tokens[$before][0] === T_NEW;
        }

        if (! $isQuery) {
            continue;
        }

        ++$calls;

        // The innermost function body holding this query, or the whole file
        // when the query is not inside one.
        $from = 0;
        $to = $count - 1;

        foreach ($scopes as $scope) {
            if ($scope['start'] <= $i && $i <= $scope['end']) {
                $from = $scope['start'];
                $to = $scope['end'];
            }
        }

        for ($k = $from; $k <= $to; ++$k) {
            if (! is_array($tokens[$k]) || $tokens[$k][0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            if (trim($tokens[$k][1], "'\"") !== 'post__in') {
                continue;
            }

            $where = $path . ':' . $tokens[$k][2] . ', in the function that queries products at line ' . $token[2];

            if (! in_array($where, $bad, true)) {
                $bad[] = $where;
            }
        }
    }
}

if ($calls === 0) {
    fwrite(STDERR, "FAIL: found no product queries in src/, the check is not reading what it thinks it is.\n");
    exit(1);
}

if ($bad !== []) {
    fwrite(STDERR, "FAIL: 'post__in' appears in a function that queries products:\n");

    foreach ($bad as $where) {
        fwrite(STDERR, "  - {$where}\n");
    }

    fwrite(STDERR, "\nUse 'include' => \$ids. 'post__in' is silently replaced by the empty 'include'\n");
    fwrite(STDERR, "default, and the query then returns the newest products the limit allows.\n");
    exit(1);
}

echo "OK: {$calls} product queries in src/, none in a function that mentions 'post__in'.\n";
