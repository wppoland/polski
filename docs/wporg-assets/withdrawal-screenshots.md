# Withdrawal module - screenshot capture specs

Eight additional screenshots for the wp.org listing, covering the new withdrawal
flow added in FREE 1.16.0 and PRO 1.14.0. Capture in Polish UI at 1280×800 or
1440×900 (Retina ×2 preferred), PNG, no browser chrome, no admin sidebar
overlap unless documenting an admin screen.

For each screenshot the "Setup" describes the state to reproduce in wp-env;
"Capture" notes what the frame should contain; "Caption" is the suggested
`readme.txt` line.

## screenshot-9 - withdrawal-lookup-form

- **Setup**: publish a page at `/odstapienie/` containing the
  `[polski_withdrawal_lookup]` shortcode. Point
  `polski_withdrawal.lookup_page_id` at it. Set `polski_general.company_name`
  to a recognisable PL shop name.
- **Capture**: the full lookup section including the 200-word intro, both
  labelled fields, the inline help, and the submit button "Wyślij link do
  formularza".
- **Caption**: "Formularz odstąpienia dla gości - autoryzacja e-mailem + numerem zamówienia, bezpieczny link jednorazowy."

## screenshot-10 - withdrawal-two-step-form

- **Setup**: log in as a customer who has an order in `wc-completed`. Open
  My Account › Orders and click "Withdraw from contract" on that order.
- **Capture**: the items table with quantity inputs (showing partial-zwrot is
  possible), Step 1/Step 2 headings, reason textarea, "Złóż oświadczenie i
  wyślij potwierdzenie na e-mail" submit.
- **Caption**: "Dwustopniowy formularz odstąpienia z wyborem pozycji i ilości - możliwy częściowy zwrot."

## screenshot-11 - withdrawal-admin-list

- **Setup**: seed two or three withdrawal requests (one online, one manual,
  one rejected). Open Polski › Withdrawals.
- **Capture**: list table with filter dropdown set to "All", showing ID,
  Order, Channel, Status, Reason, Filed at columns; "Register manually"
  action visible in the header.
- **Caption**: "Panel administratora - lista odstąpień z kanałami online / telefon / e-mail / list / w sklepie."

## screenshot-12 - withdrawal-admin-manual

- **Setup**: open Polski › Withdrawals › Register withdrawal.
- **Capture**: the manual-registration form with order ID, channel select
  expanded showing all options, and reason textarea.
- **Caption**: "Rejestracja odstąpień otrzymanych poza sklepem - telefonicznie, mailowo, listownie."

## screenshot-13 - withdrawal-settings

- **Setup**: open Polski › Withdrawal settings. Have at least one trigger
  status checked, lookup page configured, digital-consent mode set to
  "Optional", and the bundle refund mode visible.
- **Capture**: top of the page through the "Refund handling (Pro)" section,
  showing the section headings.
- **Caption**: "Wszystkie ustawienia odstąpień w jednym miejscu - termin, statusy uruchamiające, zgody dla treści cyfrowych, integracje Pro."

## screenshot-14 - withdrawal-product-exemption

- **Setup**: open a product edit screen with the Polski exemption field
  visible (e.g. a tee-shirt category configured as exempt).
- **Capture**: the "Polski" tab on the product editor showing the
  "Exclude from withdrawal" checkbox plus the Art. 38 reason dropdown
  expanded.
- **Caption**: "Wykluczenia produktów z prawa odstąpienia - gotowe powody z Art. 38 (na zamówienie, krótki termin, zapieczętowane itd.)."

## screenshot-15 - withdrawal-category-exemption (PRO)

- **Setup**: open Products › Categories, edit any category and scroll to the
  Polski section.
- **Capture**: the category-level "Wyklucz z prawa odstąpienia" checkbox plus
  reason dropdown - clearly distinct from the product-level meta box.
- **Caption**: "Wykluczenia per kategoria - jeden checkbox dla całego asortymentu zamiast setek produktów (PRO)."

## screenshot-16 - withdrawal-reports-dashboard (PRO)

- **Setup**: seed audit log data so the scorecards (filed, completed, refund
  volume) have non-zero values. Open Polski › Withdrawal reports.
- **Capture**: the scorecard grid (6 cards) and the Top reasons table.
- **Caption**: "Dashboard raportowy - liczba odstąpień, średni czas obsługi, wolumen zwrotów, powody (PRO)."

## Source artefacts

The HTML/data behind every screenshot lives in the plugin already; the
acceptance criteria for "correct screenshot" are codified in
`tests/E2E/withdrawal-guest-flow.spec.ts` and the admin tests added in this
batch. A future automated capture run (Playwright `page.screenshot`) can
re-emit the PNGs from those specs without manual intervention.
