<?php

declare(strict_types=1);

/**
 * Fails when the modules screen offers a setting that no code ever reads.
 *
 * A switch nobody reads is worse than a missing feature: the merchant ticks it,
 * sees nothing happen, and reports a bug. It cost us two support reports in a
 * week. `polski_omnibus|show_on_cart` was offered from the day the module
 * shipped and CartHooks was an empty stub saying "Phase 2".
 *
 * The check is deliberately crude: it looks for the sub-key as a quoted string
 * anywhere outside the screen that declares it. That is enough to catch a
 * setting with no reader at all, which is the failure that actually happens.
 * Keys assembled at runtime cannot be seen this way and are listed below with
 * the line that builds them.
 *
 * Usage: php tests/settings-are-read-check.php
 */

$root = dirname(__DIR__);

/**
 * Sub-keys built at runtime rather than written out, with where they are built.
 * Anything added here must name the file that assembles the key.
 *
 * @var array<string, string>
 */
$dynamic = [
    'amount_pet' => 'DepositService::amountForType() builds "amount_" . $type',
    'amount_can' => 'DepositService::amountForType() builds "amount_" . $type',
    'amount_glass' => 'DepositService::amountForType() builds "amount_" . $type',
];

$screen = $root . '/src/Admin/ModulesPage.php';
$declared = [];
preg_match_all(
    "/'key'\s*=>\s*'(polski_[a-z0-9_]+)\|([a-z0-9_]+)'/",
    (string) file_get_contents($screen),
    $matches,
    PREG_SET_ORDER,
);
foreach ($matches as $m) {
    $declared[$m[1] . '|' . $m[2]] = $m[2];
}

/**
 * Sources indexed per file, not concatenated.
 *
 * A single blob was not enough: a sub-key read for one module vouched for every
 * other module declaring the same name. `show_on_loop` is read from
 * `polski_brand`, and that alone was covering `polski_omnibus|show_on_loop`,
 * which nothing reads. A key counts as read only when one file mentions both
 * its option group and its sub-key.
 *
 * @var array<string, string> $sources
 */
$sources = [];
foreach (['src', 'templates', 'config'] as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir));
    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }
        // Both of these declare defaults rather than act on them. Counting
        // defaults.php as a reader is how polski_omnibus|show_on_loop and
        // |show_on_single stayed hidden: it names the group and the sub-key
        // while nothing ever branches on either.
        if (in_array($file->getFilename(), ['ModulesPage.php', 'defaults.php'], true)) {
            continue;
        }
        $sources[$file->getPathname()] = (string) file_get_contents($file->getPathname());
    }
}

$dead = [];
foreach ($declared as $full => $subKey) {
    if (isset($dynamic[$subKey])) {
        continue;
    }

    [$group] = explode('|', $full, 2);
    $read = false;

    foreach ($sources as $code) {
        $mentionsKey = str_contains($code, "'" . $subKey . "'") || str_contains($code, '"' . $subKey . '"');

        if (! $mentionsKey) {
            continue;
        }

        // The group can be named directly, or reached through a helper such as
        // getSettings() on the service that owns it. A file that reads the
        // sub-key and never names another group is taken as the owner.
        $otherGroups = 0;
        foreach ($declared as $otherFull => $_sub) {
            [$otherGroup] = explode('|', $otherFull, 2);
            if ($otherGroup !== $group && str_contains($code, "'" . $otherGroup . "'")) {
                $otherGroups++;
            }
        }

        if (str_contains($code, "'" . $group . "'") || $otherGroups === 0) {
            $read = true;
            break;
        }
    }

    if (! $read) {
        $dead[] = $full;
    }
}

// Settings known to be dead and not yet fixed. Shrink this list, never grow it.
$known = [
    // Accepted debt. This list grew from 12 to 37 on 2026-09-07 because the
    // check got stricter, not because the code got worse: it now indexes each
    // source file separately and ignores the two files that only declare
    // defaults. Before that, a sub-key read for one module vouched for every
    // module sharing the name, and defaults.php counted as a reader.
    //
    // Most of what remains is interface text that was made configurable and
    // never read back. Shrink this list, never grow it.
    'polski_checkout|parcel_delivery_checkbox_enabled',
    'polski_checkout|review_reminder_checkbox_enabled',
    'polski_dsa|success_text',
    'polski_general|admin_doi_card_title',
    'polski_general|admin_legal_pages_card_progress',
    'polski_general|admin_legal_pages_card_title',
    'polski_general|admin_omnibus_external_active_text',
    'polski_general|admin_omnibus_no_external_text',
    'polski_general|admin_omnibus_plugin_detected_text',
    'polski_general|admin_omnibus_plugin_missing_text',
    'polski_general|admin_status_active',
    'polski_general|admin_status_inactive',
    'polski_general|admin_status_unconfigured',
    'polski_general|admin_vat_card_title',
    'polski_general|admin_vat_small_business_text',
    'polski_general|admin_vat_standard_text',
    'polski_waitlist|allow_guests',
    'polski_waitlist|disabled_text',
    'polski_waitlist|invalid_email_text',
    'polski_waitlist|login_required_text',
    'polski_waitlist|notify_intro_text',
    'polski_waitlist|notify_outro_text',
    'polski_waitlist|privacy_error_text',
    'polski_waitlist|product_not_found_text',
    'polski_waitlist|show_on_single',
    'polski_waitlist|success_text',
    'polski_withdrawal|column_price',
    'polski_withdrawal|column_product',
    'polski_withdrawal|column_quantity',
    'polski_withdrawal|confirmed_order_note',
    'polski_withdrawal|exempt_notice_text',
    'polski_withdrawal|items_heading',
    'polski_withdrawal|legal_notice_text',
    'polski_withdrawal|requested_order_note',
];

$new = array_values(array_diff($dead, $known));
$fixed = array_values(array_diff($known, $dead));

printf("settings declared: %d, with no reader: %d (%d known)\n", count($declared), count($dead), count($known));

if ($fixed !== []) {
    echo "These are read now. Remove them from \$known in " . basename(__FILE__) . ":\n";
    foreach ($fixed as $key) {
        echo "  - {$key}\n";
    }
    exit(1);
}

if ($new !== []) {
    echo "A setting is offered on the modules screen that nothing reads:\n";
    foreach ($new as $key) {
        echo "  - {$key}\n";
    }
    echo "Implement it, remove it from the screen, or read the key where it is used.\n";
    exit(1);
}

echo "OK: every new setting has a reader.\n";
