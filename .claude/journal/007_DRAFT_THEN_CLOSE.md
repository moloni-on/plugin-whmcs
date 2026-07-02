# 2026-07-02 — Documents always created as draft, closed only on matching totals

Changed `DocumentService::createDocumentFromOrder()` so a document is **never** created
directly in its final status. The create mutation now always sends `DocumentStatus::DRAFT`,
and the document is only promoted to `CLOSED` afterwards — and only when both conditions hold.

## The rule
1. Create the document as **draft** (`status => DocumentStatus::DRAFT`), regardless of the
   configured `document_status` setting.
2. After create, in the new `closeIfRequested()` helper:
   - if the configured status is **not** `CLOSED` → leave as draft.
   - if configured `CLOSED` **and** Moloni's returned `documentTotal` matches the WHMCS
     order total (`tblorders.amount`) → fire `documentUpdate` (`updateDocumentStatus`) to
     set `CLOSED`.
   - if configured `CLOSED` **but** totals differ → **leave as draft** and log a `warning`
     with both totals. A mismatch means the line mapping is off, so we don't lock an
     inconsistent document.

## Supporting changes
- New `totalsMatch(float,float)`: compares with `< 0.01` tolerance for float rounding.
- `persist()` now takes the **actual final status** and stores it as `invoice_status`
  (previously it re-read the configured setting, which could disagree with reality).
- The "Document created" info log now includes the resulting `status`.

## Rationale
Closing is irreversible in Moloni ON. Creating as draft first gives a safety gate: a total
mismatch surfaces as a reviewable draft + a warning log instead of a locked, wrong document.

## Verification (Docker)
- `php -l` clean · phpcs PSR-12 0/0 on `DocumentService.php`.
- No unit-test coverage: `DocumentService` depends on WHMCS DB classes, so it's verified by
  lint/static analysis only (consistent with existing test scope).

## Follow-up noted (not done)
Log messages written via `LoggerFacade` are **hardcoded English** and stored/displayed
verbatim — `templates/logs.php` only escapes `$log->message`, it does not translate it.
Only the logs-page chrome (headers, buttons, level filter) goes through `$lang()`. Revisit
if log messages must be localised (would need a message-key + context-interpolation scheme).
