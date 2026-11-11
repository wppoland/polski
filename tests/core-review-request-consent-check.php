<?php

// NOTE: no `declare(strict_types=1)` here on purpose - `wp eval-file` wraps this
// file in eval(), where a declare() can't be the first statement.

/**
 * WooCommerce's own review request email (11.x) honours the review reminder
 * consent box and the opt-out list, like Polski's own reminder does.
 *
 * Run: wp eval-file wp-content/plugins/<dir>/tests/core-review-request-consent-check.php
 */

$polski_failures = [];
$polski_check = static function (string $label, bool $ok) use (&$polski_failures): void {
    if (! $ok) {
        $polski_failures[] = $label;
    }
};

$polski_order = wc_create_order();
$polski_order->set_billing_email('review-check-' . wp_generate_password(6, false) . '@example.com');
$polski_order->save();
$polski_send = static fn () => apply_filters('woocommerce_should_send_review_request', true, wc_get_order($polski_order->get_id()));

$polski_check('no consent box on the checkout: WooCommerce may send', true === $polski_send());

$polski_order->update_meta_data('_wc_other/polski/review_reminder', '0');
$polski_order->save();
$polski_check('box left unticked: WooCommerce does not send', false === $polski_send());

$polski_order->update_meta_data('_wc_other/polski/review_reminder', '1');
$polski_order->save();
$polski_check('box ticked: WooCommerce may send', true === $polski_send());

$polski_saved = get_option('polski_review_optout_emails', []);
update_option('polski_review_optout_emails', (array) $polski_saved + [hash('sha256', strtolower($polski_order->get_billing_email())) => time()]);
$polski_check('opted out by email: WooCommerce does not send', false === $polski_send());
update_option('polski_review_optout_emails', $polski_saved);

$polski_order->delete(true);

if ($polski_failures !== []) {
    echo "FAIL  core-review-request-consent\n  - " . implode("\n  - ", $polski_failures) . "\n";
    exit(1);
}

echo "ok    core-review-request-consent\n";
