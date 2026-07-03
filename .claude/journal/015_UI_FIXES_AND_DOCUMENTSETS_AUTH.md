# 2026-07-03 — Document-sets API fix + logs/documents/nav UI fixes

Batch of fixes from admin-UI testing feedback.

## Document sets rejected on the Settings page (the "auth" symptom)

Opening **Settings** logged a WARNING "Could not load document sets." whose
context was `{"error": "Moloni ON API rejected the request."}`. The message
comes from `ApiClient::assertNoErrors()` when the operation returns a non-empty
`errors` node — i.e. the API genuinely rejected the `documentSets` query, not an
auth failure (`getCompany` on the same request succeeded, so token/company were
valid).

Root cause: `GetDocumentSets` sent **no** `options` variable. The Moloni ON list
endpoint requires pagination. The reference WordPress plugin always sends it —
its `Curl::complex()` injects `options.pagination.{qty,page}` on every list
query. Our `GetCountries` already did this; `GetDocumentSets` did not.

Fix: `GetDocumentSets::variables()` now returns
`['options' => ['pagination' => ['page' => 1, 'qty' => 100]]]`. Added a unit
test (`GraphQLOperationsTest::testGetDocumentSetsAlwaysSendsPagination`).

Also hardened diagnosis: `Dispatcher::safeDocumentSets()` now merges the
`MoloniException` data (the real `errors` array) into the log context instead of
recording only `getMessage()`, so any future API rejection shows the field-level
detail.

## Logs page

- **View always shown.** The context "View" button previously rendered only when
  a row had context; it now renders on every row (empty context opens an empty
  dialog). The `<noscript>` inline fallback is still gated on non-empty context.
- **Overlay bigger + explicit close.** Dialog grew (`max-width` 640→760px,
  added `min-height: 50vh`), body now flexes to fill, and a footer with a
  labelled **Close** button was added below the body (the header "×" stays). The
  JS backdrop/close handler already keys on `[data-moloni-overlay-close]`, so the
  new button works with no JS change.
- **Clear logs = older than a week.** `LogService::clearLogs()` now passes a
  `-1 week` cutoff to `Log::clear(?string $olderThan)`, which deletes only rows
  older than the cutoff (a null cutoff still truncates everything). Button label
  → "Clear old logs" / "Limpar registos antigos" and the confirm text updated in
  both locales.

## Documents page

Both section headings now use `<h3>` ("Created documents" and "Discarded
orders") — the discarded table was an `<h4>`, so the two titles rendered at
different sizes.

## Navbar / logout

The logout button sat above the tab baseline because the layout depended on the
WHMCS admin theme's Bootstrap version. `.moloni-on__nav` is now an explicit
`display:flex; align-items:center` row (with `flex-wrap`), and the logout form
is `display:flex; align-items:center; margin:0`, so the button lines up with the
tabs regardless of theme.

## Verification

- phpcs (standard scope): 0 errors / 0 warnings.
- PHPUnit: 35 tests, 90 assertions, green.

## Left undone / to verify in a real WHMCS install

- The logout alignment and overlay sizing are CSS-only and were not rendered in a
  browser here — worth a visual confirmation.
- If a company legitimately has >100 document sets, only the first 100 show
  (single page). Pagination-to-exhaustion was intentionally not added.
