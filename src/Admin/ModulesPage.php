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
            // === Ceny i wyswietlanie ===
            [
                'id' => 'unit_price',
                'name' => 'Cena jednostkowa',
                'description' => 'Wyswietlanie ceny za jednostke miary (np. za 1 kg, za 100 ml) zgodnie z polskim prawem konsumenckim.',
                'group' => 'Ceny i wyswietlanie',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-tag',
                'links' => [],
            ],
            [
                'id' => 'omnibus',
                'name' => 'Dyrektywa Omnibus',
                'description' => 'Automatyczne sledzenie i wyswietlanie najnizszej ceny z ostatnich 30 dni przy produktach przecenionych. Integracja z WC Price History i Omnibus by iworks.',
                'group' => 'Ceny i wyswietlanie',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-chart-line',
                'links' => [],
            ],
            [
                'id' => 'tax_display',
                'name' => 'Wyswietlanie VAT',
                'description' => 'Konfiguracja wyswietlania cen brutto/netto, informacja o stawce VAT, obsluga zwolnienia podmiotowego (art. 113 ustawy o VAT).',
                'group' => 'Ceny i wyswietlanie',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-money-alt',
                'links' => [],
            ],
            [
                'id' => 'delivery_time',
                'name' => 'Czas dostawy',
                'description' => 'Wyswietlanie przewidywanego czasu dostawy na stronie produktu. Konfiguracja per produkt lub wariant z domyslnym fallbackiem.',
                'group' => 'Ceny i wyswietlanie',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-clock',
                'links' => [],
            ],
            [
                'id' => 'shipping_notice',
                'name' => 'Informacja o kosztach wysylki',
                'description' => 'Link do strony z kosztami wysylki wyswietlany przy cenie produktu.',
                'group' => 'Ceny i wyswietlanie',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-car',
                'links' => [],
            ],

            // === Kasa i zamowienia ===
            [
                'id' => 'checkout_button',
                'name' => 'Przycisk zamowienia',
                'description' => 'Zmiana tekstu przycisku zamowienia na "Zamawiam z obowiazkiem zaplaty" zgodnie z polskim prawem.',
                'group' => 'Kasa i zamowienia',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-cart',
                'links' => [],
            ],
            [
                'id' => 'legal_checkboxes',
                'name' => 'Checkboxy prawne',
                'description' => '7 wbudowanych checkboxow: regulamin, polityka prywatnosci, prawo odstapienia, tresci cyfrowe, powiadomienia o dostawie, przypomnienie o opinii, marketing. Mozliwosc dodawania wlasnych.',
                'group' => 'Kasa i zamowienia',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-yes-alt',
                'links' => [],
            ],
            [
                'id' => 'consent_logging',
                'name' => 'Logowanie zgod (RODO)',
                'description' => 'Rejestrowanie wszystkich zgod udzielonych przez klientow z adresem IP, user agentem i znacznikiem czasu. Zgodne z RODO.',
                'group' => 'Kasa i zamowienia',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-shield',
                'links' => [],
            ],
            [
                'id' => 'contract_helper',
                'name' => 'Potwierdzenie zamowienia',
                'description' => 'Obsluga odroczonych platnosci - potwierdzenie zamowienia przed platnoscia, przycisk "Zaplac teraz" na stronie podziekowania.',
                'group' => 'Kasa i zamowienia',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-clipboard',
                'links' => [],
            ],
            [
                'id' => 'invoice_gateway',
                'name' => 'Platnosc przelewem / faktura',
                'description' => 'Bramka platnosci umozliwiajaca platnosc przelewem bankowym po otrzymaniu zamowienia. Wyswietla dane do przelewu na stronie podziekowania i w emailach.',
                'group' => 'Kasa i zamowienia',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-bank',
                'links' => [],
            ],

            // === Prawa konsumenta ===
            [
                'id' => 'legal_pages',
                'name' => 'Strony prawne',
                'description' => 'Automatyczne generowanie stron: Regulamin, Polityka prywatnosci, Prawo odstapienia od umowy, Reklamacje.',
                'group' => 'Prawa konsumenta',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-media-document',
                'links' => [],
            ],
            [
                'id' => 'withdrawal',
                'name' => 'Prawo odstapienia (14 dni)',
                'description' => 'Formularz odstapienia od umowy, przycisk "Odstap" w historii zamowien, potwierdzenie emailem, obsluga wylaczen per produkt.',
                'group' => 'Prawa konsumenta',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-undo',
                'links' => [],
            ],
            [
                'id' => 'dispute_resolution',
                'name' => 'Rozstrzyganie sporow (ODR)',
                'description' => 'Wyswietlanie informacji o platformie ODR (Online Dispute Resolution) Komisji Europejskiej.',
                'group' => 'Prawa konsumenta',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-admin-site-alt3',
                'links' => [],
            ],
            [
                'id' => 'email_attachments',
                'name' => 'Zalaczniki prawne w emailach',
                'description' => 'Dolaczanie tresci stron prawnych (regulamin, polityka prywatnosci, prawo odstapienia) do emaili z potwierdzeniem zamowienia.',
                'group' => 'Prawa konsumenta',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-email',
                'links' => [],
            ],

            // === Informacje o produkcie ===
            [
                'id' => 'manufacturer',
                'name' => 'Producent i GPSR',
                'description' => 'Informacje o producencie, osoba odpowiedzialna (GPSR), dokumenty bezpieczenstwa, instrukcje bezpieczenstwa.',
                'group' => 'Informacje o produkcie',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-building',
                'links' => [],
            ],
            [
                'id' => 'food_module',
                'name' => 'Zywnosc i suplementy',
                'description' => 'Tabela wartosci odzywczych, alergeny, skladniki, Nutri-Score, zawartosc alkoholu, kraj pochodzenia, dystrybutor.',
                'group' => 'Informacje o produkcie',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-carrot',
                'links' => [],
            ],
            [
                'id' => 'power_supply',
                'name' => 'Informacje o zasilaniu',
                'description' => 'Dane o zuzyciu energii dla urzadzen elektrycznych (etykiety energetyczne).',
                'group' => 'Informacje o produkcie',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-lightbulb',
                'links' => [],
            ],

            // === Konto klienta ===
            [
                'id' => 'double_opt_in',
                'name' => 'Podwojna weryfikacja (DOI)',
                'description' => 'Weryfikacja adresu email przy rejestracji konta. Link aktywacyjny wysylany emailem, blokada logowania dla nieaktywowanych kont.',
                'group' => 'Konto klienta',
                'enabled' => false,
                'pro' => false,
                'icon' => 'dashicons-lock',
                'links' => [],
            ],

            // === Integracje ===
            [
                'id' => 'omnibus_integration',
                'name' => 'Integracja Omnibus',
                'description' => 'Automatyczne wykrywanie wtyczek WC Price History (kkarpieszuk) i Omnibus (iworks). Jesli zainstalowane, Spolszczony korzysta z ich danych.',
                'group' => 'Integracje',
                'enabled' => true,
                'pro' => false,
                'icon' => 'dashicons-admin-plugins',
                'links' => [
                    ['label' => 'WC Price History', 'url' => 'https://wordpress.org/plugins/wc-price-history/'],
                    ['label' => 'Omnibus by iworks', 'url' => 'https://pl.wordpress.org/plugins/omnibus/'],
                ],
            ],
            [
                'id' => 'wpdesk_integration',
                'name' => 'Integracja WP Desk',
                'description' => 'Wspolpraca z Flexible Checkout Fields (80 000+ instalacji), Flexible Cookies, GPSR for WooCommerce.',
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
                'name' => 'Integracja bramek platnosci',
                'description' => 'Wykrywanie i dostosowanie Przelewy24, PayU, Tpay, BLIK, Autopay do wymagan prawnych.',
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
                'description' => 'Generowanie Faktur VAT, Faktur korygujacych i Paragonow w formacie PDF. Konfigurowalny format numeracji. Automatyczne generowanie przy zmianie statusu zamowienia.',
                'group' => 'PRO',
                'enabled' => false,
                'pro' => true,
                'icon' => 'dashicons-media-spreadsheet',
                'links' => [],
            ],
            [
                'id' => 'ksef',
                'name' => 'KSeF (e-Faktury)',
                'description' => 'Integracja z Krajowym Systemem e-Faktur. Wysylanie faktur do KSeF, podpis cyfrowy, kolejka asynchroniczna, dashboard statusow.',
                'group' => 'PRO',
                'enabled' => false,
                'pro' => true,
                'icon' => 'dashicons-cloud-upload',
                'links' => [],
            ],
            [
                'id' => 'nip_validation',
                'name' => 'Walidacja NIP',
                'description' => 'Pole NIP na stronie kasy z walidacja sumy kontrolnej. Weryfikacja w bazie GUS/REGON. Automatyczne uzupelnianie danych firmy.',
                'group' => 'PRO',
                'enabled' => false,
                'pro' => true,
                'icon' => 'dashicons-id-alt',
                'links' => [],
            ],
            [
                'id' => 'shipping_providers',
                'name' => 'Integracje wysylkowe',
                'description' => 'InPost (Paczkomaty), DPD, DHL, Poczta Polska, Orlen Paczka. Generowanie etykiet, mapa punktow odbioru, sledzenie przesylek.',
                'group' => 'PRO',
                'enabled' => false,
                'pro' => true,
                'icon' => 'dashicons-car',
                'links' => [],
            ],
            [
                'id' => 'multistep_checkout',
                'name' => 'Kasa wieloetapowa',
                'description' => 'Nowoczesna kasa podzielona na kroki: Adres > Wysylka > Platnosc > Podsumowanie. Responsywna, mobile-first.',
                'group' => 'PRO',
                'enabled' => false,
                'pro' => true,
                'icon' => 'dashicons-editor-ol',
                'links' => [],
            ],
            [
                'id' => 'accounting',
                'name' => 'Integracje ksiegowe',
                'description' => 'Synchronizacja faktur z wFirma, Fakturownia, iFirma. Automatyczny eksport danych po wystawieniu faktury.',
                'group' => 'PRO',
                'enabled' => false,
                'pro' => true,
                'icon' => 'dashicons-calculator',
                'links' => [],
            ],
            [
                'id' => 'legal_generator',
                'name' => 'Generator tekstow prawnych',
                'description' => 'Automatyczne generowanie Regulaminu, Polityki prywatnosci i Polityki zwrotow na podstawie danych sklepu.',
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

        $borderColor = $enabled ? '#46b450' : '#ccd0d4';
        $opacity = $locked ? '0.7' : '1';

        echo '<div style="background:#fff;border:1px solid ' . esc_attr($borderColor) . ';padding:16px;opacity:' . $opacity . ';position:relative;">';

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
        echo '<label style="position:relative;display:inline-block;width:40px;height:22px;">';
        printf(
            '<input type="checkbox" name="%s" value="1" %s %s style="opacity:0;width:0;height:0;">',
            esc_attr($fieldName),
            checked($enabled, true, false),
            $locked ? 'disabled' : '',
        );
        $bgColor = $enabled ? '#46b450' : '#ccc';
        $translate = $enabled ? '18px' : '2px';
        echo '<span style="position:absolute;cursor:' . ($locked ? 'not-allowed' : 'pointer') . ';top:0;left:0;right:0;bottom:0;background:' . $bgColor . ';border-radius:22px;transition:.3s;"></span>';
        echo '<span style="position:absolute;content:\'\';height:18px;width:18px;left:' . $translate . ';bottom:2px;background:#fff;border-radius:50%;transition:.3s;box-shadow:0 1px 3px rgba(0,0,0,.2);"></span>';
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
                'omnibus_integration' => true,
                'wpdesk_integration' => true,
                'payment_integration' => true,
            ];

            return $defaults[$moduleId] ?? false;
        }

        return (bool) $saved[$moduleId];
    }
}
