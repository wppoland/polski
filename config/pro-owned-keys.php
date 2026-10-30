<?php

declare(strict_types=1);

/**
 * Options and post/order meta that Polski PRO owns under the plain polski_
 * prefix, so the FREE uninstall's polski_% and _polski_% wildcards would
 * otherwise catch them, and tables both editions write to (without prefix).
 *
 * uninstall.php leaves these alone while PRO is installed; PRO's own Freemius
 * after_uninstall cleanup removes them. polski_pro_* is skipped separately.
 * Six of the options are also seeded by config/defaults.php, which is why the
 * skip depends on PRO being installed rather than applying always.
 *
 * Census of polski-pro src/ and config/ on 2026-10-29. Keep in step with
 * polski_pro_after_uninstall() in polski-pro/freemius-init.php;
 * tests/uninstall-keeps-pro-data-check.php compares the two.
 */

defined('ABSPATH') || exit;

return [
    'options' => [
        'polski_abandoned_carts',
        'polski_addons',
        'polski_affiliates',
        'polski_bundles',
        'polski_catalog',
        'polski_delivery_date',
        'polski_fbt',
        'polski_gift_cards',
        'polski_preorder',
        'polski_quote',
        'polski_subscriptions',
        'polski_tax_rules',
        'polski_unpaid_orders',
    ],
    'meta' => [
        '_polski_addons_config',
        '_polski_affiliate_id',
        '_polski_ai_description',
        '_polski_ai_description_last',
        '_polski_ai_generated',
        '_polski_bundle_button_text',
        '_polski_bundle_discount_type',
        '_polski_bundle_discount_value',
        '_polski_bundle_items',
        '_polski_bundle_title',
        '_polski_catalog_cta_text',
        '_polski_catalog_enabled',
        '_polski_catalog_hide_cart',
        '_polski_catalog_hide_price',
        '_polski_catalog_message',
        '_polski_delivery_date',
        '_polski_fbt_button_text',
        '_polski_fbt_intro',
        '_polski_fbt_product_ids',
        '_polski_fbt_title',
        '_polski_feed_exclude',
        '_polski_gift_card_allow_custom_amount',
        '_polski_gift_card_amounts',
        '_polski_gift_card_enabled',
        '_polski_gift_card_max_amount',
        '_polski_gift_card_min_amount',
        '_polski_gift_card_usage',
        '_polski_gift_cards_redeemed',
        '_polski_gpsr_preset',
        '_polski_gpsr_profile',
        '_polski_invoice_request',
        '_polski_ksef_status',
        '_polski_loyalty_points',
        '_polski_loyalty_user',
        '_polski_points_awarded',
        '_polski_points_refunded',
        '_polski_preorder_button_text',
        '_polski_preorder_date',
        '_polski_preorder_enabled',
        '_polski_quote_button_text',
        '_polski_quote_enabled',
        '_polski_quote_min_qty',
        '_polski_quote_only',
        '_polski_receipt_requires_nip',
        '_polski_shipping_carrier',
        '_polski_subscription_enabled',
        '_polski_subscription_interval',
        '_polski_subscription_length',
        '_polski_subscription_period',
        '_polski_subscription_signup_fee',
        '_polski_subscription_trial_days',
        '_polski_tracking_number',
        '_polski_tracking_url',
        '_polski_unpaid_reminded_at',
        '_polski_withdrawal_pdf',
    ],
    'tables' => [
        'polski_invoices',
        'polski_invoice_items',
    ],
];
