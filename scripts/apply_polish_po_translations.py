#!/usr/bin/env python3
"""Fill empty msgstr entries in polski-pl_PL.po with Polish translations."""
from __future__ import annotations

import sys
from pathlib import Path

import polib

ROOT = Path(__file__).resolve().parent.parent
PO_PATH = ROOT / "languages" / "polski-pl_PL.po"

# Singular: msgid -> msgstr (Polish). msgid already in Polish uses identical msgstr.
SINGULAR: dict[str, str] = {
    "od {price}": "od {price}",
    "Jak oceniasz swoje zakupy? Zostaw opinię": "Jak oceniasz swoje zakupy? Zostaw opinię",
    "Cześć {first_name}, dziękujemy za ostatnie zakupy. Będziemy wdzięczni za Twoją opinię.": (
        "Cześć {first_name}, dziękujemy za ostatnie zakupy. Będziemy wdzięczni za Twoją opinię."
    ),
    "Zostaw opinię": "Zostaw opinię",
    "Zrezygnuj z próśb o opinię": "Zrezygnuj z próśb o opinię",
    "Nie będziemy już wysyłać próśb o opinię.": "Nie będziemy już wysyłać próśb o opinię.",
    "Minimalna wartość zamówienia to {min_value}. Aktualna wartość koszyka: {current_value}.": (
        "Minimalna wartość zamówienia to {min_value}. Aktualna wartość koszyka: {current_value}."
    ),
    "Minimalna liczba produktów w zamówieniu to {min_quantity}. Aktualna liczba: {current_quantity}.": (
        "Minimalna liczba produktów w zamówieniu to {min_quantity}. Aktualna liczba: {current_quantity}."
    ),
    "Added to cart!": "Dodano do koszyka!",
    "View cart": "Zobacz koszyk",
    "Could not add to cart. Please try again.": "Nie udało się dodać do koszyka. Spróbuj ponownie.",
    "Invalid product.": "Nieprawidłowy produkt.",
    "Validation failed.": "Walidacja nie powiodła się.",
    "Could not add to cart.": "Nie udało się dodać do koszyka.",
    "Stock restored: %1$s (%2$d -> %3$d)": "Przywrócono stan: %1$s (%2$d -> %3$d)",
    "%s is a required field.": "Pole %s jest wymagane.",
    "%s is not a valid email address.": "%s nie jest prawidłowym adresem e-mail.",
    "Additional information": "Dodatkowe informacje",
    "Checkout Fields": "Pola przy zamówieniu",
    "Text": "Tekst",
    "Textarea": "Tekst wieloliniowy",
    "Select": "Lista rozwijana",
    "Checkbox": "Pole wyboru",
    "Radio": "Przycisk opcji",
    "Number": "Liczba",
    "Phone": "Telefon",
    "Billing": "Rozliczeniowe",
    "Order notes": "Uwagi do zamówienia",
    "Fields saved.": "Zapisano pola.",
    "Enabled": "Włączone",
    "Name (meta key)": "Nazwa (klucz meta)",
    "Required": "Wymagane",
    "Priority": "Kolejność",
    "Actions": "Akcje",
    "Add field": "Dodaj pole",
    "Save fields": "Zapisz pola",
    "Expert Review": "Recenzja eksperta",
    "Add review": "Dodaj recenzję",
    "Add expert review": "Dodaj recenzję eksperta",
    "Edit expert review": "Edytuj recenzję eksperta",
    "Search expert reviews": "Szukaj recenzji ekspertów",
    "Review Details": "Szczegóły recenzji",
    "Select product...": "Wybierz produkt...",
    "Rating": "Ocena",
    "Verdict": "Werdykt",
    "e.g. Recommended, Best in class": "np. Polecane, Najlepsze w klasie",
    "by": "autor",
    "Add question": "Dodaj pytanie",
    "Add FAQ question": "Dodaj pytanie FAQ",
    "Edit FAQ": "Edytuj FAQ",
    "Search FAQ": "Szukaj FAQ",
    "FAQ Categories": "Kategorie FAQ",
    "FAQ Category": "Kategoria FAQ",
    "Add FAQ category": "Dodaj kategorię FAQ",
    "Search FAQ categories": "Szukaj kategorii FAQ",
    "Minimum order value is {min_value}. Current cart value: {current_value}.": (
        "Minimalna wartość zamówienia to {min_value}. Aktualna wartość koszyka: {current_value}."
    ),
    "Minimum number of items per order is {min_quantity}. Current quantity: {current_quantity}.": (
        "Minimalna liczba pozycji w zamówieniu to {min_quantity}. Aktualna liczba: {current_quantity}."
    ),
    "Order ID": "ID zamówienia",
    "Order number": "Numer zamówienia",
    "Order date": "Data zamówienia",
    "Total": "Suma",
    "Subtotal": "Kwota częściowa",
    "Tax": "Podatek",
    "Discount": "Rabat",
    "Payment method": "Metoda płatności",
    "Shipping method": "Metoda dostawy",
    "Billing first name": "Imię (rozliczenie)",
    "Billing last name": "Nazwisko (rozliczenie)",
    "Billing email": "E-mail (rozliczenie)",
    "Billing phone": "Telefon (rozliczenie)",
    "Billing company": "Firma (rozliczenie)",
    "Billing address": "Adres (rozliczenie)",
    "Billing city": "Miasto (rozliczenie)",
    "Billing postcode": "Kod pocztowy (rozliczenie)",
    "Billing country": "Kraj (rozliczenie)",
    "Shipping first name": "Imię (dostawa)",
    "Shipping last name": "Nazwisko (dostawa)",
    "Shipping address": "Adres (dostawa)",
    "Shipping city": "Miasto (dostawa)",
    "Shipping postcode": "Kod pocztowy (dostawa)",
    "Shipping country": "Kraj (dostawa)",
    "Customer note": "Uwagi klienta",
    "Product names": "Nazwy produktów",
    "Product SKUs": "SKU produktów",
    "Product quantities": "Ilości produktów",
    "Coupon codes": "Kody kuponów",
    "Date range": "Zakres dat",
    "to": "do",
    "Order statuses": "Statusy zamówień",
    "Export fields": "Pola eksportu",
    "from {price}": "od {price}",
    "Authors": "Autorzy",
    "Author": "Autor",
    "Search authors": "Szukaj autorów",
    "All authors": "Wszyscy autorzy",
    "Edit author": "Edytuj autora",
    "Update author": "Zaktualizuj autora",
    "Add new author": "Dodaj nowego autora",
    "New author name": "Nazwa nowego autora",
    "You have been unsubscribed from review request emails.": (
        "Zrezygnowałeś z e-maili z prośbami o opinię."
    ),
    "How was your purchase? Leave a review": "Jak oceniasz swoje zakupy? Zostaw opinię",
    "Hi {first_name}, thank you for your recent purchase. We would love to hear your feedback.": (
        "Cześć {first_name}, dziękujemy za ostatnie zakupy. Będziemy wdzięczni za Twoją opinię."
    ),
    "Unsubscribe from review requests": "Zrezygnuj z próśb o opinię",
    "Leave a review": "Zostaw opinię",
    "Continue with Google": "Kontynuuj z Google",
    "Continue with Facebook": "Kontynuuj z Facebookiem",
    "Or sign in with": "Lub zaloguj się przez",
    "Social login verification failed. Please try again.": (
        "Weryfikacja logowania społecznościowego nie powiodła się. Spróbuj ponownie."
    ),
    "Could not authenticate with the social provider. Please try again.": (
        "Nie udało się uwierzytelnić u dostawcy społecznościowego. Spróbuj ponownie."
    ),
    "Could not retrieve your profile. Please ensure email access is granted.": (
        "Nie udało się pobrać profilu. Upewnij się, że przyznano dostęp do adresu e-mail."
    ),
    "Could not create your account. Please try again.": "Nie udało się utworzyć konta. Spróbuj ponownie.",
    "Product name": "Nazwa produktu",
    "Product type": "Typ produktu",
    "Stock quantity": "Stan magazynowy",
    "Stock status": "Status magazynu",
    "Regular price": "Cena regularna",
    "Sale price": "Cena promocyjna",
    "Categories": "Kategorie",
    "Weight": "Waga",
    "Products": "Produkty",
    "Only products with managed stock": "Tylko produkty z zarządzanym stanem",
    "Stock filter": "Filtr stanu",
    "No filter": "Brak filtra",
    "Stock <=": "Stan <=",
    "Stock >=": "Stan >=",
    "Stock =": "Stan =",
    "Variations": "Warianty",
    "Include product variations": "Uwzględnij warianty produktów",
    "Preview on screen": "Podgląd na ekranie",
    "Stock Export Preview": "Podgląd eksportu stanu",
    "Products found": "Znalezione produkty",
    "Preview limited to 500 rows. Use CSV export for full data.": (
        "Podgląd ograniczony do 500 wierszy. Pełne dane pobierz przez eksport CSV."
    ),
    "Back to export": "Powrót do eksportu",
    "Not managed": "Bez zarządzania",
    "ID": "ID",
    "Name": "Nazwa",
    "Stock": "Stan",
    "Regular Price": "Cena regularna",
    "Sale Price": "Cena promocyjna",
    "Filter settings": "Ustawienia filtrów",
    "Show stock": "Pokaż stan",
    "Show sale": "Pokaż promocję",
    "Polski AJAX Filters": "Polski AJAX Filters",
    "Dynamic product filters rendered on the frontend.": (
        "Dynamiczne filtry produktów renderowane po stronie witryny."
    ),
    "Search settings": "Ustawienia wyszukiwarki",
    "Search label": "Etykieta wyszukiwania",
    "Show submit button": "Pokaż przycisk wysłania",
    "Polski AJAX Search": "Polski AJAX Search",
    "Dynamic product search form rendered on the frontend.": (
        "Dynamiczny formularz wyszukiwania produktów renderowany po stronie witryny."
    ),
    "This field is required.": "To pole jest wymagane.",
    "Slider settings": "Ustawienia suwaka",
    "Source": "Źródło",
    "Upsell products": "Produkty upsell",
    "Sale products": "Produkty w promocji",
    "Featured products": "Polecane produkty",
    "Optional for related and upsell sliders outside product templates.": (
        "Opcjonalnie dla powiązanych i upsell poza szablonami produktów."
    ),
    "Product limit": "Limit produktów",
    "Show add to cart": "Pokaż dodaj do koszyka",
    "Polski Product Slider": "Polski Product Slider",
    "Dynamic merchandising slider rendered on the frontend.": (
        "Dynamiczny suwak merchandisingowy renderowany po stronie witryny."
    ),
    "%1$s kupił(a) %2$s": "%1$s kupił(a) %2$s",
    "%d min temu": "%d min temu",
    "%d godz. temu": "%d godz. temu",
    "Pytania i odpowiedzi": "Pytania i odpowiedzi",
    "Zadaj pytanie o ten produkt...": "Zadaj pytanie o ten produkt...",
    "Zadaj pytanie": "Zadaj pytanie",
    "Odpowiedz": "Odpowiedz",
    "Brak pytań. Zadaj pierwsze pytanie!": "Brak pytań. Zadaj pierwsze pytanie!",
    "Historia cen": "Historia cen",
    "Najniższa": "Najniższa",
    "Najwyższa": "Najwyższa",
    "Często zadawane pytania": "Często zadawane pytania",
    "Minimum number of characters before search starts. Recommended: 2-3": (
        "Minimalna liczba znaków przed rozpoczęciem wyszukiwania. Zalecane: 2–3"
    ),
    "Maximum number of products shown in dropdown. Recommended: 4-8": (
        "Maksymalna liczba produktów na liście rozwijanej. Zalecane: 4–8"
    ),
    "Delay in milliseconds before sending search request. Lower = faster but more server load": (
        "Opóźnienie w milisekundach przed wysłaniem zapytania. Niżej = szybciej, ale większe obciążenie serwera"
    ),
    "How many product attributes to show as filter dropdowns": (
        "Ile atrybutów produktów pokazać jako listy rozwijane filtrów"
    ),
    "Number of product columns in grid layout. Recommended: 3-4": (
        "Liczba kolumn produktów w siatce. Zalecane: 3–4"
    ),
    "Maximum products that can be compared side-by-side. Recommended: 3-5": (
        "Maksymalna liczba produktów do porównania obok siebie. Zalecane: 3–5"
    ),
    'Products published within this many days will show the "New" badge': (
        'Produkty opublikowane w ciągu tylu dni otrzymają odznakę „Nowość”'
    ),
    'Badge appears when stock quantity is at or below this number': (
        "Odznaka pojawia się, gdy stan magazynowy jest na tym poziomie lub niższy"
    ),
    'Minimum total sales count for the "Bestseller" badge to appear': (
        'Minimalna łączna liczba sprzedaży, aby pojawiła się odznaka „Bestseller”'
    ),
    "Lower number = appears earlier. WooCommerce default: Description=10, Reviews=30": (
        "Niższa liczba = wcześniejsza pozycja. Domyślnie WooCommerce: opis=10, recenzje=30"
    ),
    "Magnification factor on hover. 1.0 = no zoom, 2.0 = double size": (
        "Powiększenie po najechaniu. 1,0 = brak, 2,0 = podwójny rozmiar"
    ),
    "0 = unlimited. After this many pages, auto-loading stops and shows a manual button": (
        "0 = bez limitu. Po tylu stronach autoładowanie się kończy i pojawia się przycisk ręczny"
    ),
    "How long to wait after page load before showing the popup": (
        "Jak długo czekać po załadowaniu strony przed pokazaniem wyskakującego okna"
    ),
    "After closing, the popup will not appear again for this many days (uses cookie)": (
        "Po zamknięciu okno nie pojawi się ponownie przez tyle dni (plik cookie)"
    ),
    "Send SKU instead of WooCommerce product ID in ecommerce events": (
        "Wysyłaj SKU zamiast ID produktu WooCommerce w zdarzeniach e-commerce"
    ),
    "From Google Cloud Console > APIs & Services > Credentials": (
        "Z Google Cloud Console > Interfejsy API i usługi > Dane logowania"
    ),
    "OAuth 2.0 Client Secret from the same credential": (
        "Tajny klient OAuth 2.0 z tego samego zestawu danych logowania"
    ),
    "From Meta for Developers > App Dashboard > Settings > Basic": (
        "Z Meta for Developers > Panel aplikacji > Ustawienia > Podstawowe"
    ),
    "App Secret from the same page": "App Secret z tej samej strony",
    "Create WooCommerce accounts automatically on first social login": (
        "Twórz konta WooCommerce automatycznie przy pierwszym logowaniu społecznościowym"
    ),
    "Social Proof Notifications": "Powiadomienia social proof",
    "Floating purchase notifications showing recent orders (\"Jan from Warszawa just bought...\"). Proven to increase conversions by 10-15%. Privacy-aware, AJAX-loaded, configurable position and timing.": (
        "Pływające powiadomienia o zakupach z ostatnich zamówień („Jan z Warszawa właśnie kupił…”). "
        "Zwiększają konwersje o ok. 10–15%. Prywatność, AJAX, konfigurowalna pozycja i czas."
    ),
    "Interval between popups (seconds)": "Interwał między oknami (sekundy)",
    "Time between showing consecutive notifications. Recommended: 6-12": (
        "Czas między kolejnymi powiadomieniami. Zalecane: 6–12"
    ),
    "Display duration (seconds)": "Czas wyświetlania (sekundy)",
    "How long each notification stays visible. Recommended: 4-6": (
        "Jak długo widoczne jest każde powiadomienie. Zalecane: 4–6"
    ),
    "Anonymize customer names": "Anonimizuj imiona klientów",
    "Shows \"J. from Warszawa\" instead of full names. Recommended for GDPR": (
        "Pokazuje „J. z Warszawy” zamiast pełnych imion. Zalecane pod RODO"
    ),
    "Hide on mobile devices": "Ukryj na urządzeniach mobilnych",
    "Disable on small screens to avoid obstructing content": (
        "Wyłącz na małych ekranach, aby nie zasłaniać treści"
    ),
    "Product Q&A": "Pytania i odpowiedzi o produkcie",
    "Amazon-style questions and answers on product pages. Customers ask, anyone answers. Admin email notifications, answer voting, Schema.org QAPage markup for SEO.": (
        "Pytania i odpowiedzi na stronach produktów w stylu Amazon. Klienci pytają, każdy może odpowiedzieć. "
        "Powiadomienia e-mail do administratora, głosowanie na odpowiedzi, znaczniki Schema.org QAPage pod SEO."
    ),
    "Trust Badges": "Znaki zaufania",
    "Configurable trust signals on product, cart, and checkout pages: secure payment, fast delivery, returns, quality guarantee. Pure CSS + inline SVG for zero performance impact.": (
        "Konfigurowalne sygnały zaufania na stronach produktu, koszyka i zamówienia: bezpieczna płatność, "
        "szybka dostawa, zwroty, gwarancja jakości. Czysty CSS + inline SVG bez wpływu na wydajność."
    ),
    "Show on cart": "Pokaż w koszyku",
    "Show on checkout": "Pokaż przy zamówieniu",
    "Live Cart Sidebar": "Boczny koszyk na żywo",
    "Slide-in cart drawer that opens when a product is added to cart. Shows cart items, subtotal, free shipping progress bar, and quick checkout link. No page reload needed.": (
        "Wysuwany panel koszyka po dodaniu produktu. Pokazuje pozycje, sumę częściową, pasek darmowej dostawy "
        "i link do szybkiego zamówienia. Bez przeładowania strony."
    ),
    "Auto-open on add to cart": "Otwórz automatycznie po dodaniu do koszyka",
    "Show subtotal": "Pokaż sumę częściową",
    "Show free shipping progress": "Pokaż postęp darmowej dostawy",
    "Free shipping threshold": "Próg darmowej dostawy",
    "Set to 0 to disable progress bar": "Ustaw 0, aby wyłączyć pasek postępu",
    "Panel position": "Pozycja panelu",
    "Right": "Prawa",
    "Left": "Lewa",
    "Show background overlay": "Pokaż przyciemnienie tła",
    "Price History Chart": "Wykres historii cen",
    "Visual SVG sparkline showing price trends over 30/90/180 days on product pages. Uses Omnibus price data. Shows lowest/highest prices. Increases trust and Omnibus transparency.": (
        "Wizualna krzywa SVG z trendem cen z ostatnich 30/90/180 dni na stronach produktów. Wykorzystuje dane cen Omnibus. "
        "Pokazuje najniższe/najwyższe ceny. Buduje zaufanie i przejrzystość Omnibus."
    ),
    "History period (days)": "Okres historii (dni)",
    "Show price data from the last N days. Options: 30, 90, or 180": (
        "Dane cen z ostatnich N dni. Opcje: 30, 90 lub 180"
    ),
    "Show min/max prices": "Pokaż ceny min./maks.",
    "Line color": "Kolor linii",
    "CSS hex color for the chart line, e.g. #0369a1": (
        "Kolor szesnastkowy CSS linii wykresu, np. #0369a1"
    ),
    "Free shipping": "Darmowa dostawa",
    "Out of stock": "Brak w magazynie",
    "Close": "Zamknij",
    "Your cart is empty": "Twój koszyk jest pusty",
    "Free shipping from": "Darmowa dostawa od",
    "You qualify for free shipping!": "Kwalifikujesz się do darmowej dostawy!",
    "Add %s for free shipping": "Dodaj %s, aby uzyskać darmową dostawę",
    "Price history (%d days)": "Historia cen (%d dni)",
    "Lowest": "Najniższa",
    "Highest": "Najwyższa",
    "Questions (%d)": "Pytania (%d)",
    "Store": "Sklep",
    "Helpful": "Pomocne",
    "Write an answer...": "Napisz odpowiedź...",
    "Answer": "Odpowiedź",
    "Ask a question": "Zadaj pytanie",
    "Your question about this product...": "Twoje pytanie o ten produkt...",
    "Ask": "Zapytaj",
    "Customer": "Klient",
    "New question about: %s": "Nowe pytanie o: %s",
    "Asked by: %s": "Zadane przez: %s",
    "Answer this question": "Odpowiedz na to pytanie",
    "We still want to hear from you, {first_name}!": "Nadal chcemy usłyszeć od Ciebie, {first_name}!",
    "Hi {first_name}, you have not yet reviewed your recent purchase. Your opinion helps other customers and helps us improve.": (
        "Cześć {first_name}, nie oceniłeś jeszcze ostatniego zakupu. Twoja opinia pomaga innym klientom i nam się rozwijać."
    ),
    "just now": "przed chwilą",
    "Secure payment": "Bezpieczna płatność",
}

# Plural entries (Polish: 3 forms). Keys: (msgid, msgid_plural)
PLURAL_MSGSTR: dict[tuple[str, str], tuple[str, str, str]] = {
    ("%d minute ago", "%d minutes ago"): (
        "%d minutę temu",
        "%d minuty temu",
        "%d minut temu",
    ),
    ("%d hour ago", "%d hours ago"): (
        "%d godzinę temu",
        "%d godziny temu",
        "%d godzin temu",
    ),
}


def main() -> int:
    po = polib.pofile(str(PO_PATH))
    missing_singular: list[str] = []
    missing_plural: list[tuple[str, str]] = []
    updated = 0

    for entry in po:
        if entry.translated() or not entry.msgid:
            continue
        if entry.msgid_plural:
            key = (entry.msgid, entry.msgid_plural)
            if key not in PLURAL_MSGSTR:
                missing_plural.append(key)
                continue
            s0, s1, s2 = PLURAL_MSGSTR[key]
            entry.msgstr_plural[0] = s0
            entry.msgstr_plural[1] = s1
            entry.msgstr_plural[2] = s2
            updated += 1
            continue
        if entry.msgid not in SINGULAR:
            missing_singular.append(entry.msgid)
            continue
        entry.msgstr = SINGULAR[entry.msgid]
        updated += 1

    if missing_singular:
        print("Missing SINGULAR translations:", len(missing_singular), file=sys.stderr)
        for m in missing_singular[:20]:
            print(repr(m)[:200], file=sys.stderr)
        return 1
    if missing_plural:
        print("Missing PLURAL translations:", missing_plural, file=sys.stderr)
        return 1

    po.save()
    print(f"Updated {updated} entries in {PO_PATH}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
