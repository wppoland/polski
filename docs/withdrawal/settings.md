# Settings reference

All withdrawal settings live in a single WordPress option: `polski_withdrawal`.
The centralised admin page (`Polski > Withdrawal settings`) writes to it; you
can also set values programmatically:

```php
update_option( 'polski_withdrawal', array_merge( get_option( 'polski_withdrawal', [] ), [
    'period_days' => 30,
    'trigger_statuses' => [ 'completed', 'shipped' ],
] ) );
```

## Keys

| Key | Default | Used by | Description |
|---|---|---|---|
| `period_days` | `14` | `WithdrawalService`, `AnnexGeneratorService` | Number of days the consumer has to file a declaration after the clock starts. |
| `trigger_statuses` | `[ 'completed' ]` | `WithdrawalService::maybeStampClockStart` | WC order statuses (without the `wc-` prefix) that start the countdown. |
| `lookup_page_id` | `0` | `GuestWithdrawalService` | Page hosting `[polski_withdrawal_lookup]`. Required for guest flow + magic-link emails. |
| `my_account_endpoint_slug` | `polski-withdrawals` | `MyAccountWithdrawalsService` | URL slug of the My Account tab. Flush permalinks after change. |
| `digital_consent_mode` | `optional` | `DigitalConsentService` | `required` / `optional` / `hidden`. Drives the Art. 16(m) checkbox at checkout. |
| `digital_consent_label` | (PL default) | `DigitalConsentService` | Wording shown next to the checkbox. |
| `digital_download_verification` | `''` | `DigitalDownloadVerifier` (PRO) | When `'1'`, restores withdrawal eligibility for digital orders if no file was downloaded. |
| `annex_locale` | `''` (auto from site locale) | `AnnexMultiLanguageService` (PRO) | Force a specific Annex I(B) language. |
| `bundle_refund_mode` | `whole_bundle` | `BundleRefundCompat` (PRO) | `whole_bundle` / `proportional` / `remove_discount`. |
| `email_durable_medium_notice` | (PL default) | confirmation email templates | Footer text reminding the buyer the email is the "durable medium" record. |
| `requested_order_note` | (auto) | `WithdrawalService::createRequest` | Note appended to the order on filing. |
| `confirmed_order_note` | (auto) | `WithdrawalService::confirm` | Note appended on confirmation. |
| `form_title`, `form_intro_text` | (PL defaults) | `withdrawal-form.php` | Customer-facing form copy. |
| `button_text`, `oneclick_button_text` | (auto) | `WithdrawalService::addWithdrawalAction` | "Withdraw" action button text on the order list. |

## Per-product / per-category meta

| Meta | Type | Used by |
|---|---|---|
| `_polski_withdrawal_exempt` | post meta (`yes` / empty) | `WithdrawalExemptionService::isProductExempt` |
| `_polski_withdrawal_exempt_reason` | post meta (enum value from `WithdrawalExemptionReason`) | `WithdrawalExemptionService::getProductExemptionReason` |
| `_polski_withdrawal_exempt_reason_custom` | post meta (free text) | as above, used when reason = `custom` |
| `polski_withdrawal_exempt` | term meta on `product_cat` | category-level exemption |
| `polski_withdrawal_exempt_reason` | term meta | category-level reason |
| `polski_withdrawal_exempt_reason_custom` | term meta | category-level free-text reason |

## Per-order meta

| Meta | Set by | Read by |
|---|---|---|
| `_polski_withdrawal_clock_start` | `WithdrawalService::maybeStampClockStart` | `WithdrawalService::getClockStart` |
| `_polski_digital_consent` | `DigitalConsentService::persistConsent` | `DigitalConsentService::filterEligibility`; included in audit |
| `_polski_withdrawal_pdf` | `WithdrawalPdfService::ensurePdf` (PRO) | email attachment + admin download |
