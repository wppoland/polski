<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * Generates Annex I(A) (information on the right of withdrawal) and Annex I(B)
 * (model withdrawal form) content based on the merchant's data collected during
 * the setup wizard (option `polski_general`).
 *
 * Free tier outputs Polish text only. Multi-language generation is layered on top
 * of this service in the Pro plugin.
 *
 * Exposes two shortcodes:
 *   [polski_withdrawal_info]
 *   [polski_withdrawal_form_template]
 */
final class AnnexGeneratorService implements HasHooks
{
    public function registerHooks(): void
    {
        add_shortcode('polski_withdrawal_info', [$this, 'renderInfoShortcode']);
        add_shortcode('polski_withdrawal_form_template', [$this, 'renderFormShortcode']);
    }

    public function renderInfoShortcode(): string
    {
        $data = $this->merchantData();
        $days = $this->periodDays();

        $html = '<div class="polski-annex polski-annex--info">';
        $html .= '<h2>' . esc_html__('Prawo odstąpienia od umowy', 'polski') . '</h2>';

        $html .= '<h3>' . esc_html__('Prawo odstąpienia od umowy', 'polski') . '</h3>';
        $html .= '<p>' . sprintf(
            /* translators: %d: number of days */
            esc_html__('Mają Państwo prawo odstąpić od niniejszej umowy w terminie %d dni bez podania jakiejkolwiek przyczyny.', 'polski'),
            (int) $days,
        ) . '</p>';
        $html .= '<p>' . sprintf(
            /* translators: %d: number of days */
            esc_html__('Termin do odstąpienia od umowy wygasa po upływie %d dni od dnia, w którym weszli Państwo w posiadanie rzeczy lub w którym osoba trzecia inna niż przewoźnik i wskazana przez Państwa weszła w posiadanie rzeczy.', 'polski'),
            (int) $days,
        ) . '</p>';
        $html .= '<p>' . esc_html__('Aby skorzystać z prawa odstąpienia od umowy, muszą Państwo poinformować nas o swojej decyzji o odstąpieniu od niniejszej umowy w drodze jednoznacznego oświadczenia (na przykład pismo wysłane pocztą, faksem lub pocztą elektroniczną).', 'polski') . '</p>';

        $html .= '<address style="font-style: normal;">';
        if ($data['name'] !== '') {
            $html .= '<strong>' . esc_html($data['name']) . '</strong><br />';
        }
        if ($data['address'] !== '') {
            $html .= nl2br(esc_html($data['address'])) . '<br />';
        }
        if ($data['phone'] !== '') {
            $html .= esc_html__('tel.', 'polski') . ' ' . esc_html($data['phone']) . '<br />';
        }
        if ($data['email'] !== '') {
            $html .= esc_html__('e-mail:', 'polski') . ' ' . esc_html($data['email']) . '<br />';
        }
        if ($data['nip'] !== '') {
            $html .= esc_html__('NIP:', 'polski') . ' ' . esc_html($data['nip']);
        }
        $html .= '</address>';

        $html .= '<p>' . esc_html__('Mogą Państwo skorzystać z wzoru formularza odstąpienia od umowy, jednak nie jest to obowiązkowe.', 'polski') . '</p>';
        $html .= '<p>' . esc_html__('Aby zachować termin do odstąpienia od umowy, wystarczy, aby wysłali Państwo informację dotyczącą wykonania przysługującego Państwu prawa odstąpienia od umowy przed upływem terminu do odstąpienia od umowy.', 'polski') . '</p>';

        $html .= '<h3>' . esc_html__('Skutki odstąpienia od umowy', 'polski') . '</h3>';
        $html .= '<p>' . sprintf(
            /* translators: %d: number of days */
            esc_html__('W przypadku odstąpienia od niniejszej umowy zwracamy Państwu wszystkie otrzymane od Państwa płatności, w tym koszty dostarczenia rzeczy (z wyjątkiem dodatkowych kosztów wynikających z wybranego przez Państwa sposobu dostarczenia innego niż najtańszy zwykły sposób dostarczenia oferowany przez nas), niezwłocznie, a w każdym przypadku nie później niż %d dni od dnia, w którym zostaliśmy poinformowani o Państwa decyzji o wykonaniu prawa odstąpienia od niniejszej umowy.', 'polski'),
            (int) $days,
        ) . '</p>';
        $html .= '<p>' . esc_html__('Zwrotu płatności dokonamy przy użyciu takich samych sposobów płatności, jakie zostały przez Państwa użyte w pierwotnej transakcji, chyba że wyraźnie zgodziliście się Państwo na inne rozwiązanie; w każdym przypadku nie poniosą Państwo żadnych opłat w związku z tym zwrotem.', 'polski') . '</p>';
        $html .= '<p>' . esc_html__('Możemy wstrzymać się ze zwrotem płatności do czasu otrzymania rzeczy lub do czasu dostarczenia nam dowodu jej odesłania, w zależności od tego, które zdarzenie nastąpi wcześniej.', 'polski') . '</p>';
        $html .= '<p>' . esc_html__('Proszę odesłać lub przekazać nam rzecz na adres podany powyżej, niezwłocznie, a w każdym razie nie później niż 14 dni od dnia, w którym poinformowali nas Państwo o odstąpieniu od niniejszej umowy. Termin jest zachowany, jeżeli odeślą Państwo rzecz przed upływem terminu 14 dni.', 'polski') . '</p>';
        $html .= '<p>' . esc_html__('Będą Państwo musieli ponieść bezpośrednie koszty zwrotu rzeczy.', 'polski') . '</p>';
        $html .= '<p>' . esc_html__('Odpowiadają Państwo tylko za zmniejszenie wartości rzeczy wynikające z korzystania z niej w sposób inny niż było to konieczne do stwierdzenia charakteru, cech i funkcjonowania rzeczy.', 'polski') . '</p>';

        $html .= '</div>';

        /**
         * Filter the generated Annex I(A) HTML.
         *
         * @param string                $html The generated HTML.
         * @param array<string, string> $data Merchant data.
         * @param int                   $days Withdrawal period in days.
         */
        return (string) apply_filters('polski/annex/info_html', $html, $data, $days);
    }

    public function renderFormShortcode(): string
    {
        $data = $this->merchantData();
        $lookupUrl = $this->lookupPageUrl();

        $html = '<div class="polski-annex polski-annex--form">';
        $html .= '<h2>' . esc_html__('Wzór formularza odstąpienia od umowy', 'polski') . '</h2>';
        $html .= '<p><em>' . esc_html__('(formularz ten należy wypełnić i odesłać tylko w przypadku chęci odstąpienia od umowy)', 'polski') . '</em></p>';

        $html .= '<p>' . esc_html__('Adresat:', 'polski') . '<br />';
        if ($data['name'] !== '') {
            $html .= '<strong>' . esc_html($data['name']) . '</strong><br />';
        }
        if ($data['address'] !== '') {
            $html .= nl2br(esc_html($data['address'])) . '<br />';
        }
        if ($data['email'] !== '') {
            $html .= esc_html__('e-mail:', 'polski') . ' ' . esc_html($data['email']);
        }
        $html .= '</p>';

        $html .= '<p>' . esc_html__('Ja/My(*) niniejszym informuję/informujemy(*) o moim/naszym odstąpieniu od umowy sprzedaży następujących rzeczy(*) umowy dostawy następujących rzeczy(*) umowy o dzieło polegającej na wykonaniu następujących rzeczy(*)/o świadczenie następującej usługi(*):', 'polski') . '</p>';
        $html .= '<p><span style="display:inline-block;border-bottom:1px dotted #555;min-width:60%;">&nbsp;</span></p>';
        $html .= '<p>' . esc_html__('Data zawarcia umowy(*)/odbioru(*):', 'polski')
            . ' <span style="display:inline-block;border-bottom:1px dotted #555;min-width:40%;">&nbsp;</span></p>';
        $html .= '<p>' . esc_html__('Imię i nazwisko konsumenta(-ów):', 'polski')
            . ' <span style="display:inline-block;border-bottom:1px dotted #555;min-width:40%;">&nbsp;</span></p>';
        $html .= '<p>' . esc_html__('Adres konsumenta(-ów):', 'polski')
            . ' <span style="display:inline-block;border-bottom:1px dotted #555;min-width:40%;">&nbsp;</span></p>';
        $html .= '<p>' . esc_html__('Podpis konsumenta(-ów) (tylko jeżeli formularz jest przesyłany w wersji papierowej):', 'polski')
            . ' <span style="display:inline-block;border-bottom:1px dotted #555;min-width:40%;">&nbsp;</span></p>';
        $html .= '<p>' . esc_html__('Data:', 'polski')
            . ' <span style="display:inline-block;border-bottom:1px dotted #555;min-width:40%;">&nbsp;</span></p>';
        $html .= '<p><em>' . esc_html__('(*) Niepotrzebne skreślić.', 'polski') . '</em></p>';

        if ($lookupUrl !== '') {
            /* translators: %s: lookup page URL where the consumer can file an online withdrawal declaration */
            $onlineLinkTemplate = __('Możesz także złożyć oświadczenie online: <a href="%s">formularz odstąpienia</a>.', 'polski');
            $html .= '<p>' . sprintf(
                wp_kses($onlineLinkTemplate, ['a' => ['href' => true]]),
                esc_url($lookupUrl),
            ) . '</p>';
        }

        $html .= '</div>';

        /**
         * Filter the generated Annex I(B) HTML.
         *
         * @param string                $html      The generated HTML.
         * @param array<string, string> $data      Merchant data.
         * @param string                $lookupUrl URL of the online lookup page (may be empty).
         */
        return (string) apply_filters('polski/annex/form_html', $html, $data, $lookupUrl);
    }

    /**
     * Render the Annex I(A) HTML, suitable for prefilling a page or capturing in an email.
     */
    public function getInfoHtml(): string
    {
        return $this->renderInfoShortcode();
    }

    /**
     * Render the Annex I(B) HTML.
     */
    public function getFormHtml(): string
    {
        return $this->renderFormShortcode();
    }

    /**
     * @return array{name: string, address: string, nip: string, regon: string, email: string, phone: string}
     */
    private function merchantData(): array
    {
        $general = get_option('polski_general', []);
        $general = is_array($general) ? $general : [];

        $data = [
            'name' => trim((string) ($general['company_name'] ?? get_bloginfo('name'))),
            'address' => trim((string) ($general['company_address'] ?? '')),
            'nip' => trim((string) ($general['company_nip'] ?? '')),
            'regon' => trim((string) ($general['company_regon'] ?? '')),
            'email' => trim((string) ($general['company_email'] ?? get_option('admin_email', ''))),
            'phone' => trim((string) ($general['company_phone'] ?? '')),
        ];

        if ($data['address'] === '') {
            $line = trim((string) get_option('woocommerce_store_address', ''));
            $line2 = trim((string) get_option('woocommerce_store_address_2', ''));
            $postcode = trim((string) get_option('woocommerce_store_postcode', ''));
            $city = trim((string) get_option('woocommerce_store_city', ''));

            $parts = array_filter([
                $line,
                $line2,
                trim($postcode . ' ' . $city),
            ]);
            $data['address'] = implode("\n", $parts);
        }

        /**
         * Filter merchant data used by the Annex generator.
         *
         * @param array{name: string, address: string, nip: string, regon: string, email: string, phone: string} $data
         */
        $filtered = apply_filters('polski/annex/merchant_data', $data);

        // Defend against bad filter implementations: ensure each expected key is a string.
        return [
            'name' => isset($filtered['name']) ? (string) $filtered['name'] : '',
            'address' => isset($filtered['address']) ? (string) $filtered['address'] : '',
            'nip' => isset($filtered['nip']) ? (string) $filtered['nip'] : '',
            'regon' => isset($filtered['regon']) ? (string) $filtered['regon'] : '',
            'email' => isset($filtered['email']) ? (string) $filtered['email'] : '',
            'phone' => isset($filtered['phone']) ? (string) $filtered['phone'] : '',
        ];
    }

    private function periodDays(): int
    {
        $settings = get_option('polski_withdrawal', []);
        $settings = is_array($settings) ? $settings : [];
        $days = isset($settings['period_days']) ? (int) $settings['period_days'] : 14;

        return $days >= 1 ? $days : 14;
    }

    private function lookupPageUrl(): string
    {
        $settings = get_option('polski_withdrawal', []);
        $settings = is_array($settings) ? $settings : [];
        $pageId = isset($settings['lookup_page_id']) ? (int) $settings['lookup_page_id'] : 0;

        if ($pageId > 0) {
            $url = get_permalink($pageId);
            if (is_string($url)) {
                return $url;
            }
        }

        return '';
    }
}
