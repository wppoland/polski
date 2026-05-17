# Withdrawal module

End-to-end implementation of the consumer right of withdrawal flow required by
Directive 2011/83/EU as amended by Directive 2023/2673 (Article 11a). Spread
across the FREE plugin (core flow) and the PRO plugin (refund, PDF, audit,
reporting, multi-language Annex, integrations).

## FREE plugin (`polski/`)

| Concern | Class |
|---|---|
| Eligibility, request creation, lifecycle | `Polski\Service\WithdrawalService` |
| Custom order statuses (`wc-withdrawal-requested`, `-partial`, `-completed`) | `Polski\Service\WithdrawalOrderStatusService` |
| Guest flow (email + order number → magic-link) | `Polski\Service\GuestWithdrawalService` |
| Annex I(A) + I(B) generator (PL) | `Polski\Service\AnnexGeneratorService` |
| Per-product + per-category exemptions (Art. 38 pkt 3–13) | `Polski\Service\WithdrawalExemptionService` + `Polski\Enum\WithdrawalExemptionReason` |
| Art. 16(m) digital consent (required / optional / hidden) | `Polski\Service\DigitalConsentService` |
| "Moje odstąpienia" My Account tab | `Polski\Service\MyAccountWithdrawalsService` |
| Admin manual registration (phone / e-mail / letter / in-store) | `Polski\Admin\WithdrawalsAdminPage` |
| Centralised settings page | `Polski\Admin\WithdrawalSettingsPage` |
| Dynamic Gutenberg blocks (lookup, info, form) | `Polski\Service\WithdrawalBlocksService` |
| WP 6.9+ Abilities API surface | `Polski\Service\AbilitiesService` |
| Storage (HPOS-safe custom tables, no postmeta bloat) | `Polski\Repository\WithdrawalRepository` + `Polski\Repository\WithdrawalItemsRepository` |
| Schema | `Polski\Migration\Migration_2_2_0` |

### Public extension points

```php
// Eligibility
apply_filters( 'polski/withdrawal/eligible',          $bool, $order );
apply_filters( 'polski/withdrawal/period_days',       14 );
apply_filters( 'polski/withdrawal/trigger_statuses',  [ 'completed' ] );
apply_filters( 'polski/withdrawal/order_status_on_request',  $slug, $order, $request );
apply_filters( 'polski/withdrawal/order_status_on_complete', $slug, $order, $request );

// Annex generator
apply_filters( 'polski/annex/info_html',  $html, $merchantData, $days );
apply_filters( 'polski/annex/form_html',  $html, $merchantData, $lookupUrl );
apply_filters( 'polski/annex/merchant_data', $merchantData );
apply_filters( 'polski/annex/locale', 'pl' );

// Lifecycle events
do_action( 'polski/withdrawal/requested',         WithdrawalRequest );
do_action( 'polski/withdrawal/guest_requested',   int $id, WC_Order, string $email );
do_action( 'polski/withdrawal/manual_registered', int $id, WC_Order, string $channel );
do_action( 'polski/withdrawal/confirmed',         WithdrawalRequest );
do_action( 'polski/withdrawal/completed',         WithdrawalRequest );
do_action( 'polski/withdrawal/rejected',          WithdrawalRequest );
```

### Shortcodes & blocks

| Shortcode | Block |
|---|---|
| `[polski_withdrawal_lookup]` | `polski/withdrawal-lookup` |
| `[polski_withdrawal_info]` | `polski/withdrawal-info` |
| `[polski_withdrawal_form_template]` | `polski/withdrawal-form` |

## PRO plugin (`polski-pro/`)

| Concern | Class |
|---|---|
| A4 PDF declaration ("durable medium") | `Polski\Pro\Service\WithdrawalPdfGenerator` + `WithdrawalPdfService` |
| Operator-confirmed refund builder | `Polski\Pro\Service\WithdrawalRefundService` |
| Download verification (Art. 16m relaxation) | `Polski\Pro\Service\DigitalDownloadVerifier` |
| WooCommerce Subscriptions (proportional + cancel) | `Polski\Pro\Compatibility\SubscriptionsCompat` |
| Product Bundles refund strategy | `Polski\Pro\Service\BundleRefundCompat` |
| Annex I(B) DE/AT/FR/NL/IT/ES/EU | `Polski\Pro\Service\AnnexMultiLanguageService` |
| Audit log + CSV export | `Polski\Pro\Service\WithdrawalAuditService` + `Polski\Pro\Admin\WithdrawalAuditAdminPage` |
| Reporting dashboard | `Polski\Pro\Service\WithdrawalReportingService` + `Polski\Pro\Admin\WithdrawalReportsAdminPage` |
| PRO Abilities API | `Polski\Pro\Service\ProAbilitiesService` |
| Schema | `Polski\Pro\Migration\Migration_2_5_0` |

### PRO extension points

```php
apply_filters( 'polski_pro/withdrawal/refund_payload',  $payload, WC_Order, $withdrawalId );
apply_filters( 'polski_pro/withdrawal/download_verification_enabled', $bool );
apply_filters( 'polski_pro/withdrawal/bundle_refund_mode', 'whole_bundle' ); // | proportional | remove_discount
apply_filters( 'polski_pro/subscriptions/treat_as_exempt', false, WC_Order );

do_action( 'polski_pro/withdrawal/refund_processed', $withdrawalId, WC_Order_Refund );
do_action( 'polski/pro/withdrawal/pdf_generated',    $withdrawalId, string $filepath );
```

## Abilities (WP 6.9+ Abilities API)

20 abilities across 5 categories. See [abilities.md](abilities.md).

## Settings

All values live in the `polski_withdrawal` option. See [settings.md](settings.md)
for the keys, defaults, and where each is consumed.
