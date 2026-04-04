#!/usr/bin/env php
<?php
/**
 * Generate PO files for all supported locales from the POT template.
 * 
 * For pl_PL: Polish msgids are copied as-is to msgstr; English msgids get Polish translations.
 * For de_DE, cs_CZ, sk_SK, uk: creates properly structured PO with translated strings.
 *
 * Usage: php scripts/generate-translations.php
 */

$potFile = __DIR__ . '/../languages/polski.pot';
$langDir = __DIR__ . '/../languages';

if (!file_exists($potFile)) {
    die("POT file not found: $potFile\n");
}

$potContent = file_get_contents($potFile);

// Parse POT into entries
$entries = parsePot($potContent);
echo count($entries) . " entries parsed from POT\n";

// --- LOCALE DEFINITIONS ---
$locales = [
    'pl_PL' => [
        'name' => 'Polish',
        'team' => 'Polish <pl_PL@li.org>',
        'plural' => 'nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);',
    ],
    'de_DE' => [
        'name' => 'German',
        'team' => 'German <de_DE@li.org>',
        'plural' => 'nplurals=2; plural=(n != 1);',
    ],
    'cs_CZ' => [
        'name' => 'Czech',
        'team' => 'Czech <cs_CZ@li.org>',
        'plural' => 'nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2;',
    ],
    'sk_SK' => [
        'name' => 'Slovak',
        'team' => 'Slovak <sk_SK@li.org>',
        'plural' => 'nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2;',
    ],
    'uk' => [
        'name' => 'Ukrainian',
        'team' => 'Ukrainian <uk@li.org>',
        'plural' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);',
    ],
];

// --- TRANSLATION MAPS (key = msgid, value = msgstr) ---
// Only the most important user-facing strings. GlotPress handles the rest.

$translations = [];

// pl_PL: English msgids that need Polish translations
$translations['pl_PL'] = plTranslations();
$translations['de_DE'] = deTranslations();
$translations['cs_CZ'] = csTranslations();
$translations['sk_SK'] = skTranslations();
$translations['uk']    = ukTranslations();

foreach ($locales as $locale => $meta) {
    $poFile = "$langDir/polski-$locale.po";
    $map = $translations[$locale] ?? [];
    
    $po = generatePo($entries, $locale, $meta, $map, $potContent);
    file_put_contents($poFile, $po);
    echo "Written: $poFile (" . count($map) . " translated entries)\n";
}

echo "\nDone! Now compile with: cd languages && for f in *.po; do msgfmt -o \"\${f%.po}.mo\" \"\$f\"; done\n";

// ========== FUNCTIONS ==========

function parsePot(string $content): array {
    $entries = [];
    // Split by double newline to get blocks
    $blocks = preg_split('/\n\n+/', $content);
    foreach ($blocks as $block) {
        $block = trim($block);
        if (empty($block) || strpos($block, 'msgid') === false) continue;
        
        // Extract msgid
        if (preg_match('/^msgid\s+"(.*)"\s*$/m', $block, $m)) {
            $msgid = stripcslashes($m[1]);
            if ($msgid === '') continue; // skip header
            
            // Check for msgid_plural
            $plural = null;
            if (preg_match('/^msgid_plural\s+"(.*)"\s*$/m', $block, $mp)) {
                $plural = stripcslashes($mp[1]);
            }
            
            // Check for msgctxt
            $context = null;
            if (preg_match('/^msgctxt\s+"(.*)"\s*$/m', $block, $mc)) {
                $context = stripcslashes($mc[1]);
            }
            
            // Extract comments
            $comments = [];
            foreach (explode("\n", $block) as $line) {
                if (preg_match('/^#/', $line)) {
                    $comments[] = $line;
                }
            }
            
            $entries[] = [
                'msgid' => $msgid,
                'msgid_plural' => $plural,
                'msgctxt' => $context,
                'comments' => $comments,
                'raw' => $block,
            ];
        }
    }
    return $entries;
}

function generatePo(array $entries, string $locale, array $meta, array $map, string $potContent): string {
    $header = "# Translation of Polski for WooCommerce in {$meta['name']}\n";
    $header .= "# This file is distributed under the GPL-2.0-or-later.\n";
    $header .= "msgid \"\"\nmsgstr \"\"\n";
    $header .= "\"Project-Id-Version: Polski for WooCommerce 1.3.0\\n\"\n";
    $header .= "\"Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/polski\\n\"\n";
    $header .= "\"Last-Translator: Mariusz Szatkowski\\n\"\n";
    $header .= "\"Language-Team: {$meta['team']}\\n\"\n";
    $header .= "\"Language: $locale\\n\"\n";
    $header .= "\"MIME-Version: 1.0\\n\"\n";
    $header .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
    $header .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
    $header .= "\"POT-Creation-Date: 2026-04-04T06:47:36+00:00\\n\"\n";
    $header .= "\"PO-Revision-Date: 2026-04-04T08:58:00+02:00\\n\"\n";
    $header .= "\"Plural-Forms: {$meta['plural']}\\n\"\n";
    $header .= "\"X-Generator: Polski Translation Script 1.0\\n\"\n";
    $header .= "\"X-Domain: polski\\n\"\n\n";

    $body = '';
    foreach ($entries as $entry) {
        // Comments
        foreach ($entry['comments'] as $c) {
            $body .= "$c\n";
        }
        
        // Context
        if ($entry['msgctxt']) {
            $body .= 'msgctxt "' . addcslashes($entry['msgctxt'], '"\\') . '"' . "\n";
        }
        
        // msgid
        $body .= 'msgid "' . addcslashes($entry['msgid'], '"\\') . '"' . "\n";
        
        // Lookup key
        $key = $entry['msgid'];
        $tr = $map[$key] ?? '';
        
        // For pl_PL: default to the source string (msgid) if no explicit translation exists,
        // as most source strings in this plugin are already in Polish.
        if ($locale === 'pl_PL' && $tr === '') {
            $tr = $entry['msgid'];
        }
        
        if ($entry['msgid_plural']) {
            $body .= 'msgid_plural "' . addcslashes($entry['msgid_plural'], '"\\') . '"' . "\n";
            $trPlural = $map[$entry['msgid_plural']] ?? '';
            if (in_array($locale, ['pl_PL', 'cs_CZ', 'sk_SK', 'uk'])) {
                $body .= 'msgstr[0] "' . addcslashes($tr, '"\\') . '"' . "\n";
                $body .= 'msgstr[1] "' . addcslashes($trPlural ?: $tr, '"\\') . '"' . "\n";
                $body .= 'msgstr[2] "' . addcslashes($trPlural ?: $tr, '"\\') . '"' . "\n";
            } else {
                $body .= 'msgstr[0] "' . addcslashes($tr, '"\\') . '"' . "\n";
                $body .= 'msgstr[1] "' . addcslashes($trPlural ?: $tr, '"\\') . '"' . "\n";
            }
        } else {
            $body .= 'msgstr "' . addcslashes($tr, '"\\') . '"' . "\n";
        }
        
        $body .= "\n";
    }
    
    return $header . $body;
}

// ===== TRANSLATION MAPS =====
// Key user-facing strings only. Rest will be translated via GlotPress.

function plTranslations(): array {
    return [
        // English strings → Polish
        'Polski for WooCommerce' => 'Polski dla WooCommerce',
        'Polish legal compliance for WooCommerce: GDPR, Omnibus, withdrawal forms, unit prices, and storefront features. Free and open source.' => 'Zgodność prawna WooCommerce z polskim prawem: RODO, Omnibus, formularze odstąpienia, ceny jednostkowe i funkcje sklepu. Darmowy i open source.',
        'WP Poland' => 'WP Poland',
        'Polski requires PHP %1$s or higher. You are running PHP %2$s.' => 'Polski wymaga PHP %1$s lub wyższej. Masz zainstalowane PHP %2$s.',
        'Polski requires WooCommerce to be installed and activated.' => 'Polski wymaga zainstalowania i aktywowania WooCommerce.',
        'Polski requires WooCommerce %1$s or higher. You are running WooCommerce %2$s.' => 'Polski wymaga WooCommerce %1$s lub wyższej. Masz zainstalowane WooCommerce %2$s.',
        'This plugin provides tools to assist with Polish e-commerce compliance. It does not constitute legal advice. You are solely responsible for ensuring your store complies with all applicable laws. We recommend consulting a qualified legal professional.' => 'Ta wtyczka udostępnia narzędzia wspierające zgodność sklepu z polskim prawem e-commerce. Nie stanowi porady prawnej. Odpowiedzialność za zgodność sklepu z obowiązującym prawem spoczywa wyłącznie na Tobie. Zalecamy konsultację z wykwalifikowanym prawnikiem.',
        'I understand, dismiss' => 'Rozumiem, zamknij',
        'Placeholder' => 'Zastępczy tekst (Placeholder)',
        'Accent' => 'Wyróżnienie',
        'Success' => 'Sukces',
        'Warning' => 'Ostrzeżenie',
        'Neutral' => 'Neutralny',
        'Badge Management' => 'Zarządzanie badge\'ami',
        'Tab Manager' => 'Menedżer zakładek',
        'Featured Video' => 'Wyróżnione wideo',
        '- Select unit -' => '- Wybierz jednostkę -',
        'The reference base amount (e.g., 1 for "per 1 kg", 100 for "per 100 ml").' => 'Bazowa ilość referencyjna (np. 1 dla „za 1 kg", 100 dla „za 100 ml").',
        '- Use default -' => '- Użyj domyślnego -',
        'Privacy Policy (Polityka prywatności)' => 'Polityka prywatności',
        'Return Policy (Prawo odstąpienia)' => 'Prawo odstąpienia od umowy',
        'SKU' => 'SKU',
        'GTIN / EAN' => 'GTIN / EAN',
        'URL' => 'URL',
        'KSeF' => 'KSeF',
        'Nutri-Score' => 'Nutri-Score',
        '% vol.' => '% obj.',
        '100 g' => '100 g',
        'Brands' => 'Marki',
        'Brand' => 'Marka',
        'Add Brand' => 'Dodaj markę',
        'Edit Brand' => 'Edytuj markę',
        'Search Brands' => 'Szukaj marek',
        'Upsell' => 'Upsell',
        '%d day' => '%d dzień',
        '%d days' => '%d dni',
        '%d business day' => '%d dzień roboczy',
        '%d business days' => '%d dni roboczych',
        '%d week' => '%d tydzień',
        '%d weeks' => '%d tygodni',
        'Enable the Terms and Conditions checkbox - required by Polish consumer law.' => 'Włącz checkbox regulaminu — wymagany przez polskie prawo konsumenckie.',
        'Enable the Privacy Policy checkbox - required by GDPR Art. 6.1.a and Art. 7.' => 'Włącz checkbox polityki prywatności — wymagany przez RODO art. 6 ust. 1 lit. a i art. 7.',
        'Enable the Withdrawal Rights checkbox - required by Polish Consumer Rights Act (Art. 12).' => 'Włącz checkbox prawa odstąpienia — wymagany przez ustawę o prawach konsumenta (art. 12).',
        'Consider enabling Digital Content waiver if you sell digital products.' => 'Rozważ włączenie zrzeczenia się prawa odstąpienia dla treści cyfrowych, jeśli sprzedajesz produkty cyfrowe.',
        'Marketing consent must be optional under GDPR - change it from required to optional.' => 'Zgoda marketingowa musi być opcjonalna zgodnie z RODO — zmień z wymaganej na opcjonalną.',
        'Consider adding an optional marketing consent checkbox for newsletter compliance.' => 'Rozważ dodanie opcjonalnego checkboxa zgody marketingowej dla zgodności z przepisami o newsletterze.',
        'Review reminder consent should be optional - change it from required to optional.' => 'Zgoda na przypomnienia o opinii powinna być opcjonalna — zmień z wymaganej na opcjonalną.',
        'I have read and accept the <a href="%s" target="_blank">Terms and Conditions</a>.' => 'Przeczytałem/am i akceptuję <a href="%s" target="_blank">Regulamin</a>.',
        'I have read and accept the <a href="%s" target="_blank">Privacy Policy</a>.' => 'Przeczytałem/am i akceptuję <a href="%s" target="_blank">Politykę prywatności</a>.',
        'I have been informed about my <a href="%s" target="_blank">right to withdraw from the contract</a> within 14 days.' => 'Zostałem/am poinformowany/a o moim <a href="%s" target="_blank">prawie odstąpienia od umowy</a> w ciągu 14 dni.',
        'Privacy Policy acceptance (Polityka prywatności).' => 'Akceptacja Polityki prywatności.',
        '14-day withdrawal right acknowledgment (Prawo odstąpienia).' => 'Potwierdzenie 14-dniowego prawa odstąpienia od umowy.',
        'Publish your legal pages (Regulamin, Polityka prywatności, Prawo odstąpienia, Reklamacje).' => 'Opublikuj strony prawne (Regulamin, Polityka prywatności, Prawo odstąpienia, Reklamacje).',
        'Set up <a href="%s">tax rates</a> in WooCommerce for Polish VAT (23%%, 8%%, 5%%, 0%%).' => 'Skonfiguruj <a href="%s">stawki podatkowe</a> w WooCommerce dla polskiego VAT (23%%, 8%%, 5%%, 0%%).',
        'Configure <a href="%s">shipping zones</a> for Polish delivery.' => 'Skonfiguruj <a href="%s">strefy wysyłkowe</a> dla dostawy w Polsce.',
        'Edit product data - add unit prices and delivery times in the <a href="%s">Polski tab</a> of each product.' => 'Edytuj dane produktów — dodaj ceny jednostkowe i czasy dostawy w <a href="%s">zakładce Polski</a> każdego produktu.',
        'Test the checkout - add a product to cart and verify legal checkboxes and button text at <a href="%s">checkout</a>.' => 'Przetestuj kasę — dodaj produkt do koszyka i sprawdź checkboxy prawne i tekst przycisku na <a href="%s">stronie kasy</a>.',
        'Please fill in your Privacy Policy (Polityka prywatności).' => 'Proszę uzupełnić Politykę prywatności.',
        'Please fill in your Return and Withdrawal Policy (Prawo odstąpienia od umowy). Consumers have 14 days to withdraw.' => 'Proszę uzupełnić Prawo odstąpienia od umowy. Konsumenci mają 14 dni na odstąpienie.',
        'This field is required.' => 'To pole jest wymagane.',
        'required' => 'wymagane',
        'Filter settings' => 'Ustawienia filtrów',
        'Title' => 'Tytuł',
        'Show title' => 'Pokaż tytuł',
        'Show categories' => 'Pokaż kategorie',
        'Show brands' => 'Pokaż marki',
        'Show price' => 'Pokaż cenę',
        'Show stock' => 'Pokaż dostępność',
        'Show sale' => 'Pokaż promocje',
        'Show attributes' => 'Pokaż atrybuty',
        'Polski AJAX Filters' => 'Polski — Filtry AJAX',
        'Dynamic product filters rendered on the frontend.' => 'Dynamiczne filtry produktowe renderowane na stronie.',
        'Search settings' => 'Ustawienia wyszukiwania',
        'Search label' => 'Etykieta wyszukiwania',
        'Results label' => 'Etykieta wyników',
        'Show submit button' => 'Pokaż przycisk wyszukiwania',
        'Submit button text' => 'Tekst przycisku',
        'Minimum characters' => 'Minimalna liczba znaków',
        'Results limit' => 'Limit wyników',
        'Polski AJAX Search' => 'Polski — Wyszukiwarka AJAX',
        'Dynamic product search form rendered on the frontend.' => 'Dynamiczny formularz wyszukiwania produktów AJAX dla sklepów WooCommerce.',
        'Slider settings' => 'Ustawienia slidera',
        'Source' => 'Źródło',
        'Related products' => 'Produkty powiązane',
        'Upsell products' => 'Produkty upsell',
        'Sale products' => 'Produkty w promocji',
        'Featured products' => 'Produkty wyróżnione',
        'Product ID' => 'ID produktu',
        'Optional for related and upsell sliders outside product templates.' => 'Opcjonalne dla sliderów powiązanych i upsell poza szablonem produktu.',
        'Product limit' => 'Limit produktów',
        'Show add to cart' => 'Pokaż przycisk koszyka',
        'Polski Product Slider' => 'Polski — Slider produktów',
        'Dynamic merchandising slider rendered on the frontend.' => 'Dynamiczny slider merchandisingowy renderowany na stronie.',
        'Legal Checkboxes' => 'Checkboxy prawne',
        'Polish legal compliance checkboxes for checkout.' => 'Checkboxy zgodności prawnej dla polskiej kasy.',
        'Dynamic WooCommerce product filters with archive-safe GET fallback.' => 'Dynamiczne filtry produktowe WooCommerce z bezpiecznym fallbackiem GET dla archiwów.',
        'Dynamic AJAX product search form for WooCommerce storefronts.' => 'Dynamiczny formularz wyszukiwania AJAX dla sklepów WooCommerce.',
        'Dynamic merchandising slider for related, upsell, sale, and featured products.' => 'Dynamiczny slider merchandisingowy dla produktów powiązanych, upsell, promocyjnych i wyróżnionych.',
        'Unit' => 'Jednostka',
        'Weight (kg)' => 'Waga (kg)',
        'Weight (g)' => 'Waga (g)',
        'Volume (l)' => 'Objętość (l)',
        'Volume (ml)' => 'Objętość (ml)',
        'Length (m)' => 'Długość (m)',
        'Length (cm)' => 'Długość (cm)',
        'Area (m²)' => 'Powierzchnia (m²)',
        'Volume (m³)' => 'Objętość (m³)',
        'Pieces' => 'Sztuki',
        'szt.' => 'szt.',
        'Show unit price' => 'Pokaż cenę jednostkową',
        'Delivery Time' => 'Czas dostawy',
        'Show delivery time' => 'Pokaż czas dostawy',
        'Active' => 'Aktywny',
        'Inactive' => 'Nieaktywny',
        'Settings' => 'Ustawienia',
        'Modules' => 'Moduły',
        'Dashboard' => 'Pulpit',
        'Save Settings' => 'Zapisz ustawienia',
        'Go to Settings' => 'Przejdź do ustawień',
        'Need help?' => 'Potrzebujesz pomocy?',
        'Documentation' => 'Dokumentacja',
        'Polski version %s' => 'Wersja Polski %s',
        'General' => 'Ogólne',
        'Checkout' => 'Kasa',
        'Product Page' => 'Strona produktu',
        'Archive Page' => 'Strona archiwum',
        'B2B' => 'B2B',
        'SEO' => 'SEO',
        'Merchandising' => 'Merchandising',
        'Compliance' => 'Zgodność',
    ];
}

function deTranslations(): array {
    return [
        'Polski for WooCommerce' => 'Polski für WooCommerce',
        'Polish legal compliance for WooCommerce: GDPR, Omnibus, withdrawal forms, unit prices, and storefront features. Free and open source.' => 'Polnische Rechtskonformität für WooCommerce: DSGVO, Omnibus, Widerrufsformulare, Grundpreise und Storefront-Funktionen. Kostenlos und Open Source.',
        'I understand, dismiss' => 'Verstanden, schließen',
        'Polski requires WooCommerce to be installed and activated.' => 'Polski benötigt ein installiertes und aktiviertes WooCommerce.',
        'This field is required.' => 'Dieses Feld ist erforderlich.',
        'required' => 'erforderlich',
        'Ustawienia' => 'Einstellungen',
        'Moduły' => 'Module',
        'Pulpit' => 'Dashboard',
        'Zapisz ustawienia' => 'Einstellungen speichern',
        'Kreator' => 'Assistent',
        'Regulamin' => 'AGB',
        'Polityka prywatności' => 'Datenschutzerklärung',
        'Producent' => 'Hersteller',
        'Cena jednostkowa' => 'Grundpreis',
        'Czas dostawy' => 'Lieferzeit',
        'Marka' => 'Marke',
        'Cena' => 'Preis',
        'Ilość' => 'Menge',
        'Produkt' => 'Produkt',
        'Dostępność' => 'Verfügbarkeit',
        'SKU' => 'SKU',
        'Status' => 'Status',
        'Zamknij' => 'Schließen',
        'Kategoria' => 'Kategorie',
        'Wszystkie' => 'Alle',
        'Promocja' => 'Angebot',
        'Nowość' => 'Neu',
        'Bestseller' => 'Bestseller',
        'Ostatnie sztuki' => 'Letzte Stücke',
        'Dodaj do ulubionych' => 'Zur Wunschliste hinzufügen',
        'Usuń z ulubionych' => 'Von der Wunschliste entfernen',
        'Ulubione' => 'Wunschliste',
        'Dodaj do porównania' => 'Zum Vergleich hinzufügen',
        'Usuń z porównania' => 'Vom Vergleich entfernen',
        'Porównaj produkty' => 'Produkte vergleichen',
        'Porównanie' => 'Vergleich',
        'Szybki podgląd' => 'Schnellansicht',
        'Szybki podgląd produktu' => 'Produkt-Schnellansicht',
        'Zobacz pełną kartę produktu' => 'Vollständige Produktseite anzeigen',
        'Zamawiam z obowiązkiem zapłaty' => 'Zahlungspflichtig bestellen',
        'Polecane produkty' => 'Empfohlene Produkte',
        'Załaduj więcej produktów' => 'Mehr Produkte laden',
        'Szukaj produktów' => 'Produkte suchen',
        'Wyniki wyszukiwania produktów' => 'Suchergebnisse',
        'Aktywuj konto' => 'Konto aktivieren',
        'Zamknij popup' => 'Popup schließen',
        'Przejdź dalej' => 'Weiter',
        'Anuluj' => 'Abbrechen',
        'Edytuj' => 'Bearbeiten',
        'Data' => 'Datum',
        'Email' => 'E-Mail',
        'Adres' => 'Adresse',
        'NIP' => 'USt-IdNr.',
        'Telefon' => 'Telefon',
        'Składniki' => 'Zutaten',
        'Alergeny' => 'Allergene',
        'Kraj pochodzenia' => 'Herkunftsland',
        'Zawartość alkoholu' => 'Alkoholgehalt',
        'Zawartość netto' => 'Nettofüllmenge',
        'Dystrybutor' => 'Vertrieb',
        'Zasilanie' => 'Stromversorgung',
        'wymagane' => 'erforderlich',
        'Brands' => 'Marken',
        'Brand' => 'Marke',
        'Add Brand' => 'Marke hinzufügen',
        'Edit Brand' => 'Marke bearbeiten',
        'Search Brands' => 'Marken suchen',
    ];
}

function csTranslations(): array {
    return [
        'Polski for WooCommerce' => 'Polski pro WooCommerce',
        'Polish legal compliance for WooCommerce: GDPR, Omnibus, withdrawal forms, unit prices, and storefront features. Free and open source.' => 'Soulad polského e-commerce práva pro WooCommerce: GDPR, Omnibus, formuláře odstoupení, jednotkové ceny a funkce obchodu. Zdarma a open source.',
        'I understand, dismiss' => 'Rozumím, zavřít',
        'This field is required.' => 'Toto pole je povinné.',
        'required' => 'povinné',
        'Ustawienia' => 'Nastavení',
        'Moduły' => 'Moduly',
        'Pulpit' => 'Přehled',
        'Zapisz ustawienia' => 'Uložit nastavení',
        'Regulamin' => 'Obchodní podmínky',
        'Polityka prywatności' => 'Zásady ochrany osobních údajů',
        'Producent' => 'Výrobce',
        'Cena jednostkowa' => 'Jednotková cena',
        'Czas dostawy' => 'Dodací lhůta',
        'Marka' => 'Značka',
        'Cena' => 'Cena',
        'Ilość' => 'Množství',
        'Produkt' => 'Produkt',
        'Dostępność' => 'Dostupnost',
        'Status' => 'Stav',
        'Zamknij' => 'Zavřít',
        'Kategoria' => 'Kategorie',
        'Wszystkie' => 'Všechny',
        'Promocja' => 'Akce',
        'Nowość' => 'Novinka',
        'Bestseller' => 'Bestseller',
        'Ostatnie sztuki' => 'Poslední kusy',
        'Dodaj do ulubionych' => 'Přidat do oblíbených',
        'Ulubione' => 'Oblíbené',
        'Dodaj do porównania' => 'Přidat k porovnání',
        'Porównaj produkty' => 'Porovnat produkty',
        'Porównanie' => 'Porovnání',
        'Szybki podgląd' => 'Rychlý náhled',
        'Zamawiam z obowiązkiem zapłaty' => 'Objednávám s povinností platby',
        'Polecane produkty' => 'Doporučené produkty',
        'Szukaj produktów' => 'Hledat produkty',
        'Zamknij popup' => 'Zavřít popup',
        'Anuluj' => 'Zrušit',
        'Email' => 'E-mail',
        'Adres' => 'Adresa',
        'Telefon' => 'Telefon',
        'Składniki' => 'Složení',
        'Alergeny' => 'Alergeny',
        'Kraj pochodzenia' => 'Země původu',
        'wymagane' => 'povinné',
        'Brands' => 'Značky',
        'Brand' => 'Značka',
        'Add Brand' => 'Přidat značku',
        'Edit Brand' => 'Upravit značku',
        'Search Brands' => 'Hledat značky',
        'Nie utworzono jeszcze stron prawnych. Wygeneruj je, aby rozpocząć.' => 'Právní stránky ještě nebyly vytvořeny. Vygenerujte je pro začátek.',
        'Zostaw adres email, a damy znać, gdy produkt wróci na stan.' => 'Zanechte e-mailovou adresu a dáme vám vědět, až bude produkt znovu skladem.',
        'Użyj %s jako miejsca na link do strony regulaminu' => 'Použijte %s jako místo pro odkaz na obchodní podmínky.',
        'Użyj %s jako miejsca na link do polityki prywatności' => 'Použijte %s jako místo pro odkaz na zásady ochrany osobních údajů.',
        'Użyj %s jako miejsca na link do strony zwrotów lub odstąpienia' => 'Použijte %s jako místo pro odkaz na stránku vrácení zboží nebo odstoupení.',
        'Your withdrawal request for order #{order_number} has been confirmed.' => 'Vaše žádost o odstoupení od objednávky č. #{order_number} byla potvrzena.',
        'Podstawowa ilość referencyjna (np. 1 dla "na 1 kg", 100 dla "na 100 ml").' => 'Základní referenční množství, například 1 pro „na 1 kg“ nebo 100 pro „na 100 ml“.',
        'Consider enabling Digital Content waiver if you sell digital products.' => 'Pokud prodáváte digitální produkty, zvažte aktivaci vzdání se práva na digitální obsah.',
        'Strony regulaminu i polityki prywatnosci sa ustawione i opublikowane.' => 'Stránky obchodních podmínek a zásad ochrany osobních údajů jsou nastavené a publikované.',
        'Are you sure you want to submit a withdrawal request for order #%s?' => 'Opravdu chcete odeslat žádost o odstoupení od objednávky č. %s?',
        'Dynamic WooCommerce product filters with archive-safe GET fallback.' => 'Dynamické filtry produktů WooCommerce s bezpečným GET fallbackem pro archivy.',
    ];
}

function skTranslations(): array {
    return [
        'Polski for WooCommerce' => 'Polski pre WooCommerce',
        'I understand, dismiss' => 'Rozumiem, zavrieť',
        'This field is required.' => 'Toto pole je povinné.',
        'required' => 'povinné',
        'Ustawienia' => 'Nastavenia',
        'Moduły' => 'Moduly',
        'Pulpit' => 'Prehľad',
        'Zapisz ustawienia' => 'Uložiť nastavenia',
        'Regulamin' => 'Obchodné podmienky',
        'Polityka prywatności' => 'Zásady ochrany osobných údajov',
        'Producent' => 'Výrobca',
        'Cena jednostkowa' => 'Jednotková cena',
        'Czas dostawy' => 'Dodacia lehota',
        'Marka' => 'Značka',
        'Cena' => 'Cena',
        'Ilość' => 'Množstvo',
        'Produkt' => 'Produkt',
        'Dostępność' => 'Dostupnosť',
        'Status' => 'Stav',
        'Zamknij' => 'Zavrieť',
        'Kategoria' => 'Kategória',
        'Wszystkie' => 'Všetky',
        'Promocja' => 'Akcia',
        'Nowość' => 'Novinka',
        'Bestseller' => 'Bestseller',
        'Dodaj do ulubionych' => 'Pridať do obľúbených',
        'Ulubione' => 'Obľúbené',
        'Dodaj do porównania' => 'Pridať k porovnaniu',
        'Porównaj produkty' => 'Porovnať produkty',
        'Szybki podgląd' => 'Rýchly náhľad',
        'Zamawiam z obowiązkiem zapłaty' => 'Objednávam s povinnosťou platby',
        'Szukaj produktów' => 'Hľadať produkty',
        'Anuluj' => 'Zrušiť',
        'Email' => 'E-mail',
        'Adres' => 'Adresa',
        'Telefon' => 'Telefón',
        'Składniki' => 'Zloženie',
        'Alergeny' => 'Alergény',
        'wymagane' => 'povinné',
        'Brands' => 'Značky',
        'Brand' => 'Značka',
        'Nie utworzono jeszcze stron prawnych. Wygeneruj je, aby rozpocząć.' => 'Právne stránky ešte neboli vytvorené. Vygenerujte ich na začiatok.',
        'Zostaw adres email, a damy znać, gdy produkt wróci na stan.' => 'Nechajte e-mailovú adresu a dáme vám vedieť, keď bude produkt opäť skladom.',
        'Użyj %s jako miejsca na link do strony regulaminu' => 'Použite %s ako miesto pre odkaz na obchodné podmienky.',
        'Użyj %s jako miejsca na link do polityki prywatności' => 'Použite %s ako miesto pre odkaz na zásady ochrany osobných údajov.',
        'Użyj %s jako miejsca na link do strony zwrotów lub odstąpienia' => 'Použite %s ako miesto pre odkaz na stránku vrátenia alebo odstúpenia.',
        'Your withdrawal request for order #{order_number} has been confirmed.' => 'Vaša žiadosť o odstúpenie od objednávky č. #{order_number} bola potvrdená.',
        'Podstawowa ilość referencyjna (np. 1 dla "na 1 kg", 100 dla "na 100 ml").' => 'Základné referenčné množstvo, napríklad 1 pre „na 1 kg“ alebo 100 pre „na 100 ml“.',
        'Consider enabling Digital Content waiver if you sell digital products.' => 'Ak predávate digitálne produkty, zvážte zapnutie vzdania sa práva pri digitálnom obsahu.',
        'Strony regulaminu i polityki prywatnosci sa ustawione i opublikowane.' => 'Stránky obchodných podmienok a zásad ochrany osobných údajov sú nastavené a publikované.',
        'Are you sure you want to submit a withdrawal request for order #%s?' => 'Naozaj chcete odoslať žiadosť o odstúpenie od objednávky č. %s?',
        'Dynamic WooCommerce product filters with archive-safe GET fallback.' => 'Dynamické filtre produktov WooCommerce s bezpečným GET fallbackom pre archívy.',
    ];
}

function ukTranslations(): array {
    return [
        'Polski for WooCommerce' => 'Polski для WooCommerce',
        'I understand, dismiss' => 'Зрозуміло, закрити',
        'This field is required.' => 'Це поле є обов\'язковим.',
        'required' => 'обов\'язково',
        'Ustawienia' => 'Налаштування',
        'Moduły' => 'Модулі',
        'Pulpit' => 'Панель',
        'Zapisz ustawienia' => 'Зберегти налаштування',
        'Regulamin' => 'Умови та положення',
        'Polityka prywatności' => 'Політика конфіденційності',
        'Producent' => 'Виробник',
        'Cena jednostkowa' => 'Ціна за одиницю',
        'Czas dostawy' => 'Термін доставки',
        'Marka' => 'Бренд',
        'Cena' => 'Ціна',
        'Ilość' => 'Кількість',
        'Produkt' => 'Товар',
        'Dostępność' => 'Наявність',
        'Status' => 'Статус',
        'Zamknij' => 'Закрити',
        'Kategoria' => 'Категорія',
        'Wszystkie' => 'Усі',
        'Promocja' => 'Акція',
        'Nowość' => 'Новинка',
        'Bestseller' => 'Бестселер',
        'Dodaj do ulubionych' => 'Додати до обраного',
        'Ulubione' => 'Обране',
        'Dodaj do porównania' => 'Додати до порівняння',
        'Porównaj produkty' => 'Порівняти товари',
        'Szybki podgląd' => 'Швидкий перегляд',
        'Zamawiam z obowiązkiem zapłaty' => 'Замовляю з зобов\'язанням оплати',
        'Szukaj produktów' => 'Шукати товари',
        'Anuluj' => 'Скасувати',
        'Email' => 'Електронна пошта',
        'Adres' => 'Адреса',
        'Telefon' => 'Телефон',
        'Składniki' => 'Склад',
        'Alergeny' => 'Алергени',
        'wymagane' => 'обов\'язково',
        'Brands' => 'Бренди',
        'Brand' => 'Бренд',
    ];
}
