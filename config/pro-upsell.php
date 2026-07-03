<?php
/**
 * PRO upsell content, generated from the plogins.com registry by
 * scripts/gen-pro-upsell.mjs. The admin upsell renders this; curate the
 * feature list to fit this plugin's settings screen (do not invent features).
 *
 * @package polski-pro
 */

defined('ABSPATH') || exit;

return [
    'name'       => 'Polski PRO',
    'url'        => 'https://plogins.com/polski-pro/pricing/',
    'sellable'   => true,
    'price_from' => 69,
    'currency'   => 'EUR',
    'price_pln'  => 299,
    'lead'       => [
        'en' => 'Each module is toggled independently in the settings. The list matches the modules described in the documentation.',
        'pl' => 'Każdy moduł włączasz i wyłączasz niezależnie w ustawieniach. Lista odpowiada modułom opisanym w dokumentacji.',
    ],
    'features'   => [
        [
            'en' => ['title' => 'Invoices', 'desc' => 'VAT invoice, correction, receipt and release note with PDF generation.'],
            'pl' => ['title' => 'Faktury', 'desc' => 'Faktura VAT, korygująca, paragon i dokument WZ z generowaniem PDF.'],
        ],
        [
            'en' => ['title' => 'KSeF integration', 'desc' => 'Electronic submission of invoices to the National e-Invoicing System.'],
            'pl' => ['title' => 'Integracja KSeF', 'desc' => 'Wysyłka faktur elektronicznych do Krajowego Systemu e-Faktur.'],
        ],
        [
            'en' => ['title' => 'Accounting integrations', 'desc' => 'Export documents to wFirma, Fakturownia and iFirma.'],
            'pl' => ['title' => 'Integracje księgowe', 'desc' => 'Eksport dokumentów do wFirma, Fakturownia i iFirma.'],
        ],
        [
            'en' => ['title' => 'Multi-step checkout', 'desc' => 'Split the checkout into steps: address, delivery, payment, summary.'],
            'pl' => ['title' => 'Wieloetapowy koszyk', 'desc' => 'Podział kasy na kroki: adres, dostawa, płatność, podsumowanie.'],
        ],
        [
            'en' => ['title' => 'Consent management', 'desc' => 'Consent versioning, an audit trail and CSV and JSON export.'],
            'pl' => ['title' => 'Zarządzanie zgodami', 'desc' => 'Wersjonowanie zgód, audit trail oraz eksport CSV i JSON.'],
        ],
        [
            'en' => ['title' => 'Order fulfilment', 'desc' => 'Packed, Shipped and Delivered statuses, a tracking field and customer emails.'],
            'pl' => ['title' => 'Realizacja zamówień', 'desc' => 'Statusy Spakowane, Wysłane, Dostarczone, pole śledzenia i wiadomości e-mail do klienta.'],
        ],
        [
            'en' => ['title' => 'Shipping integrations', 'desc' => 'InPost, DPD, DHL and Poczta Polska: labels, tracking and pickup points.'],
            'pl' => ['title' => 'Integracje wysyłki', 'desc' => 'InPost, DPD, DHL i Poczta Polska: etykiety, śledzenie i punkty odbioru.'],
        ],
        [
            'en' => ['title' => 'Gift cards', 'desc' => 'Sell cards, generate codes and redeem balances in the cart.'],
            'pl' => ['title' => 'Karty podarunkowe', 'desc' => 'Sprzedaż kart, generowanie kodów i realizacja salda w koszyku.'],
        ],
        [
            'en' => ['title' => 'Subscriptions', 'desc' => 'Recurring payments with renewals, reminders and one-click cancellation.'],
            'pl' => ['title' => 'Subskrypcje', 'desc' => 'Płatności cykliczne z odnowieniami, przypomnieniami i anulowaniem jednym kliknięciem.'],
        ],
        [
            'en' => ['title' => 'Affiliate program', 'desc' => 'Referral links, commission tracking and an affiliate dashboard.'],
            'pl' => ['title' => 'Program afiliacyjny', 'desc' => 'Linki polecające, śledzenie prowizji i panel afilianta.'],
        ],
        [
            'en' => ['title' => 'Pre-orders and bundles', 'desc' => 'Sell before availability, bundle products and add per-product add-ons.'],
            'pl' => ['title' => 'Pre-ordery i pakiety', 'desc' => 'Sprzedaż przed dostępnością, bundlowanie produktów oraz dodatki per produkt.'],
        ],
        [
            'en' => ['title' => 'Catalog mode and RFQ', 'desc' => 'B2B mode that hides prices, plus quote requests instead of a cart.'],
            'pl' => ['title' => 'Tryb katalogowy i RFQ', 'desc' => 'Tryb B2B z ukryciem cen oraz zapytania ofertowe zamiast koszyka.'],
        ],
    ],
];
