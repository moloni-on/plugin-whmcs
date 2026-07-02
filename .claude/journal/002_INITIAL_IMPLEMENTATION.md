# 2026-07-02 — Initial module implementation (v1)

## What was done
Implemented the full Moloni ON WHMCS addon from the planning docs. The repo went
from docs-only to a working PSR-4 module under `src/Moloni/` with entry points,
templates, i18n, assets and unit tests.

Layers built:
- **Entry:** `moloni_on.php` (config/activate/deactivate/upgrade/output hooks), `hooks.php` (`InvoicePaid` auto-create)
- **Admin:** `Dispatcher` (front controller: OAuth flow + action routing + rendering), `Container` (lazy service factory)
- **Services:** `AuthService`, `SettingsService`, `LogService`, `OrderService`, `DocumentService`
- **API:** `ApiClient` (native cURL; OAuth grant/refresh + GraphQL), `MoloniClient` (domain wrapper)
- **GraphQL:** `AbstractOperation` + query/mutation classes (GetMe, GetCompanies, GetCompany, GetCustomers, GetDocument, GetDocumentSets, GetInvoicePdfToken, CreateCustomer, CreateDocument, UpdateDocumentStatus)
- **Models:** `AbstractModel`, `Config`, `Auth`, `Order`, `Document`, `Log`, `Whmcs` (native-table repo)
- **Support:** `Platform`, `Context`, `Lang`, `Template`; **Enums:** `DocumentType`, `DocumentStatus`; **Facades:** `LoggerFacade`
- **Exceptions:** `MoloniException` → `ApiException`, `AuthException`, `DocumentException`, `ValidationException`
- **Templates:** login, company, document (orders), documents, config, tools, logs + Blocks (header/navbar/messages/footer)
- **i18n:** `lang/en.php`, `lang/pt.php` (first-person PT); **assets:** `public/css/style.css`, `public/js/app.js`
- **Tests:** `tests/Unit` (DocumentType, Lang, GraphQL operations)

## Key decision: authentication is OAuth2, not an API key
The reference WordPress Moloni ON plugin (`[wordpress_moloni_on]`) revealed that Moloni ON
uses an **OAuth2 authorization-code flow** (API client id + secret → authorize redirect →
grant → access/refresh tokens), and the GraphQL endpoint is `https://api.molonion.pt/v1`
(not `/graphql`). The original planning docs described a static "API key", which was wrong.
Implemented the real OAuth2 flow and updated CLAUDE.md, SETUP.md, ARCHITECTURE.md and
MOLONI_ON_WHMCS_PROMPT.md accordingly.

## Schema change
Added a fifth custom table, **`mod_moloni_on_auth`** (single row), to hold the OAuth2
credentials/tokens/company — cleaner than cramming secrets into the key-value config table.

## Verification (via Docker — no PHP on this machine)
- `php -l` on all PHP files: syntax OK
- `phpcs --standard=phpcs.xml`: 0 errors, 0 warnings (PSR-12)
- `phpunit`: 12 tests / 21 assertions green

WHMCS/Illuminate classes only exist inside a real WHMCS install, so unit tests cover the
framework-independent classes. Live end-to-end testing against WHMCS + Moloni ON is still
required (Phase 9.3 manual testing).

## Known limitations / next steps
- `DocumentService::mapProducts()` builds inline product lines from invoice items; a full
  integration must resolve/create Moloni product ids and map country/language/tax ids.
- `CreateDocument` implements the `invoiceCreate` mutation; other document types share the
  same shape but need their own create mutations.
- No CSRF token wiring yet (relies on WHMCS admin auth); add WHMCS `generate_token`/`check_token`.
