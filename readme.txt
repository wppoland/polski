=== Polski for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, gpsr, omnibus, rodo, ksef
Requires at least: 6.4
Tested up to: 7.0
Stable tag: 1.24.4
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce dla polskich sklepów: GPSR, Omnibus, RODO, zwroty, NIP, KSeF, ceny jednostkowe i moduły sklepu.

== Description ==

**Polski for WooCommerce** to darmowa wtyczka WooCommerce dla polskich sklepów internetowych. Pomaga uporządkować dane produktowe GPSR, historię najniższej ceny Omnibus, zgody RODO, odstąpienia od umowy, obsługę NIP, ceny jednostkowe, hooki pod procesy KSeF, zgłoszenia DSA oraz moduły sklepu.

Wtyczka jest modułowa. Możesz włączyć tylko te funkcje, których potrzebuje dany sklep, na przykład GPSR, Omnibus, zgody w koszyku, zwroty, ceny jednostkowe, dane żywności, wishlistę, porównywarkę produktów albo wyszukiwarkę AJAX.

Polski pomaga skonfigurować techniczne procesy sklepu związane z polskim i unijnym rynkiem. Nie jest poradą prawną i nie gwarantuje zgodności z przepisami. Konfigurację sklepu, regulaminy, produkty i obowiązki zawsze trzeba zweryfikować dla konkretnego biznesu.

= Dokumentacja i linki =

* **Dokumentacja** - https://plogins.com/pl/polski/docs/
* **Strona wtyczki** - https://plogins.com/pl/polski/
* **Kod źródłowy** - https://github.com/wppoland/polski
* **Zgłoszenia błędów i propozycje funkcji** - https://github.com/wppoland/polski/issues
* **Dyskusje i pytania** - https://github.com/wppoland/polski/discussions

= Dlaczego Polski for WooCommerce? =

* **Jedna wtyczka, wiele modułów** - GPSR, Omnibus, RODO, odstąpienia od umowy, dane produktowe i moduły sklepu w jednym miejscu.
* **Dla polskich sklepów** - funkcje projektowane pod sklepy WooCommerce sprzedające w Polsce lub do polskich klientów.
* **Darmowa i z otwartym kodem** - podstawowe narzędzia produktowe, związane z kasą i sklepowe są dostępne bez opłat.
* **Nowoczesny kod** - PHP 8.1+, panel administracyjny React, REST API i wsparcie WP-CLI.
* **Obsługa bloków WooCommerce** - zgodność z klasycznym oraz blokowym koszykiem i kasą.
* **Zgodność z HPOS** - wsparcie WooCommerce High-Performance Order Storage.

= Najważniejsze moduły =

* **Pola GPSR produktu** - producent, importer, osoba odpowiedzialna w UE, identyfikatory produktu, ostrzeżenia i instrukcje bezpieczeństwa, także z importem i eksportem CSV.
* **Historia cen Omnibus** - zapisywanie i wyświetlanie najniższej ceny z ostatnich 30 dni przy produktach promocyjnych.
* **Zgody RODO i checkboxy** - konfigurowalne zgody w kasie, rejestracji i recenzjach z rejestrem zgód.
* **Odstąpienia od umowy i zwroty** - zgłoszenia z konta klienta, potwierdzenia e-mail i rejestr zgłoszeń.
* **NIP i hooki KSeF** - wykrywanie zamówień z NIP, flaga KSeF oraz hooki dla integracji fakturowych.
* **Zgłoszenia DSA** - punkt kontaktowy, formularz zgłoszenia nielegalnych treści i panel administracyjny.
* **Monitor zdrowia sklepu** - pasywne monitorowanie błędów frontendu, problemów z kasą i anomalii sprzedaży.
* **Rejestr incydentów bezpieczeństwa** - wewnętrzny rejestr incydentów, awarii, podatności i działań następczych.
* **Pola środowiskowe produktu** - podstawa twierdzeń ekologicznych, certyfikaty i daty ważności.
* **Oznaczenie zweryfikowanego zakupu** - odznaka przy recenzjach od klientów, którzy kupili produkt.

= Kasa, zgody i zwroty =

* **Checkboxy zgód** - zgody przy zamówieniu, rejestracji i recenzjach, z możliwością włączenia tylko wybranych pól.
* **Historia cen Omnibus** - automatyczne zapisywanie i wyświetlanie najniższej ceny z 30 dni.
* **Prawo odstąpienia od umowy** - formularze i zgłoszenia zwrotów z konta klienta.
* **Podwójne potwierdzenie e-mail** - potwierdzanie adresu e-mail przy rejestracji klienta.
* **Strony sklepu** - podpinanie regulaminu, polityki prywatności i treści o odstąpieniu do wiadomości WooCommerce.
* **Rozwiązywanie sporów** - moduł informacji ODR dla stron informacyjnych sklepu.
* **Rejestr zgód** - logowanie zgód z datą, kontekstem, adresem IP i wersją treści.

= Dane produktowe i oznaczenia =

* **Ceny jednostkowe** - cena za kg, litr, metr, sztukę albo własną jednostkę.
* **Czas dostawy** - informacja o przewidywanym czasie dostawy na stronach produktów i listach produktów.
* **Informacje podatkowe** - komunikaty brutto/netto i stawka VAT.
* **Wyświetlanie cen** - konfiguracja sposobu prezentowania cen w sklepie.
* **Dane żywności** - skład, wartości odżywcze, alergeny, pochodzenie, dystrybutor i inne pola dla sklepów spożywczych.

= Moduły frontu sklepu =

* **Lista życzeń** - zapisywanie produktów na później.
* **Porównywarka produktów** - porównywanie produktów obok siebie.
* **Lista oczekujących** - powiadomienia o ponownej dostępności produktu.
* **Szybki podgląd** - podgląd produktu bez przechodzenia na stronę produktu.
* **Powiększenie galerii** - rozszerzone powiększanie zdjęć produktu.
* **Wideo produktu** - dodanie filmu na stronie produktu.
* **Slider produktów** - karuzela produktów i kolekcji.
* **Nieskończone przewijanie** - automatyczne ładowanie kolejnych produktów.
* **Menedżer zakładek produktu** - konfiguracja zakładek na stronie produktu.
* **Filtry AJAX** - filtrowanie produktów bez przeładowania strony.
* **Wyszukiwarka AJAX** - wyszukiwanie produktów na żywo.
* **Odznaki produktów** - oznaczenia promocji, nowości, wyróżnień i własnych etykiet.
* **Okna popup promocyjne** - kampanie popup w sklepie.

= Narzędzia administracyjne i deweloperskie =

* **Panel React** - zarządzanie modułami i ustawieniami.
* **REST API** - API dla ustawień, checkboxów, stron prawnych, odstąpień i wyszukiwania.
* **WP-CLI** - komendy do zarządzania wybranymi funkcjami z terminala.
* **Import i eksport CSV** - masowe zarządzanie danymi produktowymi, w tym GPSR.
* **Shortcode** - osadzanie informacji GPSR, formularzy odstąpienia, zgłoszeń DSA i innych elementów.
* **Migracje bazy danych** - wersjonowane i bezpieczne aktualizacje struktur danych.
* **Hooki integracyjne** - filtry i akcje dla KSeF, faktur oraz integracji z innymi wtyczkami.
* **Zakres audytowy** - DPA, DSA, gotowość pod KSeF, kontrola twierdzeń środowiskowych, zweryfikowane recenzje i incydenty bezpieczeństwa.

== Installation ==

= Instalacja automatyczna =

1. Przejdź w panelu WordPress do **Wtyczki > Dodaj nową**.
2. Wyszukaj **Polski for WooCommerce**.
3. Kliknij **Zainstaluj**, a następnie **Włącz**.
4. Przejdź do nowego menu **Polski** w panelu administracyjnym.

= Instalacja ręczna =

1. Pobierz plik ZIP wtyczki z WordPress.org.
2. W panelu WordPress przejdź do **Wtyczki > Dodaj nową > Wyślij wtyczkę na serwer**.
3. Wybierz plik ZIP i kliknij **Zainstaluj**.
4. Kliknij **Włącz wtyczkę**.

== Getting Started ==

1. **Sprawdź strony prawne**: przejdź do **Polski > Moduły** i upewnij się, że moduł stron prawnych jest aktywny. W ustawieniach wybierz regulamin, politykę prywatności i stronę odstąpienia od umowy.
2. **Skonfiguruj checkboxy**: przejdź do modułu checkboxów prawnych i włącz zgody wymagane w Twoim sklepie.
3. **Sprawdź stawki VAT**: upewnij się, że WooCommerce ma poprawne stawki podatku dla Twojego sklepu.
4. **Uzupełnij ceny jednostkowe**: dla produktów sprzedawanych wagowo lub objętościowo uzupełnij dane w zakładce **Polski** w edycji produktu.
5. **Włącz Omnibus**: moduł zapisuje historię cen i może wyświetlać najniższą cenę z 30 dni.
6. **Uzupełnij GPSR**: dla produktów fizycznych dodaj dane producenta, importera, osoby odpowiedzialnej i informacje bezpieczeństwa.

== Configuration ==

Polski działa modułowo. Możesz włączyć tylko te funkcje, których potrzebujesz:

* **Dane produktowe**: GPSR, ceny jednostkowe, czas dostawy, dane żywności.
* **Kasa i zgody**: checkboxy, odstąpienia od umowy, strony prawne.
* **Front sklepu**: lista życzeń, porównywarka, wyszukiwarka, filtry i odznaki.

Aktywne moduły z ustawieniami pojawiają się w menu **Polski** albo mają link do ustawień na stronie modułów.

== Frequently Asked Questions ==

= Czy Polski for WooCommerce jest darmowy? =

Tak. Polski for WooCommerce jest darmową wtyczką WooCommerce dla polskich sklepów internetowych i jest udostępniany z otwartym kodem na licencji GPLv2 lub nowszej.

= Do jakiego sklepu WooCommerce jest przeznaczony Polski? =

Polski jest przeznaczony dla sklepów WooCommerce sprzedających w Polsce lub do polskich klientów. Szczególnie przydaje się tam, gdzie sklep potrzebuje modułów dla GPSR, Omnibus, RODO, NIP, odstąpień od umowy, KSeF i danych produktowych.

= Czy Polski obsługuje GPSR w WooCommerce? =

Tak. Polski dodaje pola produktowe związane z GPSR, między innymi dane producenta, importera, osoby odpowiedzialnej w UE, identyfikatory produktu, ostrzeżenia i instrukcje bezpieczeństwa. Dane można uzupełniać w edycji produktu oraz masowo przez import lub eksport CSV.

= Czy mogę pokazać dane producenta, importera i osoby odpowiedzialnej na stronie produktu? =

Tak. Moduł GPSR może przechowywać i wyświetlać dane producenta, importera oraz osoby odpowiedzialnej w UE na stronie produktu WooCommerce. Widoczność zależy od ustawień modułu i danych uzupełnionych przy produkcie.

= Czy Polski obsługuje dyrektywę Omnibus i najniższą cenę z 30 dni? =

Tak. Moduł Omnibus zapisuje historię cen i może wyświetlać najniższą cenę z ostatnich 30 dni przy produktach objętych promocją. Ustawienia wyświetlania możesz dopasować w panelu modułu.

= Czy Polski dodaje zgody RODO w WooCommerce? =

Tak. Wtyczka pozwala dodać konfigurowalne checkboxy zgód w zamówieniu, rejestracji i recenzjach, a także prowadzić rejestr zgód z datą, kontekstem i technicznymi informacjami audytowymi.

= Czy Polski dodaje checkboxy w kasie? =

Tak. Polski może dodać checkboxy dla regulaminu, polityki prywatności, informacji o odstąpieniu od umowy, zgody na treści cyfrowe, zgody marketingowej, powiadomień o dostawie i przypomnienia o recenzji.

= Czy Polski dodaje formularz odstąpienia od umowy albo zwrotu? =

Tak. Polski może dodać obsługę odstąpienia od umowy z poziomu konta klienta, potwierdzeniem zgłoszenia, logiem zgłoszeń i wiadomościami e-mail. To pomaga uporządkować proces zwrotów w WooCommerce.

= Czy Polski obsługuje NIP w WooCommerce? =

Tak. Polski zawiera funkcje i hooki związane z NIP, w tym wykrywanie zamówień mogących wymagać obsługi faktury lub procesu KSeF. Dostępność pola i zachowanie zależą od włączonych modułów.

= Czy Polski obsługuje KSeF w WooCommerce? =

Polski nie jest pełnym systemem do wysyłki faktur do KSeF, ale dodaje mechanizmy gotowe pod integracje: flagowanie zamówień po NIP, kolumnę statusu KSeF oraz hooki dla wtyczek fakturowych i własnych integracji.

= Czy Polski wystawia faktury w WooCommerce? =

Polski udostępnia dane, flagi i hooki przydatne dla faktur oraz KSeF, ale nie zastępuje pełnej wtyczki fakturowej ani systemu księgowego. Do automatycznego wystawiania faktur użyj dedykowanej integracji fakturowej.

= Czy Polski działa z blokową kasą WooCommerce? =

Tak. Polski obsługuje klasyczną kasę oraz koszyk i kasę oparte na blokach WooCommerce.

= Czy Polski działa z HPOS w WooCommerce? =

Tak. Polski deklaruje zgodność z WooCommerce HPOS, czyli High-Performance Order Storage / Custom Order Tables.

= Czy Polski dodaje ceny jednostkowe w WooCommerce? =

Tak. Wtyczka pozwala pokazywać ceny jednostkowe, na przykład za kg, litr, metr, sztukę albo własną jednostkę.

= Czy Polski nadaje się do sklepu spożywczego WooCommerce? =

Tak. Polski zawiera moduły przydatne dla sklepów spożywczych, między innymi skład, wartości odżywcze, alergeny, pochodzenie, dystrybutora i dodatkowe pola etykietowania produktów.

= Czy Polski dodaje formularz zgłoszeń DSA? =

Tak. Wtyczka zawiera narzędzia DSA, w tym ustawienia punktu kontaktowego, shortcode formularza zgłoszenia nielegalnych treści, panel obsługi zgłoszeń i powiadomienia e-mail.

= Czy Polski dodaje wishlistę, porównywarkę i szybki podgląd produktów? =

Tak. Moduły frontu sklepu obejmują między innymi listę życzeń, porównywarkę produktów, szybki podgląd, powiadomienia o dostępności, filtry AJAX, wyszukiwarkę AJAX i oznaczenia produktów.

= Czy mogę włączyć tylko wybrane moduły? =

Tak. Polski działa modułowo, więc możesz włączyć tylko potrzebne funkcje, na przykład GPSR, Omnibus, RODO, zwroty, NIP, DSA albo moduły frontu sklepu.

= Czy Polski obsługuje import i eksport CSV? =

Tak. Polski rozszerza import i eksport CSV WooCommerce o wybrane dane produktowe, między innymi pola GPSR i inne informacje o produkcie.

= Czy Polski ma shortcode? =

Tak. Wtyczka udostępnia shortcode dla wybranych modułów, między innymi informacji GPSR, formularzy odstąpienia, zgłoszeń DSA, szablonów reklamacji i komunikatów sklepu.

= Czy Polski gwarantuje zgodność z prawem? =

Nie. Polski dostarcza techniczne moduły dla WooCommerce, ale nie jest poradą prawną i nie gwarantuje zgodności sklepu z przepisami. Konfigurację sklepu, regulaminy i obowiązki zawsze trzeba zweryfikować dla konkretnego biznesu.

= Czy Polski jest gotowy na Cyber Resilience Act? =

Polski stosuje praktyki bezpieczeństwa istotne dla gotowości pod CRA: aktualizacje są dostarczane przez oficjalny kanał WordPress.org, podatności można zgłaszać zgodnie z polityką skoordynowanego ujawniania, kod używa standardowych mechanizmów bezpieczeństwa WordPress, a usługi zewnętrzne są opisane w readme. To nie jest deklaracja zgodności prawnej.

= Gdzie zgłaszać błędy lub propozycje funkcji? =

Do bieżącego wsparcia użyj forum WordPress.org. Błędy techniczne i propozycje funkcji możesz też zgłaszać w repozytorium GitHub.

= Czy wtyczka ma prosty formularz opinii? =

Tak. Panel administracyjny zawiera prosty formularz opinii, który zapisuje wiadomości lokalnie w WordPressie. Nie wpisuj tam haseł, kluczy licencyjnych ani danych osobowych klientów.

= Co dzieje się przy wyłączeniu i odinstalowaniu wtyczki? =

Wyłączenie wtyczki zostawia ustawienia i zapisane dane. Odinstalowanie usuwa pliki wtyczki. Dane wtyczki są usuwane tylko wtedy, gdy włączysz ustawienie usuwania danych przy odinstalowaniu.

== External Services ==

= API GUS REGON =

Gdy moduł wyszukiwania NIP jest włączony, wtyczka może połączyć się z publicznym rejestrem GUS REGON, aby pobrać dane firmy na podstawie numeru NIP wpisanego przez użytkownika. Połączenie jest wykonywane tylko po świadomym uruchomieniu wyszukiwania.

* Wysyłane dane: numer NIP.
* Adres usługi: [https://wyszukiwarkaregon.stat.gov.pl/](https://wyszukiwarkaregon.stat.gov.pl/)
* Regulamin usługi: [https://api.stat.gov.pl/Home/RegulaminBIR](https://api.stat.gov.pl/Home/RegulaminBIR)
* Polityka prywatności: [https://bip.stat.gov.pl/](https://bip.stat.gov.pl/)

= Google OAuth =

Gdy moduł logowania społecznościowego jest włączony i skonfigurowano logowanie Google, klient klikający przycisk kontynuacji z Google jest przekierowywany do Google w celu uwierzytelnienia. Wtyczka wymienia kod autoryzacyjny na token dostępu i pobiera dane profilu potrzebne do logowania albo utworzenia konta.

* Wysyłane dane: adres przekierowania, identyfikator klienta, kod autoryzacyjny i token dostępu do pobrania profilu.
* Otrzymywane dane: identyfikator konta Google, adres e-mail, imię i nazwisko.
* Adres usługi: [https://accounts.google.com/](https://accounts.google.com/)
* Warunki usługi: [https://policies.google.com/terms](https://policies.google.com/terms)
* Polityka prywatności: [https://policies.google.com/privacy](https://policies.google.com/privacy)

= Facebook OAuth =

Gdy moduł logowania społecznościowego jest włączony i skonfigurowano logowanie Facebook, klient klikający przycisk kontynuacji z Facebook jest przekierowywany do Facebook w celu uwierzytelnienia. Wtyczka wymienia kod autoryzacyjny na token dostępu i pobiera dane profilu potrzebne do logowania albo utworzenia konta.

* Wysyłane dane: adres przekierowania, identyfikator aplikacji, kod autoryzacyjny i token dostępu do pobrania profilu.
* Otrzymywane dane: identyfikator konta Facebook, adres e-mail, imię i nazwisko.
* Adres usługi: [https://www.facebook.com/](https://www.facebook.com/)
* Warunki usługi: [https://www.facebook.com/legal/terms](https://www.facebook.com/legal/terms)
* Polityka prywatności: [https://www.facebook.com/privacy/policy/](https://www.facebook.com/privacy/policy/)

= Google Tag Manager / Google Analytics =

Gdy moduł DataLayer jest włączony i skonfigurowano identyfikator kontenera GTM albo identyfikator pomiaru GA4, wtyczka może załadować skrypty Google Tag Manager albo Google Analytics w sklepie oraz wysyłać zdarzenia ecommerce zgodnie z konfiguracją.

* Wysyłane dane: odsłony stron i dane zdarzeń ecommerce, na przykład identyfikatory produktów, nazwy produktów, ceny, akcje koszyka, zdarzenia kasy i wartości zamówień, zależnie od konfiguracji.
* Adres usługi: [https://www.googletagmanager.com/](https://www.googletagmanager.com/)
* Warunki usługi: [https://policies.google.com/terms](https://policies.google.com/terms)
* Polityka prywatności: [https://policies.google.com/privacy](https://policies.google.com/privacy)

Opinie z panelu administracyjnego i informacje z formularza dezaktywacji są zapisywane lokalnie w WordPressie i nie są wysyłane do zewnętrznej usługi.

== Screenshots ==

1. Panel zarządzania modułami z przełącznikami i ustawieniami modułów.
2. Pola bezpieczeństwa produktu GPSR w edycji produktu.
3. Checkboxy zgód RODO w kasie z rejestrem zgód.
4. Dyrektywa Omnibus - najniższa cena z 30 dni przy produkcie promocyjnym.
5. Akcja odstąpienia od umowy w koncie klienta.
6. Formularz zgłoszenia nielegalnych treści DSA.
7. Wyszukiwarka AJAX i filtry produktów w sklepie.
8. Lista życzeń, porównywarka i szybki podgląd na liście produktów.

== Changelog ==

= 1.24.4 =
* Nowe bloki: bloki danych produktu dla motywów blokowych i edytora - Cena jednostkowa, Czas dostawy, Najniższa cena (Omnibus), Informacja podatkowa, Koszty wysyłki, Producent, Instrukcje bezpieczeństwa, Dokumenty bezpieczeństwa, Zasilanie, Opis wad, Wartości odżywcze, Alergeny, Składniki, Nutri-Score, Informacje o żywności oraz Informacja o bezpieczeństwie produktu (GPSR) - wszystkie w kategorii "Polski". Każdy z nich wyświetla odpowiednie dane produktu i nic nie pokazuje poza kontekstem produktu. Dzięki temu edytor bloków osiąga parytet z widżetami Polski dla Elementora.

= 1.24.3 =
* Nowe bloki: Formularz zgłoszenia DSA, Informacja o rozstrzyganiu sporów (ODR), Informacja o zwolnieniu z VAT dla małego podatnika oraz Dostępne metody płatności są teraz dostępne jako bloki (w kategorii "Polski"), więc można je umieścić na dowolnej stronie bez pamiętania shortcode'u. Każdy z nich nic nie wyświetla, dopóki odpowiednia funkcja nie zostanie skonfigurowana.

= 1.24.2 =
* Nowość: wszystkie bloki Polski są teraz zgrupowane w edytorze bloków w osobnej kategorii "Polski", a metadane bloków są rejestrowane z plików block.json, dzięki czemu bloki są poprawnie widoczne i łatwiejsze do znalezienia.
* Poprawka: bloki frontu sklepu, czyli wyszukiwarka AJAX, filtry AJAX i slider produktów, nie rejestrowały się na zainstalowanych stronach. Teraz rejestrują się poprawnie i pojawiają się w edytorze.

= 1.24.1 =
* Poprawka: lightbox galerii i powiększenia mógł zostać na ekranie jako ciemna nakładka, jeśli motyw wymuszał własną wartość display. Stan zamknięty jest teraz zawsze ukryty.
* Utwardzenie: nakładki popup i szybkiego podglądu także wymuszają ukryty stan, więc motyw nie powinien blokować zamkniętej nakładki na ekranie.

= 1.24.0 =
* Nowość: pełne wsparcie blokowego kasy WooCommerce. Checkboxy zgód, zgoda dla treści cyfrowych i własne pola kasy renderują się, walidują i zapisują zarówno w kasie blokowym, jak i klasycznym.
* Nowość: informacje produktowe, w tym najniższa cena z 30 dni, cena jednostkowa i czas dostawy, pojawiają się w blokowym koszyku i kasie.
* Bezpieczeństwo: utwardzono obsługę limitowania żądań w ścieżce odstąpienia dla gości oraz poprawiono anonimizację adresów IPv6 w rejestrze zgód.
* Poprawka: odinstalowanie usuwa wszystkie tabele wtyczki, migracje bazy danych wykonują się we właściwej kolejności, a okno najniższej ceny Omnibus nie sprowadza się już do bieżącej ceny przy pustym okresie.

= 1.23.2 =
* Ulepszenie: kreator konfiguracji jest teraz prowadzonym procesem wieloetapowym: firma, kwestie prawne, podatki i OSS, kasa, zakończenie. Zbiera dane firmy, włącza potrzebne moduły prawne oraz pomaga skonfigurować podatki i kasa. Kroki opcjonalne można pominąć, a zakończenie nie wyłącza modułów, których już używasz.

= 1.23.1 =
* Tłumaczenia: uzupełniono wbudowane tłumaczenia dla języka niemieckiego, czeskiego, słowackiego, ukraińskiego, litewskiego, białoruskiego i chińskiego uproszczonego, w tym teksty nowego kreatora konfiguracji oraz wyszukiwania i sortowania modułów.

= 1.23.0 =
* Nowość: kreator konfiguracji. Nowa karta proponuje gotowe scenariusze, takie jak polska baza prawna, żywność i grocery, produkty cyfrowe, B2B i hurt, fashion oraz wzrost konwersji. Kreator tylko włącza moduły, nie wyłącza istniejących ustawień.

= 1.22.7 =
* Poprawka: ikona menu Polski w panelu administracyjnym nie przesuwa się już po najechaniu i w stanie aktywnym.

= 1.22.6 =
* Nowość: ekran modułów ma teraz natychmiastowe wyszukiwanie po nazwie i opisie oraz sortowanie: grupowane, alfabetyczne albo włączone jako pierwsze. Bez przeładowania strony.

= 1.22.5 =
* Poprawka: usunięto błąd krytyczny TypeError, który mógł wystąpić przy zapytaniach o zamówienia, także na ekranie zamówień WooCommerce, gdy pomocnik zapytań odstąpień otrzymał obiekt paginacji zamiast tablicy.

= 1.22.4 =
* Nowość: moduł numeru BDO. Wpisz numer BDO i wyświetl go shortcode [polski_bdo] albo blokiem numeru BDO, na przykład w stopce. Blok identyfikacji firmy może także zawierać numer BDO.

= 1.22.3 =
* Administracja: połączono pięć osobnych ekranów ustawień w jeden ekran z kartami, a odstąpienia, rejestr zgód, incydenty CRA, SBOM, szablon reklamacji i dokumenty szkoleniowe RODO przeniesiono do centrum raportów i narzędzi.
* Administracja: podzielono listę modułów na grupy i dodano podpowiedzi do każdego modułu. Linki akcji są wygaszone, gdy moduł jest wyłączony.
* Poprawka: ikona menu Polski jest teraz wyśrodkowana względem etykiety.
* Tłumaczenia: odświeżono i ponownie skompilowano wszystkie wbudowane katalogi językowe po zmianach ustawień i menu.
* Dokumentacja: zaktualizowano linki do dokumentacji i strony wtyczki na plogins.com.

Starsze wersje są dostępne w [changelog.txt](https://plugins.svn.wordpress.org/polski/trunk/changelog.txt).

== Upgrade Notice ==

= 1.20.1 =
Poprawia ładowanie skryptów ekranów administracyjnych w wybranych konfiguracjach i odświeża wbudowane tłumaczenia.

= 1.6.3 =
Porządki po Plugin Check: dodano uzasadnienia dla zapytań do własnych tabel. Brak zmian funkcjonalnych.

= 1.6.2 =
Utwardzenie po przeglądzie WordPress.org: bezpieczniejsza obsługa danych wejściowych, szerszy opis usług zewnętrznych i poprawione sformułowania w readme.
