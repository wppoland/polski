<?php

declare(strict_types=1);

/**
 * Default settings for the Polish market.
 *
 * These are applied on first activation. Each key maps to a WordPress option.
 * Values are only set if the option does not already exist.
 */
return [
    // General.
    'spolszczony_general' => [
        'small_business' => false,
        'dispute_resolution_enabled' => true,
        'dispute_resolution_text' => 'Platforma ODR: https://ec.europa.eu/consumers/odr',
    ],

    // Price display.
    'spolszczony_prices' => [
        'tax_display_mode' => 'brutto',
        'unit_price_enabled' => true,
        'unit_price_text' => '{price} / {unit}',
        'shipping_costs_notice_enabled' => true,
        'shipping_costs_text' => 'zzgl. kosztów wysyłki',
    ],

    // Omnibus directive.
    'spolszczony_omnibus' => [
        'enabled' => true,
        'days' => 30,
        'display_text' => 'Najniższa cena z ostatnich {days} dni: {price}',
        'display_on_sale_only' => true,
        'prune_after_days' => 90,
    ],

    // Checkout.
    'spolszczony_checkout' => [
        'order_button_text' => 'Zamawiam z obowiązkiem zapłaty',
        'terms_checkbox_enabled' => true,
        'privacy_checkbox_enabled' => true,
        'withdrawal_checkbox_enabled' => true,
    ],

    // Emails.
    'spolszczony_emails' => [
        'attach_terms' => true,
        'attach_privacy' => false,
        'attach_withdrawal' => true,
    ],

    // Tax.
    'spolszczony_taxes' => [
        'vat_notice_text' => 'w tym {rate}% VAT',
        'vat_exempt_notice' => 'Zwolniony z VAT na podstawie art. 113 ust. 1 ustawy o VAT',
    ],

    // Delivery times.
    'spolszczony_delivery' => [
        'default_delivery_time' => '',
        'display_format' => 'Czas dostawy: {time}',
    ],

    // Double opt-in.
    'spolszczony_doi' => [
        'enabled' => false,
        'cleanup_days' => 7,
    ],

    // Food module.
    'spolszczony_food' => [
        'enabled' => false,
    ],
];
