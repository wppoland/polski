# Manual testing harness — withdrawal flow

Three runnable protocols for the manual validation that can't be automated
in the unit/E2E suites:

1. **Screen reader test** (NVDA on Windows, VoiceOver on macOS) — 30 min
2. **Lighthouse + axe live audit** — 15 min
3. **User testing with 5 personas** — 60–90 min per session

Each protocol lists the exact steps, expected outputs and a pass/fail
checkpoint. Run against `wp-env` (`npm run env:start`) with a seeded
`/odstapienie/` page (lookup shortcode), one `wc-completed` order for the
`customer` user, and one product with the exemption meta box visible.

---

## 1. Screen reader test (30 min)

### NVDA (Windows)

Install NVDA 2025.1 from <https://www.nvaccess.org/>. Start before opening
the browser so it picks up everything.

**Test 1 — Lookup page (5 min)**

1. Navigate to `http://localhost:8888/odstapienie/`
2. Press `H` to jump heading-to-heading. **Expected**:
   - "Odstąpienie od umowy — formularz online" (h2, the only h2 in the section)
   - "Najczęstsze pytania" (h3 lower on the page)
3. Press `F` to jump form-by-form. **Expected**: lands on the lookup form. NVDA announces "form, edit, Numer zamówienia, required".
4. Press `Tab` from the order-number field. **Expected** announcements (in order):
   - "Numer zamówienia, edit, required, Numer znajdziesz w e-mailu potwierdzającym zakup..." (label + aria-describedby help)
   - "Adres e-mail użyty przy zakupie, edit, required, Wyślemy na ten adres bezpieczny link..."
   - "Wyślij link do formularza, button"
5. Press `D` to jump landmark-to-landmark. **Expected**: section with aria-labelledby is announced as "Odstąpienie od umowy — formularz online region".
6. Submit empty form. NVDA must read the error notice without keyboard intervention:
   **Expected**: "Wpisz numer zamówienia (znajdziesz go w e-mailu potwierdzającym) i adres e-mail użyty przy zakupie."

**Pass criteria:** every announcement above heard, no double-announcements,
no "blank" or "unlabeled" announcements.

**Test 2 — Two-step My Account form (10 min)**

1. Log in as `customer` / `password`, navigate to `Moje konto › Zamówienia`,
   activate "Withdraw from contract" on the seeded order.
2. NVDA must announce the page heading "Wniosek o odstąpienie od umowy"
   followed by the intro paragraph.
3. Tab through to the items table. **Expected**: NVDA reads:
   "Pozycje zamówienia dostępne do odstąpienia, table". Then `Ctrl+Alt+Right`
   to navigate cells. Each row first column announces: "Pozycja, label, Product name, strong".
4. Reach a qty input. **Expected**: "Liczba sztuk do zwrotu, edit, spin button,
   2 of 4". (The 2/4 comes from min/max + current value.)
5. Change qty and listen for the live counter announcement: "Wybrano łącznie X sztuk".
6. Click "Wybierz wszystkie pozycje" (button). **Expected**: live counter
   re-announces immediately with the new total.
7. Tab to submit. **Expected**: "Złóż oświadczenie i wyślij potwierdzenie na e-mail, button".

**Pass criteria:** spinbutton role, live counter announces on change, table
caption read once at the start.

**Test 3 — Guest form after magic link (5 min)**

1. Visit `/odstapienie/?polski_wt=DUMMY` (use a valid token from a test request).
2. Heading "Odstąpienie od umowy — zamówienie #1001" announced.
3. The order summary table must be announced row-by-row with column headers
   ("Produkt", "Ilość", "Wartość"). The merchant has a chance to verify
   exactly what is being withdrawn — critical for cognitive accessibility
   (Bovelett clarity-dividend).
4. Tab to reason textarea, then submit.

**Pass criteria:** order summary is fully readable before the submit;
success message reads the declaration id `POL-WD-NNNNNN` as a single token.

**Test 4 — Settings page (10 min)**

1. Log in as `admin` / `password`, visit `Polski › Withdrawal settings`.
2. Each settings section heading should be announced as h2/h3.
3. The trigger-statuses checkbox group must be announced as a fieldset.
4. Save button at the bottom must be reachable via Tab.

### VoiceOver (macOS)

Repeat the four scenarios using VoiceOver (`Cmd+F5` to toggle). Notes
specific to VO:

- VO+H lists headings; verify section title is reached.
- VO+J lists form controls; expect 2 fields + 1 button on the lookup.
- "Group" instead of "region" is announced for the section landmark.
- Live regions are echoed with the "polite" priority.

---

## 2. Lighthouse + axe live audit (15 min)

### axe DevTools

1. Install the Chrome extension from the Chrome Web Store.
2. Open `/odstapienie/`, open DevTools, click the **axe** tab, then
   "Scan ALL of my page".
3. Expected result: **0 critical, 0 serious, ≤2 moderate** (moderate may
   come from the host theme; mark them out-of-scope if so).
4. Capture a JSON report (`Save as JSON` button) and store at
   `docs/wporg-assets/a11y-report-lookup.json`.
5. Repeat for the two-step form and settings page.

### Lighthouse

1. Open `/odstapienie/` in incognito Chrome (no extensions).
2. DevTools → Lighthouse → check Accessibility, Performance, Best Practices,
   SEO. Mobile profile, simulated throttling.
3. Expected scores: **Accessibility ≥ 95**, **Best Practices ≥ 90**,
   **SEO ≥ 90**, **Performance ≥ 70** (depends on host theme).
4. Save the report (`Save as HTML`) under
   `docs/wporg-assets/lighthouse-lookup.html`.

---

## 3. User testing with 5 personas (60–90 min per session)

Find five testers matching these personas. Pay €30/session via PayPal or
similar to incentivise honest feedback.

| # | Persona | Device | Tech proficiency | Task |
|---|---|---|---|---|
| 1 | 55-letnia okazjonalna kupująca (woj. lubelskie) | iPhone 13 | low | Złożyć oświadczenie jako gość po e-mailu od sklepu |
| 2 | 28-letni IT specialist (Warszawa) | MacBook | high | Częściowy zwrot 1 z 3 produktów z konta klienta |
| 3 | 67-letni emeryt (Kraków, czyta okulary) | Tablet Samsung | low | Złożyć oświadczenie i wydrukować formularz Annex I(B) |
| 4 | Sklep e-commerce ops manager | Desktop Windows | medium | Zarejestrować odstąpienie otrzymane telefonicznie w admin |
| 5 | NVDA user (PFRON) | Windows + NVDA | medium (screen reader) | Złożyć oświadczenie jako gość bez logowania |

**Protocol per session:**

1. **Intro (5 min):** "Sklep odebrał Twoje zamówienie #1001 na trzy
   produkty. Zmieniłeś zdanie i chcesz zwrócić jeden produkt. Pokaż mi,
   jak to zrobisz — myśl na głos."
2. **Task (15–30 min):** observer not interferes. Track: completion (yes/no),
   time-on-task, click count, error count, frustration cues.
3. **Debrief (15 min):** SEQ scale ("How easy was this?", 1–7), three
   "what would you change", verbatim quotes.

**Metrics to track in a shared sheet:**

- Task completion rate (target: ≥80%)
- Median time-on-task (target: ≤4 min for tasks 1,2,4 / ≤8 min for 3)
- SEQ score (target: ≥5.5/7)
- Number of failures requiring researcher intervention (target: 0)

**Output:** synthesis doc at `docs/withdrawal/user-test-results-YYYY-MM-DD.md`
covering the 3 most cited issues plus the actual quotes.

---

## Pass/fail summary template

Use this template after running all three protocols:

```markdown
# Withdrawal — manual validation results (YYYY-MM-DD)

## Screen reader
- NVDA: ✅ / ❌ — notes
- VoiceOver: ✅ / ❌ — notes
- Critical issues: [list]

## Automated a11y
- axe (lookup): N violations (N critical, N serious)
- axe (two-step): N violations
- axe (settings): N violations
- Lighthouse a11y: N / 100
- Lighthouse performance: N / 100

## User testing
- Completion rate: X / 5
- Median SEQ: X.X / 7
- Top issues:
  1. ...
  2. ...
  3. ...

## Next actions
- [P0] ...
- [P1] ...
```

Store next to the JSON / HTML reports in `docs/wporg-assets/`.
