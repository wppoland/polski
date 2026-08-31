# DRAFT (nie wysłane): odpowiedź na forum wp.org

Wątek: Problem z odstąpieniem od umowy
Adres: wordpress.org/support/topic/problem-z-odstapieniem-od-umowy/
Adresat: @murbaniak
Odpowiada na: post 8 (brak e-maila potwierdzającego) i post 9 (formularz dla gości)
Status: DRAFT, do wysłania przez Mariusza

---

Dzień dobry,

**Brak e-maila potwierdzającego: naprawione w 1.29.7.**

Przyczyna była inna niż poprzednie dwa błędy. WooCommerce tworzy obiekty e-maili dopiero wtedy, gdy coś o nie poprosi, czyli przy wywołaniu `WC()->mailer()`. Przy zgłoszeniu odstąpienia ze sklepu nic o nie nie prosiło, więc klasa e-maila potwierdzającego nigdy nie powstawała, a razem z nią nie powstawał listener podpięty do akcji `polski/withdrawal/requested`. Oświadczenie zapisywało się poprawnie, tylko wysyłka nie miała czego uruchomić. Dlatego 1.29.5 tego nie zamknęło.

Od 1.29.7 mailer jest ładowany z priorytetem 1 na akcjach odstąpienia, zanim zadziałają ich własne handlery na priorytecie 10. To samo obejmuje formularz dla gościa, endpoint REST, e-mail double opt-in oraz powiadomienia o przyjęciu i odrzuceniu.

Proszę zaktualizować do 1.29.7 i napisać, czy potwierdzenie dociera.

**Formularz odstąpienia dla gości: tak, jest.**

Shortcode: `[polski_withdrawal_lookup]`

Wstaw go na dowolnej stronie. Działa dwuetapowo:

1. Kupujący podaje numer zamówienia i adres e-mail użyty przy zakupie.
2. Na ten adres idzie jednorazowy link ważny 30 minut, a po kliknięciu na tej samej stronie pojawia się formularz odstąpienia powiązany z tym zamówieniem.

Token jest jednorazowy i przechowywany w postaci skrótu, obowiązuje limit 5 prób na 15 minut. Formularz odpowiada tym samym komunikatem niezależnie od tego, czy zamówienie istnieje, żeby nie zdradzać danych o zamówieniach.

Pozdrawiam
