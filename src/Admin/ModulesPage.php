<?php

declare(strict_types=1);
namespace Polski\Admin;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * Module management page - toggleable feature groups.
 *
 * Each module corresponds to a feature set that can be enabled/disabled.
 * Modules are stored as a serialized array in polski_modules option.
 */
final class ModulesPage implements HasHooks
{
    private const OPTION = 'polski_modules';

    public function registerHooks(): void
    {
        add_action('admin_post_polski_save_modules', [$this, 'handleSave']);
    }

    /**
     * Get all module definitions with their current state.
     *
     * @return list<array{id: string, name: string, description: string, group: string, enabled: bool, icon: string, links: list<array{label: string, url: string}>}>
     */
    public function getModules(): array
    {
        $saved = get_option(self::OPTION, []);
        $saved = is_array($saved) ? $saved : [];

        $modules = [
            // === Ceny i wyświetlanie ===
            [
                'id' => 'unit_price',
                'name' => __('Cena jednostkowa', 'polski'),
                'description' => __('Wyświetlanie ceny za jednostkę miary (np. za 1 kg, za 100 ml) zgodnie z polskim prawem konsumenckim.', 'polski'),
                'group' => __('Ceny i wyświetlanie', 'polski'),
                'enabled' => true,
                'icon' => 'dashicons-tag',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_prices|unit_price_text', 'label' => __('Szablon wyświetlania', 'polski'), 'type' => 'text', 'default' => '{price} / {unit}', 'hint' => __('Zmienne: {price}, {unit}', 'polski')],
                    ['key' => 'polski_prices|unit_price_show_loop', 'label' => __('Pokazuj na liście produktów', 'polski'), 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'omnibus',
                'name' => __('Najniższa cena (Omnibus)', 'polski'),
                'description' => __('Śledzenie historii cen i wyświetlanie najniższej ceny z ostatnich 30 dni przy produktach w promocji. Wymagane przez Dyrektywę Omnibus (UE 2019/2161).', 'polski'),
                'group' => __('Ceny i wyświetlanie', 'polski'),
                'enabled' => true,
                'icon' => 'dashicons-chart-line',
                'links' => [],
                'settings' => [
                    ['key' => '_omnibus_header_1', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;">' . __('Śledzenie cen', 'polski') . '</strong>'],
                    ['key' => 'polski_omnibus|days', 'label' => __('Okres śledzenia (dni)', 'polski'), 'type' => 'number', 'default' => 30, 'hint' => __('Dyrektywa wymaga minimum 30 dni', 'polski')],
                    ['key' => 'polski_omnibus|prune_after_days', 'label' => __('Przechowuj historię (dni)', 'polski'), 'type' => 'number', 'default' => 90, 'hint' => __('Dane starsze zostaną automatycznie usunięte', 'polski')],
                    ['key' => 'polski_omnibus|include_tax', 'label' => __('Ceny z podatkiem', 'polski'), 'type' => 'checkbox', 'default' => true, 'hint' => __('Śledź i wyświetlaj ceny brutto', 'polski')],

                    ['key' => '_omnibus_header_2', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;margin-top:8px;display:block;">' . __('Wyświetlanie', 'polski') . '</strong>'],
                    ['key' => 'polski_omnibus|display_on_sale_only', 'label' => __('Tylko produkty w promocji', 'polski'), 'type' => 'checkbox', 'default' => true, 'hint' => __('Pokazuj informację tylko gdy produkt ma cenę promocyjną', 'polski')],
                    ['key' => 'polski_omnibus|show_on_single', 'label' => __('Strona produktu', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_omnibus|show_on_loop', 'label' => __('Lista produktów (sklep, kategorie)', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_omnibus|show_on_related', 'label' => __('Produkty powiązane i polecane', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_omnibus|show_on_cart', 'label' => __('Koszyk', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_omnibus|show_regular_price', 'label' => __('Pokazuj cenę regularną (przed promocją)', 'polski'), 'type' => 'checkbox', 'default' => false, 'hint' => __('Wyświetl dodatkową informację o cenie przed rozpoczęciem promocji', 'polski')],

                    ['key' => '_omnibus_header_3', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;margin-top:8px;display:block;">' . __('Szablon wiadomości', 'polski') . '</strong>'],
                    ['key' => 'polski_omnibus|display_text', 'label' => __('Treść komunikatu', 'polski'), 'type' => 'text', 'default' => 'Najniższa cena z ostatnich {days} dni: {price}', 'hint' => __('Zmienne: {price}, {days}, {date}, {regular_price}', 'polski')],
                    ['key' => 'polski_omnibus|no_history_text', 'label' => __('Brak historii cen', 'polski'), 'type' => 'select', 'default' => 'hide', 'options' => ['hide' => __('Ukryj komunikat', 'polski'), 'current' => __('Pokaż aktualną cenę', 'polski'), 'custom' => __('Własny tekst', 'polski')]],
                    ['key' => 'polski_omnibus|no_history_custom_text', 'label' => __('Własny tekst (brak historii)', 'polski'), 'type' => 'text', 'default' => 'Cena nie uległa zmianie w okresie {days} dni'],
                    ['key' => 'polski_omnibus|price_count_from', 'label' => __('Liczona od', 'polski'), 'type' => 'select', 'default' => 'sale_start', 'options' => ['sale_start' => __('Dnia rozpoczęcia promocji', 'polski'), 'today' => __('Dnia dzisiejszego', 'polski')], 'hint' => __('Punkt odniesienia do obliczania najniższej ceny', 'polski')],

                    ['key' => '_omnibus_header_4', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;margin-top:8px;display:block;">' . __('Produkty wariantowe', 'polski') . '</strong>'],
                    ['key' => 'polski_omnibus|variable_tracking', 'label' => __('Śledź warianty oddzielnie', 'polski'), 'type' => 'checkbox', 'default' => true, 'hint' => __('Każdy wariant ma własną historię cen', 'polski')],
                ],
            ],
            [
                'id' => 'tax_display',
                'name' => __('Wyświetlanie VAT', 'polski'),
                'description' => __('Konfiguracja wyświetlania cen brutto/netto, informacja o stawce VAT, obsługa zwolnienia podmiotowego (art. 113 ustawy o VAT).', 'polski'),
                'group' => __('Ceny i wyświetlanie', 'polski'),
                'enabled' => true,
                'icon' => 'dashicons-money-alt',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_taxes|tax_display_mode', 'label' => __('Tryb wyświetlania cen', 'polski'), 'type' => 'select', 'default' => 'brutto', 'options' => ['brutto' => __('Brutto (z VAT)', 'polski'), 'netto' => __('Netto (bez VAT)', 'polski')]],
                    ['key' => 'polski_taxes|vat_notice_text', 'label' => __('Tekst informacji o VAT', 'polski'), 'type' => 'text', 'default' => 'w tym {rate}% VAT', 'hint' => __('Zmienne: {rate}', 'polski')],
                    ['key' => 'polski_general|small_business', 'label' => __('Zwolnienie podmiotowe (art. 113)', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_taxes|vat_exempt_notice', 'label' => __('Tekst zwolnienia', 'polski'), 'type' => 'text', 'default' => 'Zwolniony z VAT na podstawie art. 113 ust. 1 ustawy o VAT'],
                ],
            ],
            [
                'id' => 'delivery_time',
                'name' => __('Czas dostawy', 'polski'),
                'description' => __('Wyświetlanie przewidywanego czasu dostawy na stronie produktu. Konfiguracja per produkt lub wariant z domyślnym fallbackiem.', 'polski'),
                'group' => __('Ceny i wyświetlanie', 'polski'),
                'enabled' => true,
                'icon' => 'dashicons-clock',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_delivery|display_format', 'label' => __('Format wyświetlania', 'polski'), 'type' => 'text', 'default' => 'Czas dostawy: {time}', 'hint' => __('Zmienne: {time}', 'polski')],
                    ['key' => 'polski_delivery|default_delivery_time', 'label' => __('Domyślny czas dostawy', 'polski'), 'type' => 'delivery_time_select'],
                ],
            ],
            [
                'id' => 'shipping_notice',
                'name' => __('Informacja o kosztach wysyłki', 'polski'),
                'description' => __('Link do strony z kosztami wysyłki wyświetlany przy cenie produktu.', 'polski'),
                'group' => __('Ceny i wyświetlanie', 'polski'),
                'enabled' => true,
                'icon' => 'dashicons-car',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_prices|shipping_costs_text', 'label' => __('Tekst linku', 'polski'), 'type' => 'text', 'default' => 'zzgl. kosztów wysyłki'],
                ],
            ],

            // === Kasa i zamówienia ===
            [
                'id' => 'checkout_button',
                'name' => __('Przycisk zamówienia', 'polski'),
                'description' => __('Zmiana tekstu przycisku zamówienia na "Zamawiam z obowiązkiem zapłaty" zgodnie z polskim prawem.', 'polski'),
                'group' => __('Kasa i zamówienia', 'polski'),
                'enabled' => true,
                'icon' => 'dashicons-cart',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_checkout|order_button_text', 'label' => __('Tekst przycisku', 'polski'), 'type' => 'text', 'default' => 'Zamawiam z obowiązkiem zapłaty'],
                ],
            ],
            [
                'id' => 'legal_checkboxes',
                'name' => __('Checkboxy prawne', 'polski'),
                'description' => __('7 wbudowanych checkboxów: regulamin, polityka prywatności, prawo odstąpienia, treści cyfrowe, powiadomienia o dostawie, przypomnienie o opinii, marketing.', 'polski'),
                'group' => __('Kasa i zamówienia', 'polski'),
                'enabled' => true,
                'icon' => 'dashicons-yes-alt',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_checkout|terms_checkbox_enabled', 'label' => __('Regulamin sklepu', 'polski'), 'type' => 'checkbox', 'default' => true],
                    /* translators: %s: terms page URL placeholder */
                    ['key' => 'polski_checkout|terms_checkbox_label', 'label' => __('Etykieta - Regulamin', 'polski'), 'type' => 'textarea', 'default' => 'Zapoznałem się i akceptuję <a href="%s" target="_blank">Regulamin sklepu</a>.', 'hint' => __('Użyj %s jako miejsca na link do strony regulaminu', 'polski')],
                    ['key' => 'polski_checkout|terms_checkbox_error', 'label' => __('Błąd - Regulamin', 'polski'), 'type' => 'text', 'default' => 'Musisz zaakceptować Regulamin, aby złożyć zamówienie.'],
                    ['key' => 'polski_checkout|terms_checkbox_description', 'label' => __('Opis - Regulamin', 'polski'), 'type' => 'text', 'default' => 'Akceptacja Regulaminu sklepu.'],
                    ['key' => 'polski_checkout|privacy_checkbox_enabled', 'label' => __('Polityka prywatności', 'polski'), 'type' => 'checkbox', 'default' => true],
                    /* translators: %s: privacy policy URL placeholder */
                    ['key' => 'polski_checkout|privacy_checkbox_label', 'label' => __('Etykieta - Polityka prywatności', 'polski'), 'type' => 'textarea', 'default' => 'Zapoznałem się i akceptuję <a href="%s" target="_blank">Politykę prywatności</a>.', 'hint' => __('Użyj %s jako miejsca na link do polityki prywatności', 'polski')],
                    ['key' => 'polski_checkout|privacy_checkbox_error', 'label' => __('Błąd - Polityka prywatności', 'polski'), 'type' => 'text', 'default' => 'Musisz zaakceptować Politykę prywatności.'],
                    ['key' => 'polski_checkout|privacy_checkbox_description', 'label' => __('Opis - Polityka prywatności', 'polski'), 'type' => 'text', 'default' => 'Akceptacja Polityki prywatności.'],
                    ['key' => 'polski_checkout|withdrawal_checkbox_enabled', 'label' => __('Prawo odstąpienia', 'polski'), 'type' => 'checkbox', 'default' => true],
                    /* translators: %s: withdrawal information URL placeholder */
                    ['key' => 'polski_checkout|withdrawal_checkbox_label', 'label' => __('Etykieta - Prawo odstąpienia', 'polski'), 'type' => 'textarea', 'default' => 'Potwierdzam, że zostałem poinformowany o <a href="%s" target="_blank">prawie odstąpienia od umowy</a> w ciągu 14 dni.', 'hint' => __('Użyj %s jako miejsca na link do strony zwrotów lub odstąpienia', 'polski')],
                    ['key' => 'polski_checkout|withdrawal_checkbox_error', 'label' => __('Błąd - Prawo odstąpienia', 'polski'), 'type' => 'text', 'default' => 'Musisz potwierdzić zapoznanie się z informacją o prawie odstąpienia.'],
                    ['key' => 'polski_checkout|withdrawal_checkbox_description', 'label' => __('Opis - Prawo odstąpienia', 'polski'), 'type' => 'text', 'default' => 'Potwierdzenie informacji o 14-dniowym prawie odstąpienia.'],
                    ['key' => 'polski_checkout|digital_waiver_checkbox_enabled', 'label' => __('Treści cyfrowe (zrzeczenie)', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_checkout|digital_waiver_checkbox_label', 'label' => __('Etykieta - Treści cyfrowe', 'polski'), 'type' => 'textarea', 'default' => 'Wyrażam zgodę na rozpoczęcie dostarczania treści cyfrowych przed upływem terminu do odstąpienia od umowy i przyjmuję do wiadomości utratę prawa odstąpienia.'],
                    ['key' => 'polski_checkout|digital_waiver_checkbox_error', 'label' => __('Błąd - Treści cyfrowe', 'polski'), 'type' => 'text', 'default' => 'Musisz wyrazić zgodę na natychmiastowe dostarczenie treści cyfrowych.'],
                    ['key' => 'polski_checkout|digital_waiver_checkbox_description', 'label' => __('Opis - Treści cyfrowe', 'polski'), 'type' => 'text', 'default' => 'Zrzeczenie się prawa odstąpienia dla treści cyfrowych.'],
                    ['key' => 'polski_checkout|parcel_delivery_checkbox_enabled', 'label' => __('Powiadomienia o dostawie', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_checkout|parcel_delivery_checkbox_label', 'label' => __('Etykieta - Powiadomienia o dostawie', 'polski'), 'type' => 'textarea', 'default' => 'Wyrażam zgodę na otrzymywanie powiadomień SMS/email o statusie dostawy przesyłki.'],
                    ['key' => 'polski_checkout|parcel_delivery_checkbox_description', 'label' => __('Opis - Powiadomienia o dostawie', 'polski'), 'type' => 'text', 'default' => 'Opcjonalna zgoda na powiadomienia o dostawie.'],
                    ['key' => 'polski_checkout|review_reminder_checkbox_enabled', 'label' => __('Przypomnienie o opinii', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_checkout|review_reminder_checkbox_label', 'label' => __('Etykieta - Przypomnienie o opinii', 'polski'), 'type' => 'textarea', 'default' => 'Wyrażam zgodę na otrzymanie przypomnienia o wystawieniu opinii drogą mailową po zakupie.'],
                    ['key' => 'polski_checkout|review_reminder_checkbox_description', 'label' => __('Opis - Przypomnienie o opinii', 'polski'), 'type' => 'text', 'default' => 'Opcjonalna zgoda na przypomnienia o opinii.'],
                    ['key' => 'polski_checkout|marketing_checkbox_enabled', 'label' => __('Zgoda marketingowa', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_checkout|marketing_checkbox_label', 'label' => __('Etykieta - Marketing', 'polski'), 'type' => 'textarea', 'default' => 'Wyrażam zgodę na otrzymywanie komunikacji marketingowej i newslettera.'],
                    ['key' => 'polski_checkout|marketing_checkbox_description', 'label' => __('Opis - Marketing', 'polski'), 'type' => 'text', 'default' => 'Opcjonalna zgoda marketingowa.'],
                ],
            ],
            [
                'id' => 'nip_lookup',
                'name' => 'NIP - Weryfikacja i autouzupelnianie',
                'description' => 'Pole NIP na stronie kasy z walidacja sumy kontrolnej. Automatyczne pobieranie danych firmy z bazy GUS REGON po wpisaniu NIP.',
                'group' => __('Kasa i zamówienia', 'polski'),
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-building',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_nip|nip_required', 'label' => 'NIP wymagany na kasie', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_nip|gus_environment', 'label' => 'Srodowisko GUS API', 'type' => 'select', 'default' => 'test', 'options' => ['test' => 'Testowe', 'production' => 'Produkcyjne']],
                    ['key' => 'polski_nip|gus_api_key', 'label' => 'Klucz API GUS (produkcja)', 'type' => 'text', 'default' => '', 'hint' => 'Wnioskuj o klucz na stronie stat.gov.pl. W trybie testowym uzyty zostanie klucz testowy.'],
                ],
            ],
            [
                'id' => 'consent_logging',
                'name' => __('Logowanie zgód (RODO)', 'polski'),
                'description' => __('Rejestrowanie wszystkich zgód udzielonych przez klientów z adresem IP, user agentem i znacznikiem czasu. Zgodne z RODO.', 'polski'),
                'group' => __('Kasa i zamówienia', 'polski'),
                'enabled' => true,
                'icon' => 'dashicons-shield',
                'links' => [],
                'settings' => [],
            ],
            // === Prawa konsumenta ===
            [
                'id' => 'legal_pages',
                'name' => __('Strony prawne', 'polski'),
                'description' => __('Automatyczne generowanie stron: Regulamin, Polityka prywatności, Prawo odstąpienia od umowy, Reklamacje.', 'polski'),
                'group' => __('Prawa konsumenta', 'polski'),
                'enabled' => true,
                'icon' => 'dashicons-media-document',
                'links' => [],
                'settings' => [
                    ['key' => '_legal_pages_link', 'label' => '', 'type' => 'html', 'html' => '<a href="' . admin_url('admin.php?page=polski&tab=dashboard') . '">' . __('Zarządzaj stronami prawnymi na Pulpicie &rarr;', 'polski') . '</a>'],
                ],
            ],
            [
                'id' => 'withdrawal',
                'name' => __('Right of withdrawal (14 days)', 'polski'),
                'description' => __('Withdrawal request form, My Account action with confirmation step, confirmation email, and per-product exclusions.', 'polski'),
                'group' => __('Prawa konsumenta', 'polski'),
                'enabled' => true,
                'icon' => 'dashicons-undo',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_withdrawal|button_text', 'label' => __('Order action label', 'polski'), 'type' => 'text', 'default' => __('Withdraw from contract', 'polski')],
                    ['key' => 'polski_withdrawal|form_title', 'label' => __('Form title', 'polski'), 'type' => 'text', 'default' => __('Withdrawal request', 'polski')],
                    ['key' => 'polski_withdrawal|form_intro_text', 'label' => __('Form introduction', 'polski'), 'type' => 'textarea', 'default' => __('You are submitting a withdrawal request for order #{order_number} placed on {order_date}.', 'polski'), 'hint' => __('Variables: {order_number}, {order_date}', 'polski')],
                    ['key' => 'polski_withdrawal|legal_notice_text', 'label' => __('Legal notice', 'polski'), 'type' => 'textarea', 'default' => __('Under Polish consumer law, you may withdraw from the contract within 14 days without giving a reason.', 'polski')],
                    ['key' => 'polski_withdrawal|items_heading', 'label' => __('Order items heading', 'polski'), 'type' => 'text', 'default' => __('Order items', 'polski')],
                    ['key' => 'polski_withdrawal|column_product', 'label' => __('Product column', 'polski'), 'type' => 'text', 'default' => __('Product', 'polski')],
                    ['key' => 'polski_withdrawal|column_quantity', 'label' => __('Quantity column', 'polski'), 'type' => 'text', 'default' => __('Quantity', 'polski')],
                    ['key' => 'polski_withdrawal|column_price', 'label' => __('Price column', 'polski'), 'type' => 'text', 'default' => __('Price', 'polski')],
                    ['key' => 'polski_withdrawal|exempt_notice_text', 'label' => __('Exemption notice', 'polski'), 'type' => 'text', 'default' => __('(This product is excluded from the right of withdrawal)', 'polski')],
                    ['key' => 'polski_withdrawal|reason_label', 'label' => __('Reason field label', 'polski'), 'type' => 'text', 'default' => __('Reason for withdrawal (optional)', 'polski')],
                    ['key' => 'polski_withdrawal|submit_button_text', 'label' => __('Submit button text', 'polski'), 'type' => 'text', 'default' => __('Submit withdrawal request', 'polski')],
                    ['key' => 'polski_withdrawal|invalid_nonce_text', 'label' => __('Invalid nonce message', 'polski'), 'type' => 'text', 'default' => __('Something went wrong on our side. Please try again.', 'polski')],
                    ['key' => 'polski_withdrawal|order_not_found_text', 'label' => __('Order not found message', 'polski'), 'type' => 'text', 'default' => __('We could not find that order.', 'polski')],
                    ['key' => 'polski_withdrawal|permission_error_text', 'label' => __('Permission error message', 'polski'), 'type' => 'text', 'default' => __('You do not have permission to withdraw from this order.', 'polski')],
                    ['key' => 'polski_withdrawal|success_text', 'label' => __('Success message', 'polski'), 'type' => 'text', 'default' => __('Your withdrawal request has been received. We will send a confirmation email shortly.', 'polski')],
                    ['key' => 'polski_withdrawal|not_eligible_text', 'label' => __('Not eligible message', 'polski'), 'type' => 'text', 'default' => __('This order is not eligible for withdrawal.', 'polski')],
                    ['key' => 'polski_withdrawal|status_heading', 'label' => __('Status section heading', 'polski'), 'type' => 'text', 'default' => __('Withdrawal request', 'polski')],
                    ['key' => 'polski_withdrawal|status_label', 'label' => __('Status label', 'polski'), 'type' => 'text', 'default' => __('Status', 'polski')],
                    ['key' => 'polski_withdrawal|submitted_label', 'label' => __('Submitted label', 'polski'), 'type' => 'text', 'default' => __('Submitted', 'polski')],
                    ['key' => 'polski_withdrawal|requested_order_note', 'label' => __('Order note after request', 'polski'), 'type' => 'text', 'default' => __('The customer submitted a withdrawal request.', 'polski')],
                    ['key' => 'polski_withdrawal|confirmed_order_note', 'label' => __('Order note after confirmation', 'polski'), 'type' => 'text', 'default' => __('The withdrawal request has been confirmed.', 'polski')],
                    ['key' => 'polski_withdrawal|status_date_format', 'label' => __('Status date format', 'polski'), 'type' => 'text', 'default' => __('Y-m-d H:i', 'polski')],
                    ['key' => 'polski_withdrawal|email_subject', 'label' => __('Confirmation email subject', 'polski'), 'type' => 'text', 'default' => __('Your withdrawal request for order #{order_number} has been confirmed.', 'polski'), 'hint' => __('Variables: {order_number}, {order_date}, {withdrawal_date}', 'polski')],
                    ['key' => 'polski_withdrawal|email_heading', 'label' => __('Confirmation email heading', 'polski'), 'type' => 'text', 'default' => __('Withdrawal confirmed', 'polski')],
                    ['key' => 'polski_withdrawal|email_greeting', 'label' => __('Email greeting', 'polski'), 'type' => 'text', 'default' => __('Hello {name},', 'polski'), 'hint' => __('Variable: {name}', 'polski')],
                    ['key' => 'polski_withdrawal|email_intro_text', 'label' => __('Confirmation email body', 'polski'), 'type' => 'textarea', 'default' => __('Your withdrawal request for order #{order_number} has been confirmed.', 'polski'), 'hint' => __('Variable: {order_number}', 'polski')],
                    ['key' => 'polski_withdrawal|email_reason_label', 'label' => __('Reason label in email', 'polski'), 'type' => 'text', 'default' => __('Your reason', 'polski')],
                    ['key' => 'polski_withdrawal|email_return_instruction', 'label' => __('Return instruction in email', 'polski'), 'type' => 'textarea', 'default' => __('Please return the products to the address below within 14 days:', 'polski')],
                    ['key' => 'polski_withdrawal|email_additional_content', 'label' => __('Additional email content', 'polski'), 'type' => 'textarea', 'default' => __('Your refund will be processed within 14 days after the returned products are received.', 'polski')],
                ],
            ],
            [
                'id' => 'dispute_resolution',
                'name' => __('Rozstrzyganie sporów (ODR)', 'polski'),
                'description' => __('Wyświetlanie informacji o platformie ODR (Online Dispute Resolution) Komisji Europejskiej.', 'polski'),
                'group' => __('Prawa konsumenta', 'polski'),
                'enabled' => true,
                'icon' => 'dashicons-admin-site-alt3',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_general|dispute_resolution_text', 'label' => __('Treść informacji ODR', 'polski'), 'type' => 'textarea', 'default' => 'Platforma ODR: https://ec.europa.eu/consumers/odr'],
                    ['key' => 'polski_general|admin_pages_generated_notice', 'label' => __('Komunikat po wygenerowaniu stron prawnych', 'polski'), 'type' => 'textarea', 'default' => 'Gotowe! Wygenerowaliśmy dla Ciebie wstępne szkice stron prawnych. Przejrzyj je, dostosuj do swoich potrzeb i śmiało opublikuj.'],
                    ['key' => 'polski_general|admin_modules_saved_notice', 'label' => __('Komunikat po zapisaniu modułów', 'polski'), 'type' => 'text', 'default' => 'Moduły zapisane.'],
                    ['key' => 'polski_general|admin_setup_note_title', 'label' => __('Tytuł notki onboardingowej', 'polski'), 'type' => 'text', 'default' => 'Skonfiguruj Polski dla Twojego sklepu'],
                    ['key' => 'polski_general|admin_setup_note_content', 'label' => __('Treść notki onboardingowej', 'polski'), 'type' => 'textarea', 'default' => 'Jeszcze chwila i sklep będzie gotowy. Przejrzyj moduły, ustaw strony prawne i domknij konfigurację w panelu Polski.'],
                    ['key' => 'polski_general|admin_setup_note_button', 'label' => __('Przycisk notki onboardingowej', 'polski'), 'type' => 'text', 'default' => 'Otwórz konfigurację Polski'],
                    ['key' => 'polski_general|admin_status_active', 'label' => __('Status aktywny', 'polski'), 'type' => 'text', 'default' => 'Aktywna'],
                    ['key' => 'polski_general|admin_status_inactive', 'label' => __('Status nieaktywny', 'polski'), 'type' => 'text', 'default' => 'Wyłączona'],
                    ['key' => 'polski_general|admin_status_unconfigured', 'label' => __('Status nieskonfigurowany', 'polski'), 'type' => 'text', 'default' => 'Nieskonfigurowany'],
                    ['key' => 'polski_general|admin_legal_pages_card_title', 'label' => __('Tytuł karty stron prawnych', 'polski'), 'type' => 'text', 'default' => 'Strony prawne'],
                    ['key' => 'polski_general|admin_legal_pages_card_progress', 'label' => __('Postęp konfiguracji stron prawnych', 'polski'), 'type' => 'text', 'default' => 'Masz już za sobą {done} z {total} kroków. Znakomicie!', 'hint' => __('Zmienne: {done}, {total}', 'polski')],
                    ['key' => 'polski_general|admin_vat_card_title', 'label' => __('Tytuł karty VAT', 'polski'), 'type' => 'text', 'default' => 'Wyświetlanie podatku'],
                    ['key' => 'polski_general|admin_vat_small_business_text', 'label' => __('Tekst zwolnienia podmiotowego', 'polski'), 'type' => 'text', 'default' => 'Zwolnienie podmiotowe (art. 113)'],
                    ['key' => 'polski_general|admin_vat_standard_text', 'label' => __('Tekst standardowego VAT', 'polski'), 'type' => 'text', 'default' => 'Standardowy VAT'],
                    ['key' => 'polski_general|admin_doi_card_title', 'label' => __('Tytuł karty DOI', 'polski'), 'type' => 'text', 'default' => 'Podwójna weryfikacja (DOI)'],
                    ['key' => 'polski_general|admin_legal_pages_section_title', 'label' => __('Tytuł sekcji stron prawnych', 'polski'), 'type' => 'text', 'default' => 'Strony prawne'],
                    ['key' => 'polski_general|admin_legal_pages_table_page', 'label' => __('Nagłówek kolumny Strona', 'polski'), 'type' => 'text', 'default' => 'Strona'],
                    ['key' => 'polski_general|admin_legal_pages_table_status', 'label' => __('Nagłówek kolumny Status', 'polski'), 'type' => 'text', 'default' => 'Status'],
                    ['key' => 'polski_general|admin_legal_pages_published', 'label' => __('Status opublikowana', 'polski'), 'type' => 'text', 'default' => 'Opublikowana'],
                    ['key' => 'polski_general|admin_legal_pages_draft', 'label' => __('Status szkic', 'polski'), 'type' => 'text', 'default' => 'Szkic'],
                    ['key' => 'polski_general|admin_legal_pages_missing', 'label' => __('Status nieutworzona', 'polski'), 'type' => 'text', 'default' => 'Nie utworzona'],
                    ['key' => 'polski_general|admin_edit_button_text', 'label' => __('Tekst przycisku edycji', 'polski'), 'type' => 'text', 'default' => 'Edytuj'],
                    ['key' => 'polski_general|admin_generate_pages_empty_text', 'label' => __('Pusty stan stron prawnych', 'polski'), 'type' => 'text', 'default' => 'Nie utworzono jeszcze stron prawnych. Wygeneruj je, aby rozpocząć.'],
                    ['key' => 'polski_general|admin_generate_pages_button_text', 'label' => __('Tekst przycisku generowania stron', 'polski'), 'type' => 'text', 'default' => 'Wygeneruj strony prawne'],
                    ['key' => 'polski_general|admin_next_steps_title', 'label' => __('Tytuł kolejnych kroków', 'polski'), 'type' => 'text', 'default' => 'Kolejne kroki'],
                    ['key' => 'polski_general|admin_next_steps_publish_pages', 'label' => __('Krok - publikacja stron', 'polski'), 'type' => 'text', 'default' => 'Opublikuj swoje strony prawne, Regulamin, Politykę prywatności, Prawo odstąpienia i Reklamacje.'],
                    ['key' => 'polski_general|admin_next_steps_tax', 'label' => __('Krok - stawki VAT', 'polski'), 'type' => 'textarea', 'default' => 'Skonfiguruj <a href="%s">stawki VAT</a> w WooCommerce dla polskiego rynku, 23%%, 8%%, 5%% i 0%%.'],
                    ['key' => 'polski_general|admin_next_steps_shipping', 'label' => __('Krok - strefy wysyłki', 'polski'), 'type' => 'textarea', 'default' => 'Skonfiguruj <a href="%s">strefy wysyłki</a> dla dostaw w Polsce.'],
                    ['key' => 'polski_general|admin_next_steps_products', 'label' => __('Krok - dane produktów', 'polski'), 'type' => 'textarea', 'default' => 'Uzupełnij dane produktów, dodaj ceny jednostkowe i czasy dostawy w <a href="%s">zakładce Polski</a> dla każdego produktu.'],
                    ['key' => 'polski_general|admin_next_steps_checkout', 'label' => __('Krok - test checkoutu', 'polski'), 'type' => 'textarea', 'default' => 'Przetestuj checkout, dodaj produkt do koszyka i sprawdź checkboxy prawne oraz tekst przycisku na <a href="%s">stronie zamówienia</a>.'],
                    ['key' => 'polski_general|admin_omnibus_plugin_detected_text', 'label' => __('Status wykrytej wtyczki Omnibus', 'polski'), 'type' => 'text', 'default' => 'wykryta, dane synchronizowane'],
                    ['key' => 'polski_general|admin_omnibus_plugin_missing_text', 'label' => __('Status brakującej wtyczki Omnibus', 'polski'), 'type' => 'text', 'default' => 'niezainstalowana'],
                    ['key' => 'polski_general|admin_omnibus_no_external_text', 'label' => __('Komunikat bez zewnętrznej wtyczki Omnibus', 'polski'), 'type' => 'textarea', 'default' => 'Żadna zewnętrzna wtyczka Omnibus nie jest zainstalowana. Polski używa wbudowanego systemu śledzenia cen.'],
                    ['key' => 'polski_general|admin_omnibus_external_active_text', 'label' => __('Komunikat po wykryciu zewnętrznej wtyczki Omnibus', 'polski'), 'type' => 'textarea', 'default' => 'Zewnętrzna wtyczka wykryta. Polski korzysta z jej danych zamiast wbudowanego systemu.'],
                ],
            ],
            [
                'id' => 'email_attachments',
                'name' => 'Załączniki prawne w emailach',
                'description' => 'Dołączanie treści stron prawnych (regulamin, polityka prywatności, prawo odstąpienia) do emaili z potwierdzeniem zamówienia.',
                'group' => 'Prawa konsumenta',
                'enabled' => true,
                'icon' => 'dashicons-email',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_emails|attach_terms', 'label' => 'Dołącz Regulamin', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_emails|attach_privacy', 'label' => 'Dołącz Politykę prywatności', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_emails|attach_withdrawal', 'label' => 'Dołącz Prawo odstąpienia', 'type' => 'checkbox', 'default' => true],
                ],
            ],

            // === Informacje o produkcie ===
            [
                'id' => 'manufacturer',
                'name' => __('Producent i GPSR', 'polski'),
                'description' => __('Informacje o producencie, osoba odpowiedzialna (GPSR), dokumenty bezpieczeństwa, instrukcje bezpieczeństwa.', 'polski'),
                'group' => __('Informacje o produkcie', 'polski'),
                'enabled' => true,
                'icon' => 'dashicons-building',
                'links' => [],
                'settings' => [],
            ],
            [
                'id' => 'food_module',
                'name' => __('Żywność i suplementy', 'polski'),
                'description' => __('Tabela wartości odżywczych, alergeny, składniki, Nutri-Score, zawartość alkoholu, kraj pochodzenia, dystrybutor.', 'polski'),
                'group' => __('Informacje o produkcie', 'polski'),
                'enabled' => false,
                'icon' => 'dashicons-carrot',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_food|show_ingredients', 'label' => __('Pokazuj składniki', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_allergens', 'label' => __('Pokazuj alergeny', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_nutrients', 'label' => __('Pokazuj tabelę wartości odżywczych', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_nutri_score', 'label' => __('Pokazuj Nutri-Score', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_alcohol', 'label' => __('Pokazuj zawartość alkoholu', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_origin', 'label' => __('Pokazuj kraj pochodzenia', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_distributor', 'label' => __('Pokazuj dystrybutora', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_net_filling', 'label' => __('Pokazuj zawartość netto', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|ingredients_label', 'label' => __('Etykieta składników', 'polski'), 'type' => 'text', 'default' => 'Składniki'],
                    ['key' => 'polski_food|allergens_label', 'label' => __('Etykieta alergenów', 'polski'), 'type' => 'text', 'default' => 'Alergeny'],
                    ['key' => 'polski_food|nutrients_caption_prefix', 'label' => __('Prefix nagłówka tabeli', 'polski'), 'type' => 'text', 'default' => 'Wartości odżywcze na'],
                    ['key' => 'polski_food|nutrients_reference_unit', 'label' => __('Domyślna jednostka odniesienia', 'polski'), 'type' => 'text', 'default' => '100 g'],
                    ['key' => 'polski_food|nutrients_column_name', 'label' => __('Kolumna składnika odżywczego', 'polski'), 'type' => 'text', 'default' => 'Składnik odżywczy'],
                    ['key' => 'polski_food|nutrients_column_value', 'label' => __('Kolumna wartości', 'polski'), 'type' => 'text', 'default' => 'Wartość'],
                    ['key' => 'polski_food|nutri_score_label', 'label' => __('Etykieta Nutri-Score', 'polski'), 'type' => 'text', 'default' => 'Nutri-Score'],
                    ['key' => 'polski_food|alcohol_label', 'label' => __('Etykieta alkoholu', 'polski'), 'type' => 'text', 'default' => 'Zawartość alkoholu'],
                    ['key' => 'polski_food|alcohol_suffix', 'label' => __('Sufiks alkoholu', 'polski'), 'type' => 'text', 'default' => '% vol.'],
                    ['key' => 'polski_food|origin_label', 'label' => __('Etykieta kraju pochodzenia', 'polski'), 'type' => 'text', 'default' => 'Kraj pochodzenia'],
                    ['key' => 'polski_food|distributor_label', 'label' => __('Etykieta dystrybutora', 'polski'), 'type' => 'text', 'default' => 'Dystrybutor'],
                    ['key' => 'polski_food|net_filling_label', 'label' => __('Etykieta zawartości netto', 'polski'), 'type' => 'text', 'default' => 'Zawartość netto'],
                ],
            ],
            [
                'id' => 'power_supply',
                'name' => __('Informacje o zasilaniu', 'polski'),
                'description' => __('Dane o zużyciu energii dla urządzeń elektrycznych (etykiety energetyczne).', 'polski'),
                'group' => __('Informacje o produkcie', 'polski'),
                'enabled' => false,
                'icon' => 'dashicons-lightbulb',
                'links' => [],
                'settings' => [],
            ],

            // === Konto klienta ===
            [
                'id' => 'double_opt_in',
                'name' => __('Podwójna weryfikacja (DOI)', 'polski'),
                'description' => __('Weryfikacja adresu email przy rejestracji konta. Link aktywacyjny wysyłany emailem, blokada logowania dla nieaktywowanych kont.', 'polski'),
                'group' => __('Konto klienta', 'polski'),
                'enabled' => false,
                'icon' => 'dashicons-lock',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_doi|cleanup_days', 'label' => __('Usuń nieaktywne konta po ilu dniach', 'polski'), 'type' => 'number', 'default' => 7],
                    ['key' => 'polski_doi|login_blocked_text', 'label' => __('Komunikat blokady logowania', 'polski'), 'type' => 'text', 'default' => 'Twoje konto czeka na aktywację! Zerknij do swojej skrzynki e-mail i kliknij w przesłany przez nas link.'],
                    ['key' => 'polski_doi|invalid_link_text', 'label' => __('Komunikat błędnego linku', 'polski'), 'type' => 'text', 'default' => 'Nieprawidłowy link aktywacyjny.'],
                    ['key' => 'polski_doi|activation_success_text', 'label' => __('Komunikat po aktywacji', 'polski'), 'type' => 'text', 'default' => 'Wspaniale! Twoje konto jest już aktywowane. Możesz się teraz śmiało zalogować.'],
                    ['key' => 'polski_doi|email_subject', 'label' => __('Temat emaila', 'polski'), 'type' => 'text', 'default' => 'Aktywuj swoje konto w {site_title}', 'hint' => __('Zmienne: {site_title}', 'polski')],
                    ['key' => 'polski_doi|email_heading', 'label' => __('Nagłówek emaila', 'polski'), 'type' => 'text', 'default' => 'Potwierdź swój adres email'],
                    ['key' => 'polski_doi|email_greeting', 'label' => __('Powitanie emaila', 'polski'), 'type' => 'text', 'default' => 'Cześć {name},', 'hint' => __('Zmienne: {name}', 'polski')],
                    ['key' => 'polski_doi|email_intro_html', 'label' => __('Treść emaila HTML', 'polski'), 'type' => 'textarea', 'default' => 'Dziękujemy za założenie konta. Kliknij przycisk poniżej, aby aktywować konto:'],
                    ['key' => 'polski_doi|email_button_text', 'label' => __('Tekst przycisku emaila', 'polski'), 'type' => 'text', 'default' => 'Aktywuj konto'],
                    ['key' => 'polski_doi|email_link_intro', 'label' => __('Tekst linku zapasowego', 'polski'), 'type' => 'text', 'default' => 'Jeśli wolisz, skopiuj i wklej ten link do przeglądarki:'],
                    ['key' => 'polski_doi|email_intro_plain', 'label' => __('Treść emaila plain text', 'polski'), 'type' => 'textarea', 'default' => 'Dziękujemy za założenie konta. Odwiedź poniższy link, aby aktywować konto:'],
                    ['key' => 'polski_doi|additional_content', 'label' => __('Dodatkowa treść emaila', 'polski'), 'type' => 'textarea', 'default' => 'Jeśli to nie Ty zakładałeś/-aś u nas konto, nie przejmuj się i po prostu wykasuj tę wiadomość.'],
                ],
            ],

            // === Sprzedaż i B2B ===
            [
                'id' => 'ajax_search',
                'name' => __('Wyszukiwarka AJAX', 'polski'),
                'description' => __('Szybkie podpowiedzi produktów w trakcie pisania, z obsługą SKU, kategorii i lekkim frontem przyjaznym dla web vitals.', 'polski'),
                'group' => __('Sprzedaż i B2B', 'polski'),
                'enabled' => false,
                'icon' => 'dashicons-search',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_search|min_chars', 'label' => __('Minimalna liczba znaków', 'polski'), 'type' => 'number', 'default' => 2],
                    ['key' => 'polski_search|limit', 'label' => __('Liczba wyników', 'polski'), 'type' => 'number', 'default' => 6],
                    ['key' => 'polski_search|debounce_ms', 'label' => __('Opóźnienie zapytania (ms)', 'polski'), 'type' => 'number', 'default' => 180],
                    ['key' => 'polski_search|show_submit_button', 'label' => __('Pokazuj przycisk wyszukiwania', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|show_image', 'label' => __('Pokazuj miniatury', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|show_price', 'label' => __('Pokazuj ceny', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|show_sku', 'label' => __('Pokazuj SKU', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|show_view_all_link', 'label' => __('Pokazuj link do pełnych wyników', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|search_sku', 'label' => __('Szukaj po SKU', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|search_categories', 'label' => __('Szukaj po kategoriach', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|include_out_of_stock', 'label' => __('Uwzględniaj produkty bez stanu', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_search|search_label', 'label' => __('Etykieta pola wyszukiwania', 'polski'), 'type' => 'text', 'default' => 'Szukaj produktów'],
                    ['key' => 'polski_search|results_label', 'label' => __('Etykieta wyników', 'polski'), 'type' => 'text', 'default' => 'Wyniki wyszukiwania produktów'],
                    ['key' => 'polski_search|sku_label', 'label' => __('Etykieta SKU', 'polski'), 'type' => 'text', 'default' => 'SKU'],
                    ['key' => 'polski_search|placeholder', 'label' => __('Placeholder', 'polski'), 'type' => 'text', 'default' => 'Szukaj produktów, kodów SKU lub kategorii'],
                    ['key' => 'polski_search|submit_button_text', 'label' => __('Tekst przycisku', 'polski'), 'type' => 'text', 'default' => 'Szukaj'],
                    ['key' => 'polski_search|no_results_text', 'label' => __('Brak wyników', 'polski'), 'type' => 'text', 'default' => 'Brak wyników dla podanego zapytania.'],
                    ['key' => 'polski_search|view_all_text', 'label' => __('Zobacz wszystkie wyniki', 'polski'), 'type' => 'text', 'default' => 'Zobacz wszystkie wyniki'],
                ],
            ],

            // === Merchandising ===
            [
                'id' => 'brands',
                'name' => __('Marki', 'polski'),
                'description' => __('Obsługa marek produktowych niezależnie od producenta, z widokiem na produkcie i listach oraz własną taksonomią.', 'polski'),
                'group' => __('Merchandising', 'polski'),
                'enabled' => false,
                'icon' => 'dashicons-tag',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_brand|show_on_single', 'label' => __('Pokazuj na stronie produktu', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_brand|show_on_loop', 'label' => __('Pokazuj na listach produktów', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_brand|label', 'label' => __('Etykieta', 'polski'), 'type' => 'text', 'default' => 'Marka'],
                    ['key' => 'polski_brand|show_label', 'label' => __('Pokazuj etykietę', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_brand|separator', 'label' => __('Separator marek', 'polski'), 'type' => 'text', 'default' => ', '],
                    ['key' => 'polski_brand|link_terms', 'label' => __('Linkuj do archiwum marki', 'polski'), 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'ajax_filters',
                'name' => __('Filtry AJAX', 'polski'),
                'description' => __('Filtrowanie list produktów bez przeładowania strony, z kategoriami, markami, ceną, stanem magazynowym, promocją i atrybutami.', 'polski'),
                'group' => __('Merchandising', 'polski'),
                'enabled' => false,
                'icon' => 'dashicons-filter',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_filters|show_on_shop', 'label' => __('Pokazuj na archiwach sklepu', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_title', 'label' => __('Pokazuj nagłówek formularza', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_categories', 'label' => __('Filtr kategorii', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_brands', 'label' => __('Filtr marek', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_price', 'label' => __('Filtr ceny', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_stock', 'label' => __('Filtr dostępności', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_sale', 'label' => __('Filtr promocji', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_attributes', 'label' => __('Filtry atrybutów', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|max_attribute_taxonomies', 'label' => __('Maks. liczba atrybutów', 'polski'), 'type' => 'number', 'default' => 4],
                    ['key' => 'polski_filters|title', 'label' => __('Nagłówek', 'polski'), 'type' => 'text', 'default' => 'Filtry produktów'],
                    ['key' => 'polski_filters|category_label', 'label' => __('Etykieta kategorii', 'polski'), 'type' => 'text', 'default' => 'Kategoria'],
                    ['key' => 'polski_filters|category_all_text', 'label' => __('Tekst wszystkich kategorii', 'polski'), 'type' => 'text', 'default' => 'Wszystkie'],
                    ['key' => 'polski_filters|brand_label', 'label' => __('Etykieta marki', 'polski'), 'type' => 'text', 'default' => 'Marka'],
                    ['key' => 'polski_filters|brand_all_text', 'label' => __('Tekst wszystkich marek', 'polski'), 'type' => 'text', 'default' => 'Wszystkie'],
                    ['key' => 'polski_filters|min_price_label', 'label' => __('Etykieta ceny od', 'polski'), 'type' => 'text', 'default' => 'Cena od'],
                    ['key' => 'polski_filters|max_price_label', 'label' => __('Etykieta ceny do', 'polski'), 'type' => 'text', 'default' => 'Cena do'],
                    ['key' => 'polski_filters|stock_label', 'label' => __('Etykieta dostępności', 'polski'), 'type' => 'text', 'default' => 'Dostępność'],
                    ['key' => 'polski_filters|stock_any_text', 'label' => __('Tekst dowolnej dostępności', 'polski'), 'type' => 'text', 'default' => 'Dowolna'],
                    ['key' => 'polski_filters|stock_instock_text', 'label' => __('Tekst dostępnego produktu', 'polski'), 'type' => 'text', 'default' => 'Dostępne od ręki'],
                    ['key' => 'polski_filters|sale_label', 'label' => __('Etykieta promocji', 'polski'), 'type' => 'text', 'default' => 'Promocje'],
                    ['key' => 'polski_filters|attribute_any_text', 'label' => __('Tekst dowolnej wartości atrybutu', 'polski'), 'type' => 'text', 'default' => 'Dowolny'],
                    ['key' => 'polski_filters|show_reset_link', 'label' => __('Pokazuj link resetu', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|submit_text', 'label' => __('Tekst przycisku', 'polski'), 'type' => 'text', 'default' => 'Filtruj'],
                    ['key' => 'polski_filters|reset_text', 'label' => __('Tekst resetu', 'polski'), 'type' => 'text', 'default' => 'Wyczyść filtry'],
                ],
            ],
            [
                'id' => 'wishlist',
                'name' => __('Lista życzeń', 'polski'),
                'description' => __('Zapisywanie ulubionych produktów dla gości i zalogowanych, z listą w koncie klienta i AJAX-owym dodawaniem/usuwaniem.', 'polski'),
                'group' => __('Merchandising', 'polski'),
                'enabled' => false,
                'icon' => 'dashicons-heart',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_wishlist|allow_guests', 'label' => __('Pozwól gościom zapisywać ulubione', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_on_single', 'label' => __('Pokazuj na stronie produktu', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_on_loop', 'label' => __('Pokazuj na listingach', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_in_account', 'label' => __('Pokazuj w Moim koncie', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_title', 'label' => __('Pokazuj tytuł listy', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_product_image', 'label' => __('Pokazuj zdjęcia produktów', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_product_name', 'label' => __('Pokazuj nazwy produktów', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_price', 'label' => __('Pokazuj cenę w liście', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_add_to_cart', 'label' => __('Pokazuj przycisk koszyka w liście', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_remove_button', 'label' => __('Pokazuj przycisk usuwania w liście', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|grid_columns', 'label' => __('Liczba kolumn w liście', 'polski'), 'type' => 'number', 'default' => 4],
                    ['key' => 'polski_wishlist|account_label', 'label' => __('Etykieta w Moim koncie', 'polski'), 'type' => 'text', 'default' => 'Ulubione'],
                    ['key' => 'polski_wishlist|title', 'label' => __('Tytuł listy', 'polski'), 'type' => 'text', 'default' => 'Twoje ulubione produkty'],
                    ['key' => 'polski_wishlist|account_intro_text', 'label' => __('Opis sekcji', 'polski'), 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_wishlist|button_add_text', 'label' => __('Tekst dodawania', 'polski'), 'type' => 'text', 'default' => 'Dodaj do ulubionych'],
                    ['key' => 'polski_wishlist|button_remove_text', 'label' => __('Tekst usuwania', 'polski'), 'type' => 'text', 'default' => 'Usuń z ulubionych'],
                    ['key' => 'polski_wishlist|login_required_text', 'label' => __('Komunikat logowania', 'polski'), 'type' => 'text', 'default' => 'Zaloguj się, aby korzystać z listy życzeń.'],
                    ['key' => 'polski_wishlist|product_not_found_text', 'label' => __('Komunikat braku produktu', 'polski'), 'type' => 'text', 'default' => 'Nie znaleziono produktu.'],
                    ['key' => 'polski_wishlist|empty_text', 'label' => __('Pusty stan', 'polski'), 'type' => 'text', 'default' => 'Lista ulubionych jest pusta.'],
                ],
            ],
            [
                'id' => 'compare',
                'name' => __('Porównanie produktów', 'polski'),
                'description' => __('Porównywarka produktów z tabelą cech, wyróżnianiem różnic, obsługą gości i klientów oraz widokiem w Moim koncie.', 'polski'),
                'group' => __('Merchandising', 'polski'),
                'enabled' => false,
                'icon' => 'dashicons-randomize',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_compare|allow_guests', 'label' => __('Pozwól gościom porównywać produkty', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_on_single', 'label' => __('Pokazuj na stronie produktu', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_on_loop', 'label' => __('Pokazuj na listingach', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_in_account', 'label' => __('Pokazuj w Moim koncie', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_product_image', 'label' => __('Pokazuj zdjęcia produktów', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_add_to_cart', 'label' => __('Pokazuj przycisk koszyka', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_remove_button', 'label' => __('Pokazuj przycisk usuwania', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|account_label', 'label' => __('Etykieta w Moim koncie', 'polski'), 'type' => 'text', 'default' => 'Porównanie'],
                    ['key' => 'polski_compare|title', 'label' => __('Tytuł porównania', 'polski'), 'type' => 'text', 'default' => 'Porównanie produktów'],
                    ['key' => 'polski_compare|max_items', 'label' => __('Maksymalna liczba produktów', 'polski'), 'type' => 'number', 'default' => 4],
                    ['key' => 'polski_compare|button_add_text', 'label' => __('Tekst dodawania', 'polski'), 'type' => 'text', 'default' => 'Dodaj do porównania'],
                    ['key' => 'polski_compare|button_remove_text', 'label' => __('Tekst usuwania', 'polski'), 'type' => 'text', 'default' => 'Usuń z porównania'],
                    ['key' => 'polski_compare|compare_link_text', 'label' => __('Tekst linku do porównania', 'polski'), 'type' => 'text', 'default' => 'Porównaj produkty'],
                    ['key' => 'polski_compare|clear_text', 'label' => __('Tekst czyszczenia', 'polski'), 'type' => 'text', 'default' => 'Wyczyść porównanie'],
                    ['key' => 'polski_compare|feature_label', 'label' => __('Nagłówek kolumny cech', 'polski'), 'type' => 'text', 'default' => 'Cecha'],
                    ['key' => 'polski_compare|differences_toggle_text', 'label' => __('Etykieta filtra różnic', 'polski'), 'type' => 'text', 'default' => 'Pokazuj tylko różnice'],
                    ['key' => 'polski_compare|login_required_text', 'label' => __('Komunikat logowania', 'polski'), 'type' => 'text', 'default' => 'Zaloguj się, aby korzystać z porównania produktów.'],
                    ['key' => 'polski_compare|product_not_found_text', 'label' => __('Komunikat braku produktu', 'polski'), 'type' => 'text', 'default' => 'Nie znaleziono produktu.'],
                    ['key' => 'polski_compare|limit_notice_text', 'label' => __('Komunikat limitu', 'polski'), 'type' => 'text', 'default' => 'Możesz porównać maksymalnie {limit} produkty jednocześnie. Najstarszy wpis został zastąpiony automatycznie.', 'hint' => __('Zmienna: {limit}', 'polski')],
                    ['key' => 'polski_compare|clear_error_text', 'label' => __('Komunikat błędu czyszczenia', 'polski'), 'type' => 'text', 'default' => 'Nie możesz wyczyścić porównania.'],
                    ['key' => 'polski_compare|intro_text', 'label' => __('Opis sekcji', 'polski'), 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_compare|empty_text', 'label' => __('Pusty stan', 'polski'), 'type' => 'text', 'default' => 'Lista porównawcza jest pusta.'],
                    ['key' => 'polski_compare|highlight_differences', 'label' => __('Wyróżniaj różnice', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_only_differences', 'label' => __('Domyślnie pokazuj tylko różnice', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_compare|price_label', 'label' => __('Etykieta ceny', 'polski'), 'type' => 'text', 'default' => 'Cena'],
                    ['key' => 'polski_compare|unit_price_label', 'label' => __('Etykieta ceny jednostkowej', 'polski'), 'type' => 'text', 'default' => 'Cena jednostkowa'],
                    ['key' => 'polski_compare|sku_label', 'label' => __('Etykieta SKU', 'polski'), 'type' => 'text', 'default' => 'SKU'],
                    ['key' => 'polski_compare|availability_label', 'label' => __('Etykieta dostępności', 'polski'), 'type' => 'text', 'default' => 'Dostępność'],
                    ['key' => 'polski_compare|delivery_time_label', 'label' => __('Etykieta czasu dostawy', 'polski'), 'type' => 'text', 'default' => 'Czas dostawy'],
                    ['key' => 'polski_compare|brand_label', 'label' => __('Etykieta marki', 'polski'), 'type' => 'text', 'default' => 'Marka'],
                    ['key' => 'polski_compare|manufacturer_label', 'label' => __('Etykieta producenta', 'polski'), 'type' => 'text', 'default' => 'Producent'],
                    ['key' => 'polski_compare|gtin_label', 'label' => __('Etykieta GTIN / EAN', 'polski'), 'type' => 'text', 'default' => 'GTIN / EAN'],
                    ['key' => 'polski_compare|description_label', 'label' => __('Etykieta krótkiego opisu', 'polski'), 'type' => 'text', 'default' => 'Krótki opis'],
                    ['key' => 'polski_compare|show_description', 'label' => __('Pokazuj krótki opis', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_attributes', 'label' => __('Pokazuj atrybuty produktu', 'polski'), 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'quick_view',
                'name' => __('Szybki podgląd', 'polski'),
                'description' => __('Lekki modal produktu na listingach, z obsługą wariantów, cen, galerii i podstawowych informacji zakupowych.', 'polski'),
                'group' => __('Merchandising', 'polski'),
                'enabled' => false,
                'icon' => 'dashicons-visibility',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_quick_view|show_on_loop', 'label' => __('Pokazuj na listingach', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|button_text', 'label' => __('Tekst przycisku', 'polski'), 'type' => 'text', 'default' => 'Szybki podgląd'],
                    ['key' => 'polski_quick_view|modal_title', 'label' => __('Etykieta modala', 'polski'), 'type' => 'text', 'default' => 'Szybki podgląd produktu'],
                    ['key' => 'polski_quick_view|close_label', 'label' => __('Etykieta zamknięcia', 'polski'), 'type' => 'text', 'default' => 'Zamknij'],
                    ['key' => 'polski_quick_view|loading_text', 'label' => __('Tekst ładowania', 'polski'), 'type' => 'text', 'default' => 'Ładowanie produktu...'],
                    ['key' => 'polski_quick_view|error_text', 'label' => __('Tekst błędu AJAX', 'polski'), 'type' => 'text', 'default' => 'Nie udało się wczytać podglądu produktu.'],
                    ['key' => 'polski_quick_view|product_not_found_text', 'label' => __('Tekst braku produktu', 'polski'), 'type' => 'text', 'default' => 'Nie znaleziono produktu.'],
                    ['key' => 'polski_quick_view|sku_label', 'label' => __('Etykieta SKU', 'polski'), 'type' => 'text', 'default' => 'SKU'],
                    ['key' => 'polski_quick_view|show_modal_label', 'label' => __('Pokazuj tytuł modala w treści', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_close_button', 'label' => __('Pokazuj przycisk zamknięcia', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_title', 'label' => __('Pokazuj nazwę produktu', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_image', 'label' => __('Pokazuj zdjęcie główne', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_gallery', 'label' => __('Pokazuj mini galerię', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_price', 'label' => __('Pokazuj cenę', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_unit_price', 'label' => __('Pokazuj cenę jednostkową', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_sku', 'label' => __('Pokazuj SKU', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_delivery_time', 'label' => __('Pokazuj czas dostawy', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_brand', 'label' => __('Pokazuj markę', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_manufacturer', 'label' => __('Pokazuj producenta', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_short_description', 'label' => __('Pokazuj krótki opis', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_add_to_cart', 'label' => __('Pokazuj formularz zakupu', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_view_product_link', 'label' => __('Pokazuj link do pełnej karty', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|view_product_text', 'label' => __('Tekst linku do produktu', 'polski'), 'type' => 'text', 'default' => 'Zobacz pełną kartę produktu'],
                    ['key' => 'polski_quick_view|view_product_target', 'label' => __('Jak otwierać pełną kartę', 'polski'), 'type' => 'select', 'default' => 'same_tab', 'options' => ['same_tab' => __('W tej samej karcie', 'polski'), 'new_tab' => __('W nowej karcie', 'polski')]],
                    ['key' => 'polski_quick_view|show_backdrop_close', 'label' => __('Zamykaj po kliknięciu tła', 'polski'), 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'badge_management',
                'name' => __('Badge Management', 'polski'),
                'description' => __('Merchandisingowe badge na produkcie i listingu, z automatycznymi warunkami i ręcznymi wyróżnieniami per produkt.', 'polski'),
                'group' => __('Merchandising', 'polski'),
                'enabled' => false,
                'icon' => 'dashicons-awards',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_badges|show_on_single', 'label' => __('Pokazuj na stronie produktu', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|show_on_loop', 'label' => __('Pokazuj na listingach', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|show_manual_badge', 'label' => __('Pokazuj badge ręczny', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|manual_badge_style', 'label' => __('Domyślny styl badge ręcznego', 'polski'), 'type' => 'select', 'default' => 'accent', 'options' => ['accent' => 'Accent', 'neutral' => 'Neutral', 'warning' => 'Warning', 'success' => 'Success']],
                    ['key' => 'polski_badges|show_secondary_badge', 'label' => __('Pokazuj badge dodatkowy', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|secondary_badge_style', 'label' => __('Styl badge dodatkowego', 'polski'), 'type' => 'select', 'default' => 'neutral', 'options' => ['accent' => 'Accent', 'neutral' => 'Neutral', 'warning' => 'Warning', 'success' => 'Success']],
                    ['key' => 'polski_badges|shape', 'label' => __('Kształt badge', 'polski'), 'type' => 'select', 'default' => 'pill', 'options' => ['pill' => 'Pill', 'rounded' => 'Rounded']],
                    ['key' => 'polski_badges|uppercase', 'label' => __('Wielkie litery', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_badges|max_badges_single', 'label' => __('Maks. badge na stronie produktu', 'polski'), 'type' => 'number', 'default' => 4],
                    ['key' => 'polski_badges|max_badges_loop', 'label' => __('Maks. badge na listingach', 'polski'), 'type' => 'number', 'default' => 3],
                    ['key' => 'polski_badges|show_sale_badge', 'label' => __('Badge promocji', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|sale_badge_text', 'label' => __('Tekst badge promocji', 'polski'), 'type' => 'text', 'default' => 'Promocja'],
                    ['key' => 'polski_badges|show_new_badge', 'label' => __('Badge nowości', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|new_badge_text', 'label' => __('Tekst badge nowości', 'polski'), 'type' => 'text', 'default' => 'Nowość'],
                    ['key' => 'polski_badges|newness_days', 'label' => __('Nowość przez ile dni', 'polski'), 'type' => 'number', 'default' => 30],
                    ['key' => 'polski_badges|show_low_stock_badge', 'label' => __('Badge niskiego stanu', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|low_stock_badge_text', 'label' => __('Tekst badge niskiego stanu', 'polski'), 'type' => 'text', 'default' => 'Ostatnie sztuki'],
                    ['key' => 'polski_badges|low_stock_threshold', 'label' => __('Próg niskiego stanu', 'polski'), 'type' => 'number', 'default' => 3],
                    ['key' => 'polski_badges|show_bestseller_badge', 'label' => __('Badge bestseller', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|bestseller_badge_text', 'label' => __('Tekst badge bestseller', 'polski'), 'type' => 'text', 'default' => 'Bestseller'],
                    ['key' => 'polski_badges|bestseller_threshold', 'label' => __('Próg bestselleru (sprzedaż)', 'polski'), 'type' => 'number', 'default' => 25],
                ],
            ],
            [
                'id' => 'tab_manager',
                'name' => __('Tab Manager', 'polski'),
                'description' => __('Dodatkowe zakładki produktu z treścią per produkt oraz zakładkami globalnymi dla dostawy, zwrotów i informacji handlowych.', 'polski'),
                'group' => __('Merchandising', 'polski'),
                'enabled' => false,
                'icon' => 'dashicons-index-card',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_tabs|enable_global_shipping_tab', 'label' => __('Globalna zakładka dostawy', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_tabs|shipping_tab_title', 'label' => __('Tytuł zakładki dostawy', 'polski'), 'type' => 'text', 'default' => 'Dostawa i płatność'],
                    ['key' => 'polski_tabs|shipping_tab_content', 'label' => __('Treść zakładki dostawy', 'polski'), 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_tabs|shipping_tab_priority', 'label' => __('Priorytet zakładki dostawy', 'polski'), 'type' => 'number', 'default' => 47],
                    ['key' => 'polski_tabs|enable_global_returns_tab', 'label' => __('Globalna zakładka zwrotów', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_tabs|returns_tab_title', 'label' => __('Tytuł zakładki zwrotów', 'polski'), 'type' => 'text', 'default' => 'Zwroty i reklamacje'],
                    ['key' => 'polski_tabs|returns_tab_content', 'label' => __('Treść zakładki zwrotów', 'polski'), 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_tabs|returns_tab_priority', 'label' => __('Priorytet zakładki zwrotów', 'polski'), 'type' => 'number', 'default' => 48],
                    ['key' => 'polski_tabs|enable_product_tab_1', 'label' => __('Włącz pierwszy tab produktowy', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_tabs|product_tab_1_priority', 'label' => __('Priorytet pierwszego tabu produktowego', 'polski'), 'type' => 'number', 'default' => 45],
                    ['key' => 'polski_tabs|enable_product_tab_2', 'label' => __('Włącz drugi tab produktowy', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_tabs|product_tab_2_priority', 'label' => __('Priorytet drugiego tabu produktowego', 'polski'), 'type' => 'number', 'default' => 46],
                ],
            ],
            [
                'id' => 'featured_video',
                'name' => __('Featured Video', 'polski'),
                'description' => __('Wideo produktowe na karcie produktu, osadzone z YouTube, Vimeo albo jako lokalny plik MP4 w sekcji mediów.', 'polski'),
                'group' => __('Merchandising', 'polski'),
                'enabled' => false,
                'icon' => 'dashicons-video-alt3',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_featured_video|show_on_single', 'label' => __('Pokazuj na stronie produktu', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_featured_video|position', 'label' => __('Pozycja', 'polski'), 'type' => 'select', 'default' => 'after_gallery', 'options' => ['after_gallery' => __('Pod galerią', 'polski'), 'before_summary' => __('Przed podsumowaniem produktu', 'polski')]],
                    ['key' => 'polski_featured_video|title', 'label' => __('Nagłówek sekcji', 'polski'), 'type' => 'text', 'default' => 'Zobacz produkt w użyciu'],
                    ['key' => 'polski_featured_video|intro_text', 'label' => __('Opis sekcji', 'polski'), 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_featured_video|show_title', 'label' => __('Pokazuj nagłówek sekcji', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_featured_video|show_intro', 'label' => __('Pokazuj opis sekcji', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_featured_video|autoplay', 'label' => __('Autoplay dla wspieranych osadzeń', 'polski'), 'type' => 'checkbox', 'default' => false],
                ],
            ],
            [
                'id' => 'gallery_zoom',
                'name' => __('Gallery & Zoom', 'polski'),
                'description' => __('Lekki zoom zdjęć produktowych i prosty lightbox galerii bez zewnętrznych bibliotek sliderowych.', 'polski'),
                'group' => __('Merchandising', 'polski'),
                'enabled' => false,
                'icon' => 'dashicons-format-gallery',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_gallery_zoom|enable_zoom', 'label' => __('Włącz zoom na hover', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_gallery_zoom|zoom_scale', 'label' => __('Skala zoomu', 'polski'), 'type' => 'number', 'default' => 1.45],
                    ['key' => 'polski_gallery_zoom|enable_lightbox', 'label' => __('Włącz lightbox po kliknięciu', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_gallery_zoom|dialog_label', 'label' => __('Etykieta okna lightbox', 'polski'), 'type' => 'text', 'default' => 'Podgląd galerii produktu'],
                    ['key' => 'polski_gallery_zoom|close_label', 'label' => __('Etykieta zamknięcia', 'polski'), 'type' => 'text', 'default' => 'Zamknij podgląd galerii'],
                    ['key' => 'polski_gallery_zoom|show_backdrop_close', 'label' => __('Zamykaj po kliknięciu tła', 'polski'), 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'product_slider_carousel',
                'name' => __('Product Slider Carousel', 'polski'),
                'description' => __('Lekki slider produktowy oparty o scroll-snap, z produktami powiązanymi, promocyjnymi lub wyróżnionymi.', 'polski'),
                'group' => __('Merchandising', 'polski'),
                'enabled' => false,
                'icon' => 'dashicons-images-alt2',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_slider|show_on_single', 'label' => __('Pokazuj na stronie produktu', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_slider|source', 'label' => __('Źródło produktów', 'polski'), 'type' => 'select', 'default' => 'related', 'options' => ['related' => __('Powiązane', 'polski'), 'upsell' => __('Upsell', 'polski'), 'sale' => __('Promocje', 'polski'), 'featured' => __('Wyróżnione', 'polski')]],
                    ['key' => 'polski_slider|title', 'label' => __('Nagłówek sekcji', 'polski'), 'type' => 'text', 'default' => 'Polecane produkty'],
                    ['key' => 'polski_slider|limit', 'label' => __('Liczba produktów', 'polski'), 'type' => 'number', 'default' => 8],
                    ['key' => 'polski_slider|show_title', 'label' => __('Pokazuj nagłówek sekcji', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_slider|show_intro_text', 'label' => __('Pokazuj opis sekcji', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_slider|intro_text', 'label' => __('Opis sekcji', 'polski'), 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_slider|show_image', 'label' => __('Pokazuj zdjęcia produktów', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_slider|show_price', 'label' => __('Pokazuj ceny', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_slider|show_name', 'label' => __('Pokazuj nazwę produktu', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_slider|show_add_to_cart', 'label' => __('Pokazuj przycisk koszyka', 'polski'), 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_slider|show_view_all_link', 'label' => __('Pokazuj link "zobacz wszystkie"', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_slider|show_empty_state', 'label' => __('Pokazuj pusty stan bez produktów', 'polski'), 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_slider|empty_text', 'label' => __('Tekst pustego stanu', 'polski'), 'type' => 'text', 'default' => 'Brak produktów do wyświetlenia w tej sekcji.'],
                    ['key' => 'polski_slider|view_all_text', 'label' => __('Tekst linku "zobacz wszystkie"', 'polski'), 'type' => 'text', 'default' => 'Zobacz wszystkie wyniki'],
                    ['key' => 'polski_slider|view_all_target', 'label' => __('Jak otwierać link "zobacz wszystkie"', 'polski'), 'type' => 'select', 'default' => 'same_tab', 'options' => ['same_tab' => __('W tej samej karcie', 'polski'), 'new_tab' => __('W nowej karcie', 'polski')]],
                ],
            ],
            [
                'id' => 'waitlist',
                'name' => 'Waitlist',
                'description' => 'Lista oczekujących dla produktów niedostępnych, z zapisem email i automatycznymi powiadomieniami po powrocie stanu.',
                'group' => 'Merchandising',
                'enabled' => false,
                'icon' => 'dashicons-email-alt',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_waitlist|show_on_single', 'label' => 'Pokazuj na stronie produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_waitlist|allow_guests', 'label' => 'Pozwól gościom się zapisywać', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_waitlist|title', 'label' => 'Nagłówek', 'type' => 'text', 'default' => 'Powiadom mnie o dostępności'],
                    ['key' => 'polski_waitlist|show_title', 'label' => 'Pokazuj nagłówek', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_waitlist|intro_text', 'label' => 'Opis', 'type' => 'textarea', 'default' => 'Zostaw adres email, a damy znać, gdy produkt wróci na stan.'],
                    ['key' => 'polski_waitlist|show_intro', 'label' => 'Pokazuj opis', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_waitlist|email_label', 'label' => 'Etykieta pola email', 'type' => 'text', 'default' => 'Adres email'],
                    ['key' => 'polski_waitlist|email_placeholder', 'label' => 'Placeholder pola email', 'type' => 'text', 'default' => 'Twój adres email'],
                    ['key' => 'polski_waitlist|button_text', 'label' => 'Tekst przycisku', 'type' => 'text', 'default' => 'Powiadom mnie'],
                    ['key' => 'polski_waitlist|success_text', 'label' => 'Komunikat sukcesu', 'type' => 'text', 'default' => 'Dziękujemy. Zapisaliśmy Cię na listę oczekujących.'],
                    ['key' => 'polski_waitlist|privacy_label', 'label' => 'Treść zgody', 'type' => 'text', 'default' => 'Akceptuję kontakt email w sprawie dostępności tego produktu.'],
                    ['key' => 'polski_waitlist|product_not_found_text', 'label' => 'Błąd braku produktu', 'type' => 'text', 'default' => 'Nie znaleziono produktu.'],
                    ['key' => 'polski_waitlist|disabled_text', 'label' => 'Błąd niedostępnej waitlisty', 'type' => 'text', 'default' => 'Lista oczekujących jest niedostępna dla tego produktu.'],
                    ['key' => 'polski_waitlist|invalid_email_text', 'label' => 'Błąd niepoprawnego emaila', 'type' => 'text', 'default' => 'Podaj poprawny adres email.'],
                    ['key' => 'polski_waitlist|privacy_error_text', 'label' => 'Błąd braku zgody', 'type' => 'text', 'default' => 'Musisz zaakceptować zgodę na kontakt email.'],
                    ['key' => 'polski_waitlist|login_required_text', 'label' => 'Błąd wymaganego logowania', 'type' => 'text', 'default' => 'Zaloguj się, aby zapisać się na listę oczekujących.'],
                    ['key' => 'polski_waitlist|notify_subject', 'label' => 'Temat emaila', 'type' => 'text', 'default' => 'Produkt ponownie dostępny - {product_name}'],
                    ['key' => 'polski_waitlist|notify_intro_text', 'label' => 'Treść intro emaila', 'type' => 'text', 'default' => 'Produkt {product_name} jest ponownie dostępny.', 'hint' => 'Zmienne: {product_name}'],
                    ['key' => 'polski_waitlist|notify_outro_text', 'label' => 'Treść końcowa emaila', 'type' => 'text', 'default' => 'Jeśli nie chcesz już otrzymywać takich wiadomości, po prostu zignoruj ten email.'],
                ],
            ],
            [
                'id' => 'infinite_scroll',
                'name' => 'Infinite Scrolling',
                'description' => 'Lekki loading kolejnych produktów na archiwach WooCommerce, z trybem przycisku lub automatycznym doładowaniem.',
                'group' => 'Merchandising',
                'enabled' => false,
                'icon' => 'dashicons-update-alt',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_infinite_scroll|mode', 'label' => 'Tryb działania', 'type' => 'select', 'default' => 'button', 'options' => ['button' => 'Przycisk', 'auto' => 'Automatyczny scroll']],
                    ['key' => 'polski_infinite_scroll|button_text', 'label' => 'Tekst przycisku', 'type' => 'text', 'default' => 'Załaduj więcej produktów'],
                    ['key' => 'polski_infinite_scroll|loading_text', 'label' => 'Tekst ładowania', 'type' => 'text', 'default' => 'Ładowanie produktów...'],
                    ['key' => 'polski_infinite_scroll|error_text', 'label' => 'Tekst błędu ładowania', 'type' => 'text', 'default' => 'Nie udało się załadować kolejnych produktów.'],
                    ['key' => 'polski_infinite_scroll|end_text', 'label' => 'Tekst końca listy', 'type' => 'text', 'default' => 'To już wszystkie produkty.'],
                    ['key' => 'polski_infinite_scroll|show_status', 'label' => 'Pokazuj komunikaty statusu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_infinite_scroll|show_button_in_auto_mode', 'label' => 'Pokazuj przycisk także w trybie auto', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_infinite_scroll|show_on_shop', 'label' => 'Pokazuj na stronie sklepu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_infinite_scroll|show_on_taxonomies', 'label' => 'Pokazuj na kategoriach i tagach', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_infinite_scroll|auto_after_pages', 'label' => 'Ile stron auto-doładować', 'type' => 'number', 'default' => 0],
                ],
            ],
            [
                'id' => 'popup',
                'name' => 'WooCommerce Popup',
                'description' => 'Lekki popup promocyjny lub leadowy z kontrolą częstotliwości, opóźnienia i miejsc wyświetlania.',
                'group' => 'Merchandising',
                'enabled' => false,
                'icon' => 'dashicons-megaphone',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_popup|title', 'label' => 'Nagłówek', 'type' => 'text', 'default' => 'Masz pytanie o produkt lub warunki handlowe?'],
                    ['key' => 'polski_popup|content', 'label' => 'Treść', 'type' => 'textarea', 'default' => 'Skontaktuj się z nami, jeśli chcesz dostać ofertę B2B, rabat dla hurtu albo pomoc z doborem produktu.'],
                    ['key' => 'polski_popup|cta_text', 'label' => 'Tekst CTA', 'type' => 'text', 'default' => 'Przejdź do kontaktu'],
                    ['key' => 'polski_popup|show_title', 'label' => 'Pokazuj nagłówek', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_popup|show_close_button', 'label' => 'Pokazuj przycisk zamknięcia', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_popup|close_label', 'label' => 'Etykieta zamknięcia', 'type' => 'text', 'default' => 'Zamknij popup'],
                    ['key' => 'polski_popup|dialog_label', 'label' => 'Etykieta okna dialogowego', 'type' => 'text', 'default' => 'Popup promocyjny'],
                    ['key' => 'polski_popup|cta_url', 'label' => 'URL CTA', 'type' => 'text', 'default' => ''],
                    ['key' => 'polski_popup|fallback_cta_url', 'label' => 'Fallback URL CTA', 'type' => 'select', 'default' => 'account', 'options' => ['account' => 'Moje konto', 'home' => 'Strona główna', 'shop' => 'Sklep']],
                    ['key' => 'polski_popup|show_cta', 'label' => 'Pokazuj przycisk CTA', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_popup|cta_target', 'label' => 'Jak otwierać CTA', 'type' => 'select', 'default' => 'same_tab', 'options' => ['same_tab' => 'W tej samej karcie', 'new_tab' => 'W nowej karcie']],
                    ['key' => 'polski_popup|show_backdrop_close', 'label' => 'Zamykaj po kliknięciu tła', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_popup|show_on_home', 'label' => 'Strona główna', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_popup|show_on_shop', 'label' => 'Sklep i archiwa', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_popup|show_on_product', 'label' => 'Strona produktu', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_popup|show_on_cart', 'label' => 'Koszyk', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_popup|show_on_checkout', 'label' => 'Checkout', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_popup|delay_seconds', 'label' => 'Opóźnienie w sekundach', 'type' => 'number', 'default' => 4],
                    ['key' => 'polski_popup|frequency_days', 'label' => 'Częstotliwość ponownego pokazania (dni)', 'type' => 'number', 'default' => 7],
                ],
            ],
            // === Nowe moduly compliance 2026 ===
            [
                'id' => 'gpsr',
                'name' => 'GPSR - Product safety',
                'description' => 'GPSR product safety tools: manufacturer and importer data, responsible person, product identifiers, safety warnings, instructions, and CSV bulk import or export.',
                'group' => 'Informacje o produkcie',
                'enabled' => true,
                'icon' => 'dashicons-shield-alt',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_gpsr|display_mode', 'label' => 'Display mode', 'type' => 'select', 'default' => 'accordion', 'options' => ['accordion' => 'Accordion', 'section' => 'Section']],
                    ['key' => 'polski_gpsr|section_title', 'label' => 'Section title', 'type' => 'text', 'default' => 'Product safety'],
                ],
            ],
            [
                'id' => 'verified_review',
                'name' => 'Verified purchase badge',
                'description' => 'Badge shown on reviews from customers who actually purchased the product.',
                'group' => 'Informacje o produkcie',
                'enabled' => false,
                'icon' => 'dashicons-star-filled',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_verified_review|badge_text', 'label' => 'Badge text', 'type' => 'text', 'default' => 'Verified purchase'],
                ],
            ],
            [
                'id' => 'green_claims',
                'name' => 'Anti-greenwashing',
                'description' => 'Pola do produktow: podstawa twierdzenia ekologicznego, link do certyfikatu, data waznosci. Zgodnosc z dyrektywa anty-greenwashingowa (wrzesien 2026).',
                'group' => 'Informacje o produkcie',
                'enabled' => false,
                'icon' => 'dashicons-palmtree',
                'links' => [],
                'settings' => [],
            ],
            [
                'id' => 'dsa_toolkit',
                'name' => 'DSA Toolkit',
                'description' => 'Digital Services Act tools: contact point settings, report form for illegal content or products, and an admin reports screen. Shortcode: [polski_dsa_report].',
                'group' => 'Prawa konsumenta',
                'enabled' => false,
                'icon' => 'dashicons-megaphone',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_dsa|contact_name', 'label' => 'DSA contact name', 'type' => 'text', 'default' => ''],
                    ['key' => 'polski_dsa|contact_email', 'label' => 'DSA contact email', 'type' => 'email', 'default' => ''],
                    ['key' => 'polski_dsa|contact_phone', 'label' => 'DSA contact phone', 'type' => 'text', 'default' => ''],
                    ['key' => 'polski_dsa|form_title', 'label' => 'Form title', 'type' => 'text', 'default' => 'Report illegal content'],
                    ['key' => 'polski_dsa|success_text', 'label' => 'Success message', 'type' => 'text', 'default' => 'Thank you for your report. We will review it within 7 business days.'],
                ],
            ],
            [
                'id' => 'ksef_ready',
                'name' => 'KSeF readiness',
                'description' => 'Automatic detection of orders that may require KSeF invoicing based on NIP, plus integration hooks and an admin status indicator.',
                'group' => 'Prawa konsumenta',
                'enabled' => false,
                'icon' => 'dashicons-media-spreadsheet',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_ksef|auto_detect_nip', 'label' => 'Automatycznie wykrywaj na podstawie NIP', 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'security_incidents',
                'name' => 'Security incidents',
                'description' => 'CRA-oriented incident log for vulnerabilities, breaches, third-party failures, and operational follow-up. Includes status tracking and CSV export.',
                'group' => 'Prawa konsumenta',
                'enabled' => true,
                'icon' => 'dashicons-shield',
                'links' => [
                    ['label' => 'Incident log', 'url' => admin_url('admin.php?page=polski-security-incidents')],
                ],
                'settings' => [
                    ['key' => 'polski_security|incident_contact_email', 'label' => 'Security contact email', 'type' => 'email', 'default' => 'security@example.com'],
                    ['key' => 'polski_security|default_reporter_name', 'label' => 'Default reporter name', 'type' => 'text', 'default' => 'Store administrator'],
                ],
            ],

            // === SEO i Optymalizacja ===
            [
                'id' => 'schema_org',
                'name' => 'Wzbogacone Dane (Schema.org)',
                'description' => 'Automatyczne wstrzykiwanie zaawansowanych tagów JSON-LD, wspierających indeksowanie produktów przez Google z zachowaniem danych specyficznych dla wtyczki.',
                'group' => 'SEO i Optymalizacja',
                'enabled' => true,
                'icon' => 'dashicons-search',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_seo|schema_enabled', 'label' => 'Włącz integrację danych ustrukturyzowanych', 'type' => 'checkbox', 'default' => true, 'hint' => 'Główny włącznik modyfikacji Schema.org JSON-LD.'],
                    ['key' => '_schema_header', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;margin-top:8px;display:block;">Dane do dołączenia</strong>'],
                    ['key' => 'polski_seo|schema_brand', 'label' => 'Dołącz Markę (Brand)', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_seo|schema_manufacturer', 'label' => 'Dołącz Producenta (Manufacturer)', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_seo|schema_gtin', 'label' => 'Dołącz kody kreskowe (GTIN)', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_seo|schema_unit_price', 'label' => 'Dołącz Cenę jednostkową', 'type' => 'checkbox', 'default' => true],
                ],
            ],

            // === Integracje ===
            [
                'id' => 'checkout_toolkit_integration',
                'name' => 'Integracja checkoutu i zgód',
                'description' => 'Wykrywanie popularnych rozszerzeń pól checkoutu, cookies i danych produktowych, aby zachować zgodność ustawień i komunikatów.',
                'group' => 'Integracje',
                'enabled' => true,
                'icon' => 'dashicons-admin-plugins',
                'settings' => [
                    ['key' => '_checkout_toolkit_status', 'type' => 'html', 'html' => $this->getCheckoutToolkitStatus()],
                ],
                'links' => [
                    ['label' => 'Flexible Checkout Fields', 'url' => 'https://wordpress.org/plugins/flexible-checkout-fields/'],
                ],
            ],

            // === Narzedzia ===
            [
                'id' => 'site_audit',
                'name' => 'Audyt sklepu',
                'description' => 'Automatyczna weryfikacja najczestszych problemow: brakujace strony prawne, pre-zaznaczone checkboxy, dane firmy, RODO, Omnibus.',
                'group' => 'Narzedzia',
                'enabled' => true,
                'icon' => 'dashicons-search',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_general|remove_data_on_uninstall', 'label' => 'Delete plugin data on uninstall', 'type' => 'checkbox', 'default' => false, 'hint' => 'If enabled, uninstall removes plugin tables, settings, and stored logs including deactivation feedback.'],
                ],
            ],
            [
                'id' => 'cra_readiness',
                'name' => 'CRA - Cyberodpornosc',
                'description' => 'Cyber Resilience Act: plik security.txt (RFC 9116), kontakt bezpieczenstwa, polityka zglaszania podatnosci.',
                'group' => 'Narzedzia',
                'enabled' => false,
                'icon' => 'dashicons-lock',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_cra|security_contact', 'label' => 'Email kontaktu bezpieczenstwa', 'type' => 'email', 'default' => ''],
                    ['key' => 'polski_cra|security_policy_url', 'label' => 'URL polityki bezpieczenstwa', 'type' => 'text', 'default' => ''],
                ],
            ],
            [
                'id' => 'dpa_tracker',
                'name' => 'Rejestr DPA (RODO)',
                'description' => 'Wykrywanie uslug zewnetrznych przetwarzajacych dane osobowe klientow. Sledzenie statusu umow powierzenia danych.',
                'group' => 'Narzedzia',
                'enabled' => false,
                'icon' => 'dashicons-clipboard',
                'links' => [],
                'settings' => [],
            ],
        ];

        // Apply saved states.
        foreach ($modules as &$module) {
            if (isset($saved[$module['id']])) {
                $module['enabled'] = (bool) $saved[$module['id']];
            }
        }

        return $modules;
    }

    /**
     * Render the modules management page.
     */
    public function render(): void
    {
        $modules = $this->getModules();

        // Toggle CSS + JS.
        $this->renderToggleStyles();

        // Group modules.
        $groups = [];
        foreach ($modules as $module) {
            $groups[$module['group']][] = $module;
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('polski_save_modules', '_polski_modules_nonce');
        echo '<input type="hidden" name="action" value="polski_save_modules" />';

        foreach ($groups as $groupName => $groupModules) {
            echo '<div style="margin-top:30px;">';
            echo '<h2 style="display:flex;align-items:center;gap:8px;">';
            echo esc_html($groupName);
            echo '</h2>';

            echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:12px;">';

            foreach ($groupModules as $module) {
                $this->renderModuleCard($module, false);
            }

            echo '</div></div>';
        }

        echo '<p class="submit" style="margin-top:20px;">';
        printf(
            '<button type="submit" class="button button-primary button-hero">%s</button>',
            esc_html__('Zapisz moduły', 'polski'),
        );
        echo '</p>';

        echo '</form>';
    }

    /**
     * Render a single module card with toggle.
     *
     * @param array<string, mixed> $module
     * @param bool                 $locked
     */
    private function renderModuleCard(array $module, bool $locked): void
    {
        $id = $module['id'];
        $enabled = $module['enabled'];
        $fieldName = "polski_module_{$id}";
        $hasSettings = ! empty($module['settings']);

        $classes = 'sp-card' . ($enabled ? ' sp-card--active' : '') . ($locked ? ' sp-card--locked' : '');

        echo '<div class="' . esc_attr($classes) . '">';

        // Header with icon and toggle.
        echo '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">';
        echo '<div style="display:flex;align-items:center;gap:8px;">';

        if (! empty($module['icon'])) {
            echo '<span class="dashicons ' . esc_attr($module['icon']) . '" style="color:#666;"></span>';
        }

        echo '<strong>' . esc_html($module['name']) . '</strong>';

        echo '</div>';

        // Toggle switch.
        echo '<label class="sp-toggle' . ($locked ? ' sp-toggle--locked' : '') . '">';
        printf(
            '<input type="checkbox" name="%s" value="1" %s %s>',
            esc_attr($fieldName),
            checked($enabled, true, false),
            $locked ? 'disabled' : '',
        );
        echo '<span class="sp-toggle__track"></span>';
        echo '<span class="sp-toggle__knob"></span>';
        echo '</label>';

        echo '</div>';

        // Description.
        echo '<p style="margin:0;color:#666;font-size:13px;line-height:1.5;">' . esc_html($module['description']) . '</p>';

        // Links.
        if (! empty($module['links'])) {
            echo '<div style="margin-top:8px;">';
            foreach ($module['links'] as $link) {
                printf(
                    '<a href="%s" target="_blank" rel="noopener" style="font-size:12px;margin-right:12px;">%s &rarr;</a>',
                    esc_url($link['url']),
                    esc_html($link['label']),
                );
            }
            echo '</div>';
        }

        // Settings link button.
        if ($hasSettings && $enabled && ! $locked) {
            $settingsUrl = admin_url('admin.php?page=polski-module-' . $id);
            printf(
                '<div style="margin-top:8px;"><a href="%s" class="button button-small">%s</a></div>',
                esc_url($settingsUrl),
                esc_html__('Ustawienia', 'polski'),
            );
        }

        // Settings panel (collapsible).
        if ($hasSettings && ! $locked) {
            $detailsId = 'polski-settings-' . $id;

            echo '<details id="' . esc_attr($detailsId) . '" style="margin-top:12px;border-top:1px solid #eee;padding-top:10px;">';
            echo '<summary style="cursor:pointer;font-size:12px;color:#0073aa;user-select:none;">Konfiguruj &darr;</summary>';
            echo '<div style="margin-top:10px;">';

            foreach ($module['settings'] as $field) {
                $this->renderSettingsField($field);
            }

            echo '</div></details>';
        }

        if ($locked) {
            echo '<div style="position:absolute;top:8px;right:8px;">';
            echo '<span style="font-size:11px;color:#7f54b3;">' . esc_html__('Wkrotce dostepne', 'polski') . '</span>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Render a single settings field within a module card.
     *
     * @param array<string, mixed> $field
     */
    public function renderSettingsField(array $field, bool $tableRow = false): void
    {
        $type = $field['type'] ?? 'text';
        $key = $field['key'] ?? '';
        $label = $field['label'] ?? '';
        $hint = $field['hint'] ?? '';

        // HTML type - raw output.
        if ($type === 'html') {
            echo '<div style="margin-bottom:8px;">' . wp_kses_post($field['html'] ?? '') . '</div>';
            return;
        }

        // Parse key format: "option_name|field_key"
        [$optionName, $fieldKey] = explode('|', $key, 2) + ['', ''];

        // Get current value.
        $options = get_option($optionName, []);
        $options = is_array($options) ? $options : [];
        $currentValue = $options[$fieldKey] ?? ($field['default'] ?? '');
        $inputName = "polski_setting[{$optionName}][{$fieldKey}]";

        if ($tableRow) {
            echo '<tr>';
            echo '<th scope="row">' . esc_html($label) . '</th>';
            echo '<td>';
        } else {
            echo '<div style="margin-bottom:10px;">';
        }

        if ($type === 'checkbox') {
            echo '<label style="display:flex;align-items:center;gap:6px;font-size:13px;">';
            printf(
                '<input type="checkbox" name="%s" value="1" %s>',
                esc_attr($inputName),
                checked($currentValue, true, false),
            );
            echo esc_html($label);
            echo '</label>';
        } else {
            if ($label !== '') {
                echo '<label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px;">' . esc_html($label) . '</label>';
            }

            if ($type === 'text') {
                printf(
                    '<input type="text" name="%s" value="%s" class="regular-text" style="width:100%%;font-size:12px;">',
                    esc_attr($inputName),
                    esc_attr((string) $currentValue),
                );
            } elseif ($type === 'email') {
                printf(
                    '<input type="email" name="%s" value="%s" class="regular-text" style="width:100%%;font-size:12px;">',
                    esc_attr($inputName),
                    esc_attr((string) $currentValue),
                );
            } elseif ($type === 'number') {
                printf(
                    '<input type="number" name="%s" value="%s" class="small-text" style="width:80px;">',
                    esc_attr($inputName),
                    esc_attr((string) $currentValue),
                );
            } elseif ($type === 'textarea') {
                printf(
                    '<textarea name="%s" rows="3" style="width:100%%;font-size:12px;">%s</textarea>',
                    esc_attr($inputName),
                    esc_textarea((string) $currentValue),
                );
            } elseif ($type === 'select') {
                echo '<select name="' . esc_attr($inputName) . '" style="font-size:12px;">';
                foreach (($field['options'] ?? []) as $val => $optLabel) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($val),
                        selected($currentValue, $val, false),
                        esc_html($optLabel),
                    );
                }
                echo '</select>';
            } elseif ($type === 'delivery_time_select') {
                $terms = get_terms(['taxonomy' => 'polski_delivery_time', 'hide_empty' => false]);
                echo '<select name="' . esc_attr($inputName) . '" style="font-size:12px;">';
                echo '<option value="">-- brak --</option>';
                if (is_array($terms)) {
                    foreach ($terms as $term) {
                        if ($term instanceof \WP_Term) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr((string) $term->term_id),
                                selected($currentValue, (string) $term->term_id, false),
                                esc_html($term->name),
                            );
                        }
                    }
                }
                echo '</select>';
            }
        }

        if ($hint !== '') {
            echo '<p class="description">' . esc_html($hint) . '</p>';
        }

        if ($tableRow) {
            echo '</td></tr>';
        } else {
            echo '</div>';
        }
    }

    /**
     * Handle module save form submission.
     */
    public function handleSave(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(__('Przepraszamy, ale wydaje się, że nie masz dostępu do tej strony.', 'polski'));
        }

        check_admin_referer('polski_save_modules', '_polski_modules_nonce');

        $modules = $this->getModules();
        $saved = [];

        foreach ($modules as $module) {
            $fieldName = 'polski_module_' . $module['id'];
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $saved[$module['id']] = isset($_POST[$fieldName]) ? true : false;
        }

        update_option(self::OPTION, $saved);

        // Save per-module settings.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $settingsData = $_POST['polski_setting'] ?? [];

        if (is_array($settingsData)) {
            foreach ($settingsData as $optionName => $fields) {
                if (! is_array($fields)) {
                    continue;
                }

                $optionName = sanitize_key($optionName);
                $existing = get_option($optionName, []);
                $existing = is_array($existing) ? $existing : [];

                foreach ($fields as $fieldKey => $value) {
                    $fieldKey = sanitize_key($fieldKey);
                    $field = $this->findFieldDefinition($modules, $optionName, $fieldKey);
                    $existing[$fieldKey] = $this->sanitizeFieldValue($value, $field);
                }

                update_option($optionName, $existing);
            }
        }

        // Handle unchecked checkboxes (they don't send POST data).
        foreach ($modules as $module) {
            if (empty($module['settings'])) {
                continue;
            }

            foreach ($module['settings'] as $field) {
                if (($field['type'] ?? '') !== 'checkbox' || ! isset($field['key'])) {
                    continue;
                }

                [$optName, $fKey] = explode('|', $field['key'], 2) + ['', ''];

                if ($optName === '' || $fKey === '') {
                    continue;
                }

                // If checkbox was not in POST, set to false.
                if (! isset($settingsData[$optName][$fKey])) {
                    $opt = get_option($optName, []);
                    $opt = is_array($opt) ? $opt : [];
                    $opt[$fKey] = false;
                    update_option($optName, $opt);
                }
            }
        }

        \Polski\Service\CacheHelper::flush();

        wp_safe_redirect(admin_url('admin.php?page=polski&modules_saved=1'));
        exit;
    }

    /**
     * @return array<string, bool>
     */
    public static function getDefaultModuleStates(): array
    {
        return [
            'unit_price' => true,
            'omnibus' => true,
            'tax_display' => true,
            'delivery_time' => true,
            'shipping_notice' => true,
            'checkout_button' => true,
            'legal_checkboxes' => true,
            'consent_logging' => true,
            'legal_pages' => true,
            'withdrawal' => true,
            'dispute_resolution' => true,
            'email_attachments' => true,
            'manufacturer' => true,
            'food_module' => false,
            'power_supply' => false,
            'double_opt_in' => false,
            'ajax_search' => false,
            'brands' => false,
            'ajax_filters' => false,
            'wishlist' => false,
            'compare' => false,
            'quick_view' => false,
            'badge_management' => false,
            'tab_manager' => false,
            'featured_video' => false,
            'gallery_zoom' => false,
            'product_slider_carousel' => false,
            'waitlist' => false,
            'infinite_scroll' => false,
            'popup' => false,
            'schema_org' => true,
            'checkout_toolkit_integration' => true,
            'gpsr' => true,
            'verified_review' => false,
            'green_claims' => false,
            'dsa_toolkit' => false,
            'ksef_ready' => false,
            'security_incidents' => true,
            'site_audit' => true,
            'cra_readiness' => false,
            'dpa_tracker' => false,
            'nip_lookup' => false,
        ];
    }

    /**
     * Check if a module is enabled.
     */
    public static function isModuleEnabled(string $moduleId): bool
    {
        $saved = get_option(self::OPTION, []);

        if (! is_array($saved) || ! isset($saved[$moduleId])) {
            $defaults = self::getDefaultModuleStates();

            return $defaults[$moduleId] ?? false;
        }

        return (bool) $saved[$moduleId];
    }

    /**
     * Render CSS and JS for toggle switches.
     */
    private function renderToggleStyles(): void
    {
        echo '<style>
            .sp-card{background:#fff;border:1px solid #ccd0d4;padding:16px;position:relative;transition:border-color .2s;}
            .sp-card--active{border-color:#46b450;}
            .sp-card--locked{opacity:.7;}

            .sp-toggle{position:relative;display:inline-block;width:40px;height:22px;flex-shrink:0;}
            .sp-toggle input{opacity:0;width:0;height:0;position:absolute;}
            .sp-toggle__track{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;border-radius:22px;transition:background .2s;}
            .sp-toggle__knob{position:absolute;height:18px;width:18px;left:2px;bottom:2px;background:#fff;border-radius:50%;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.2);}
            .sp-toggle input:checked ~ .sp-toggle__track{background:#46b450;}
            .sp-toggle input:checked ~ .sp-toggle__knob{transform:translateX(18px);}
            .sp-toggle--locked .sp-toggle__track{cursor:not-allowed;}
        </style>
        <script>
        document.addEventListener("DOMContentLoaded",function(){
            document.querySelectorAll(".sp-toggle input[type=checkbox]").forEach(function(cb){
                cb.addEventListener("change",function(){
                    var card=this.closest(".sp-card");
                    if(card){
                        card.classList.toggle("sp-card--active",this.checked);
                    }
                });
            });
        });
        </script>';
    }

    /**
     * Get HTML showing Omnibus plugin integration status.
     */
    private function getOmnibusIntegrationStatus(): string
    {
        $generalSettings = get_option('polski_general', []);
        $generalSettings = is_array($generalSettings) ? $generalSettings : [];

        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = [
            ['file' => 'wc-price-history/wc-price-history.php', 'name' => __('Kompatybilne rozszerzenie Omnibus A', 'polski')],
            ['file' => 'omnibus/omnibus.php', 'name' => __('Kompatybilne rozszerzenie Omnibus B', 'polski')],
        ];

        $html = '<div style="font-size:12px;">';

        $anyActive = false;

        foreach ($plugins as $plugin) {
            $active = is_plugin_active($plugin['file']);
            $icon = $active ? '<span style="color:#46b450;">&#10003;</span>' : '<span style="color:#999;">&#8212;</span>';
            $status = $active
                ? (string) ($generalSettings['admin_omnibus_plugin_detected_text'] ?? 'wykryta, dane synchronizowane')
                : (string) ($generalSettings['admin_omnibus_plugin_missing_text'] ?? 'niezainstalowana');

            if ($active) {
                $anyActive = true;
            }

            $html .= sprintf(
                '<div style="margin-bottom:4px;">%s %s - <em>%s</em></div>',
                $icon,
                esc_html($plugin['name']),
                esc_html($status),
            );
        }

        if (! $anyActive) {
            $html .= '<div style="margin-top:6px;color:#666;">' . esc_html((string) ($generalSettings['admin_omnibus_no_external_text'] ?? __('Żadna zewnętrzna wtyczka Omnibus nie jest zainstalowana. Polski używa wbudowanego systemu śledzenia cen.', 'polski'))) . '</div>';
        } else {
            $html .= '<div style="margin-top:6px;color:#46b450;">' . esc_html((string) ($generalSettings['admin_omnibus_external_active_text'] ?? __('Zewnętrzna wtyczka wykryta. Polski korzysta z jej danych zamiast wbudowanego systemu.', 'polski'))) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    private function getCheckoutToolkitStatus(): string
    {
        $generalSettings = get_option('polski_general', []);
        $generalSettings = is_array($generalSettings) ? $generalSettings : [];

        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = [
            ['file' => 'flexible-checkout-fields/flexible-checkout-fields.php', 'name' => 'Flexible Checkout Fields'],
            ['file' => 'flexible-cookies/flexible-cookies.php', 'name' => 'Flexible Cookies'],
            ['file' => 'gpsr-for-woocommerce/gpsr-for-woocommerce.php', 'name' => 'GPSR for WooCommerce'],
        ];

        $html = '<div style="font-size:12px;">';
        $anyActive = false;

        foreach ($plugins as $plugin) {
            $active = is_plugin_active($plugin['file']);
            $icon = $active ? '<span style="color:#46b450;">&#10003;</span>' : '<span style="color:#999;">&#8212;</span>';
            $status = $active
                ? (string) ($generalSettings['admin_integration_detected_text'] ?? 'wykryta, integracja aktywna')
                : (string) ($generalSettings['admin_integration_missing_text'] ?? 'niewykryta');

            if ($active) {
                $anyActive = true;
            }

            $html .= sprintf(
                '<div style="margin-bottom:4px;">%s %s - <em>%s</em></div>',
                $icon,
                esc_html($plugin['name']),
                esc_html($status),
            );
        }

        if (! $anyActive) {
            $html .= '<div style="margin-top:6px;color:#666;">' . esc_html((string) ($generalSettings['admin_checkout_toolkit_no_external_text'] ?? __('Nie wykryto wspieranych rozszerzeń checkoutu i cookies. Polski działa nadal samodzielnie.', 'polski'))) . '</div>';
        } else {
            $html .= '<div style="margin-top:6px;color:#46b450;">' . esc_html((string) ($generalSettings['admin_checkout_toolkit_external_active_text'] ?? __('Wykryto wspierane rozszerzenia checkoutu, cookies lub danych produktowych. Polski może dopasować integrację do aktywnego zestawu.', 'polski'))) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param list<array<string, mixed>> $modules
     * @return array<string, mixed>|null
     */
    private function findFieldDefinition(array $modules, string $optionName, string $fieldKey): ?array
    {
        foreach ($modules as $module) {
            if (empty($module['settings']) || ! is_array($module['settings'])) {
                continue;
            }

            foreach ($module['settings'] as $field) {
                if (! is_array($field) || empty($field['key'])) {
                    continue;
                }

                [$candidateOptionName, $candidateFieldKey] = explode('|', (string) $field['key'], 2) + ['', ''];

                if ($candidateOptionName === $optionName && $candidateFieldKey === $fieldKey) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * @param mixed                     $value
     * @param array<string, mixed>|null $field
     * @return mixed
     */
    private function sanitizeFieldValue(mixed $value, ?array $field): mixed
    {
        $type = $field['type'] ?? 'text';

        return match ($type) {
            'checkbox' => (bool) $value,
            'number' => is_numeric($value) ? $value + 0 : 0,
            'textarea' => sanitize_textarea_field((string) $value),
            'email' => sanitize_email((string) $value),
            'select', 'delivery_time_select', 'text' => sanitize_text_field((string) $value),
            default => is_string($value) ? sanitize_text_field($value) : $value,
        };
    }
}
