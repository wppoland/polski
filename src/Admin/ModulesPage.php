<?php

declare(strict_types=1);

namespace Spolszczony\Admin;

use Spolszczony\Contract\HasHooks;

/**
 * Module management page - toggleable feature groups.
 *
 * Each module corresponds to a feature set that can be enabled/disabled.
 * Modules are stored as a serialized array in spolszczony_modules option.
 */
final class ModulesPage implements HasHooks
{
    private const OPTION = 'spolszczony_modules';

    public function registerHooks(): void
    {
        add_action('admin_post_spolszczony_save_modules', [$this, 'handleSave']);
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
                    ['key' => 'spolszczony_prices|unit_price_text', 'label' => 'Szablon wyświetlania', 'type' => 'text', 'default' => '{price} / {unit}', 'hint' => 'Zmienne: {price}, {unit}'],
                    ['key' => 'spolszczony_prices|unit_price_show_loop', 'label' => 'Pokazuj na liście produktów', 'type' => 'checkbox', 'default' => true],
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
                    ['key' => 'spolszczony_omnibus|days', 'label' => 'Okres śledzenia (dni)', 'type' => 'number', 'default' => 30, 'hint' => 'Dyrektywa wymaga minimum 30 dni'],
                    ['key' => 'spolszczony_omnibus|prune_after_days', 'label' => 'Przechowuj historię (dni)', 'type' => 'number', 'default' => 90, 'hint' => 'Dane starsze zostaną automatycznie usunięte'],
                    ['key' => 'spolszczony_omnibus|include_tax', 'label' => 'Ceny z podatkiem', 'type' => 'checkbox', 'default' => true, 'hint' => 'Śledź i wyświetlaj ceny brutto'],

                    ['key' => '_omnibus_header_2', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;margin-top:8px;display:block;">Wyświetlanie</strong>'],
                    ['key' => 'spolszczony_omnibus|display_on_sale_only', 'label' => 'Tylko produkty w promocji', 'type' => 'checkbox', 'default' => true, 'hint' => 'Pokazuj informację tylko gdy produkt ma cenę promocyjną'],
                    ['key' => 'spolszczony_omnibus|show_on_single', 'label' => 'Strona produktu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'spolszczony_omnibus|show_on_loop', 'label' => 'Lista produktów (sklep, kategorie)', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'spolszczony_omnibus|show_on_related', 'label' => 'Produkty powiązane i polecane', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'spolszczony_omnibus|show_on_cart', 'label' => 'Koszyk', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'spolszczony_omnibus|show_regular_price', 'label' => 'Pokazuj cenę regularną (przed promocją)', 'type' => 'checkbox', 'default' => false, 'hint' => 'Wyświetl dodatkową informację o cenie przed rozpoczęciem promocji'],

                    ['key' => '_omnibus_header_3', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;margin-top:8px;display:block;">Szablon wiadomości</strong>'],
                    ['key' => 'spolszczony_omnibus|display_text', 'label' => 'Treść komunikatu', 'type' => 'text', 'default' => 'Najniższa cena z ostatnich {days} dni: {price}', 'hint' => 'Zmienne: {price}, {days}, {date}, {regular_price}'],
                    ['key' => 'spolszczony_omnibus|no_history_text', 'label' => 'Brak historii cen', 'type' => 'select', 'default' => 'hide', 'options' => ['hide' => 'Ukryj komunikat', 'current' => 'Pokaż aktualną cenę', 'custom' => 'Własny tekst']],
                    ['key' => 'spolszczony_omnibus|no_history_custom_text', 'label' => 'Własny tekst (brak historii)', 'type' => 'text', 'default' => 'Cena nie uległa zmianie w okresie {days} dni'],
                    ['key' => 'spolszczony_omnibus|price_count_from', 'label' => 'Liczona od', 'type' => 'select', 'default' => 'sale_start', 'options' => ['sale_start' => 'Dnia rozpoczęcia promocji', 'today' => 'Dnia dzisiejszego'], 'hint' => 'Punkt odniesienia do obliczania najniższej ceny'],

                    ['key' => '_omnibus_header_4', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;margin-top:8px;display:block;">Produkty wariantowe</strong>'],
                    ['key' => 'spolszczony_omnibus|variable_tracking', 'label' => 'Śledź warianty oddzielnie', 'type' => 'checkbox', 'default' => true, 'hint' => 'Każdy wariant ma własną historię cen'],

                    ['key' => '_omnibus_header_5', 'label' => '', 'type' => 'html', 'html' => '<strong style="font-size:13px;margin-top:8px;display:block;">Integracje</strong>'],
                    ['key' => '_omnibus_integrations', 'label' => '', 'type' => 'html', 'html' => $this->getOmnibusIntegrationStatus()],
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
                    ['key' => 'spolszczony_taxes|tax_display_mode', 'label' => 'Tryb wyświetlania cen', 'type' => 'select', 'default' => 'brutto', 'options' => ['brutto' => 'Brutto (z VAT)', 'netto' => 'Netto (bez VAT)']],
                    ['key' => 'spolszczony_taxes|vat_notice_text', 'label' => 'Tekst informacji o VAT', 'type' => 'text', 'default' => 'w tym {rate}% VAT', 'hint' => 'Zmienne: {rate}'],
                    ['key' => 'spolszczony_general|small_business', 'label' => 'Zwolnienie podmiotowe (art. 113)', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'spolszczony_taxes|vat_exempt_notice', 'label' => 'Tekst zwolnienia', 'type' => 'text', 'default' => 'Zwolniony z VAT na podstawie art. 113 ust. 1 ustawy o VAT'],
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
                    ['key' => 'spolszczony_delivery|display_format', 'label' => 'Format wyświetlania', 'type' => 'text', 'default' => 'Czas dostawy: {time}', 'hint' => 'Zmienne: {time}'],
                    ['key' => 'spolszczony_delivery|default_delivery_time', 'label' => 'Domyślny czas dostawy', 'type' => 'delivery_time_select'],
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
                    ['key' => 'spolszczony_prices|shipping_costs_text', 'label' => 'Tekst linku', 'type' => 'text', 'default' => 'zzgl. kosztów wysyłki'],
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
                    ['key' => 'spolszczony_checkout|order_button_text', 'label' => 'Tekst przycisku', 'type' => 'text', 'default' => 'Zamawiam z obowiązkiem zapłaty'],
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
                    ['key' => 'spolszczony_checkout|terms_checkbox_enabled', 'label' => 'Regulamin sklepu', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'spolszczony_checkout|privacy_checkbox_enabled', 'label' => 'Polityka prywatności', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'spolszczony_checkout|withdrawal_checkbox_enabled', 'label' => 'Prawo odstąpienia', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'spolszczony_checkout|digital_waiver_checkbox_enabled', 'label' => 'Treści cyfrowe (zrzeczenie)', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'spolszczony_checkout|parcel_delivery_checkbox_enabled', 'label' => 'Powiadomienia o dostawie', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'spolszczony_checkout|review_reminder_checkbox_enabled', 'label' => 'Przypomnienie o opinii', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'spolszczony_checkout|marketing_checkbox_enabled', 'label' => 'Zgoda marketingowa', 'type' => 'checkbox', 'default' => false],
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
                    ['key' => 'spolszczony_checkout|delayed_payment_enabled', 'label' => 'Odroczona płatność', 'type' => 'checkbox', 'default' => false, 'hint' => 'Potwierdzenie zamówienia wysylane przed platnoscia'],
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
                    ['key' => '_gateway_link', 'label' => '', 'type' => 'html', 'html' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=spolszczony_invoice') . '">Konfiguruj bramkę płatności w WooCommerce &rarr;</a>'],
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
                    ['key' => '_legal_pages_link', 'label' => '', 'type' => 'html', 'html' => '<a href="' . admin_url('admin.php?page=spolszczony&tab=dashboard') . '">Zarządzaj stronami prawnymi na Pulpicie &rarr;</a>'],
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
                'settings' => [],
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
                    ['key' => 'spolszczony_general|dispute_resolution_text', 'label' => 'Treść informacji ODR', 'type' => 'textarea', 'default' => 'Platforma ODR: https://ec.europa.eu/consumers/odr'],
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
                    ['key' => 'spolszczony_emails|attach_terms', 'label' => 'Dołącz Regulamin', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'spolszczony_emails|attach_privacy', 'label' => 'Dołącz Politykę prywatności', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'spolszczony_emails|attach_withdrawal', 'label' => 'Dołącz Prawo odstąpienia', 'type' => 'checkbox', 'default' => true],
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
                'settings' => [],
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
        $proActive = defined('Spolszczony\Pro\VERSION');

        // Group modules.
        $groups = [];
        foreach ($modules as $module) {
            $groups[$module['group']][] = $module;
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('spolszczony_save_modules', '_spolszczony_modules_nonce');
        echo '<input type="hidden" name="action" value="spolszczony_save_modules" />';

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
            esc_html__('Save modules', 'spolszczony'),
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
        $fieldName = "spolszczony_module_{$id}";
        $hasSettings = ! empty($module['settings']);

        $borderColor = $enabled ? '#46b450' : '#ccd0d4';
        $opacity = $locked ? '0.7' : '1';

        echo '<div class="spolszczony-module-card" style="background:#fff;border:1px solid ' . esc_attr($borderColor) . ';padding:16px;opacity:' . $opacity . ';position:relative;">';

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
        echo '<label class="spolszczony-toggle" style="position:relative;display:inline-block;width:40px;height:22px;flex-shrink:0;">';
        printf(
            '<input type="checkbox" name="%s" value="1" %s %s style="opacity:0;width:0;height:0;">',
            esc_attr($fieldName),
            checked($enabled, true, false),
            $locked ? 'disabled' : '',
        );
        $bgColor = $enabled ? '#46b450' : '#ccc';
        $translate = $enabled ? '18px' : '2px';
        echo '<span style="position:absolute;cursor:' . ($locked ? 'not-allowed' : 'pointer') . ';top:0;left:0;right:0;bottom:0;background:' . $bgColor . ';border-radius:22px;transition:.3s;"></span>';
        echo '<span style="position:absolute;height:18px;width:18px;left:' . $translate . ';bottom:2px;background:#fff;border-radius:50%;transition:.3s;box-shadow:0 1px 3px rgba(0,0,0,.2);"></span>';
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
            $detailsId = 'spolszczony-settings-' . $id;

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
                'https://wppoland.com/spolszczony-pro',
                esc_html__('Get PRO', 'spolszczony'),
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
        $inputName = "spolszczony_setting[{$optionName}][{$fieldKey}]";

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
                $terms = get_terms(['taxonomy' => 'spolszczony_delivery_time', 'hide_empty' => false]);
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
            wp_die(__('You do not have permission to access this resource.', 'spolszczony'));
        }

        check_admin_referer('spolszczony_save_modules', '_spolszczony_modules_nonce');

        $modules = $this->getModules();
        $saved = [];

        foreach ($modules as $module) {
            if ($module['pro']) {
                continue; // PRO modules managed by PRO plugin.
            }

            $fieldName = 'spolszczony_module_' . $module['id'];
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $saved[$module['id']] = isset($_POST[$fieldName]) ? true : false;
        }

        update_option(self::OPTION, $saved);

        // Save per-module settings.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $settingsData = $_POST['spolszczony_setting'] ?? [];

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
                    $existing[$fieldKey] = is_string($value) ? sanitize_text_field($value) : $value;
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

        \Spolszczony\Service\CacheHelper::flush();

        wp_safe_redirect(admin_url('admin.php?page=spolszczony&modules_saved=1'));
        exit;
    }

    /**
     * Check if a module is enabled.
     */
    public static function isModuleEnabled(string $moduleId): bool
    {
        $saved = get_option(self::OPTION, []);

        if (! is_array($saved) || ! isset($saved[$moduleId])) {
            // Return default state from module definition.
            $defaults = [
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
                'wpdesk_integration' => true,
                'payment_integration' => true,
            ];

            return $defaults[$moduleId] ?? false;
        }

        return (bool) $saved[$moduleId];
    }

    /**
     * Get HTML showing Omnibus plugin integration status.
     */
    private function getOmnibusIntegrationStatus(): string
    {
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
            $status = $active ? 'wykryta, dane synchronizowane' : 'niezainstalowana';

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
            $html .= '<div style="margin-top:6px;color:#666;">Żadna zewnętrzna wtyczka Omnibus nie jest zainstalowana. Spolszczony używa wbudowanego systemu śledzenia cen.</div>';
        } else {
            $html .= '<div style="margin-top:6px;color:#46b450;">Zewnętrzna wtyczka wykryta. Spolszczony korzysta z jej danych zamiast wbudowanego systemu.</div>';
        }

        $html .= '</div>';

        return $html;
    }
}
