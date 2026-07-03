# 2026-07-03 — Clean-code refactor pass

Acted on a Clean Code review of `src/Moloni/`. All changes verified via Docker: phpcs
(PSR-12) 0 errors/0 warnings, PHPUnit green (25 tests). No behaviour change except the
`tax_exemption` wiring (below).

## Dead code removed
- `MoloniClient::getMe()` and its now-orphaned `GraphQL/Queries/GetMe.php` (no callers).
- `Company::hasWebhooks()` **kept** — it is exercised by `CompanyTest` and mirrors
  `hasApiClient()`.

## `tax_exemption` — kept and finally wired
The review flagged `tax_exemption` as a written-but-never-read no-op. It is **not** dead:
it now gates line-level exemption. In `DocumentService::buildLine()`, a line that resolves
to no VAT carries the configured `exemption_reason` **only when `tax_exemption` is on**;
when off, untaxed lines carry no exemption reason. (Previously the reason was applied to
every untaxed line unconditionally.)

## Magic numbers named
- `AuthService::DEFAULT_ACCESS_TTL` / `DEFAULT_REFRESH_TTL` (was `3000` / `864000`).
- `DocumentService::MONETARY_EPSILON` (was `0.01`); `totalsMatch()` params renamed.

## New value objects (`src/Moloni/Support/`)
- `FiscalZone` (code + countryId) replaces the `array{code,countryId}` clump threaded
  through `DocumentService`/`TaxResolver`; centralises the `PT` default and upper-casing.
- `LineInput` (name/price/reference/summary/discount) collapsed `buildLine()` from 8
  parameters to 4.
- `Request` — typed view over `$_GET/$_POST/$_REQUEST/$_SERVER`. `Dispatcher` no longer
  touches request superglobals and takes an optional `Request` for testability.

## Duplication / structure
- PDF transport moved out of `DocumentService` into `ApiClient::download()`, exposed as
  `MoloniClient::downloadMedia()`; the raw cURL is no longer duplicated.
- `Dispatcher::renderLayout()` extracted; `renderPage`/`renderStandalone`/
  `renderCompanySelect` compose through it.
- `DocumentService::createDocumentFromOrder()` (~110 lines) split into
  `guardAgainstMassPayment()`, `buildDocumentPayload()`, `submitAndReconcile()`;
  `closeIfRequested()` reads the wanted status internally (6 → 5 params).

## Deliberately left as-is
- `LineMapper::domain()`'s `bool $withDates` — encodes a real per-type rule (transfers
  carry no renewal period). Splitting would near-duplicate the method; a small, named,
  documented flag on a private method is clearer than the alternatives.
- Untyped `object` WHMCS rows — inherent to `Capsule` stdClass results.
