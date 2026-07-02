# 2026-07-02 — Enhancements: multi document-type, product/customer mapping, CSRF

Addressed the three known limitations from `002_INITIAL_IMPLEMENTATION.md`.

## 1. Full product / customer / country mapping
- **CountryResolver** — fetches the Moloni ON `countries` list once and maps a WHMCS ISO-3166-1
  alpha-2 code (e.g. `PT`) to `countryId` + default `languageId`.
- **ProductResolver** — resolves a Moloni `productId` per order line: searches by a deterministic
  reference (`WHMCS-<slug>`) and creates the product (`productCreate`) when missing, using default
  measurement unit / category / tax ids from settings.
- **DocumentService** now builds richer `customerCreate` input (countryId, languageId, phone,
  contactName, sequential `number` via `customerNextNumber`) and real product lines
  (`productId` + `quantity` + `price`, per the confirmed InvoiceInsert shape).
- New settings: `measurement_unit_id`, `product_category_id`, `tax_id` (exposed on the Settings page).
- New enum `ProductType` (PRODUCT/SERVICE; WHMCS items default to SERVICE).

## 2. Multiple document types
- `CreateDocument`, `UpdateDocumentStatus` and `GetDocumentPdfToken` are now type-parametrized:
  they build the operation name + input type from the document type code
  (e.g. `simplifiedInvoice` → `simplifiedInvoiceCreate` / `SimplifiedInvoiceInsert` /
  `simplifiedInvoiceGetPDFToken`). `MoloniClient` and `DocumentService` thread the type through;
  `downloadPdf()` resolves the stored type from the order tracking row.

## 3. CSRF protection
- All state-changing actions are now **POST with a WHMCS CSRF token** (previously some were GET links).
  `Template::csrf()` emits `generate_token()`; `Template::postForm()` renders inline POST buttons
  (row actions live outside the bulk form and associate via the HTML5 `form=` attribute to avoid
  nested forms). `Dispatcher::verifyCsrf()` calls WHMCS `check_token()` on every POST. Read-only PDF
  download stays GET.

## Schema-verification caveat
The reference plugins were already deleted, and docs.molonion.pt is a JS SPA (not fetchable), so the
exact **`ProductInsert`** field set could not be re-verified offline. The fields used (name, reference,
price, type, hasStock, measurementUnitId, categoryId, taxes) follow Moloni ON conventions and are
isolated in `CreateProduct` / `ProductResolver` for easy adjustment. Likewise the `products` search
option shape (`options.search{field,value}`) mirrors the confirmed `customers` query. These need a
quick check against the live schema during Phase 9.3 manual testing.

## Verification (Docker)
- `php -l`: 66 files OK · phpcs PSR-12: 0/0 · phpunit: 13 tests / 26 assertions green
