# DRAFT, NIE WYSLANY

Watek: https://wordpress.org/support/topic/nip-przycisk-zamowienia-checkboxy-prawne-raz-jeszcze/
Do: czester
Wersja, ktora to naprawia: 1.30.8 (juz na wordpress.org)

---

Dziekuje za tak dokladne zgloszenie. Odtworzylem wszystkie trzy przypadki na czystej instalacji z WooCommerce 11 i wszystkie trzy byly prawdziwymi bledami po mojej stronie. Kazdy z innej przyczyny, ale laczy je jedno: nowy checkout blokowy, ktory WooCommerce daje domyslnie.

**Pole NIP.** Wtyczka rejestrowala je w zdarzeniu `woocommerce_init`, ktore WooCommerce wywoluje zanim Polski zdazy sie uruchomic. Rejestracja trafiala w zdarzenie, ktore juz minelo, wiec pole nie powstawalo nigdy. To samo dotyczylo pol B2B, a w wersji PRO pola zadania faktury.

**Tekst przycisku zamowienia.** Na checkoucie blokowym przycisk rysuje React, a jego napis pobierany jest z wlasnego filtra WooCommerce. Wtyczka probowala go podmienic filtrem PHP, ktory dla tego napisu nie jest w ogole wolany, wiec ustawienie nie mialo prawa zadzialac. Teraz uzywam wspieranego filtra WooCommerce. Na klasycznym checkoucie nic sie nie zmienia.

**Checkboxy, ktorych nie dalo sie wylaczyc.** Do zapisu ustawien podpiete byly dwie procedury. Wygrywala ta zarejestrowana pierwsza, a ona zapisywala wylacznie pola obecne w formularzu. Odznaczony checkbox w formularzu HTML po prostu nie istnieje, wiec kazdy zapis mogl tylko dodawac, nigdy odejmowac. Druga procedura, ta ktora nigdy nie dochodzila do glosu, byla akurat napisana poprawnie. Zostala jedna sciezka zapisu. Przy okazji przestala wycinac odnosnik z etykiety regulaminu i polityki prywatnosci.

Wyszlo przy tym jeszcze jedno: gdy modul NIP i pola B2B byly wlaczone razem, to samo pole rejestrowalo sie dwa razy, czego WooCommerce nie przyjmuje. Teraz wlascicielem pola jest modul NIP.

Sprawdzalem to nie z lektury kodu, tylko na uruchomionym sklepie: pole NIP renderuje sie na checkoucie blokowym, przycisk pokazuje ustawiony napis, a odznaczenie checkboxa zostaje po zapisie i po przeladowaniu strony. Dolozylem tez test, ktory wywala sie na obu tych bledach, zeby nie wrocily niezauwazone. Uruchomiony na wersji 1.30.7 raportuje dokladnie te trzy usterki.

Prosze zaktualizowac do 1.30.8 i dac znac, gdyby ktorykolwiek z modulow dalej nie zachowywal sie jak trzeba. Jesli wolisz zglaszac takie rzeczy poza forum, sa jeszcze:

- zgloszenia bledow: github.com/wppoland/polski/issues
- pytania i dyskusje: github.com/wppoland/polski/discussions
- dokumentacja: plogins.com/polski/docs/

Dziekuje za cierpliwosc i za to, ze opisales objawy tak konkretnie. Bez tego trzy niedzialajace moduly siedzialyby tam dalej.
