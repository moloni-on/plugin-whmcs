# 2026-07-03 — Clean-code review fixes

Acted on a clean-code review of the `create-plugin` working tree. One behaviour
fix plus a batch of internal refinements; PSR-12 clean, PHPUnit green (34 tests).

## Behaviour fix

- **`TaxResolver::resolve()` no longer caches a failed lookup.** Previously a
  failed `findTax` + failed `create` (returning `[]` / no `taxId`) was memoised
  for the whole request, so every later line at the same rate/zone silently
  dropped its VAT (and could become tax-exempt) — a fiscal-correctness hazard.
  Now only a real resolution (non-empty `taxId`) is cached; a transient failure
  is retried on the next line. No config/schema/flow change, hence no doc-map
  update — recorded here.

## Internal refactors (no observable change)

- **`Dispatcher::dispatch()`** split into `dispatch()` +
  `resolveAuthenticatedState()` + `ensureCompanySelected()`, dropping the method
  from ~75 lines of mixed abstraction to a linear sequence.
- **Empty-string ↔ null translation for `document_type`** centralised in
  `Dispatcher::normaliseDocumentType()`; the scattered `?: null` at the service
  call sites is gone.
- **Magic literals promoted to constants** in `Dispatcher`
  (`MODULE_SLUG`/`MODULE_PATH`, `PAGES` whitelist shared by the router) and in
  `DocumentService` (`ORDER_TOTAL_LINE_NAME`, `ORDER_TOTAL_REFERENCE`,
  `FALLBACK_LINE_NAME`).
- **`DocumentService::resolveProductLines()`** extracted its no-items branch to
  `singleOrderTotalLine()`; the tax-ordering counter in `buildLine()` renamed
  `$order` → `$taxOrdering` to stop it reading like the WHMCS order.
- **`MoloniClient`**: envelope unwrapping now lives in one private `dataNode()`
  helper used by both `run()` and `getCustomerNextNumber()` (the latter no
  longer reaches into `ApiClient::request()` directly); the hardcoded
  `'invoice'` defaults on `createDocument`/`updateDocumentStatus`/
  `getDocumentPdfToken` now use `DocumentType::INVOICE`.
- **`ApiClient::download()`** documents that empty-body validation is
  deliberately the caller's responsibility.

## Deliberately left undone

- Nothing outstanding from the review; all findings addressed.
