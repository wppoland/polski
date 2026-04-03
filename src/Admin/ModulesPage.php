<?php

declare(strict_types=1);

namespace Polski\Admin;

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
     * @return list<array{id: string, name: string, description: string, group: string, enabled: bool, pro: bool, icon: string, links: list<array{label: string, url: string}>}>
     */
    public function getModules(): array
    {
        $saved = get_option(self::OPTION, []);
        $saved = is_array($saved) ? $saved : [];

        $modules = [
            // === Ceny i wyświetlanie ===
            [
                'id' => 'unit_price',
                'name' => 'Cena jednostkowa',
                'description' => 'Wyświetlanie ceny za jednostkę miary (np. za 1 kg, za 100 ml) zgodnie z polskim prawem konsumenckim.',
                'group' => 'Ceny i wyświetlanie',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-tag',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_prices|unit_price_text', 'label' => 'Szablon wyświetlania', 'type' => 'text', 'default' => '{price} / {unit}', 'hint' => 'Zmienne: {price}, {unit}'],
                    ['key' => 'polski_prices|unit_price_show_loop', 'label' => 'Pokazuj na liście produktów', 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'omnibus',
                'name' => 'Najniższa cena (Omnibus)',
                'description' => 'Śledzenie historii cen i wyświetlanie najniższej ceny z ostatnich 30 dni przy produktach w promocji. Wymagane przez Dyrektywę Omnibus (UE 2019/2161).',
                'group' => 'Ceny i wyświetlanie',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-chart-line',
                'links' => [],
                'settings' => [
                    ['key' => '_omnibus_header_1', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;">Śledzenie cen</strong>'],
                    ['key' => 'polski_omnibus|days', 'label' => 'Okres śledzenia (dni)', 'type' => 'number', 'default' => 30, 'hint' => 'Dyrektywa wymaga minimum 30 dni'],
                    ['key' => 'polski_omnibus|prune_after_days', 'label' => 'Przechowuj historię (dni)', 'type' => 'number', 'default' => 90, 'hint' => 'Dane starsze zostaną automatycznie usunięte'],
                    ['key' => 'polski_omnibus|include_tax', 'label' => 'Ceny z podatkiem', 'type' => 'checkbox', 'default' => true, 'hint' => 'Śledź i wyświetlaj ceny brutto'],

                    ['key' => '_omnibus_header_2', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;margin-top:8px;display:block;">Wyświetlanie</strong>'],
                    ['key' => 'polski_omnibus|display_on_sale_only', 'label' => 'Tylko produkty w promocji', 'type' => 'checkbox', 'default' => true, 'hint' => 'Pokazuj informację tylko gdy produkt ma cenę promocyjną'],
                    ['key' => 'polski_omnibus|show_on_single', 'label' => 'Strona produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_omnibus|show_on_loop', 'label' => 'Lista produktów (sklep, kategorie)', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_omnibus|show_on_related', 'label' => 'Produkty powiązane i polecane', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_omnibus|show_on_cart', 'label' => 'Koszyk', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_omnibus|show_regular_price', 'label' => 'Pokazuj cenę regularną (przed promocją)', 'type' => 'checkbox', 'default' => false, 'hint' => 'Wyświetl dodatkową informację o cenie przed rozpoczęciem promocji'],

                    ['key' => '_omnibus_header_3', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;margin-top:8px;display:block;">Szablon wiadomości</strong>'],
                    ['key' => 'polski_omnibus|display_text', 'label' => 'Treść komunikatu', 'type' => 'text', 'default' => 'Najniższa cena z ostatnich {days} dni: {price}', 'hint' => 'Zmienne: {price}, {days}, {date}, {regular_price}'],
                    ['key' => 'polski_omnibus|no_history_text', 'label' => 'Brak historii cen', 'type' => 'select', 'default' => 'hide', 'options' => ['hide' => 'Ukryj komunikat', 'current' => 'Pokaż aktualną cenę', 'custom' => 'Własny tekst']],
                    ['key' => 'polski_omnibus|no_history_custom_text', 'label' => 'Własny tekst (brak historii)', 'type' => 'text', 'default' => 'Cena nie uległa zmianie w okresie {days} dni'],
                    ['key' => 'polski_omnibus|price_count_from', 'label' => 'Liczona od', 'type' => 'select', 'default' => 'sale_start', 'options' => ['sale_start' => 'Dnia rozpoczęcia promocji', 'today' => 'Dnia dzisiejszego'], 'hint' => 'Punkt odniesienia do obliczania najniższej ceny'],

                    ['key' => '_omnibus_header_4', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;margin-top:8px;display:block;">Produkty wariantowe</strong>'],
                    ['key' => 'polski_omnibus|variable_tracking', 'label' => 'Śledź warianty oddzielnie', 'type' => 'checkbox', 'default' => true, 'hint' => 'Każdy wariant ma własną historię cen'],
                ],
            ],
            [
                'id' => 'tax_display',
                'name' => 'Wyświetlanie VAT',
                'description' => 'Konfiguracja wyświetlania cen brutto/netto, informacja o stawce VAT, obsługa zwolnienia podmiotowego (art. 113 ustawy o VAT).',
                'group' => 'Ceny i wyświetlanie',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-money-alt',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_taxes|tax_display_mode', 'label' => 'Tryb wyświetlania cen', 'type' => 'select', 'default' => 'brutto', 'options' => ['brutto' => 'Brutto (z VAT)', 'netto' => 'Netto (bez VAT)']],
                    ['key' => 'polski_taxes|vat_notice_text', 'label' => 'Tekst informacji o VAT', 'type' => 'text', 'default' => 'w tym {rate}% VAT', 'hint' => 'Zmienne: {rate}'],
                    ['key' => 'polski_general|small_business', 'label' => 'Zwolnienie podmiotowe (art. 113)', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_taxes|vat_exempt_notice', 'label' => 'Tekst zwolnienia', 'type' => 'text', 'default' => 'Zwolniony z VAT na podstawie art. 113 ust. 1 ustawy o VAT'],
                ],
            ],
            [
                'id' => 'delivery_time',
                'name' => 'Czas dostawy',
                'description' => 'Wyświetlanie przewidywanego czasu dostawy na stronie produktu. Konfiguracja per produkt lub wariant z domyślnym fallbackiem.',
                'group' => 'Ceny i wyświetlanie',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-clock',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_delivery|display_format', 'label' => 'Format wyświetlania', 'type' => 'text', 'default' => 'Czas dostawy: {time}', 'hint' => 'Zmienne: {time}'],
                    ['key' => 'polski_delivery|default_delivery_time', 'label' => 'Domyślny czas dostawy', 'type' => 'delivery_time_select'],
                ],
            ],
            [
                'id' => 'shipping_notice',
                'name' => 'Informacja o kosztach wysyłki',
                'description' => 'Link do strony z kosztami wysyłki wyświetlany przy cenie produktu.',
                'group' => 'Ceny i wyświetlanie',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-car',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_prices|shipping_costs_text', 'label' => 'Tekst linku', 'type' => 'text', 'default' => 'zzgl. kosztów wysyłki'],
                ],
            ],

            // === Kasa i zamówienia ===
            [
                'id' => 'checkout_button',
                'name' => 'Przycisk zamówienia',
                'description' => 'Zmiana tekstu przycisku zamówienia na "Zamawiam z obowiązkiem zapłaty" zgodnie z polskim prawem.',
                'group' => 'Kasa i zamówienia',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-cart',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_checkout|order_button_text', 'label' => 'Tekst przycisku', 'type' => 'text', 'default' => 'Zamawiam z obowiązkiem zapłaty'],
                ],
            ],
            [
                'id' => 'legal_checkboxes',
                'name' => 'Checkboxy prawne',
                'description' => '7 wbudowanych checkboxów: regulamin, polityka prywatności, prawo odstąpienia, treści cyfrowe, powiadomienia o dostawie, przypomnienie o opinii, marketing.',
                'group' => 'Kasa i zamówienia',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-yes-alt',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_checkout|terms_checkbox_enabled', 'label' => 'Regulamin sklepu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_checkout|terms_checkbox_label', 'label' => 'Etykieta - Regulamin', 'type' => 'textarea', 'default' => 'Zapoznałem się i akceptuję <a href="%s" target="_blank">Regulamin sklepu</a>.', 'hint' => 'Użyj %s jako miejsca na link do strony regulaminu'],
                    ['key' => 'polski_checkout|terms_checkbox_error', 'label' => 'Błąd - Regulamin', 'type' => 'text', 'default' => 'Musisz zaakceptować Regulamin, aby złożyć zamówienie.'],
                    ['key' => 'polski_checkout|terms_checkbox_description', 'label' => 'Opis - Regulamin', 'type' => 'text', 'default' => 'Akceptacja Regulaminu sklepu.'],
                    ['key' => 'polski_checkout|privacy_checkbox_enabled', 'label' => 'Polityka prywatności', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_checkout|privacy_checkbox_label', 'label' => 'Etykieta - Polityka prywatności', 'type' => 'textarea', 'default' => 'Zapoznałem się i akceptuję <a href="%s" target="_blank">Politykę prywatności</a>.', 'hint' => 'Użyj %s jako miejsca na link do polityki prywatności'],
                    ['key' => 'polski_checkout|privacy_checkbox_error', 'label' => 'Błąd - Polityka prywatności', 'type' => 'text', 'default' => 'Musisz zaakceptować Politykę prywatności.'],
                    ['key' => 'polski_checkout|privacy_checkbox_description', 'label' => 'Opis - Polityka prywatności', 'type' => 'text', 'default' => 'Akceptacja Polityki prywatności.'],
                    ['key' => 'polski_checkout|withdrawal_checkbox_enabled', 'label' => 'Prawo odstąpienia', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_checkout|withdrawal_checkbox_label', 'label' => 'Etykieta - Prawo odstąpienia', 'type' => 'textarea', 'default' => 'Potwierdzam, że zostałem poinformowany o <a href="%s" target="_blank">prawie odstąpienia od umowy</a> w ciągu 14 dni.', 'hint' => 'Użyj %s jako miejsca na link do strony zwrotów lub odstąpienia'],
                    ['key' => 'polski_checkout|withdrawal_checkbox_error', 'label' => 'Błąd - Prawo odstąpienia', 'type' => 'text', 'default' => 'Musisz potwierdzić zapoznanie się z informacją o prawie odstąpienia.'],
                    ['key' => 'polski_checkout|withdrawal_checkbox_description', 'label' => 'Opis - Prawo odstąpienia', 'type' => 'text', 'default' => 'Potwierdzenie informacji o 14-dniowym prawie odstąpienia.'],
                    ['key' => 'polski_checkout|digital_waiver_checkbox_enabled', 'label' => 'Treści cyfrowe (zrzeczenie)', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_checkout|digital_waiver_checkbox_label', 'label' => 'Etykieta - Treści cyfrowe', 'type' => 'textarea', 'default' => 'Wyrażam zgodę na rozpoczęcie dostarczania treści cyfrowych przed upływem terminu do odstąpienia od umowy i przyjmuję do wiadomości utratę prawa odstąpienia.'],
                    ['key' => 'polski_checkout|digital_waiver_checkbox_error', 'label' => 'Błąd - Treści cyfrowe', 'type' => 'text', 'default' => 'Musisz wyrazić zgodę na natychmiastowe dostarczenie treści cyfrowych.'],
                    ['key' => 'polski_checkout|digital_waiver_checkbox_description', 'label' => 'Opis - Treści cyfrowe', 'type' => 'text', 'default' => 'Zrzeczenie się prawa odstąpienia dla treści cyfrowych.'],
                    ['key' => 'polski_checkout|parcel_delivery_checkbox_enabled', 'label' => 'Powiadomienia o dostawie', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_checkout|parcel_delivery_checkbox_label', 'label' => 'Etykieta - Powiadomienia o dostawie', 'type' => 'textarea', 'default' => 'Wyrażam zgodę na otrzymywanie powiadomień SMS/email o statusie dostawy przesyłki.'],
                    ['key' => 'polski_checkout|parcel_delivery_checkbox_description', 'label' => 'Opis - Powiadomienia o dostawie', 'type' => 'text', 'default' => 'Opcjonalna zgoda na powiadomienia o dostawie.'],
                    ['key' => 'polski_checkout|review_reminder_checkbox_enabled', 'label' => 'Przypomnienie o opinii', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_checkout|review_reminder_checkbox_label', 'label' => 'Etykieta - Przypomnienie o opinii', 'type' => 'textarea', 'default' => 'Wyrażam zgodę na otrzymanie przypomnienia o wystawieniu opinii drogą mailową po zakupie.'],
                    ['key' => 'polski_checkout|review_reminder_checkbox_description', 'label' => 'Opis - Przypomnienie o opinii', 'type' => 'text', 'default' => 'Opcjonalna zgoda na przypomnienia o opinii.'],
                    ['key' => 'polski_checkout|marketing_checkbox_enabled', 'label' => 'Zgoda marketingowa', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_checkout|marketing_checkbox_label', 'label' => 'Etykieta - Marketing', 'type' => 'textarea', 'default' => 'Wyrażam zgodę na otrzymywanie komunikacji marketingowej i newslettera.'],
                    ['key' => 'polski_checkout|marketing_checkbox_description', 'label' => 'Opis - Marketing', 'type' => 'text', 'default' => 'Opcjonalna zgoda marketingowa.'],
                ],
            ],
            [
                'id' => 'consent_logging',
                'name' => 'Logowanie zgód (RODO)',
                'description' => 'Rejestrowanie wszystkich zgód udzielonych przez klientów z adresem IP, user agentem i znacznikiem czasu. Zgodne z RODO.',
                'group' => 'Kasa i zamówienia',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-shield',
                'links' => [],
                'settings' => [],
            ],
            [
                'id' => 'contract_helper',
                'name' => 'Potwierdzenie zamówienia',
                'description' => 'Obsługa odroczonych płatności - potwierdzenie zamówienia przed płatnością, przycisk "Zapłać teraz" na stronie podziękowania.',
                'group' => 'Kasa i zamówienia',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-clipboard',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_checkout|delayed_payment_enabled', 'label' => 'Odroczona płatność', 'type' => 'checkbox', 'default' => false, 'hint' => 'Potwierdzenie zamówienia wysylane przed płatnościa'],
                ],
            ],
            [
                'id' => 'invoice_gateway',
                'name' => 'Przelew bankowy / faktura',
                'description' => 'Bramka płatności umożliwiająca płatność przelewem bankowym. Dane do przelewu na stronie podziękowania i w emailach.',
                'group' => 'Kasa i zamówienia',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-bank',
                'links' => [],
                'settings' => [
                    ['key' => '_gateway_link', 'label' => '', 'type' => 'html', 'html' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=polski_invoice') . '">Konfiguruj bramkę płatności w WooCommerce &rarr;</a>'],
                ],
            ],

            // === Prawa konsumenta ===
            [
                'id' => 'legal_pages',
                'name' => 'Strony prawne',
                'description' => 'Automatyczne generowanie stron: Regulamin, Polityka prywatności, Prawo odstąpienia od umowy, Reklamacje.',
                'group' => 'Prawa konsumenta',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-media-document',
                'links' => [],
                'settings' => [
                    ['key' => '_legal_pages_link', 'label' => '', 'type' => 'html', 'html' => '<a href="' . admin_url('admin.php?page=polski&tab=dashboard') . '">Zarządzaj stronami prawnymi na Pulpicie &rarr;</a>'],
                ],
            ],
            [
                'id' => 'withdrawal',
                'name' => 'Prawo odstąpienia (14 dni)',
                'description' => 'Formularz odstąpienia od umowy, przycisk "Odstąp" w historii zamówień, potwierdzenie emailem, obsługa wyłączeń per produkt.',
                'group' => 'Prawa konsumenta',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-undo',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_withdrawal|button_text', 'label' => 'Tekst przycisku w zamówieniach', 'type' => 'text', 'default' => 'Odstąp od umowy'],
                    ['key' => 'polski_withdrawal|form_title', 'label' => 'Tytuł formularza', 'type' => 'text', 'default' => 'Wniosek o odstąpienie od umowy'],
                    ['key' => 'polski_withdrawal|form_intro_text', 'label' => 'Opis formularza', 'type' => 'textarea', 'default' => 'Składasz wniosek o odstąpienie dla zamówienia #{order_number} z dnia {order_date}.', 'hint' => 'Zmienne: {order_number}, {order_date}'],
                    ['key' => 'polski_withdrawal|legal_notice_text', 'label' => 'Nota prawna formularza', 'type' => 'textarea', 'default' => 'Zgodnie z polskim prawem masz 14 dni na odstąpienie od umowy bez podawania przyczyny.'],
                    ['key' => 'polski_withdrawal|items_heading', 'label' => 'Nagłówek pozycji zamówienia', 'type' => 'text', 'default' => 'Pozycje zamówienia'],
                    ['key' => 'polski_withdrawal|column_product', 'label' => 'Kolumna Produkt', 'type' => 'text', 'default' => 'Produkt'],
                    ['key' => 'polski_withdrawal|column_quantity', 'label' => 'Kolumna Ilość', 'type' => 'text', 'default' => 'Ilość'],
                    ['key' => 'polski_withdrawal|column_price', 'label' => 'Kolumna Cena', 'type' => 'text', 'default' => 'Cena'],
                    ['key' => 'polski_withdrawal|exempt_notice_text', 'label' => 'Komunikat dla pozycji wyłączonej', 'type' => 'text', 'default' => '(Produkt wyłączony z prawa odstąpienia)'],
                    ['key' => 'polski_withdrawal|reason_label', 'label' => 'Etykieta pola powodu', 'type' => 'text', 'default' => 'Powód odstąpienia (opcjonalnie)'],
                    ['key' => 'polski_withdrawal|submit_button_text', 'label' => 'Tekst przycisku formularza', 'type' => 'text', 'default' => 'Wyślij wniosek o odstąpienie'],
                    ['key' => 'polski_withdrawal|invalid_nonce_text', 'label' => 'Błąd nonce', 'type' => 'text', 'default' => 'Ups, coś poszło nie tak po naszej stronie. Spróbuj ponownie, proszę!'],
                    ['key' => 'polski_withdrawal|order_not_found_text', 'label' => 'Błąd braku zamówienia', 'type' => 'text', 'default' => 'Niestety, nie udało nam się znaleźć takiego zamówienia.'],
                    ['key' => 'polski_withdrawal|permission_error_text', 'label' => 'Błąd uprawnień', 'type' => 'text', 'default' => 'Nie masz uprawnień do odstąpienia od tego zamówienia.'],
                    ['key' => 'polski_withdrawal|success_text', 'label' => 'Komunikat sukcesu', 'type' => 'text', 'default' => 'Twój wniosek o zwrot został przyjęty. Niedługo wyślemy Ci potwierdzenie na e-mail!'],
                    ['key' => 'polski_withdrawal|not_eligible_text', 'label' => 'Komunikat braku kwalifikacji', 'type' => 'text', 'default' => 'To zamówienie nie kwalifikuje się do odstąpienia.'],
                    ['key' => 'polski_withdrawal|status_heading', 'label' => 'Nagłówek statusu przy zamówieniu', 'type' => 'text', 'default' => 'Wniosek o odstąpienie'],
                    ['key' => 'polski_withdrawal|status_label', 'label' => 'Etykieta statusu', 'type' => 'text', 'default' => 'Status'],
                    ['key' => 'polski_withdrawal|submitted_label', 'label' => 'Etykieta daty złożenia', 'type' => 'text', 'default' => 'Złożono'],
                    ['key' => 'polski_withdrawal|requested_order_note', 'label' => 'Notatka zamówienia po zgłoszeniu', 'type' => 'text', 'default' => 'Klient złożył wniosek o odstąpienie od umowy.'],
                    ['key' => 'polski_withdrawal|confirmed_order_note', 'label' => 'Notatka zamówienia po potwierdzeniu', 'type' => 'text', 'default' => 'Wniosek o odstąpienie potwierdzony.'],
                    ['key' => 'polski_withdrawal|status_date_format', 'label' => 'Format daty statusu', 'type' => 'text', 'default' => 'Y-m-d H:i'],
                    ['key' => 'polski_withdrawal|email_subject', 'label' => 'Temat emaila potwierdzenia', 'type' => 'text', 'default' => 'Dobra wiadomość! Twój wniosek o zwrot, zamówienie #{order_number}, został pomyślnie potwierdzony.', 'hint' => 'Zmienne: {order_number}, {order_date}, {withdrawal_date}'],
                    ['key' => 'polski_withdrawal|email_heading', 'label' => 'Nagłówek emaila potwierdzenia', 'type' => 'text', 'default' => 'Odstąpienie potwierdzone'],
                    ['key' => 'polski_withdrawal|email_greeting', 'label' => 'Powitanie emaila', 'type' => 'text', 'default' => 'Dzień dobry {name},', 'hint' => 'Zmienna: {name}'],
                    ['key' => 'polski_withdrawal|email_intro_text', 'label' => 'Treść emaila potwierdzenia', 'type' => 'textarea', 'default' => 'Twój wniosek o odstąpienie dla zamówienia #{order_number} został potwierdzony.', 'hint' => 'Zmienna: {order_number}'],
                    ['key' => 'polski_withdrawal|email_reason_label', 'label' => 'Etykieta powodu w emailu', 'type' => 'text', 'default' => 'Twój powód'],
                    ['key' => 'polski_withdrawal|email_return_instruction', 'label' => 'Instrukcja zwrotu w emailu', 'type' => 'textarea', 'default' => 'Odeślij produkty na poniższy adres w ciągu 14 dni:'],
                    ['key' => 'polski_withdrawal|email_additional_content', 'label' => 'Dodatkowa treść emaila', 'type' => 'textarea', 'default' => 'Zwrot środków zostanie zrealizowany w ciągu 14 dni od daty otrzymania zwróconych produktów.'],
                ],
            ],
            [
                'id' => 'dispute_resolution',
                'name' => 'Rozstrzyganie sporów (ODR)',
                'description' => 'Wyświetlanie informacji o platformie ODR (Online Dispute Resolution) Komisji Europejskiej.',
                'group' => 'Prawa konsumenta',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-admin-site-alt3',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_general|dispute_resolution_text', 'label' => 'Treść informacji ODR', 'type' => 'textarea', 'default' => 'Platforma ODR: https://ec.europa.eu/consumers/odr'],
                    ['key' => 'polski_general|admin_pages_generated_notice', 'label' => 'Komunikat po wygenerowaniu stron prawnych', 'type' => 'textarea', 'default' => 'Gotowe! Wygenerowaliśmy dla Ciebie wstępne szkice stron prawnych. Przejrzyj je, dostosuj do swoich potrzeb i śmiało opublikuj.'],
                    ['key' => 'polski_general|admin_modules_saved_notice', 'label' => 'Komunikat po zapisaniu modułów', 'type' => 'text', 'default' => 'Moduły zapisane.'],
                    ['key' => 'polski_general|admin_setup_note_title', 'label' => 'Tytuł notki onboardingowej', 'type' => 'text', 'default' => 'Skonfiguruj Polski dla Twojego sklepu'],
                    ['key' => 'polski_general|admin_setup_note_content', 'label' => 'Treść notki onboardingowej', 'type' => 'textarea', 'default' => 'Jeszcze chwila i sklep będzie gotowy. Przejrzyj moduły, ustaw strony prawne i domknij konfigurację w panelu Polski.'],
                    ['key' => 'polski_general|admin_setup_note_button', 'label' => 'Przycisk notki onboardingowej', 'type' => 'text', 'default' => 'Otwórz konfigurację Polski'],
                    ['key' => 'polski_general|admin_status_active', 'label' => 'Status aktywny', 'type' => 'text', 'default' => 'Aktywna'],
                    ['key' => 'polski_general|admin_status_inactive', 'label' => 'Status nieaktywny', 'type' => 'text', 'default' => 'Wyłączona'],
                    ['key' => 'polski_general|admin_status_unconfigured', 'label' => 'Status nieskonfigurowany', 'type' => 'text', 'default' => 'Nieskonfigurowany'],
                    ['key' => 'polski_general|admin_legal_pages_card_title', 'label' => 'Tytuł karty stron prawnych', 'type' => 'text', 'default' => 'Strony prawne'],
                    ['key' => 'polski_general|admin_legal_pages_card_progress', 'label' => 'Postęp konfiguracji stron prawnych', 'type' => 'text', 'default' => 'Masz już za sobą {done} z {total} kroków. Znakomicie!', 'hint' => 'Zmienne: {done}, {total}'],
                    ['key' => 'polski_general|admin_vat_card_title', 'label' => 'Tytuł karty VAT', 'type' => 'text', 'default' => 'Wyświetlanie podatku'],
                    ['key' => 'polski_general|admin_vat_small_business_text', 'label' => 'Tekst zwolnienia podmiotowego', 'type' => 'text', 'default' => 'Zwolnienie podmiotowe (art. 113)'],
                    ['key' => 'polski_general|admin_vat_standard_text', 'label' => 'Tekst standardowego VAT', 'type' => 'text', 'default' => 'Standardowy VAT'],
                    ['key' => 'polski_general|admin_doi_card_title', 'label' => 'Tytuł karty DOI', 'type' => 'text', 'default' => 'Podwójna weryfikacja (DOI)'],
                    ['key' => 'polski_general|admin_legal_pages_section_title', 'label' => 'Tytuł sekcji stron prawnych', 'type' => 'text', 'default' => 'Strony prawne'],
                    ['key' => 'polski_general|admin_legal_pages_table_page', 'label' => 'Nagłówek kolumny Strona', 'type' => 'text', 'default' => 'Strona'],
                    ['key' => 'polski_general|admin_legal_pages_table_status', 'label' => 'Nagłówek kolumny Status', 'type' => 'text', 'default' => 'Status'],
                    ['key' => 'polski_general|admin_legal_pages_published', 'label' => 'Status opublikowana', 'type' => 'text', 'default' => 'Opublikowana'],
                    ['key' => 'polski_general|admin_legal_pages_draft', 'label' => 'Status szkic', 'type' => 'text', 'default' => 'Szkic'],
                    ['key' => 'polski_general|admin_legal_pages_missing', 'label' => 'Status nieutworzona', 'type' => 'text', 'default' => 'Nie utworzona'],
                    ['key' => 'polski_general|admin_edit_button_text', 'label' => 'Tekst przycisku edycji', 'type' => 'text', 'default' => 'Edytuj'],
                    ['key' => 'polski_general|admin_generate_pages_empty_text', 'label' => 'Pusty stan stron prawnych', 'type' => 'text', 'default' => 'Nie utworzono jeszcze stron prawnych. Wygeneruj je, aby rozpocząć.'],
                    ['key' => 'polski_general|admin_generate_pages_button_text', 'label' => 'Tekst przycisku generowania stron', 'type' => 'text', 'default' => 'Wygeneruj strony prawne'],
                    ['key' => 'polski_general|admin_next_steps_title', 'label' => 'Tytuł kolejnych kroków', 'type' => 'text', 'default' => 'Kolejne kroki'],
                    ['key' => 'polski_general|admin_next_steps_publish_pages', 'label' => 'Krok - publikacja stron', 'type' => 'text', 'default' => 'Opublikuj swoje strony prawne, Regulamin, Politykę prywatności, Prawo odstąpienia i Reklamacje.'],
                    ['key' => 'polski_general|admin_next_steps_tax', 'label' => 'Krok - stawki VAT', 'type' => 'textarea', 'default' => 'Skonfiguruj <a href="%s">stawki VAT</a> w WooCommerce dla polskiego rynku, 23%%, 8%%, 5%% i 0%%.'],
                    ['key' => 'polski_general|admin_next_steps_shipping', 'label' => 'Krok - strefy wysyłki', 'type' => 'textarea', 'default' => 'Skonfiguruj <a href="%s">strefy wysyłki</a> dla dostaw w Polsce.'],
                    ['key' => 'polski_general|admin_next_steps_products', 'label' => 'Krok - dane produktów', 'type' => 'textarea', 'default' => 'Uzupełnij dane produktów, dodaj ceny jednostkowe i czasy dostawy w <a href="%s">zakładce Polski</a> dla każdego produktu.'],
                    ['key' => 'polski_general|admin_next_steps_checkout', 'label' => 'Krok - test checkoutu', 'type' => 'textarea', 'default' => 'Przetestuj checkout, dodaj produkt do koszyka i sprawdź checkboxy prawne oraz tekst przycisku na <a href="%s">stronie zamówienia</a>.'],
                    ['key' => 'polski_general|admin_omnibus_plugin_detected_text', 'label' => 'Status wykrytej wtyczki Omnibus', 'type' => 'text', 'default' => 'wykryta, dane synchronizowane'],
                    ['key' => 'polski_general|admin_omnibus_plugin_missing_text', 'label' => 'Status brakującej wtyczki Omnibus', 'type' => 'text', 'default' => 'niezainstalowana'],
                    ['key' => 'polski_general|admin_omnibus_no_external_text', 'label' => 'Komunikat bez zewnętrznej wtyczki Omnibus', 'type' => 'textarea', 'default' => 'Żadna zewnętrzna wtyczka Omnibus nie jest zainstalowana. Polski używa wbudowanego systemu śledzenia cen.'],
                    ['key' => 'polski_general|admin_omnibus_external_active_text', 'label' => 'Komunikat po wykryciu zewnętrznej wtyczki Omnibus', 'type' => 'textarea', 'default' => 'Zewnętrzna wtyczka wykryta. Polski korzysta z jej danych zamiast wbudowanego systemu.'],
                ],
            ],
            [
                'id' => 'email_attachments',
                'name' => 'Załączniki prawne w emailach',
                'description' => 'Dołączanie treści stron prawnych (regulamin, polityka prywatności, prawo odstąpienia) do emaili z potwierdzeniem zamówienia.',
                'group' => 'Prawa konsumenta',
                'enabled' => true,
                'pro' => false,
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
                'name' => 'Producent i GPSR',
                'description' => 'Informacje o producencie, osoba odpowiedzialna (GPSR), dokumenty bezpieczeństwa, instrukcje bezpieczeństwa.',
                'group' => 'Informacje o produkcie',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-building',
                'links' => [],
                'settings' => [],
            ],
            [
                'id' => 'food_module',
                'name' => 'Żywność i suplementy',
                'description' => 'Tabela wartości odżywczych, alergeny, składniki, Nutri-Score, zawartość alkoholu, kraj pochodzenia, dystrybutor.',
                'group' => 'Informacje o produkcie',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-carrot',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_food|show_ingredients', 'label' => 'Pokazuj składniki', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_allergens', 'label' => 'Pokazuj alergeny', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_nutrients', 'label' => 'Pokazuj tabelę wartości odżywczych', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_nutri_score', 'label' => 'Pokazuj Nutri-Score', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_alcohol', 'label' => 'Pokazuj zawartość alkoholu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_origin', 'label' => 'Pokazuj kraj pochodzenia', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_distributor', 'label' => 'Pokazuj dystrybutora', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|show_net_filling', 'label' => 'Pokazuj zawartość netto', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_food|ingredients_label', 'label' => 'Etykieta składników', 'type' => 'text', 'default' => 'Składniki'],
                    ['key' => 'polski_food|allergens_label', 'label' => 'Etykieta alergenów', 'type' => 'text', 'default' => 'Alergeny'],
                    ['key' => 'polski_food|nutrients_caption_prefix', 'label' => 'Prefix nagłówka tabeli', 'type' => 'text', 'default' => 'Wartości odżywcze na'],
                    ['key' => 'polski_food|nutrients_reference_unit', 'label' => 'Domyślna jednostka odniesienia', 'type' => 'text', 'default' => '100 g'],
                    ['key' => 'polski_food|nutrients_column_name', 'label' => 'Kolumna składnika odżywczego', 'type' => 'text', 'default' => 'Składnik odżywczy'],
                    ['key' => 'polski_food|nutrients_column_value', 'label' => 'Kolumna wartości', 'type' => 'text', 'default' => 'Wartość'],
                    ['key' => 'polski_food|nutri_score_label', 'label' => 'Etykieta Nutri-Score', 'type' => 'text', 'default' => 'Nutri-Score'],
                    ['key' => 'polski_food|alcohol_label', 'label' => 'Etykieta alkoholu', 'type' => 'text', 'default' => 'Zawartość alkoholu'],
                    ['key' => 'polski_food|alcohol_suffix', 'label' => 'Sufiks alkoholu', 'type' => 'text', 'default' => '% vol.'],
                    ['key' => 'polski_food|origin_label', 'label' => 'Etykieta kraju pochodzenia', 'type' => 'text', 'default' => 'Kraj pochodzenia'],
                    ['key' => 'polski_food|distributor_label', 'label' => 'Etykieta dystrybutora', 'type' => 'text', 'default' => 'Dystrybutor'],
                    ['key' => 'polski_food|net_filling_label', 'label' => 'Etykieta zawartości netto', 'type' => 'text', 'default' => 'Zawartość netto'],
                ],
            ],
            [
                'id' => 'power_supply',
                'name' => 'Informacje o zasilaniu',
                'description' => 'Dane o zużyciu energii dla urządzeń elektrycznych (etykiety energetyczne).',
                'group' => 'Informacje o produkcie',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-lightbulb',
                'links' => [],
                'settings' => [],
            ],

            // === Konto klienta ===
            [
                'id' => 'double_opt_in',
                'name' => 'Podwójna weryfikacja (DOI)',
                'description' => 'Weryfikacja adresu email przy rejestracji konta. Link aktywacyjny wysyłany emailem, blokada logowania dla nieaktywowanych kont.',
                'group' => 'Konto klienta',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-lock',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_doi|cleanup_days', 'label' => 'Usuń nieaktywne konta po ilu dniach', 'type' => 'number', 'default' => 7],
                    ['key' => 'polski_doi|login_blocked_text', 'label' => 'Komunikat blokady logowania', 'type' => 'text', 'default' => 'Twoje konto czeka na aktywację! Zerknij do swojej skrzynki e-mail i kliknij w przesłany przez nas link.'],
                    ['key' => 'polski_doi|invalid_link_text', 'label' => 'Komunikat błędnego linku', 'type' => 'text', 'default' => 'Nieprawidłowy link aktywacyjny.'],
                    ['key' => 'polski_doi|activation_success_text', 'label' => 'Komunikat po aktywacji', 'type' => 'text', 'default' => 'Wspaniale! Twoje konto jest już aktywowane. Możesz się teraz śmiało zalogować.'],
                    ['key' => 'polski_doi|email_subject', 'label' => 'Temat emaila', 'type' => 'text', 'default' => 'Aktywuj swoje konto w {site_title}', 'hint' => 'Zmienne: {site_title}'],
                    ['key' => 'polski_doi|email_heading', 'label' => 'Nagłówek emaila', 'type' => 'text', 'default' => 'Potwierdź swój adres email'],
                    ['key' => 'polski_doi|email_greeting', 'label' => 'Powitanie emaila', 'type' => 'text', 'default' => 'Cześć {name},', 'hint' => 'Zmienne: {name}'],
                    ['key' => 'polski_doi|email_intro_html', 'label' => 'Treść emaila HTML', 'type' => 'textarea', 'default' => 'Dziękujemy za założenie konta. Kliknij przycisk poniżej, aby aktywować konto:'],
                    ['key' => 'polski_doi|email_button_text', 'label' => 'Tekst przycisku emaila', 'type' => 'text', 'default' => 'Aktywuj konto'],
                    ['key' => 'polski_doi|email_link_intro', 'label' => 'Tekst linku zapasowego', 'type' => 'text', 'default' => 'Jeśli wolisz, skopiuj i wklej ten link do przeglądarki:'],
                    ['key' => 'polski_doi|email_intro_plain', 'label' => 'Treść emaila plain text', 'type' => 'textarea', 'default' => 'Dziękujemy za założenie konta. Odwiedź poniższy link, aby aktywować konto:'],
                    ['key' => 'polski_doi|additional_content', 'label' => 'Dodatkowa treść emaila', 'type' => 'textarea', 'default' => 'Jeśli to nie Ty zakładałeś/-aś u nas konto, nie przejmuj się i po prostu wykasuj tę wiadomość.'],
                ],
            ],

            // === Sprzedaż i B2B ===
            [
                'id' => 'request_quote',
                'name' => 'Zapytania ofertowe',
                'description' => 'Formularz ofertowy dla B2B i większych zamówień, z obsługą firmy, telefonu, NIP, ilości oraz statusem leadu w panelu.',
                'group' => 'Sprzedaż i B2B',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-media-text',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_quote|availability', 'label' => 'Zakres działania', 'type' => 'select', 'default' => 'selected', 'options' => ['selected' => 'Tylko wybrane produkty', 'all_products' => 'Wszystkie produkty']],
                    ['key' => 'polski_quote|show_on_single', 'label' => 'Pokazuj na stronie produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quote|show_on_loop', 'label' => 'Pokazuj na listach produktów', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_quote|allow_guest', 'label' => 'Pozwól gościom wysyłać zapytania', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quote|replace_add_to_cart', 'label' => 'Zastąp koszyk trybem wyceny', 'type' => 'checkbox', 'default' => false, 'hint' => 'Działa dla produktów oznaczonych jako "tylko wycena"'],
                    ['key' => 'polski_quote|hide_prices', 'label' => 'Ukrywaj ceny przy "tylko wycena"', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_quote|button_text', 'label' => 'Tekst przycisku', 'type' => 'text', 'default' => 'Zapytaj o wycenę'],
                    ['key' => 'polski_quote|modal_title', 'label' => 'Tytuł formularza', 'type' => 'text', 'default' => 'Zapytaj o indywidualną wycenę'],
                    ['key' => 'polski_quote|intro_text', 'label' => 'Opis formularza', 'type' => 'textarea', 'default' => 'Wypełnij krótki formularz, a wrócimy z wyceną dopasowaną do Twojego zamówienia.'],
                    ['key' => 'polski_quote|close_label', 'label' => 'Etykieta zamknięcia formularza', 'type' => 'text', 'default' => 'Zamknij formularz wyceny'],
                    ['key' => 'polski_quote|login_required_text', 'label' => 'Komunikat dla niezalogowanych', 'type' => 'text', 'default' => 'Zaloguj się, aby wysłać zapytanie ofertowe dla tego produktu.'],
                    ['key' => 'polski_quote|name_label', 'label' => 'Etykieta pola imienia i nazwiska', 'type' => 'text', 'default' => 'Imię i nazwisko'],
                    ['key' => 'polski_quote|email_label', 'label' => 'Etykieta pola email', 'type' => 'text', 'default' => 'Email'],
                    ['key' => 'polski_quote|phone_label', 'label' => 'Etykieta pola telefonu', 'type' => 'text', 'default' => 'Telefon'],
                    ['key' => 'polski_quote|company_label', 'label' => 'Etykieta pola firmy', 'type' => 'text', 'default' => 'Firma'],
                    ['key' => 'polski_quote|nip_label', 'label' => 'Etykieta pola NIP', 'type' => 'text', 'default' => 'NIP'],
                    ['key' => 'polski_quote|postcode_label', 'label' => 'Etykieta pola kodu pocztowego', 'type' => 'text', 'default' => 'Kod pocztowy'],
                    ['key' => 'polski_quote|quantity_label', 'label' => 'Etykieta pola ilości', 'type' => 'text', 'default' => 'Ilość'],
                    ['key' => 'polski_quote|message_label', 'label' => 'Etykieta pola wiadomości', 'type' => 'text', 'default' => 'Szczegóły zapytania'],
                    ['key' => 'polski_quote|message_placeholder', 'label' => 'Placeholder wiadomości', 'type' => 'text', 'default' => 'Napisz czego potrzebujesz, jaki masz termin i jakie są założenia zamówienia.'],
                    ['key' => 'polski_quote|submit_text', 'label' => 'Tekst przycisku wysyłki', 'type' => 'text', 'default' => 'Wyślij zapytanie'],
                    ['key' => 'polski_quote|success_text', 'label' => 'Komunikat po wysyłce', 'type' => 'textarea', 'default' => 'Dziękujemy. Twoje zapytanie zostało wysłane, wrócimy z odpowiedzią tak szybko, jak to możliwe.'],
                    ['key' => 'polski_quote|price_placeholder_text', 'label' => 'Tekst zamiast ceny w trybie tylko wycena', 'type' => 'text', 'default' => 'Cena dostępna po wycenie'],
                    ['key' => 'polski_quote|invalid_contact_text', 'label' => 'Błąd: niepoprawne dane kontaktowe', 'type' => 'text', 'default' => 'Uzupełnij poprawnie imię i adres email.'],
                    ['key' => 'polski_quote|phone_required_text', 'label' => 'Błąd: brak telefonu', 'type' => 'text', 'default' => 'Podaj numer telefonu do kontaktu.'],
                    ['key' => 'polski_quote|company_required_text', 'label' => 'Błąd: brak firmy', 'type' => 'text', 'default' => 'Podaj nazwę firmy.'],
                    ['key' => 'polski_quote|nip_required_text', 'label' => 'Błąd: brak NIP', 'type' => 'text', 'default' => 'Podaj numer NIP.'],
                    ['key' => 'polski_quote|nip_invalid_text', 'label' => 'Błąd: niepoprawny NIP', 'type' => 'text', 'default' => 'Podaj poprawny numer NIP.'],
                    ['key' => 'polski_quote|postcode_required_text', 'label' => 'Błąd: brak kodu pocztowego', 'type' => 'text', 'default' => 'Podaj kod pocztowy.'],
                    ['key' => 'polski_quote|privacy_required_text', 'label' => 'Błąd: brak zgody prywatności', 'type' => 'text', 'default' => 'Musisz zaakceptować zgodę prywatności.'],
                    ['key' => 'polski_quote|minimum_quantity_text', 'label' => 'Błąd: minimalna ilość', 'type' => 'text', 'default' => 'Minimalna ilość dla tego zapytania to {quantity}.', 'hint' => 'Zmienna: {quantity}'],
                    ['key' => 'polski_quote|save_error_text', 'label' => 'Błąd zapisu', 'type' => 'text', 'default' => 'Nie udało się zapisać zapytania. Spróbuj ponownie.'],
                    ['key' => 'polski_quote|product_unavailable_text', 'label' => 'Błąd niedostępnego produktu', 'type' => 'text', 'default' => 'Nie udało się przygotować zapytania dla tego produktu.'],
                    ['key' => 'polski_quote|recipient_email', 'label' => 'Email odbiorcy', 'type' => 'email', 'default' => '', 'hint' => 'Puste pole = email administratora'],
                    ['key' => 'polski_quote|require_company', 'label' => 'Wymagaj nazwy firmy', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quote|require_phone', 'label' => 'Wymagaj telefonu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quote|require_nip', 'label' => 'Wymagaj NIP', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_quote|require_postcode', 'label' => 'Wymagaj kodu pocztowego', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_quote|privacy_required', 'label' => 'Wymagaj zgody prywatności', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quote|privacy_label', 'label' => 'Treść zgody prywatności', 'type' => 'text', 'default' => 'Akceptuję politykę prywatności i zgadzam się na kontakt w sprawie wyceny.'],
                    ['key' => 'polski_quote|customer_email_enabled', 'label' => 'Wysyłaj potwierdzenie do klienta', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quote|admin_page_title', 'label' => 'Tytuł strony zapytań', 'type' => 'text', 'default' => 'Zapytania ofertowe'],
                    ['key' => 'polski_quote|admin_success_notice', 'label' => 'Komunikat po zmianie statusu', 'type' => 'text', 'default' => 'Status zapytania został zaktualizowany.'],
                    ['key' => 'polski_quote|admin_filter_all_label', 'label' => 'Etykieta filtra wszystkie', 'type' => 'text', 'default' => 'Wszystkie'],
                    ['key' => 'polski_quote|admin_column_date', 'label' => 'Kolumna Data', 'type' => 'text', 'default' => 'Data'],
                    ['key' => 'polski_quote|admin_column_product', 'label' => 'Kolumna Produkt', 'type' => 'text', 'default' => 'Produkt'],
                    ['key' => 'polski_quote|admin_column_customer', 'label' => 'Kolumna Klient', 'type' => 'text', 'default' => 'Klient'],
                    ['key' => 'polski_quote|admin_column_company', 'label' => 'Kolumna Firma / NIP', 'type' => 'text', 'default' => 'Firma / NIP'],
                    ['key' => 'polski_quote|admin_column_quantity', 'label' => 'Kolumna Ilość', 'type' => 'text', 'default' => 'Ilość'],
                    ['key' => 'polski_quote|admin_column_status', 'label' => 'Kolumna Status', 'type' => 'text', 'default' => 'Status'],
                    ['key' => 'polski_quote|admin_column_actions', 'label' => 'Kolumna Akcje', 'type' => 'text', 'default' => 'Akcje'],
                    ['key' => 'polski_quote|admin_empty_text', 'label' => 'Pusty stan strony zapytań', 'type' => 'text', 'default' => 'Brak zapytań ofertowych.'],
                    ['key' => 'polski_quote|admin_postcode_label', 'label' => 'Etykieta kodu pocztowego', 'type' => 'text', 'default' => 'Kod'],
                    ['key' => 'polski_quote|admin_date_format', 'label' => 'Format daty na stronie zapytań', 'type' => 'text', 'default' => 'Y-m-d H:i'],
                ],
            ],
            [
                'id' => 'catalog_mode',
                'name' => 'Tryb katalogowy B2B',
                'description' => 'Ukrywanie cen i koszyka dla wybranych produktów lub grup odbiorców, z przekierowaniem do logowania albo zapytania ofertowego.',
                'group' => 'Sprzedaż i B2B',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-visibility',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_catalog|availability', 'label' => 'Zakres działania', 'type' => 'select', 'default' => 'selected', 'options' => ['selected' => 'Tylko wybrane produkty', 'all_products' => 'Wszystkie produkty']],
                    ['key' => 'polski_catalog|audience', 'label' => 'Dla kogo aktywny', 'type' => 'select', 'default' => 'guests_only', 'options' => ['guests_only' => 'Tylko goście', 'logged_in' => 'Wszyscy zalogowani', 'all_users' => 'Wszyscy użytkownicy', 'selected_roles' => 'Wybrane role']],
                    ['key' => 'polski_catalog|selected_roles', 'label' => 'Role użytkowników', 'type' => 'textarea', 'default' => '', 'hint' => 'Podaj slug roli, po przecinku lub w osobnych liniach, np. customer, b2b_customer'],
                    ['key' => 'polski_catalog|hide_prices', 'label' => 'Ukrywaj ceny', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_catalog|hide_add_to_cart', 'label' => 'Ukrywaj koszyk / zakup', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_catalog|replacement_mode', 'label' => 'CTA zastępcze', 'type' => 'select', 'default' => 'quote', 'options' => ['quote' => 'Zapytanie ofertowe', 'login' => 'Logowanie', 'custom_url' => 'Własny link', 'none' => 'Brak CTA']],
                    ['key' => 'polski_catalog|cta_text', 'label' => 'Tekst CTA', 'type' => 'text', 'default' => 'Zapytaj o warunki handlowe'],
                    ['key' => 'polski_catalog|login_cta_text', 'label' => 'Tekst CTA logowania', 'type' => 'text', 'default' => 'Zaloguj się, aby zobaczyć ofertę'],
                    ['key' => 'polski_catalog|custom_url', 'label' => 'Własny link CTA', 'type' => 'text', 'default' => '', 'hint' => 'Używany tylko przy trybie "Własny link"'],
                    ['key' => 'polski_catalog|custom_url_target', 'label' => 'Jak otwierać własny link', 'type' => 'select', 'default' => 'same_tab', 'options' => ['same_tab' => 'W tej samej karcie', 'new_tab' => 'W nowej karcie']],
                    ['key' => 'polski_catalog|hidden_price_text', 'label' => 'Tekst zamiast ceny', 'type' => 'text', 'default' => 'Cena dostępna po zalogowaniu lub po kontakcie z obsługą.'],
                    ['key' => 'polski_catalog|single_notice', 'label' => 'Komunikat na stronie produktu', 'type' => 'textarea', 'default' => 'Produkt dostępny w trybie katalogowym. Skontaktuj się z nami, aby uzyskać warunki handlowe lub ofertę B2B.'],
                    ['key' => 'polski_catalog|loop_notice', 'label' => 'Komunikat na listingach', 'type' => 'text', 'default' => 'Zobacz warunki handlowe'],
                    ['key' => 'polski_catalog|single_both_hidden_text', 'label' => 'Komunikat: ukryta cena i zakup', 'type' => 'text', 'default' => 'Ceny i zakup online są ukryte dla tego produktu.'],
                    ['key' => 'polski_catalog|single_price_hidden_text', 'label' => 'Komunikat: ukryta cena', 'type' => 'text', 'default' => 'Cena jest ukryta dla tego produktu.'],
                    ['key' => 'polski_catalog|single_cart_hidden_text', 'label' => 'Komunikat: wyłączony zakup', 'type' => 'text', 'default' => 'Zakup online jest wyłączony dla tego produktu.'],
                    ['key' => 'polski_catalog|show_single_notice', 'label' => 'Pokazuj główny komunikat na stronie produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_catalog|show_single_restriction_text', 'label' => 'Pokazuj opis ograniczeń na stronie produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_catalog|show_single_cta', 'label' => 'Pokazuj CTA na stronie produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_catalog|show_loop_notice', 'label' => 'Pokazuj komunikat na listingach', 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'ajax_search',
                'name' => 'Wyszukiwarka AJAX',
                'description' => 'Szybkie podpowiedzi produktów w trakcie pisania, z obsługą SKU, kategorii i lekkim frontem przyjaznym dla web vitals.',
                'group' => 'Sprzedaż i B2B',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-search',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_search|min_chars', 'label' => 'Minimalna liczba znaków', 'type' => 'number', 'default' => 2],
                    ['key' => 'polski_search|limit', 'label' => 'Liczba wyników', 'type' => 'number', 'default' => 6],
                    ['key' => 'polski_search|debounce_ms', 'label' => 'Opóźnienie zapytania (ms)', 'type' => 'number', 'default' => 180],
                    ['key' => 'polski_search|show_submit_button', 'label' => 'Pokazuj przycisk wyszukiwania', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|show_image', 'label' => 'Pokazuj miniatury', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|show_price', 'label' => 'Pokazuj ceny', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|show_sku', 'label' => 'Pokazuj SKU', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|show_view_all_link', 'label' => 'Pokazuj link do pełnych wyników', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|search_sku', 'label' => 'Szukaj po SKU', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|search_categories', 'label' => 'Szukaj po kategoriach', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_search|include_out_of_stock', 'label' => 'Uwzględniaj produkty bez stanu', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_search|search_label', 'label' => 'Etykieta pola wyszukiwania', 'type' => 'text', 'default' => 'Szukaj produktów'],
                    ['key' => 'polski_search|results_label', 'label' => 'Etykieta wyników', 'type' => 'text', 'default' => 'Wyniki wyszukiwania produktów'],
                    ['key' => 'polski_search|sku_label', 'label' => 'Etykieta SKU', 'type' => 'text', 'default' => 'SKU'],
                    ['key' => 'polski_search|placeholder', 'label' => 'Placeholder', 'type' => 'text', 'default' => 'Szukaj produktów, kodów SKU lub kategorii'],
                    ['key' => 'polski_search|submit_button_text', 'label' => 'Tekst przycisku', 'type' => 'text', 'default' => 'Szukaj'],
                    ['key' => 'polski_search|no_results_text', 'label' => 'Brak wyników', 'type' => 'text', 'default' => 'Brak wyników dla podanego zapytania.'],
                    ['key' => 'polski_search|view_all_text', 'label' => 'Zobacz wszystkie wyniki', 'type' => 'text', 'default' => 'Zobacz wszystkie wyniki'],
                ],
            ],

            // === Merchandising ===
            [
                'id' => 'brands',
                'name' => 'Marki',
                'description' => 'Obsługa marek produktowych niezależnie od producenta, z widokiem na produkcie i listach oraz własną taksonomią.',
                'group' => 'Merchandising',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-tag',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_brand|show_on_single', 'label' => 'Pokazuj na stronie produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_brand|show_on_loop', 'label' => 'Pokazuj na listach produktów', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_brand|label', 'label' => 'Etykieta', 'type' => 'text', 'default' => 'Marka'],
                    ['key' => 'polski_brand|show_label', 'label' => 'Pokazuj etykietę', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_brand|separator', 'label' => 'Separator marek', 'type' => 'text', 'default' => ', '],
                    ['key' => 'polski_brand|link_terms', 'label' => 'Linkuj do archiwum marki', 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'ajax_filters',
                'name' => 'Filtry AJAX',
                'description' => 'Filtrowanie list produktów bez przeładowania strony, z kategoriami, markami, ceną, stanem magazynowym, promocją i atrybutami.',
                'group' => 'Merchandising',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-filter',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_filters|show_on_shop', 'label' => 'Pokazuj na archiwach sklepu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_title', 'label' => 'Pokazuj nagłówek formularza', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_categories', 'label' => 'Filtr kategorii', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_brands', 'label' => 'Filtr marek', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_price', 'label' => 'Filtr ceny', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_stock', 'label' => 'Filtr dostępności', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_sale', 'label' => 'Filtr promocji', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|show_attributes', 'label' => 'Filtry atrybutów', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|max_attribute_taxonomies', 'label' => 'Maks. liczba atrybutów', 'type' => 'number', 'default' => 4],
                    ['key' => 'polski_filters|title', 'label' => 'Nagłówek', 'type' => 'text', 'default' => 'Filtry produktów'],
                    ['key' => 'polski_filters|category_label', 'label' => 'Etykieta kategorii', 'type' => 'text', 'default' => 'Kategoria'],
                    ['key' => 'polski_filters|category_all_text', 'label' => 'Tekst wszystkich kategorii', 'type' => 'text', 'default' => 'Wszystkie'],
                    ['key' => 'polski_filters|brand_label', 'label' => 'Etykieta marki', 'type' => 'text', 'default' => 'Marka'],
                    ['key' => 'polski_filters|brand_all_text', 'label' => 'Tekst wszystkich marek', 'type' => 'text', 'default' => 'Wszystkie'],
                    ['key' => 'polski_filters|min_price_label', 'label' => 'Etykieta ceny od', 'type' => 'text', 'default' => 'Cena od'],
                    ['key' => 'polski_filters|max_price_label', 'label' => 'Etykieta ceny do', 'type' => 'text', 'default' => 'Cena do'],
                    ['key' => 'polski_filters|stock_label', 'label' => 'Etykieta dostępności', 'type' => 'text', 'default' => 'Dostępność'],
                    ['key' => 'polski_filters|stock_any_text', 'label' => 'Tekst dowolnej dostępności', 'type' => 'text', 'default' => 'Dowolna'],
                    ['key' => 'polski_filters|stock_instock_text', 'label' => 'Tekst dostępnego produktu', 'type' => 'text', 'default' => 'Dostępne od ręki'],
                    ['key' => 'polski_filters|sale_label', 'label' => 'Etykieta promocji', 'type' => 'text', 'default' => 'Promocje'],
                    ['key' => 'polski_filters|attribute_any_text', 'label' => 'Tekst dowolnej wartości atrybutu', 'type' => 'text', 'default' => 'Dowolny'],
                    ['key' => 'polski_filters|show_reset_link', 'label' => 'Pokazuj link resetu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_filters|submit_text', 'label' => 'Tekst przycisku', 'type' => 'text', 'default' => 'Filtruj'],
                    ['key' => 'polski_filters|reset_text', 'label' => 'Tekst resetu', 'type' => 'text', 'default' => 'Wyczyść filtry'],
                ],
            ],
            [
                'id' => 'wishlist',
                'name' => 'Lista życzeń',
                'description' => 'Zapisywanie ulubionych produktów dla gości i zalogowanych, z listą w koncie klienta i AJAX-owym dodawaniem/usuwaniem.',
                'group' => 'Merchandising',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-heart',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_wishlist|allow_guests', 'label' => 'Pozwól gościom zapisywać ulubione', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_on_single', 'label' => 'Pokazuj na stronie produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_on_loop', 'label' => 'Pokazuj na listingach', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_in_account', 'label' => 'Pokazuj w Moim koncie', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_title', 'label' => 'Pokazuj tytuł listy', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_product_image', 'label' => 'Pokazuj zdjęcia produktów', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_product_name', 'label' => 'Pokazuj nazwy produktów', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_price', 'label' => 'Pokazuj cenę w liście', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_add_to_cart', 'label' => 'Pokazuj przycisk koszyka w liście', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|show_remove_button', 'label' => 'Pokazuj przycisk usuwania w liście', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_wishlist|grid_columns', 'label' => 'Liczba kolumn w liście', 'type' => 'number', 'default' => 4],
                    ['key' => 'polski_wishlist|account_label', 'label' => 'Etykieta w Moim koncie', 'type' => 'text', 'default' => 'Ulubione'],
                    ['key' => 'polski_wishlist|title', 'label' => 'Tytuł listy', 'type' => 'text', 'default' => 'Twoje ulubione produkty'],
                    ['key' => 'polski_wishlist|account_intro_text', 'label' => 'Opis sekcji', 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_wishlist|button_add_text', 'label' => 'Tekst dodawania', 'type' => 'text', 'default' => 'Dodaj do ulubionych'],
                    ['key' => 'polski_wishlist|button_remove_text', 'label' => 'Tekst usuwania', 'type' => 'text', 'default' => 'Usuń z ulubionych'],
                    ['key' => 'polski_wishlist|login_required_text', 'label' => 'Komunikat logowania', 'type' => 'text', 'default' => 'Zaloguj się, aby korzystać z listy życzeń.'],
                    ['key' => 'polski_wishlist|product_not_found_text', 'label' => 'Komunikat braku produktu', 'type' => 'text', 'default' => 'Nie znaleziono produktu.'],
                    ['key' => 'polski_wishlist|empty_text', 'label' => 'Pusty stan', 'type' => 'text', 'default' => 'Lista ulubionych jest pusta.'],
                ],
            ],
            [
                'id' => 'compare',
                'name' => 'Porównanie produktów',
                'description' => 'Porównywarka produktów z tabelą cech, wyróżnianiem różnic, obsługą gości i klientów oraz widokiem w Moim koncie.',
                'group' => 'Merchandising',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-randomize',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_compare|allow_guests', 'label' => 'Pozwól gościom porównywać produkty', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_on_single', 'label' => 'Pokazuj na stronie produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_on_loop', 'label' => 'Pokazuj na listingach', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_in_account', 'label' => 'Pokazuj w Moim koncie', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_product_image', 'label' => 'Pokazuj zdjęcia produktów', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_add_to_cart', 'label' => 'Pokazuj przycisk koszyka', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_remove_button', 'label' => 'Pokazuj przycisk usuwania', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|account_label', 'label' => 'Etykieta w Moim koncie', 'type' => 'text', 'default' => 'Porównanie'],
                    ['key' => 'polski_compare|title', 'label' => 'Tytuł porównania', 'type' => 'text', 'default' => 'Porównanie produktów'],
                    ['key' => 'polski_compare|max_items', 'label' => 'Maksymalna liczba produktów', 'type' => 'number', 'default' => 4],
                    ['key' => 'polski_compare|button_add_text', 'label' => 'Tekst dodawania', 'type' => 'text', 'default' => 'Dodaj do porównania'],
                    ['key' => 'polski_compare|button_remove_text', 'label' => 'Tekst usuwania', 'type' => 'text', 'default' => 'Usuń z porównania'],
                    ['key' => 'polski_compare|compare_link_text', 'label' => 'Tekst linku do porównania', 'type' => 'text', 'default' => 'Porównaj produkty'],
                    ['key' => 'polski_compare|clear_text', 'label' => 'Tekst czyszczenia', 'type' => 'text', 'default' => 'Wyczyść porównanie'],
                    ['key' => 'polski_compare|feature_label', 'label' => 'Nagłówek kolumny cech', 'type' => 'text', 'default' => 'Cecha'],
                    ['key' => 'polski_compare|differences_toggle_text', 'label' => 'Etykieta filtra różnic', 'type' => 'text', 'default' => 'Pokazuj tylko różnice'],
                    ['key' => 'polski_compare|login_required_text', 'label' => 'Komunikat logowania', 'type' => 'text', 'default' => 'Zaloguj się, aby korzystać z porównania produktów.'],
                    ['key' => 'polski_compare|product_not_found_text', 'label' => 'Komunikat braku produktu', 'type' => 'text', 'default' => 'Nie znaleziono produktu.'],
                    ['key' => 'polski_compare|limit_notice_text', 'label' => 'Komunikat limitu', 'type' => 'text', 'default' => 'Możesz porównać maksymalnie {limit} produkty jednocześnie. Najstarszy wpis został zastąpiony automatycznie.', 'hint' => 'Zmienna: {limit}'],
                    ['key' => 'polski_compare|clear_error_text', 'label' => 'Komunikat błędu czyszczenia', 'type' => 'text', 'default' => 'Nie możesz wyczyścić porównania.'],
                    ['key' => 'polski_compare|intro_text', 'label' => 'Opis sekcji', 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_compare|empty_text', 'label' => 'Pusty stan', 'type' => 'text', 'default' => 'Lista porównawcza jest pusta.'],
                    ['key' => 'polski_compare|highlight_differences', 'label' => 'Wyróżniaj różnice', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_only_differences', 'label' => 'Domyślnie pokazuj tylko różnice', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_compare|price_label', 'label' => 'Etykieta ceny', 'type' => 'text', 'default' => 'Cena'],
                    ['key' => 'polski_compare|unit_price_label', 'label' => 'Etykieta ceny jednostkowej', 'type' => 'text', 'default' => 'Cena jednostkowa'],
                    ['key' => 'polski_compare|sku_label', 'label' => 'Etykieta SKU', 'type' => 'text', 'default' => 'SKU'],
                    ['key' => 'polski_compare|availability_label', 'label' => 'Etykieta dostępności', 'type' => 'text', 'default' => 'Dostępność'],
                    ['key' => 'polski_compare|delivery_time_label', 'label' => 'Etykieta czasu dostawy', 'type' => 'text', 'default' => 'Czas dostawy'],
                    ['key' => 'polski_compare|brand_label', 'label' => 'Etykieta marki', 'type' => 'text', 'default' => 'Marka'],
                    ['key' => 'polski_compare|manufacturer_label', 'label' => 'Etykieta producenta', 'type' => 'text', 'default' => 'Producent'],
                    ['key' => 'polski_compare|gtin_label', 'label' => 'Etykieta GTIN / EAN', 'type' => 'text', 'default' => 'GTIN / EAN'],
                    ['key' => 'polski_compare|description_label', 'label' => 'Etykieta krótkiego opisu', 'type' => 'text', 'default' => 'Krótki opis'],
                    ['key' => 'polski_compare|show_description', 'label' => 'Pokazuj krótki opis', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_compare|show_attributes', 'label' => 'Pokazuj atrybuty produktu', 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'quick_view',
                'name' => 'Szybki podgląd',
                'description' => 'Lekki modal produktu na listingach, z obsługą wariantów, cen, galerii i podstawowych informacji zakupowych.',
                'group' => 'Merchandising',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-visibility',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_quick_view|show_on_loop', 'label' => 'Pokazuj na listingach', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|button_text', 'label' => 'Tekst przycisku', 'type' => 'text', 'default' => 'Szybki podgląd'],
                    ['key' => 'polski_quick_view|modal_title', 'label' => 'Etykieta modala', 'type' => 'text', 'default' => 'Szybki podgląd produktu'],
                    ['key' => 'polski_quick_view|close_label', 'label' => 'Etykieta zamknięcia', 'type' => 'text', 'default' => 'Zamknij'],
                    ['key' => 'polski_quick_view|loading_text', 'label' => 'Tekst ładowania', 'type' => 'text', 'default' => 'Ładowanie produktu...'],
                    ['key' => 'polski_quick_view|error_text', 'label' => 'Tekst błędu AJAX', 'type' => 'text', 'default' => 'Nie udało się wczytać podglądu produktu.'],
                    ['key' => 'polski_quick_view|product_not_found_text', 'label' => 'Tekst braku produktu', 'type' => 'text', 'default' => 'Nie znaleziono produktu.'],
                    ['key' => 'polski_quick_view|sku_label', 'label' => 'Etykieta SKU', 'type' => 'text', 'default' => 'SKU'],
                    ['key' => 'polski_quick_view|show_modal_label', 'label' => 'Pokazuj tytuł modala w treści', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_close_button', 'label' => 'Pokazuj przycisk zamknięcia', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_title', 'label' => 'Pokazuj nazwę produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_image', 'label' => 'Pokazuj zdjęcie główne', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_gallery', 'label' => 'Pokazuj mini galerię', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_price', 'label' => 'Pokazuj cenę', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_unit_price', 'label' => 'Pokazuj cenę jednostkową', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_sku', 'label' => 'Pokazuj SKU', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_delivery_time', 'label' => 'Pokazuj czas dostawy', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_brand', 'label' => 'Pokazuj markę', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_manufacturer', 'label' => 'Pokazuj producenta', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_short_description', 'label' => 'Pokazuj krótki opis', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_add_to_cart', 'label' => 'Pokazuj formularz zakupu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|show_view_product_link', 'label' => 'Pokazuj link do pełnej karty', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_quick_view|view_product_text', 'label' => 'Tekst linku do produktu', 'type' => 'text', 'default' => 'Zobacz pełną kartę produktu'],
                    ['key' => 'polski_quick_view|view_product_target', 'label' => 'Jak otwierać pełną kartę', 'type' => 'select', 'default' => 'same_tab', 'options' => ['same_tab' => 'W tej samej karcie', 'new_tab' => 'W nowej karcie']],
                    ['key' => 'polski_quick_view|show_backdrop_close', 'label' => 'Zamykaj po kliknięciu tła', 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'frequently_bought_together',
                'name' => 'Często kupowane razem',
                'description' => 'Cross-sell w formie gotowego zestawu na karcie produktu, z checkboxami, sumą i dodawaniem całego pakietu do koszyka.',
                'group' => 'Merchandising',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-cart',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_fbt|show_on_single', 'label' => 'Pokazuj na stronie produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_fbt|title', 'label' => 'Nagłówek sekcji', 'type' => 'text', 'default' => 'Często kupowane razem'],
                    ['key' => 'polski_fbt|intro_text', 'label' => 'Opis sekcji', 'type' => 'textarea', 'default' => 'Zaproponuj klientowi gotowy zestaw produktów komplementarnych i dodaj całość do koszyka jednym kliknięciem.'],
                    ['key' => 'polski_fbt|button_text', 'label' => 'Tekst przycisku', 'type' => 'text', 'default' => 'Dodaj zestaw do koszyka'],
                    ['key' => 'polski_fbt|empty_text', 'label' => 'Pusty stan', 'type' => 'text', 'default' => 'Brak skonfigurowanych produktów do zestawu.'],
                    ['key' => 'polski_fbt|show_title', 'label' => 'Pokazuj nagłówek sekcji', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_fbt|show_price', 'label' => 'Pokazuj ceny produktów', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_fbt|show_empty_state', 'label' => 'Pokazuj pusty stan bez produktów', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_fbt|total_label', 'label' => 'Etykieta łącznej ceny', 'type' => 'text', 'default' => 'Łącznie:'],
                    ['key' => 'polski_fbt|success_text', 'label' => 'Komunikat po dodaniu zestawu', 'type' => 'text', 'default' => 'Dodano {count} produktów z zestawu do koszyka.', 'hint' => 'Zmienna: {count}'],
                    ['key' => 'polski_fbt|show_total', 'label' => 'Pokazuj łączną cenę', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_fbt|show_images', 'label' => 'Pokazuj zdjęcia produktów', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_fbt|show_checkboxes', 'label' => 'Pokazuj checkboxy wyboru', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_fbt|preselect_products', 'label' => 'Domyślnie zaznacz produkty', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_fbt|show_short_description', 'label' => 'Pokazuj krótkie opisy', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_fbt|max_related_products', 'label' => 'Maks. liczba produktów dodatkowych', 'type' => 'number', 'default' => 4],
                ],
            ],
            [
                'id' => 'badge_management',
                'name' => 'Badge Management',
                'description' => 'Merchandisingowe badge na produkcie i listingu, z automatycznymi warunkami i ręcznymi wyróżnieniami per produkt.',
                'group' => 'Merchandising',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-awards',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_badges|show_on_single', 'label' => 'Pokazuj na stronie produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|show_on_loop', 'label' => 'Pokazuj na listingach', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|show_manual_badge', 'label' => 'Pokazuj badge ręczny', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|manual_badge_style', 'label' => 'Domyślny styl badge ręcznego', 'type' => 'select', 'default' => 'accent', 'options' => ['accent' => 'Accent', 'neutral' => 'Neutral', 'warning' => 'Warning', 'success' => 'Success']],
                    ['key' => 'polski_badges|show_secondary_badge', 'label' => 'Pokazuj badge dodatkowy', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|secondary_badge_style', 'label' => 'Styl badge dodatkowego', 'type' => 'select', 'default' => 'neutral', 'options' => ['accent' => 'Accent', 'neutral' => 'Neutral', 'warning' => 'Warning', 'success' => 'Success']],
                    ['key' => 'polski_badges|shape', 'label' => 'Kształt badge', 'type' => 'select', 'default' => 'pill', 'options' => ['pill' => 'Pill', 'rounded' => 'Rounded']],
                    ['key' => 'polski_badges|uppercase', 'label' => 'Wielkie litery', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_badges|max_badges_single', 'label' => 'Maks. badge na stronie produktu', 'type' => 'number', 'default' => 4],
                    ['key' => 'polski_badges|max_badges_loop', 'label' => 'Maks. badge na listingach', 'type' => 'number', 'default' => 3],
                    ['key' => 'polski_badges|show_sale_badge', 'label' => 'Badge promocji', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|sale_badge_text', 'label' => 'Tekst badge promocji', 'type' => 'text', 'default' => 'Promocja'],
                    ['key' => 'polski_badges|show_new_badge', 'label' => 'Badge nowości', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|new_badge_text', 'label' => 'Tekst badge nowości', 'type' => 'text', 'default' => 'Nowość'],
                    ['key' => 'polski_badges|newness_days', 'label' => 'Nowość przez ile dni', 'type' => 'number', 'default' => 30],
                    ['key' => 'polski_badges|show_low_stock_badge', 'label' => 'Badge niskiego stanu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|low_stock_badge_text', 'label' => 'Tekst badge niskiego stanu', 'type' => 'text', 'default' => 'Ostatnie sztuki'],
                    ['key' => 'polski_badges|low_stock_threshold', 'label' => 'Próg niskiego stanu', 'type' => 'number', 'default' => 3],
                    ['key' => 'polski_badges|show_bestseller_badge', 'label' => 'Badge bestseller', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_badges|bestseller_badge_text', 'label' => 'Tekst badge bestseller', 'type' => 'text', 'default' => 'Bestseller'],
                    ['key' => 'polski_badges|bestseller_threshold', 'label' => 'Próg bestselleru (sprzedaż)', 'type' => 'number', 'default' => 25],
                ],
            ],
            [
                'id' => 'tab_manager',
                'name' => 'Tab Manager',
                'description' => 'Dodatkowe zakładki produktu z treścią per produkt oraz zakładkami globalnymi dla dostawy, zwrotów i informacji handlowych.',
                'group' => 'Merchandising',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-index-card',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_tabs|enable_global_shipping_tab', 'label' => 'Globalna zakładka dostawy', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_tabs|shipping_tab_title', 'label' => 'Tytuł zakładki dostawy', 'type' => 'text', 'default' => 'Dostawa i płatność'],
                    ['key' => 'polski_tabs|shipping_tab_content', 'label' => 'Treść zakładki dostawy', 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_tabs|shipping_tab_priority', 'label' => 'Priorytet zakładki dostawy', 'type' => 'number', 'default' => 47],
                    ['key' => 'polski_tabs|enable_global_returns_tab', 'label' => 'Globalna zakładka zwrotów', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_tabs|returns_tab_title', 'label' => 'Tytuł zakładki zwrotów', 'type' => 'text', 'default' => 'Zwroty i reklamacje'],
                    ['key' => 'polski_tabs|returns_tab_content', 'label' => 'Treść zakładki zwrotów', 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_tabs|returns_tab_priority', 'label' => 'Priorytet zakładki zwrotów', 'type' => 'number', 'default' => 48],
                    ['key' => 'polski_tabs|enable_product_tab_1', 'label' => 'Włącz pierwszy tab produktowy', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_tabs|product_tab_1_priority', 'label' => 'Priorytet pierwszego tabu produktowego', 'type' => 'number', 'default' => 45],
                    ['key' => 'polski_tabs|enable_product_tab_2', 'label' => 'Włącz drugi tab produktowy', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_tabs|product_tab_2_priority', 'label' => 'Priorytet drugiego tabu produktowego', 'type' => 'number', 'default' => 46],
                ],
            ],
            [
                'id' => 'featured_video',
                'name' => 'Featured Video',
                'description' => 'Wideo produktowe na karcie produktu, osadzone z YouTube, Vimeo albo jako lokalny plik MP4 w sekcji mediów.',
                'group' => 'Merchandising',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-video-alt3',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_featured_video|show_on_single', 'label' => 'Pokazuj na stronie produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_featured_video|position', 'label' => 'Pozycja', 'type' => 'select', 'default' => 'after_gallery', 'options' => ['after_gallery' => 'Pod galerią', 'before_summary' => 'Przed podsumowaniem produktu']],
                    ['key' => 'polski_featured_video|title', 'label' => 'Nagłówek sekcji', 'type' => 'text', 'default' => 'Zobacz produkt w użyciu'],
                    ['key' => 'polski_featured_video|intro_text', 'label' => 'Opis sekcji', 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_featured_video|show_title', 'label' => 'Pokazuj nagłówek sekcji', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_featured_video|show_intro', 'label' => 'Pokazuj opis sekcji', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_featured_video|autoplay', 'label' => 'Autoplay dla wspieranych osadzeń', 'type' => 'checkbox', 'default' => false],
                ],
            ],
            [
                'id' => 'gallery_zoom',
                'name' => 'Gallery & Zoom',
                'description' => 'Lekki zoom zdjęć produktowych i prosty lightbox galerii bez zewnętrznych bibliotek sliderowych.',
                'group' => 'Merchandising',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-format-gallery',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_gallery_zoom|enable_zoom', 'label' => 'Włącz zoom na hover', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_gallery_zoom|zoom_scale', 'label' => 'Skala zoomu', 'type' => 'number', 'default' => 1.45],
                    ['key' => 'polski_gallery_zoom|enable_lightbox', 'label' => 'Włącz lightbox po kliknięciu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_gallery_zoom|dialog_label', 'label' => 'Etykieta okna lightbox', 'type' => 'text', 'default' => 'Podgląd galerii produktu'],
                    ['key' => 'polski_gallery_zoom|close_label', 'label' => 'Etykieta zamknięcia', 'type' => 'text', 'default' => 'Zamknij podgląd galerii'],
                    ['key' => 'polski_gallery_zoom|show_backdrop_close', 'label' => 'Zamykaj po kliknięciu tła', 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'product_slider_carousel',
                'name' => 'Product Slider Carousel',
                'description' => 'Lekki slider produktowy oparty o scroll-snap, z produktami powiązanymi, promocyjnymi lub wyróżnionymi.',
                'group' => 'Merchandising',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-images-alt2',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_slider|show_on_single', 'label' => 'Pokazuj na stronie produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_slider|source', 'label' => 'Źródło produktów', 'type' => 'select', 'default' => 'related', 'options' => ['related' => 'Powiązane', 'upsell' => 'Upsell', 'sale' => 'Promocje', 'featured' => 'Wyróżnione']],
                    ['key' => 'polski_slider|title', 'label' => 'Nagłówek sekcji', 'type' => 'text', 'default' => 'Polecane produkty'],
                    ['key' => 'polski_slider|limit', 'label' => 'Liczba produktów', 'type' => 'number', 'default' => 8],
                    ['key' => 'polski_slider|show_title', 'label' => 'Pokazuj nagłówek sekcji', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_slider|show_intro_text', 'label' => 'Pokazuj opis sekcji', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_slider|intro_text', 'label' => 'Opis sekcji', 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_slider|show_image', 'label' => 'Pokazuj zdjęcia produktów', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_slider|show_price', 'label' => 'Pokazuj ceny', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_slider|show_name', 'label' => 'Pokazuj nazwę produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_slider|show_add_to_cart', 'label' => 'Pokazuj przycisk koszyka', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_slider|show_view_all_link', 'label' => 'Pokazuj link "zobacz wszystkie"', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_slider|show_empty_state', 'label' => 'Pokazuj pusty stan bez produktów', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_slider|empty_text', 'label' => 'Tekst pustego stanu', 'type' => 'text', 'default' => 'Brak produktów do wyświetlenia w tej sekcji.'],
                    ['key' => 'polski_slider|view_all_text', 'label' => 'Tekst linku "zobacz wszystkie"', 'type' => 'text', 'default' => 'Zobacz wszystkie produkty'],
                    ['key' => 'polski_slider|view_all_target', 'label' => 'Jak otwierać link "zobacz wszystkie"', 'type' => 'select', 'default' => 'same_tab', 'options' => ['same_tab' => 'W tej samej karcie', 'new_tab' => 'W nowej karcie']],
                ],
            ],
            [
                'id' => 'pre_order',
                'name' => 'Pre-Order',
                'description' => 'Przedsprzedaż produktów z datą wysyłki, własnymi komunikatami i oddzielnym CTA zakupowym.',
                'group' => 'Merchandising',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-calendar-alt',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_preorder|show_on_single', 'label' => 'Pokazuj na stronie produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_preorder|show_on_loop', 'label' => 'Pokazuj na listingach', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_preorder|show_notice', 'label' => 'Pokazuj komunikat na stronie produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_preorder|button_text', 'label' => 'Tekst przycisku', 'type' => 'text', 'default' => 'Zamów w przedsprzedaży'],
                    ['key' => 'polski_preorder|availability_text', 'label' => 'Tekst dostępności', 'type' => 'text', 'default' => 'Przedsprzedaż, wysyłka od {date}'],
                    ['key' => 'polski_preorder|notice_text', 'label' => 'Komunikat na produkcie', 'type' => 'textarea', 'default' => 'Ten produkt jest dostępny w przedsprzedaży. Zamów teraz, a wysyłkę zrealizujemy od {date}.'],
                    ['key' => 'polski_preorder|notice_title', 'label' => 'Tytuł komunikatu', 'type' => 'text', 'default' => 'Przedsprzedaż'],
                    ['key' => 'polski_preorder|show_notice_title', 'label' => 'Pokazuj tytuł komunikatu', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'polski_preorder|date_format', 'label' => 'Format daty', 'type' => 'text', 'default' => 'd.m.Y', 'hint' => 'Format zgodny z wp_date(), np. d.m.Y albo j F Y'],
                    ['key' => 'polski_preorder|allow_mixed_cart', 'label' => 'Pozwól mieszać z innymi produktami', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_preorder|mixed_cart_error_text', 'label' => 'Błąd mieszanego koszyka', 'type' => 'text', 'default' => 'Produkty z przedsprzedaży nie mogą być łączone z innymi produktami w tym samym koszyku.'],
                ],
            ],
            [
                'id' => 'waitlist',
                'name' => 'Waitlist',
                'description' => 'Lista oczekujących dla produktów niedostępnych, z zapisem email i automatycznymi powiadomieniami po powrocie stanu.',
                'group' => 'Merchandising',
                'enabled' => false,
                'pro' => false,
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
                'id' => 'product_add_ons',
                'name' => 'Product Add-Ons',
                'description' => 'Konfigurowalne dodatki i usługi do produktu, np. grawer, pakowanie na prezent, rozszerzona gwarancja, montaż, wniesienie, konfiguracja lub pakiet serwisowy, z dopłatami, walidacją i zapisem do koszyka oraz zamówienia.',
                'group' => 'Merchandising',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-plus-alt2',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_addons|show_on_single', 'label' => 'Pokazuj na stronie produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_addons|section_title', 'label' => 'Nagłówek sekcji', 'type' => 'text', 'default' => 'Dodatki do produktu'],
                    ['key' => 'polski_addons|section_intro', 'label' => 'Opis sekcji', 'type' => 'textarea', 'default' => 'Dodaj usługi i opcje powiązane z tym produktem, np. grawer, pakowanie na prezent, rozszerzoną gwarancję, montaż albo pakiet serwisowy. Każda opcja może być darmowa, płatna lub wymagana.'],
                    ['key' => 'polski_addons|show_price_inline', 'label' => 'Pokazuj dopłaty przy polach', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_addons|show_descriptions', 'label' => 'Pokazuj opisy pól', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_addons|field_style', 'label' => 'Styl pól', 'type' => 'select', 'default' => 'boxed', 'options' => ['boxed' => 'Karty', 'minimal' => 'Minimalny']],
                    ['key' => 'polski_addons|required_badge_text', 'label' => 'Etykieta pola wymaganego', 'type' => 'text', 'default' => 'Wymagane'],
                    ['key' => 'polski_addons|optional_badge_text', 'label' => 'Etykieta pola opcjonalnego', 'type' => 'text', 'default' => 'Opcjonalne'],
                    ['key' => 'polski_addons|select_placeholder', 'label' => 'Placeholder selecta', 'type' => 'text', 'default' => 'Wybierz opcję'],
                    ['key' => 'polski_addons|text_placeholder', 'label' => 'Domyślny placeholder pola tekstowego', 'type' => 'text', 'default' => 'Wpisz wartość'],
                    ['key' => 'polski_addons|textarea_placeholder', 'label' => 'Domyślny placeholder pola wielowierszowego', 'type' => 'text', 'default' => 'Wpisz szczegóły'],
                    ['key' => 'polski_addons|textarea_rows', 'label' => 'Liczba wierszy textarea', 'type' => 'number', 'default' => 3],
                    ['key' => 'polski_addons|price_prefix', 'label' => 'Prefix dopłaty', 'type' => 'text', 'default' => '+'],
                ],
            ],
            [
                'id' => 'product_bundles',
                'name' => 'Product Bundles',
                'description' => 'Konfigurowalne zestawy produktowe z rabatem pakietowym, wspólnym CTA i osobnym liczeniem korzyści dla klienta.',
                'group' => 'Merchandising',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-screenoptions',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_bundles|show_on_single', 'label' => 'Pokazuj na stronie produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_bundles|title', 'label' => 'Nagłówek sekcji', 'type' => 'text', 'default' => 'Kup w zestawie'],
                    ['key' => 'polski_bundles|intro_text', 'label' => 'Opis sekcji', 'type' => 'textarea', 'default' => 'Połącz produkt główny z akcesoriami i kup całość w jednym pakiecie.'],
                    ['key' => 'polski_bundles|button_text', 'label' => 'Tekst przycisku', 'type' => 'text', 'default' => 'Dodaj zestaw do koszyka'],
                    ['key' => 'polski_bundles|selection_required_text', 'label' => 'Błąd braku wyboru', 'type' => 'text', 'default' => 'Wybierz przynajmniej jeden dodatkowy produkt do zestawu.'],
                    ['key' => 'polski_bundles|success_text', 'label' => 'Komunikat sukcesu', 'type' => 'text', 'default' => 'Dodano zestaw do koszyka.'],
                    ['key' => 'polski_bundles|show_total', 'label' => 'Pokazuj łączną cenę', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_bundles|show_quantities', 'label' => 'Pokazuj ilości produktów', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_bundles|bundle_total_label', 'label' => 'Etykieta ceny zestawu', 'type' => 'text', 'default' => 'Cena zestawu'],
                    ['key' => 'polski_bundles|included_label', 'label' => 'Etykieta produktu głównego', 'type' => 'text', 'default' => 'w zestawie'],
                    ['key' => 'polski_bundles|quantity_format', 'label' => 'Format ilości', 'type' => 'text', 'default' => 'x {quantity}', 'hint' => 'Zmienna: {quantity}'],
                    ['key' => 'polski_bundles|discount_label', 'label' => 'Etykieta oszczędności', 'type' => 'text', 'default' => 'Oszczędzasz'],
                ],
            ],
            [
                'id' => 'gift_cards',
                'name' => 'Gift Cards',
                'description' => 'Karty podarunkowe z zakupem online, kodami, saldem, realizacją w koszyku i wysyłką email do odbiorcy.',
                'group' => 'Merchandising',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-tickets-alt',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_gift_cards|show_on_single', 'label' => 'Pokazuj formularz na stronie produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_gift_cards|show_in_account', 'label' => 'Pokazuj w Moim koncie', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_gift_cards|account_label', 'label' => 'Etykieta w Moim koncie', 'type' => 'text', 'default' => 'Karty podarunkowe'],
                    ['key' => 'polski_gift_cards|product_form_title', 'label' => 'Nagłówek formularza produktu', 'type' => 'text', 'default' => 'Dane karty podarunkowej'],
                    ['key' => 'polski_gift_cards|show_product_form_title', 'label' => 'Pokazuj nagłówek formularza produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_gift_cards|recipient_required_text', 'label' => 'Błąd brakujących danych', 'type' => 'text', 'default' => 'Uzupełnij dane nadawcy i odbiorcy karty podarunkowej.'],
                    ['key' => 'polski_gift_cards|recipient_email_error_text', 'label' => 'Błąd emaila odbiorcy', 'type' => 'text', 'default' => 'Podaj poprawny adres email odbiorcy.'],
                    ['key' => 'polski_gift_cards|amount_error_text', 'label' => 'Błąd kwoty', 'type' => 'text', 'default' => 'Wybierz poprawną kwotę karty podarunkowej.'],
                    ['key' => 'polski_gift_cards|recipient_name_label', 'label' => 'Etykieta imienia odbiorcy', 'type' => 'text', 'default' => 'Imię odbiorcy'],
                    ['key' => 'polski_gift_cards|recipient_email_label', 'label' => 'Etykieta emaila odbiorcy', 'type' => 'text', 'default' => 'Email odbiorcy'],
                    ['key' => 'polski_gift_cards|sender_name_label', 'label' => 'Etykieta imienia nadawcy', 'type' => 'text', 'default' => 'Imię nadawcy'],
                    ['key' => 'polski_gift_cards|amount_label', 'label' => 'Etykieta kwoty', 'type' => 'text', 'default' => 'Kwota'],
                    ['key' => 'polski_gift_cards|custom_amount_label', 'label' => 'Etykieta własnej kwoty', 'type' => 'text', 'default' => 'Własna kwota'],
                    ['key' => 'polski_gift_cards|message_label', 'label' => 'Etykieta wiadomości', 'type' => 'text', 'default' => 'Wiadomość'],
                    ['key' => 'polski_gift_cards|redeem_title', 'label' => 'Nagłówek realizacji kodu', 'type' => 'text', 'default' => 'Masz kartę podarunkową?'],
                    ['key' => 'polski_gift_cards|show_redeem_title', 'label' => 'Pokazuj nagłówek realizacji kodu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_gift_cards|code_placeholder', 'label' => 'Placeholder pola kodu', 'type' => 'text', 'default' => 'Wpisz kod karty'],
                    ['key' => 'polski_gift_cards|redeem_button_text', 'label' => 'Tekst przycisku realizacji', 'type' => 'text', 'default' => 'Zastosuj kod'],
                    ['key' => 'polski_gift_cards|remove_button_text', 'label' => 'Tekst usuwania kodu', 'type' => 'text', 'default' => 'Usuń kod'],
                    ['key' => 'polski_gift_cards|invalid_code_text', 'label' => 'Komunikat błędnego kodu', 'type' => 'text', 'default' => 'Podany kod karty podarunkowej jest nieprawidłowy lub nieaktywny.'],
                    ['key' => 'polski_gift_cards|applied_code_text', 'label' => 'Komunikat zastosowania kodu', 'type' => 'text', 'default' => 'Kod karty podarunkowej został zastosowany.'],
                    ['key' => 'polski_gift_cards|removed_code_text', 'label' => 'Komunikat usunięcia kodu', 'type' => 'text', 'default' => 'Usunięto kartę podarunkową z koszyka.'],
                    ['key' => 'polski_gift_cards|account_title', 'label' => 'Nagłówek widoku w Moim koncie', 'type' => 'text', 'default' => 'Karty podarunkowe'],
                    ['key' => 'polski_gift_cards|show_account_title', 'label' => 'Pokazuj nagłówek w Moim koncie', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_gift_cards|account_intro_text', 'label' => 'Opis widoku w Moim koncie', 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_gift_cards|empty_text', 'label' => 'Tekst pustej listy', 'type' => 'text', 'default' => 'Brak kart podarunkowych przypisanych do Twojego konta.'],
                    ['key' => 'polski_gift_cards|column_code', 'label' => 'Kolumna Kod', 'type' => 'text', 'default' => 'Kod'],
                    ['key' => 'polski_gift_cards|column_balance', 'label' => 'Kolumna Saldo', 'type' => 'text', 'default' => 'Saldo'],
                    ['key' => 'polski_gift_cards|column_recipient', 'label' => 'Kolumna Odbiorca', 'type' => 'text', 'default' => 'Odbiorca'],
                    ['key' => 'polski_gift_cards|column_status', 'label' => 'Kolumna Status', 'type' => 'text', 'default' => 'Status'],
                    ['key' => 'polski_gift_cards|column_expiry', 'label' => 'Kolumna Ważna do', 'type' => 'text', 'default' => 'Ważna do'],
                    ['key' => 'polski_gift_cards|show_balance_column', 'label' => 'Pokazuj kolumnę salda', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_gift_cards|show_recipient_column', 'label' => 'Pokazuj kolumnę odbiorcy', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_gift_cards|show_status_column', 'label' => 'Pokazuj kolumnę statusu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_gift_cards|show_expiry_column', 'label' => 'Pokazuj kolumnę daty ważności', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_gift_cards|show_recipient_email_in_account', 'label' => 'Pokazuj email odbiorcy w tabeli', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_gift_cards|date_format', 'label' => 'Format daty ważności', 'type' => 'text', 'default' => 'd.m.Y'],
                    ['key' => 'polski_gift_cards|status_active', 'label' => 'Etykieta statusu aktywna', 'type' => 'text', 'default' => 'Aktywna'],
                    ['key' => 'polski_gift_cards|status_used', 'label' => 'Etykieta statusu wykorzystana', 'type' => 'text', 'default' => 'Wykorzystana'],
                    ['key' => 'polski_gift_cards|status_expired', 'label' => 'Etykieta statusu wygasła', 'type' => 'text', 'default' => 'Wygasła'],
                    ['key' => 'polski_gift_cards|fee_label', 'label' => 'Etykieta rabatu w koszyku', 'type' => 'text', 'default' => 'Karta podarunkowa {code}', 'hint' => 'Zmienne: {code}'],
                    ['key' => 'polski_gift_cards|code_prefix', 'label' => 'Prefiks kodu', 'type' => 'text', 'default' => 'SP'],
                    ['key' => 'polski_gift_cards|expiry_days', 'label' => 'Ważność kodu w dniach', 'type' => 'number', 'default' => 365],
                    ['key' => 'polski_gift_cards|default_amounts', 'label' => 'Domyślne kwoty', 'type' => 'text', 'default' => '50,100,200'],
                    ['key' => 'polski_gift_cards|min_amount', 'label' => 'Minimalna kwota', 'type' => 'number', 'default' => 20],
                    ['key' => 'polski_gift_cards|max_amount', 'label' => 'Maksymalna kwota', 'type' => 'number', 'default' => 1000],
                    ['key' => 'polski_gift_cards|email_subject', 'label' => 'Temat emaila', 'type' => 'text', 'default' => 'Otrzymujesz kartę podarunkową - {code}'],
                ],
            ],
            [
                'id' => 'subscriptions',
                'name' => 'Subscriptions',
                'description' => 'Subskrypcje produktowe z interwałami odnowień, opłatą startową, okresem próbnym i ręcznie opłacanymi zamówieniami odnowieniowymi.',
                'group' => 'Merchandising',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-update',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_subscriptions|show_on_single', 'label' => 'Pokazuj na stronie produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_subscriptions|show_in_account', 'label' => 'Pokazuj w Moim koncie', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_subscriptions|account_label', 'label' => 'Etykieta w Moim koncie', 'type' => 'text', 'default' => 'Subskrypcje'],
                    ['key' => 'polski_subscriptions|section_title', 'label' => 'Nagłówek sekcji', 'type' => 'text', 'default' => 'Subskrypcja produktu'],
                    ['key' => 'polski_subscriptions|show_section_title', 'label' => 'Pokazuj nagłówek sekcji', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_subscriptions|section_intro', 'label' => 'Opis sekcji', 'type' => 'textarea', 'default' => 'Kupuj cyklicznie bez ponownego składania zamówienia.'],
                    ['key' => 'polski_subscriptions|show_section_intro', 'label' => 'Pokazuj opis sekcji', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_subscriptions|subscription_label', 'label' => 'Etykieta cyklu', 'type' => 'text', 'default' => 'Subskrypcja'],
                    ['key' => 'polski_subscriptions|subscription_cycle_format', 'label' => 'Format cyklu', 'type' => 'text', 'default' => 'co {count} {period}', 'hint' => 'Zmienne: {count}, {period}'],
                    ['key' => 'polski_subscriptions|signup_fee_label', 'label' => 'Tekst opłaty startowej', 'type' => 'text', 'default' => 'Opłata startowa: {price}', 'hint' => 'Zmienne: {price}'],
                    ['key' => 'polski_subscriptions|trial_label', 'label' => 'Tekst okresu próbnego', 'type' => 'text', 'default' => 'Okres próbny: {duration}', 'hint' => 'Zmienne: {days}, {duration}'],
                    ['key' => 'polski_subscriptions|trial_value_format', 'label' => 'Wartość okresu próbnego', 'type' => 'text', 'default' => '{days} {period}', 'hint' => 'Zmienne: {days}, {period}'],
                    ['key' => 'polski_subscriptions|renewal_notice', 'label' => 'Komunikat o odnowieniu', 'type' => 'text', 'default' => 'Kolejne odnowienie: co {interval} {period}.'],
                    ['key' => 'polski_subscriptions|period_day_singular', 'label' => 'Okres dzień - liczba pojedyncza', 'type' => 'text', 'default' => 'dzień'],
                    ['key' => 'polski_subscriptions|period_day_plural', 'label' => 'Okres dzień - liczba mnoga', 'type' => 'text', 'default' => 'dni'],
                    ['key' => 'polski_subscriptions|period_week_singular', 'label' => 'Okres tydzień - liczba pojedyncza', 'type' => 'text', 'default' => 'tydzień'],
                    ['key' => 'polski_subscriptions|period_week_plural', 'label' => 'Okres tydzień - liczba mnoga', 'type' => 'text', 'default' => 'tygodnie'],
                    ['key' => 'polski_subscriptions|period_month_singular', 'label' => 'Okres miesiąc - liczba pojedyncza', 'type' => 'text', 'default' => 'miesiąc'],
                    ['key' => 'polski_subscriptions|period_month_plural', 'label' => 'Okres miesiąc - liczba mnoga', 'type' => 'text', 'default' => 'miesiące'],
                    ['key' => 'polski_subscriptions|period_year_singular', 'label' => 'Okres rok - liczba pojedyncza', 'type' => 'text', 'default' => 'rok'],
                    ['key' => 'polski_subscriptions|period_year_plural', 'label' => 'Okres rok - liczba mnoga', 'type' => 'text', 'default' => 'lata'],
                    ['key' => 'polski_subscriptions|allow_cancellation', 'label' => 'Pozwól anulować w Moim koncie', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_subscriptions|account_title', 'label' => 'Nagłówek widoku w Moim koncie', 'type' => 'text', 'default' => 'Subskrypcje'],
                    ['key' => 'polski_subscriptions|show_account_title', 'label' => 'Pokazuj nagłówek w Moim koncie', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_subscriptions|account_intro_text', 'label' => 'Opis widoku w Moim koncie', 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_subscriptions|empty_text', 'label' => 'Tekst pustej listy', 'type' => 'text', 'default' => 'Brak aktywnych subskrypcji.'],
                    ['key' => 'polski_subscriptions|column_product', 'label' => 'Kolumna Produkt', 'type' => 'text', 'default' => 'Produkt'],
                    ['key' => 'polski_subscriptions|column_cycle', 'label' => 'Kolumna Cykl', 'type' => 'text', 'default' => 'Cykl'],
                    ['key' => 'polski_subscriptions|column_amount', 'label' => 'Kolumna Kwota', 'type' => 'text', 'default' => 'Kwota'],
                    ['key' => 'polski_subscriptions|column_status', 'label' => 'Kolumna Status', 'type' => 'text', 'default' => 'Status'],
                    ['key' => 'polski_subscriptions|column_next_payment', 'label' => 'Kolumna Następne odnowienie', 'type' => 'text', 'default' => 'Następne odnowienie'],
                    ['key' => 'polski_subscriptions|column_actions', 'label' => 'Kolumna Akcje', 'type' => 'text', 'default' => 'Akcje'],
                    ['key' => 'polski_subscriptions|show_product_column', 'label' => 'Pokazuj kolumnę produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_subscriptions|show_cycle_column', 'label' => 'Pokazuj kolumnę cyklu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_subscriptions|show_amount_column', 'label' => 'Pokazuj kolumnę kwoty', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_subscriptions|show_status_column', 'label' => 'Pokazuj kolumnę statusu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_subscriptions|show_next_payment_column', 'label' => 'Pokazuj kolumnę następnego odnowienia', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_subscriptions|show_actions_column', 'label' => 'Pokazuj kolumnę akcji', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_subscriptions|date_format', 'label' => 'Format daty odnowienia', 'type' => 'text', 'default' => 'd.m.Y'],
                    ['key' => 'polski_subscriptions|cancel_button_text', 'label' => 'Tekst przycisku anulowania', 'type' => 'text', 'default' => 'Anuluj'],
                    ['key' => 'polski_subscriptions|reactivate_button_text', 'label' => 'Tekst przycisku wznowienia', 'type' => 'text', 'default' => 'Wznów'],
                    ['key' => 'polski_subscriptions|cancel_success_text', 'label' => 'Komunikat po anulowaniu', 'type' => 'text', 'default' => 'Subskrypcja została anulowana.'],
                    ['key' => 'polski_subscriptions|reactivate_success_text', 'label' => 'Komunikat po wznowieniu', 'type' => 'text', 'default' => 'Subskrypcja została ponownie aktywowana.'],
                    ['key' => 'polski_subscriptions|status_active', 'label' => 'Etykieta statusu aktywna', 'type' => 'text', 'default' => 'Aktywna'],
                    ['key' => 'polski_subscriptions|status_trial', 'label' => 'Etykieta statusu trial', 'type' => 'text', 'default' => 'Okres próbny'],
                    ['key' => 'polski_subscriptions|status_cancelled', 'label' => 'Etykieta statusu anulowana', 'type' => 'text', 'default' => 'Anulowana'],
                    ['key' => 'polski_subscriptions|status_completed', 'label' => 'Etykieta statusu zakończona', 'type' => 'text', 'default' => 'Zakończona'],
                    ['key' => 'polski_subscriptions|reminder_days', 'label' => 'Przypomnienie przed odnowieniem (dni)', 'type' => 'number', 'default' => 3],
                    ['key' => 'polski_subscriptions|renewal_subject', 'label' => 'Temat emaila odnowienia', 'type' => 'text', 'default' => 'Nowe odnowienie subskrypcji - {product_name}'],
                    ['key' => 'polski_subscriptions|reminder_subject', 'label' => 'Temat przypomnienia', 'type' => 'text', 'default' => 'Zbliża się odnowienie subskrypcji - {product_name}'],
                    ['key' => 'polski_subscriptions|reminder_intro_text', 'label' => 'Treść przypomnienia', 'type' => 'text', 'default' => 'Subskrypcja produktu {product_name} odnowi się {date}.', 'hint' => 'Zmienne: {product_name}, {date}'],
                    ['key' => 'polski_subscriptions|reminder_amount_label', 'label' => 'Kwota w przypomnieniu', 'type' => 'text', 'default' => 'Kwota odnowienia: {amount}', 'hint' => 'Zmienna: {amount}'],
                    ['key' => 'polski_subscriptions|renewal_intro_text', 'label' => 'Treść emaila odnowienia', 'type' => 'text', 'default' => 'Utworzyliśmy nowe zamówienie odnowieniowe dla subskrypcji produktu {product_name}.', 'hint' => 'Zmienna: {product_name}'],
                    ['key' => 'polski_subscriptions|renewal_amount_label', 'label' => 'Kwota w emailu odnowienia', 'type' => 'text', 'default' => 'Kwota do opłacenia: {amount}', 'hint' => 'Zmienna: {amount}'],
                ],
            ],
            [
                'id' => 'infinite_scroll',
                'name' => 'Infinite Scrolling',
                'description' => 'Lekki loading kolejnych produktów na archiwach WooCommerce, z trybem przycisku lub automatycznym doładowaniem.',
                'group' => 'Merchandising',
                'enabled' => false,
                'pro' => false,
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
                'pro' => false,
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
            [
                'id' => 'affiliates',
                'name' => 'WooCommerce Affiliates',
                'description' => 'Lekki program partnerski z linkiem polecającym, atrybucją zamówień, prowizją procentową i panelem partnera.',
                'group' => 'Merchandising',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-groups',
                'links' => [],
                'settings' => [
                    ['key' => 'polski_affiliates|show_in_account', 'label' => 'Pokazuj w Moim koncie', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_affiliates|show_dashboard_title', 'label' => 'Pokazuj tytuł dashboardu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_affiliates|show_dashboard_intro', 'label' => 'Pokazuj opis dashboardu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_affiliates|show_referral_link', 'label' => 'Pokazuj link partnerski', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_affiliates|show_stats', 'label' => 'Pokazuj statystyki', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_affiliates|show_table', 'label' => 'Pokazuj tabelę poleceń', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_affiliates|account_label', 'label' => 'Etykieta w Moim koncie', 'type' => 'text', 'default' => 'Program partnerski'],
                    ['key' => 'polski_affiliates|dashboard_title', 'label' => 'Tytuł dashboardu', 'type' => 'text', 'default' => 'Panel partnera'],
                    ['key' => 'polski_affiliates|dashboard_intro_text', 'label' => 'Opis dashboardu', 'type' => 'textarea', 'default' => ''],
                    ['key' => 'polski_affiliates|login_required_text', 'label' => 'Komunikat logowania', 'type' => 'text', 'default' => 'Zaloguj się, aby korzystać z programu partnerskiego.'],
                    ['key' => 'polski_affiliates|referral_link_label', 'label' => 'Etykieta linku partnerskiego', 'type' => 'text', 'default' => 'Twój link partnerski'],
                    ['key' => 'polski_affiliates|stats_referrals_label', 'label' => 'Etykieta liczby poleceń', 'type' => 'text', 'default' => 'Polecenia: {count}', 'hint' => 'Zmienna: {count}'],
                    ['key' => 'polski_affiliates|stats_revenue_label', 'label' => 'Etykieta sprzedaży', 'type' => 'text', 'default' => 'Sprzedaż: {amount}', 'hint' => 'Zmienna: {amount}'],
                    ['key' => 'polski_affiliates|stats_commission_label', 'label' => 'Etykieta prowizji', 'type' => 'text', 'default' => 'Prowizja: {amount}', 'hint' => 'Zmienna: {amount}'],
                    ['key' => 'polski_affiliates|empty_text', 'label' => 'Pusty stan', 'type' => 'text', 'default' => 'Brak poleceń przypisanych do Twojego konta.'],
                    ['key' => 'polski_affiliates|column_order', 'label' => 'Kolumna Zamówienie', 'type' => 'text', 'default' => 'Zamówienie'],
                    ['key' => 'polski_affiliates|column_customer', 'label' => 'Kolumna Klient', 'type' => 'text', 'default' => 'Klient'],
                    ['key' => 'polski_affiliates|column_value', 'label' => 'Kolumna Wartość', 'type' => 'text', 'default' => 'Wartość'],
                    ['key' => 'polski_affiliates|column_commission', 'label' => 'Kolumna Prowizja', 'type' => 'text', 'default' => 'Prowizja'],
                    ['key' => 'polski_affiliates|column_status', 'label' => 'Kolumna Status', 'type' => 'text', 'default' => 'Status'],
                    ['key' => 'polski_affiliates|column_date', 'label' => 'Kolumna Data', 'type' => 'text', 'default' => 'Data'],
                    ['key' => 'polski_affiliates|show_order_column', 'label' => 'Pokazuj kolumnę Zamówienie', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_affiliates|show_customer_column', 'label' => 'Pokazuj kolumnę Klient', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_affiliates|show_value_column', 'label' => 'Pokazuj kolumnę Wartość', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_affiliates|show_commission_column', 'label' => 'Pokazuj kolumnę Prowizja', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_affiliates|show_status_column', 'label' => 'Pokazuj kolumnę Status', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_affiliates|show_date_column', 'label' => 'Pokazuj kolumnę Daty', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'polski_affiliates|order_prefix', 'label' => 'Prefix numeru zamówienia', 'type' => 'text', 'default' => '#'],
                    ['key' => 'polski_affiliates|date_format', 'label' => 'Format daty', 'type' => 'text', 'default' => 'd.m.Y'],
                    ['key' => 'polski_affiliates|referral_param', 'label' => 'Parametr referral', 'type' => 'text', 'default' => 'poleca'],
                    ['key' => 'polski_affiliates|cookie_days', 'label' => 'Czas atrybucji w dniach', 'type' => 'number', 'default' => 30],
                    ['key' => 'polski_affiliates|commission_percent', 'label' => 'Prowizja procentowa', 'type' => 'number', 'default' => 5],
                    ['key' => 'polski_affiliates|minimum_order_total', 'label' => 'Minimalna wartość zamówienia', 'type' => 'number', 'default' => 0],
                    ['key' => 'polski_affiliates|pending_statuses', 'label' => 'Statusy liczące referral', 'type' => 'text', 'default' => 'processing,completed'],
                ],
            ],

            // === SEO i Optymalizacja ===
            [
                'id' => 'schema_org',
                'name' => 'Wzbogacone Dane (Schema.org)',
                'description' => 'Automatyczne wstrzykiwanie zaawansowanych tagów JSON-LD, wspierających indeksowanie produktów przez Google z zachowaniem danych specyficznych dla wtyczki.',
                'group' => 'SEO i Optymalizacja',
                'enabled' => true,
                'pro' => false,
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
                'id' => 'wpdesk_integration',
                'name' => 'Integracja WP Desk',
                'description' => 'Współpraca z Flexible Checkout Fields (80 000+ instalacji), Flexible Cookies, GPSR for WooCommerce.',
                'group' => 'Integracje',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-admin-plugins',
                'settings' => [
                    ['key' => '_wpdesk_status', 'type' => 'html', 'html' => $this->getWpDeskIntegrationStatus()],
                ],
                'links' => [
                    ['label' => 'Flexible Checkout Fields', 'url' => 'https://wordpress.org/plugins/flexible-checkout-fields/'],
                ],
            ],
            [
                'id' => 'payment_integration',
                'name' => 'Integracja bramek płatności',
                'description' => 'Wykrywanie i dostosowanie Przelewy24, PayU, Tpay, BLIK, Autopay do wymagań prawnych.',
                'group' => 'Integracje',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-money',
                'settings' => [
                    ['key' => '_payment_status', 'type' => 'html', 'html' => $this->getPaymentIntegrationStatus()],
                ],
                'links' => [],
            ],

            // === PRO ===
            [
                'id' => 'pdf_invoices',
                'name' => 'Faktury PDF',
                'description' => 'Generowanie Faktur VAT, Faktur korygujących i Paragonów w formacie PDF. Konfigurowalny format numeracji. Automatyczne generowanie przy zmianie statusu zamówienia.',
                'group' => 'PRO',
                'enabled' => false,
                'pro' => true,
                'icon' => 'dashicons-media-spreadsheet',
                'links' => [],
            ],
            [
                'id' => 'ksef',
                'name' => 'KSeF (e-Faktury)',
                'description' => 'Integracja z Krajowym Systemem e-Faktur. Wysyłanie faktur do KSeF, podpis cyfrowy, kolejka asynchroniczna, dashboard statusów.',
                'group' => 'PRO',
                'enabled' => false,
                'pro' => true,
                'icon' => 'dashicons-cloud-upload',
                'links' => [],
            ],
            [
                'id' => 'nip_validation',
                'name' => 'Walidacja NIP',
                'description' => 'Pole NIP na stronie kasy z walidacją sumy kontrolnej. Weryfikacja w bazie GUS/REGON. Automatyczne uzupełnianie danych firmy.',
                'group' => 'PRO',
                'enabled' => false,
                'pro' => true,
                'icon' => 'dashicons-id-alt',
                'links' => [],
            ],
            [
                'id' => 'shipping_providers',
                'name' => 'Integracje wysyłkowe',
                'description' => 'InPost (Paczkomaty), DPD, DHL, Poczta Polska, Orlen Paczka. Generowanie etykiet, mapa punktów odbioru, śledzenie przesyłek.',
                'group' => 'PRO',
                'enabled' => false,
                'pro' => true,
                'icon' => 'dashicons-car',
                'links' => [],
            ],
            [
                'id' => 'multistep_checkout',
                'name' => 'Kasa wieloetapowa',
                'description' => 'Nowoczesna kasa podzielona na kroki: Adres > Wysyłka > Płatność > Podsumowanie. Responsywna, mobile-first.',
                'group' => 'PRO',
                'enabled' => false,
                'pro' => true,
                'icon' => 'dashicons-editor-ol',
                'links' => [],
            ],
            [
                'id' => 'accounting',
                'name' => 'Integracje księgowe',
                'description' => 'Synchronizacja faktur z wFirma, Fakturownia, iFirma. Automatyczny eksport danych po wystawieniu faktury.',
                'group' => 'PRO',
                'enabled' => false,
                'pro' => true,
                'icon' => 'dashicons-calculator',
                'links' => [],
            ],
            [
                'id' => 'legal_generator',
                'name' => 'Generator tekstów prawnych',
                'description' => 'Automatyczne generowanie Regulaminu, Polityki prywatności i Polityki zwrotów na podstawie danych sklepu.',
                'group' => 'PRO',
                'enabled' => false,
                'pro' => true,
                'icon' => 'dashicons-edit',
                'links' => [],
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
        $proActive = defined('Polski\Pro\VERSION');

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
            $isPro = $groupName === 'PRO';

            echo '<div style="margin-top:30px;">';
            echo '<h2 style="display:flex;align-items:center;gap:8px;">';
            echo esc_html($groupName);

            if ($isPro && ! $proActive) {
                echo ' <span style="background:#7f54b3;color:#fff;padding:2px 8px;border-radius:3px;font-size:12px;font-weight:normal;">PRO</span>';
            }

            echo '</h2>';

            echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:12px;">';

            foreach ($groupModules as $module) {
                $this->renderModuleCard($module, $isPro && ! $proActive);
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

        if ($module['pro']) {
            echo ' <span style="background:#7f54b3;color:#fff;padding:1px 6px;border-radius:3px;font-size:10px;">PRO</span>';
        }

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
            printf(
                '<a href="%s" target="_blank" style="font-size:11px;color:#7f54b3;">%s</a>',
                'https://wppoland.com/polski-pro',
                esc_html__('Kup PRO', 'polski'),
            );
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Render a single settings field within a module card.
     *
     * @param array<string, mixed> $field
     */
    private function renderSettingsField(array $field): void
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

        echo '<div style="margin-bottom:10px;">';

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
            echo '<p style="margin:2px 0 0;color:#999;font-size:11px;">' . esc_html($hint) . '</p>';
        }

        echo '</div>';
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
            if ($module['pro']) {
                continue; // PRO modules managed by PRO plugin.
            }

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
            if ($module['pro'] || empty($module['settings'])) {
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
            'contract_helper' => false,
            'invoice_gateway' => false,
            'legal_pages' => true,
            'withdrawal' => true,
            'dispute_resolution' => true,
            'email_attachments' => true,
            'manufacturer' => true,
            'food_module' => false,
            'power_supply' => false,
            'double_opt_in' => false,
            'request_quote' => false,
            'catalog_mode' => false,
            'ajax_search' => false,
            'brands' => false,
            'ajax_filters' => false,
            'wishlist' => false,
            'compare' => false,
            'quick_view' => false,
            'frequently_bought_together' => false,
            'badge_management' => false,
            'tab_manager' => false,
            'featured_video' => false,
            'gallery_zoom' => false,
            'product_slider_carousel' => false,
            'pre_order' => false,
            'waitlist' => false,
            'product_add_ons' => false,
            'product_bundles' => false,
            'gift_cards' => false,
            'subscriptions' => false,
            'infinite_scroll' => false,
            'popup' => false,
            'affiliates' => false,
            'schema_org' => true,
            'wpdesk_integration' => true,
            'payment_integration' => true,
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
            ['file' => 'wc-price-history/wc-price-history.php', 'name' => 'WC Price History (kkarpieszuk)', 'url' => 'https://wordpress.org/plugins/wc-price-history/'],
            ['file' => 'omnibus/omnibus.php', 'name' => 'Omnibus (iworks)', 'url' => 'https://pl.wordpress.org/plugins/omnibus/'],
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
                '<div style="margin-bottom:4px;">%s <a href="%s" target="_blank">%s</a> - <em>%s</em></div>',
                $icon,
                esc_url($plugin['url']),
                esc_html($plugin['name']),
                esc_html($status),
            );
        }

        if (! $anyActive) {
            $html .= '<div style="margin-top:6px;color:#666;">' . esc_html((string) ($generalSettings['admin_omnibus_no_external_text'] ?? 'Żadna zewnętrzna wtyczka Omnibus nie jest zainstalowana. Polski używa wbudowanego systemu śledzenia cen.')) . '</div>';
        } else {
            $html .= '<div style="margin-top:6px;color:#46b450;">' . esc_html((string) ($generalSettings['admin_omnibus_external_active_text'] ?? 'Zewnętrzna wtyczka wykryta. Polski korzysta z jej danych zamiast wbudowanego systemu.')) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    private function getWpDeskIntegrationStatus(): string
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
            $html .= '<div style="margin-top:6px;color:#666;">' . esc_html((string) ($generalSettings['admin_wpdesk_no_external_text'] ?? 'Nie wykryto wspieranych wtyczek WP Desk. Polski działa nadal samodzielnie.')) . '</div>';
        } else {
            $html .= '<div style="margin-top:6px;color:#46b450;">' . esc_html((string) ($generalSettings['admin_wpdesk_external_active_text'] ?? 'Wykryto wspierane wtyczki WP Desk. Polski może dopasować integrację do checkoutu, cookies i GPSR.')) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    private function getPaymentIntegrationStatus(): string
    {
        $generalSettings = get_option('polski_general', []);
        $generalSettings = is_array($generalSettings) ? $generalSettings : [];

        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $items = [
            ['id' => 'przelewy24', 'name' => 'Przelewy24', 'plugin' => 'woocommerce-przelewy24/woocommerce-przelewy24.php'],
            ['id' => 'payu', 'name' => 'PayU', 'plugin' => 'woo-payu-payment-gateway/woo-payu-payment-gateway.php'],
            ['id' => 'tpay', 'name' => 'Tpay', 'plugin' => 'tpay-com-payment-gateway/tpay-com-payment-gateway.php'],
            ['id' => 'autopay', 'name' => 'Autopay', 'plugin' => 'autopay-woocommerce/autopay-woocommerce.php'],
            ['id' => 'blik', 'name' => 'BLIK', 'plugin' => ''],
        ];

        $activeGatewayIds = $this->getActiveGatewayIds();
        $html = '<div style="font-size:12px;">';
        $anyActive = false;

        foreach ($items as $item) {
            $active = ($item['plugin'] !== '' && is_plugin_active($item['plugin'])) || $this->isGatewayDetected($item['id'], $activeGatewayIds);
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
                esc_html((string) $item['name']),
                esc_html($status),
            );
        }

        if (! $anyActive) {
            $html .= '<div style="margin-top:6px;color:#666;">' . esc_html((string) ($generalSettings['admin_payment_no_external_text'] ?? 'Nie wykryto wspieranych polskich bramek płatności. Polski używa własnych ustawień checkoutu bez dodatkowych integracji.')) . '</div>';
        } else {
            $html .= '<div style="margin-top:6px;color:#46b450;">' . esc_html((string) ($generalSettings['admin_payment_external_active_text'] ?? 'Wykryto polskie bramki płatności. Polski może dopasować checkout i komunikaty prawne do aktywnych metod.')) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @return list<string>
     */
    private function getActiveGatewayIds(): array
    {
        if (! function_exists('WC')) {
            return [];
        }

        $wc = WC();

        if (! $wc instanceof \WooCommerce) {
            return [];
        }

        $paymentGateways = $wc->payment_gateways();

        if (! $paymentGateways instanceof \WC_Payment_Gateways) {
            return [];
        }

        $gatewayObjects = $paymentGateways->payment_gateways();

        if (! is_array($gatewayObjects)) {
            return [];
        }

        $detected = [];

        foreach ($gatewayObjects as $gateway) {
            if (! $gateway instanceof \WC_Payment_Gateway || $gateway->enabled !== 'yes') {
                continue;
            }

            $gatewayId = strtolower((string) $gateway->id);

            if ($gatewayId !== '') {
                $detected[] = $gatewayId;
            }
        }

        return array_values(array_unique($detected));
    }

    /**
     * @param list<string> $activeGatewayIds
     */
    private function isGatewayDetected(string $integrationId, array $activeGatewayIds): bool
    {
        foreach ($activeGatewayIds as $gatewayId) {
            if ($integrationId === 'przelewy24' && (str_contains($gatewayId, 'przelewy24') || str_contains($gatewayId, 'p24'))) {
                return true;
            }

            if ($integrationId === 'payu' && str_contains($gatewayId, 'payu')) {
                return true;
            }

            if ($integrationId === 'tpay' && str_contains($gatewayId, 'tpay')) {
                return true;
            }

            if ($integrationId === 'autopay' && (str_contains($gatewayId, 'autopay') || str_contains($gatewayId, 'bluepayment') || str_contains($gatewayId, 'blue_media'))) {
                return true;
            }

            if ($integrationId === 'blik' && str_contains($gatewayId, 'blik')) {
                return true;
            }
        }

        return false;
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
