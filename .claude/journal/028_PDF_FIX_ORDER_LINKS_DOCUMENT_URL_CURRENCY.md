# 2026-07-03 — PDF download fix + order links, Moloni doc URL, currency on lists

Batch from admin-UI testing feedback.

## PDF download fix (the reported bug)

Downloading a document PDF failed with `Could not download PDF` /
`{"document_id":110037,"error":"Moloni ON API rejected the request."}`.

Root cause (confirmed against the WordPress plugin's `DownloadOrderDocument` +
`CreateDocumentPDF`): the `<type>GetPDFToken` query is rejected until the PDF has
actually been **exported**. The reference first checks the document's
`pdfExport`; if empty it runs the `<type>GetPDF` mutation and waits, then fetches
the token.

Fix:
- New `GraphQL/Mutations/CreateDocumentPdf` (op `<type>GetPDF(companyId,
  documentId)`, bare-scalar result) + `MoloniClient::createDocumentPdf()`.
- `DocumentService::downloadPdf()` now: `getDocument` → resolve type from the
  live `documentType.apiCode` (falling back to the stored order type) → if
  `pdfExport` is empty, `createDocumentPdf()` + `sleep(2)` → then get the token.
- **Ordering bug also fixed:** the `downloadPdf` (and new `openDocument`) ops were
  handled *before* `resolveAuthenticatedState()`/`ensureCompanySelected()`, so the
  API client had no token/company. Moved them to after auth + company resolution.
- PDF/open-document error logs now include the API `errors` data (via
  `$e->getData()`), so future rejections show the field-level detail instead of
  just the generic message.

## View document in Moloni ON

- `GetDocument` query extended with `documentType.apiCodePlural`.
- `DocumentService::documentViewUrl()` builds
  `AC_URL + company.slug + '/' + apiCodePlural + '/view/' + documentId`
  (mirrors the reference `OpenDocument` service).
- New `op=openDocument` dispatcher action redirects there; Documents page gets a
  "View in Moloni ON" button (`view_in_moloni` locale string).

## Order links + currency on the lists

- New `$orderUrl` template helper → `orders.php?action=view&id=<id>` (relative to
  the admin dir). Orders, Documents and Discarded now render the order number as
  a link.
- New `$money($amount, $row)` template helper formats an amount in the client's
  currency (prefix/suffix, code fallback); replaced the ad-hoc closure that was
  in `document.php`.
- Documents page: order column now shows the **order number** (not the raw id)
  and the total shows currency. Stored documents only keep `order_id`, so
  `OrderService::getCreatedDocuments()` enriches each row with order number +
  currency via new `Whmcs::orderMetaByIds()`.
- Discarded page: order number is now a link and a currency **Amount** column was
  added (data already came from `ordersWithClients()`).

## Verification

- phpcs (standard scope): 0 errors / 0 warnings.
- PHPUnit: 48 tests, 134 assertions, green (added `CreateDocumentPdf` coverage).
- Templates `php -l` clean.

## Notes / left undone

- `sleep(2)` after PDF generation mirrors the WordPress plugin; not exercised
  against the live API here. If generation is slower, the first token fetch could
  still miss — a poll/retry could replace the flat sleep later.
- `orders.php?action=view&id=` is the standard WHMCS admin order-view URL; not
  verified in a live admin here.
- The Moloni document URL / PDF two-step were not run against the live API.
