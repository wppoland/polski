# DRAFT, NIE WYSLANY

Watek: https://wordpress.org/support/topic/problem-z-odstapieniem-od-umowy/#post-19007164
Do: murbaniak
Temat: teksty shortcode'u [polski_withdrawal_lookup]

---

Cieszę się, że reszta działa. Miałeś rację, że tego brakowało, więc dorobiłem to zamiast tłumaczyć, jak to obejść.

**Wersja 1.30.4 jest już na wordpress.org.** Po aktualizacji w ustawieniach odstąpienia jest sekcja **Teksty formularza dla gościa**, a w niej nagłówek, akapit wstępny, etykiety obu pól i przycisk wysyłki.

Puste pole zostawia domyślne brzmienie, więc aktualizacja niczego Ci nie zmienia, dopóki sam czegoś nie wpiszesz. Domyślne teksty dalej idą przez tłumaczenia wtyczki, a nie są zamrażane w bazie.

W akapicie wstępnym możesz użyć dwóch znaczników:

- `%1$s` wstawi nazwę Twojej firmy,
- `%2$d` wstawi liczbę dni na odstąpienie.

Nie musisz ich używać. Jeśli wpiszesz zwykły tekst bez znaczników, zostanie wypisany dosłownie.

Jeśli chcesz zmienić coś, czego nie ma na tej liście, na przykład sekcję najczęstszych pytań albo zdanie o ważności linku, są jeszcze dwie drogi:

**Tłumaczenie.** Wszystkie 17 napisów tego formularza to zwykłe ciągi w domenie `polski`, więc wtyczką w rodzaju Loco Translate podmienisz dowolny z nich bez kodu.

**Własny szablon**, jeśli chcesz pełną kontrolę nad układem. Wtyczka szuka szablonu najpierw w motywie, więc skopiuj `templates/forms/withdrawal-lookup.php` z katalogu wtyczki do:

```
wp-content/themes/twoj-motyw/polski/forms/withdrawal-lookup.php
```

Od tej chwili używana jest Twoja kopia. Jedna uwaga: kopia nie dostaje poprawek, które wprowadzam w oryginale, więc przy większych zmianach w tym formularzu warto ją co jakiś czas porównać.

Napisz, jeśli któregoś konkretnego napisu dalej brakuje w ustawieniach, dołożę go.
