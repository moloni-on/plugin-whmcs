# 2026-07-06 — Code-review fixes (round 2): idempotency, HTTP-status, build/CI hardening

A second external code review was triaged against the actual code. Several of its
"critical billing" findings did **not** hold up (see "Rejected" below); this entry
records the ones that did and were fixed.

## Fixed

### 1. Idempotency guard on document creation (the real critical)
`DocumentService::createDocumentFromOrder()` had no already-synced check, and
neither did the manual dispatcher path — only the Orders *list* and the
`InvoicePaid` hook filtered synced orders. A double-submit, a bulk re-run, or the
auto-create hook racing a manual click could issue a **second** sequentially-
numbered legal document.

- Added `alreadySyncedDocumentId()`: when the tracking row is `synced` with a real
  `moloni_document_id`, `createDocumentFromOrder()` now logs and returns that id
  instead of creating a duplicate. The guard runs before the `try` block so it
  never marks the order failed.
- `mod_moloni_on_orders.order_id` is already `UNIQUE`, so a second tracking row
  cannot appear either.
- **Left undone (documented, not a silent gap):** the narrow TOCTOU window where
  two truly-simultaneous requests both pass the read guard before either persists.
  A DB-level claim/advisory lock would be the full fix; it was deliberately not
  added here because it can't be exercised in the no-PHP-locally / no-live-DB
  environment. The read guard + unique constraint cover every realistic case
  (double-click, bulk re-run, hook-vs-tracked-order).

### 2. HTTP-status handling in `ApiClient`
GraphQL/grant requests ignored the HTTP status entirely — a `401` (expired token)
was indistinguishable from a business error, and a transient `5xx`/`429` during a
token refresh was read as a rejected refresh token, permanently forcing re-auth.

- `httpPost()` now returns `{status, body}` and captures `CURLINFO_HTTP_CODE`; a
  connection-level failure is flagged `transient`.
- `request()` calls `assertHttpOk()`: `401` → `AuthException`; `429`/`5xx` →
  transient `ApiException`. A non-2xx/3xx status with no recognisable GraphQL error
  node is no longer treated as success.
- `grantRequest()` flags transient outages the same way.
- `ApiClient::refresh()` rethrows transient failures (instead of returning null),
  and `AuthService::refresh()`/`ensureAuthenticated()` let that propagate — so a
  transient outage during refresh keeps the session for a later retry rather than
  logging the admin out.

### 3. `expirationDate` = invoice due date
The document `date` and `expirationDate` were both the issue timestamp, making
every document due immediately. `expirationDate()` now uses the WHMCS invoice
`duedate` (guarding the zero-date and unparseable values), falling back to the
issue date.

### 4. OAuth `redirect_uri` origin from `SystemURL`
`absoluteModuleUrl()` built the redirect_uri origin from the attacker-controlled
`Host` header. It now takes the origin (scheme+host+port) from the WHMCS-configured
`SystemURL` (`WHMCS\Config\Setting::getValue`), keeping the request-derived path so
a renamed admin folder still resolves, and falling back to the request origin
outside WHMCS (tests).

### 5. `build.sh` never ships dev deps
`build.sh` reused an existing `vendor/` unless `--install` was passed, so a local
build after `composer install` bundled PHPUnit/phpcs into the zip. It now runs a
fresh `--no-dev` install by **default**; `--skip-install` opts out for a vendor/
known to be prod-only. `release.yml` passes `--skip-install` (it already installs
`--no-dev`).

### 6. CI gate
Added `.github/workflows/ci.yml`: phpcs (PSR-12) + PHPUnit on every push/PR across
PHP 7.4/8.1/8.2 (the release workflow only ran on tags and did neither, so
regressions and style violations could reach main). Added `CurrencyExchangeTest`
pinning `toBase()`'s conversion direction (which the review wrongly claimed was
inverted).

## Rejected (verified not real)

- **"Discount double-counted on promo orders."** WHMCS stores promotions as
  separate negative line items; the product line keeps its **gross** price, and the
  discount % is computed from that same gross amount, so Moloni recomputes the
  correct net. Confirmed via `Whmcs::getLineDiscountAmount()` and the model docs.
- **"Currency conversion direction likely inverted."** `toBase() = amount / rate`
  with `rate` = base→order is correct; only the missing test was fair, now added.

## Verification

`php -l` clean, phpcs 0/0 on all changed files, PHPUnit green (60 tests) via Docker.
