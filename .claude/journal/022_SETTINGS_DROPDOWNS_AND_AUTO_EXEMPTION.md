# 2026-07-03 — Settings dropdowns, auto tax-exemption, trimmed document types

Batch of Settings-page changes from admin-UI feedback. Patterns mirrored from
the official Moloni ON WordPress/WooCommerce plugin (`[wordpress_moloni_on]`).

## Measurement unit & product category → live dropdowns

Both were free-text numeric ID inputs. They now render as `<select>`s populated
from Moloni ON:

- New `GraphQL/Queries/GetMeasurementUnits` (operation `measurementUnits`;
  fields `measurementUnitId`, `name`, `abbreviation`; paginated — the API
  rejects unpaginated list queries, see [[moloni-list-queries-need-pagination]]).
- New `GraphQL/Queries/GetProductCategories` (operation `productCategories`;
  fields `productCategoryId`, `name`). Filters to **root** categories only via
  `parentId eq null` (per the request), plus pagination.
- `MoloniClient::getMeasurementUnits()` / `getProductCategories()`.
- Each list carries a `— None —` option (value `0`) so the mapping can be
  cleared; `0` still means "omit" in `ProductResolver`.

## Tax exemption reason → predefined select vs free text

The reason field now shows a dropdown of the company fiscal zone's predefined
codes when the zone defines them (e.g. Portugal's M-codes), otherwise a
free-text input — matching the WordPress plugin's `ExemptionOption` logic.

The codes come from the **company** payload, not a new query: `GetCompany` now
selects `fiscalZone.exemption { type reasons { code name } }`, and
`Support\Company::getExemptionReasons()` exposes them (plus `getFiscalZone()`).
No extra API round-trip — the company is already loaded once per request.

## "Apply tax exemption" toggle removed — now automatic

The on/off checkbox is gone. `DocumentService::buildLine()` now applies the
configured `exemption_reason` to **any** line that resolves to no VAT,
unconditionally (previously gated on the toggle). Removed
`SettingsService::TAX_EXEMPTION` + `taxExemption()`, the `saveSettings` write,
the template checkbox, and the `setting_tax_exemption` locale strings.

## Document types trimmed

`DocumentType::all()` no longer offers `receipt` or `billsOfLading` (they aren't
used to invoice an order). The constants remain (e.g. `hasPayments()` still
references `RECEIPT`, and `UpdateDocumentStatus('receipt')` is still valid); only
the selectable list changed.

## Dispatcher plumbing

- `safeDocumentSets()` generalised into `safeList(string $what, callable $fetch,
  ?string $userMessage = null)` — log-only by default, optional user-facing
  warning alert (only document sets pass one). Used for all three lists.
- New `configPageData()` assembles the config page: settings, document types, the
  three safe lists, and `exemptionReasons` from `Context::company()`.

## Docs

Updated CLAUDE.md (config-keys paragraph), README.md, and this journal.

## Verification

- phpcs (standard scope): 0 errors / 0 warnings.
- PHPUnit: 46 tests, 125 assertions, green (added coverage for the two new
  queries' pagination/root filter, and for receipt/bill-of-lading exclusion).

## Left undone / notes

- `GetProductCategories` fetches only root categories in a single 100-row page,
  matching the request; nested categories and >100 roots are out of scope.
- If a measurement-unit / category list fails to load (API down), its select
  falls back to just the `— None —` option; saving then would reset a
  previously-stored id. Low risk (the whole page would be erroring), left as-is.
- Selects not rendered in a real WHMCS browser here.
