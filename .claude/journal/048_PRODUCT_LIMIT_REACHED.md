# 2026-09-30 — Clear failure when the plan's product limit is reached

## What changed

When a document needs a product that doesn't exist in Moloni ON yet and the
company's plan product limit is full, the document now fails with
"Could not create ":reference" in Moloni ON: the plan's product limit has been
reached." (`product_limit_reached`, en + pt). Before, it failed with the generic
"Moloni ON API rejected the request.", and the real reason was only in the log context.

- `GetCompany` / `GetCompanies` also select `resource` and `remaining` in `limits`.
- `Support\Company` keeps the `limits` entry with `resource: "products"` (the
  module allow-list is keyed on `moduleId` and used to drop it) and adds
  `canCreateProducts()` (`remaining > 0`). If there's no entry, or the company could
  not be loaded, it doesn't block and Moloni ON decides.
- New `Exceptions\ProductLimitException extends ApiException` (so every existing
  `ApiException` / `Throwable` catch behaves as before). It holds the message and
  `isApiError()`, which matches Moloni ON's exact
  "Number of items is over the allowed limit." in the operation errors that
  `ApiClient::assertNoErrors()` keeps in the exception data.
- `ProductResolver::resolveId()`, only after the find-by-reference miss (so existing
  products keep resolving on a full plan), checks the limit first and maps the API
  error to `ProductLimitException`.

## Why

Moloni ON caps products per plan. Every trigger (InvoicePaid hook, manual
create, bulk create) failed with a message that didn't say why. The same count is
public on the company query, so the module can refuse up front and explain.

## Decisions

- **The document still fails.** Using a generic product instead would change what
  gets invoiced, so it needs a product decision.
- **The message goes through `Lang`**, unlike the other (English-only) exception
  messages, because it reaches the admin alert (`document_failed`) and the order's
  `error_message`. It's Portuguese in the admin; in the InvoicePaid hook `Lang` isn't
  booted, so it falls back to English, which is fine for the log.
- **Bulk create keeps trying each order.** It doesn't stop the batch when the limit
  is reached: an order whose products all exist can still be billed. The company is
  read once per request, so a stale `remaining` is covered by the API-error mapping.
  Nothing is remembered for the rest of the run, to keep it simple.

## Tests

`CompanyTest` (products entry kept, `canCreateProducts()`), `ProductLimitExceptionTest`
(API error matching, en/pt message) and `ProductResolverTest` (existing product on a
full plan, pre-check without an API call, API rejection mapped, other errors
rethrown, create with room). PHPUnit green on PHP 7.4 and 8.2; phpcs clean.

## Left undone

Not exercised in a real WHMCS against a company at its product limit.
