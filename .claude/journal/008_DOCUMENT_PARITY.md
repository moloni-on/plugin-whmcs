# 2026-07-03 — Document-creation parity with the legacy/WooCommerce plugins

Closed several gaps between `DocumentService::createDocumentFromOrder()` and the two
reference plugins (`[whmcs_moloni_pt]` classic REST, `[wordpress_moloni_on]` same GraphQL
API). The WordPress plugin was used as the authoritative source for Moloni ON `*Insert`
field names. Currency exchange remains **out of scope** (still the open P1 item).

## Line references from WHMCS line type (earlier, folded in here)
`LineMapper` (renamed from `ItemReferenceResolver`) maps a `tblinvoiceitems` row to
`name / reference / summary / discount / skip`. The reference is derived from the line
`type` (+ `relid`) so equivalent items reuse one Moloni product: `DomainRegister → REG-<TLD>`,
`Domain → REN-<TLD>`, `DomainTransfer → T-<TLD>`, `Hosting → Alojamento`,
`Upgrade → UPGRADE`, `Setup → TAX-INSTALL`, `AddFunds/LateFee → ADD-FUNDS/LATE-FEE`,
`Item/'' → 9999`. Unknown types fall back to a name-derived reference.

## Mass-payment skip
A WHMCS mass-payment invoice (only `Invoice`-type lines, no billable line of its own) is
**not** turned into a document. `isMassPayment()` detects it; the order is marked
`discarded`, logged, and a new `SkippedException` propagates. Single-create reports it as a
success ("skipped"); `bulkCreateDocuments()` counts a separate `skipped` bucket (not
`failed`). `SkippedException` is re-thrown before the generic failure handler so it never
marks the order failed.

## This pass — the five `defineX` steps
- **defineBasics → expiration date only.** Added `expirationDate` (= document date).
  The WP plugin sets `expirationDate` but has **no maturity date** on the insert, so
  maturity was deliberately **not** implemented.
- **defineFiscalZone → new setting.** `fiscal_zone_based_on` (`company` | `billing`,
  default `company`). `billing` follows the client's ISO2 country — the fiscal-zone `code`
  *is* the uppercased country code, `countryId` via `CountryResolver` — and **falls back to
  the company zone** when the client has no country. See `resolveFiscalZone()` /
  `companyFiscalZone()`.
- **defineCustomer → new `CustomerResolver`.** Extracted from `DocumentService`. VAT from a
  configurable client custom field (`vat_field`) falling back to native `tax_id`; search by
  VAT, else by e-mail, else always create; an existing customer is **always updated**
  (`customerUpdate`). Mirrors WP `OrderCustomer`.
- **definePayment → new `PaymentResolver`.** WHMCS gateway display name → find/create Moloni
  payment method → payment line valued at the order total. Gated by
  `DocumentType::hasPayments()`, true only for receipt / invoiceReceipt / proForma /
  simplified (a plain invoice's payment is registered separately) — matches WP.
- **defineProducts → discount + summary.** Each line now carries a `summary`
  (domain/hosting name + due-date range) and a `discount` %. Promotions (`PromoDomain` /
  `PromoHosting`, stored by WHMCS as separate negative lines) are matched by `relid`,
  converted to a discount percentage on the related line, and **skipped** as standalone
  lines. Design choice confirmed with the user (fold promos into %, VAT custom-field with
  tax_id fallback).

## Supporting changes
- New GraphQL ops: `UpdateCustomer`, `GetPaymentMethods`, `CreatePaymentMethod`;
  `GetCustomers` extended to search by e-mail as well as VAT.
- New `MoloniClient` methods: `findCustomerByEmail`, `updateCustomer`,
  `findPaymentMethodByName`, `createPaymentMethod`.
- New `Whmcs` reads: `getClientCustomFieldValue`, `getLineDiscountAmount`, `getGatewayName`,
  plus `getDomainInfo`/`getHostingInfo`/`getAddonInfo`/`getUpgradeInfo` (for LineMapper).
- New settings + EN/PT strings + `config.php` UI (fiscal zone select, VAT field input),
  persisted in `Dispatcher::saveSettings()`.
- `DocumentService` reorganised so the payload assembly reads as clear steps.

## Verification (Docker)
- `php -l` clean · phpcs PSR-12 **0/0** across `src/` + `tests/`.
- PHPUnit **25 tests / 62 assertions** green. Added coverage for `DocumentType::hasPayments`
  and the new GraphQL operations. `DocumentService`/resolvers depend on WHMCS DB classes, so
  they remain lint/static-only (consistent with existing test scope).

## Follow-ups noted (not done)
- **Currency exchange** still not implemented — non-base-currency clients get wrong totals.
  Needs currency/exchange-rate lookups on the API side (no `MoloniClient` currency methods
  yet).
- `GetCustomers` doesn't return the `deletable` flag, so `CustomerResolver` always sends the
  name on update; WP skips renaming a shared non-deletable "final consumer" customer.
