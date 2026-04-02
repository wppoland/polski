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
        'digital_waiver_checkbox_enabled' => false,
        'parcel_delivery_checkbox_enabled' => false,
        'review_reminder_checkbox_enabled' => false,
        'marketing_checkbox_enabled' => false,
        'delayed_payment_enabled' => false,
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

    // Request a quote.
    'spolszczony_quote' => [
        'enabled' => false,
        'availability' => 'selected',
        'show_on_single' => true,
        'show_on_loop' => false,
        'allow_guest' => true,
        'replace_add_to_cart' => false,
        'hide_prices' => false,
        'button_text' => 'Zapytaj o wycenę',
        'modal_title' => 'Zapytaj o indywidualną wycenę',
        'intro_text' => 'Wypełnij krótki formularz, a wrócimy z wyceną dopasowaną do Twojego zamówienia.',
        'submit_text' => 'Wyślij zapytanie',
        'success_text' => 'Dziękujemy. Twoje zapytanie zostało wysłane, wrócimy z odpowiedzią tak szybko, jak to możliwe.',
        'recipient_email' => '',
        'require_company' => true,
        'require_phone' => true,
        'require_nip' => false,
        'require_postcode' => false,
        'privacy_required' => true,
        'privacy_label' => 'Akceptuję politykę prywatności i zgadzam się na kontakt w sprawie wyceny.',
        'admin_email_subject' => 'Nowe zapytanie ofertowe - {product_name}',
        'customer_email_subject' => 'Potwierdzenie zapytania ofertowego - {product_name}',
        'customer_email_enabled' => true,
    ],

    // Catalog mode.
    'spolszczony_catalog' => [
        'enabled' => false,
        'availability' => 'selected',
        'audience' => 'guests_only',
        'selected_roles' => '',
        'hide_prices' => true,
        'hide_add_to_cart' => true,
        'replacement_mode' => 'quote',
        'cta_text' => 'Zapytaj o warunki handlowe',
        'custom_url' => '',
        'hidden_price_text' => 'Cena dostępna po zalogowaniu lub po kontakcie z obsługą.',
        'single_notice' => 'Produkt dostępny w trybie katalogowym. Skontaktuj się z nami, aby uzyskać warunki handlowe lub ofertę B2B.',
        'loop_notice' => 'Zobacz warunki handlowe',
    ],

    // Food module.
    'spolszczony_food' => [
        'enabled' => false,
    ],
];
