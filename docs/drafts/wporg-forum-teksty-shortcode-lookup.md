# DRAFT, NIE WYSLANY

Watek: https://wordpress.org/support/topic/problem-z-odstapieniem-od-umowy/#post-19007164
Do: murbaniak
Temat: teksty shortcode'u [polski_withdrawal_lookup]

---

Cieszę się, że reszta działa.

Krótka odpowiedź: nie, w ustawieniach nie ma dziś pól na teksty formularza `[polski_withdrawal_lookup]`. Konfigurowalne teksty są, ale dotyczą ścieżki dla zalogowanego klienta w Moim koncie (etykieta przycisku, komunikat po złożeniu, etykieta powodu, komunikaty błędów). Formularz dla gościa pobiera z ustawień tylko liczbę dni i nazwę firmy, cała reszta to napisy w kodzie.

Są trzy sposoby, żeby je zmienić już teraz. Wszystkie działają, wybór zależy od tego, ile chcesz zmienić.

**1. Tłumaczenie, bez kodu.** Wszystkie 17 napisów tego formularza to zwykłe ciągi tłumaczone w domenie `polski`. Wtyczką w rodzaju Loco Translate otwierasz polskie tłumaczenie wtyczki i podmieniasz dowolny z nich na własny. To jest najprostsza droga, jeśli chodzi o poprawienie brzmienia kilku zdań, i przeżywa aktualizacje wtyczki.

**2. Filtr `gettext`, jeśli chodzi o jeden czy dwa napisy.** Do `functions.php` motywu potomnego albo do wtyczki na snippety:

```php
add_filter( 'gettext', function ( $translated, $original, $domain ) {
    if ( 'polski' !== $domain ) {
        return $translated;
    }

    $custom = [
        'Email me the link to the form' => 'Wyślij mi link do formularza',
        'All fields are required.'      => 'Wszystkie pola są wymagane.',
    ];

    return $custom[ $original ] ?? $translated;
}, 10, 3 );
```

Kluczem jest **oryginalny angielski** napis, nie polskie tłumaczenie, bo filtr dostaje oryginał.

**3. Podmiana całego szablonu, jeśli chcesz pełną kontrolę.** Wtyczka szuka szablonu najpierw w motywie. Skopiuj plik `templates/forms/withdrawal-lookup.php` z katalogu wtyczki do swojego motywu, zachowując strukturę:

```
wp-content/themes/twoj-motyw/polski/forms/withdrawal-lookup.php
```

Od tej chwili wtyczka użyje Twojej kopii i możesz w niej zmienić dowolny tekst, kolejność pól czy sekcję FAQ. Uwaga na jedno: kopia nie dostaje poprawek, które wprowadzam w oryginale, więc przy większych zmianach w tym formularzu warto ją co jakiś czas porównać z wersją z wtyczki.

Osobno: uważam, że masz rację, że tego brakuje. To, że teksty w Moim koncie da się ustawić z panelu, a te w formularzu dla gościa nie, jest niekonsekwencją, nie decyzją projektową. Dopiszę pola na najważniejsze z nich, czyli nagłówek, opis wstępny, etykiety pól i przycisk. Jeśli masz listę konkretnych napisów, które chcesz zmienić, wypisz je, a dopilnuję, żeby wszystkie znalazły się w tej pierwszej wersji.
