# CLAUDE.md

Guidance for working in this repository.

## What this is

A **WHMCS addon module for Moloni ON** — syncs WHMCS orders into [Moloni ON](https://www.molonion.pt/) (a Portuguese invoicing platform) as invoices/documents via its GraphQL API (`https://api.molonion.pt/v1`).

**Current status: v1 implemented.** The module is built under `src/Moloni/` with `moloni_on.php`/`hooks.php` entry points, templates, i18n and unit tests. Verified locally via Docker: `php -l` clean, phpcs (PSR-12) 0 errors/0 warnings, PHPUnit green.

**Authentication is OAuth2** (authorization-code flow: API client id + secret → authorize redirect → grant → access/refresh tokens), NOT a static API key — the original planning docs were wrong about this. See [[moloni-on-api-auth]].

## Documentation map

| Doc | Purpose |
|-----|---------|
| [README.md](README.md) | Overview, install, structure, dev/build |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Layered system design, services, models, data flow, DB schema |
| [SETUP.md](SETUP.md) | Install/config guide, DB table DDL, deployment checklist |
| [.claude/PROJECT_PLAN.md](.claude/PROJECT_PLAN.md) | Phased implementation checklist with acceptance criteria |
| [.claude/journal/](.claude/journal/) | Progress notes & decision log (`NNN_DESCRIPTION.md`) |

## Architecture (layered)

```
Templates (UI)  →  moloni_on.php (output) / hooks.php
                →  Admin\Dispatcher (router) + Admin\Container (service factory)
                →  Services (business logic)
                →  Api (MoloniClient / ApiClient)  →  Moloni ON GraphQL API
                →  Models  →  WHMCS DB (tblorders, tblclients, mod_moloni_on_*)
```

- **Admin** (`src/Moloni/Admin/`): `Dispatcher` (front controller: OAuth flow + action routing + rendering), `Container` (lazy service factory)
- **Services** (`src/Moloni/Services/`): `DocumentService` (assembles the `<Type>Insert`), `OrderService`, `LogService`, `SettingsService`, `AuthService`, `CountryResolver` (ISO2 → Moloni country/language), `CustomerResolver` (find by VAT/e-mail → create, else always update; VAT from a client custom field falling back to tax_id), `ProductResolver` (find-by-reference or create product per line), `TaxResolver` (find/create VAT tax by the order's rate + fiscal zone), `LineMapper` (WHMCS line type → reference/name/summary/discount; folds promo lines into a discount %), `PaymentResolver` (WHMCS gateway → Moloni payment method + payment line)
- **Api** (`src/Moloni/Api/`): `ApiClient` (native-cURL HTTP: OAuth grant/refresh + GraphQL + `download()` for media/PDFs), `MoloniClient` (domain wrapper; `downloadMedia()` delegates to `ApiClient::download()`)
- **GraphQL** (`src/Moloni/GraphQL/`): one class per query/mutation extending `AbstractOperation`; GraphQL string in the `QUERY` constant, plus `operation()` + `variables($data)`
- **Models** (`src/Moloni/Models/`): `Order`, `Document`, `Config`, `Log`, `Auth` (extend `AbstractModel`); `Whmcs` reads native WHMCS tables
- **Support** (`src/Moloni/Support/`): `Platform` (endpoints), `Context` (per-request session/token/company), `Company` (company payload + feature permissions via `limits`), `Lang` (i18n), `Template` (renderer), `Request` (typed view over the request superglobals, keeps `Dispatcher` testable), `FiscalZone` (code + countryId value object; centralises the `PT`/upper-case defaults), `LineInput` (billing fields of one document line: name/price/reference/summary/discount), `Paginator` (immutable page state — items + totals — for the list views; `PER_PAGE`=15, built via `fromSlice()` for in-memory lists or `paginate()` for DB queries)
- **Enums** (`src/Moloni/Enums/`): `DocumentType`, `DocumentStatus`, `ProductType`, `TaxType`, `TaxFiscalZoneType`
- **Facades** (`src/Moloni/Facades/`): `LoggerFacade`
- **Exceptions** (`src/Moloni/Exceptions/`): `MoloniException` base → `ApiException`, `DocumentException`, `AuthException`, `ValidationException`, `SkippedException` (order intentionally not billed, e.g. mass-payment invoice)

## Database tables (custom, `mod_moloni_on_*`)

Created by `src/Moloni/Database/Installer.php` on module activation.

- `mod_moloni_on_config` — key-value settings (document_type, document_status, document_set_id, auto_create, send_email, payment_method_id, measurement_unit_id, product_category_id, exemption_reason, fiscal_zone_based_on [company|billing], vat_field [client custom-field name for VAT]). `payment_method_id` is the default payment method (dropdown from the `paymentMethods` query) used by `PaymentResolver` when a WHMCS gateway can't be matched to a Moloni method by name; payments are only attached to document types where `DocumentType::hasPayments()` is true. `send_email` e-mails the document to the customer after creation, but only once it is actually closed (via the `<type>SendMail` mutation); drafts and customers without an e-mail are skipped and logged, never fatal. The config page renders measurement_unit_id / product_category_id as dropdowns fetched live from Moloni ON (`measurementUnits` / root `productCategories` queries); exemption_reason is a dropdown of the company fiscal zone's predefined reason codes (from `company.fiscalZone.exemption.reasons`) when the zone defines them, else a free-text input. Document-line VAT is derived from each WHMCS order's tax rate via `TaxResolver`, not stored here. Any line that resolves to no VAT is automatically tax-exempt and carries the configured `exemption_reason` (there is no on/off toggle). Offered document types exclude receipts; bills of lading were removed from the plugin entirely.
- `mod_moloni_on_auth` — single-row OAuth2 session (client_id, client_secret, access_token, refresh_token, expiries, company_id)
- `mod_moloni_on_orders` — order sync tracking (order_id, moloni_document_id, status, …)
- `mod_moloni_on_logs` — application logs (level, message, context, order_id, timestamp)
- `mod_moloni_on_documents` — created documents (order_id, order_total, invoice_id, invoice_date, invoice_status, invoice_total, value)

## UI pages (`templates/`)

`login.php` → `company.php` → dashboard: Orders (`orders.php`), Documents (`documents.php`), Discarded (`discarded.php`), Settings (`config.php`), Tools (`tools.php`), Logs (`logs.php`). All list pages paginate server-side at 15/page via `Support\Paginator` + `Blocks/pagination.php`. Reusable parts in `Blocks/`, Bootstrap modals in `Modals/`. Orders/documents/discarded show the order number as a link to the WHMCS admin order view (`$orderUrl` template helper) and the amount in the client's currency (`$money` helper; documents are enriched with order number + currency via `Whmcs::orderMetaByIds()`). Documents also link to the document on the Moloni ON site (`op=openDocument` → `DocumentService::documentViewUrl()`, built from `company.slug` + `documentType.apiCodePlural`).

**PDF download is two-step:** the `<type>GetPDFToken` query is rejected until the PDF has been exported, so `DocumentService::downloadPdf()` first calls `getDocument` and, when `pdfExport` is empty, runs the `<type>GetPDF` mutation (`CreateDocumentPdf`) and briefly waits before requesting the token. The `downloadPdf`/`openDocument` ops run after auth + company resolution so the API client has a token and company id.

## Conventions

- **PHP 7.4+**, **PSR-12**, PSR-4 autoload for `Moloni\` → `src/Moloni/`.
- **i18n**: no hardcoded UI strings — use `lang/en.php` and `lang/pt.php`. Portuguese addresses the user in the second person, informal ("tu"); system action results use neutral/passive phrasing (e.g. "Configurações guardadas.").
- **Logging**: log all errors via `LogService`; no silent failures. Bulk operations continue past individual failures.
- **Security**: API key in Authorization header (never query string); HTTPS only; validate order IDs and document types; rely on WHMCS CSRF tokens.

## Commands

```bash
composer install      # dependencies
composer test         # PHPUnit (tests/Unit, tests/Feature)
composer lint         # PHP CodeSniffer (PSR-12)
composer lint:fix     # auto-fix style
composer build        # package addon -> dist/moloni_on.zip (build:install refreshes prod deps first)
```

**No PHP locally** — this machine has no `php`/`composer` on PATH. Run tooling through Docker, e.g.
`docker run --rm -v "$PWD":/app -w /app composer:2 vendor/bin/phpunit` and
`... vendor/bin/phpcs --standard=phpcs.xml`. WHMCS classes (`WHMCS\Database\Capsule`,
`Illuminate\*`, `add_hook`) are only present inside a real WHMCS install, so unit tests
cover framework-independent classes (enums, Lang, GraphQL operations).

## Workflow notes

**Documentation is part of "done" — never merge a behaviour change without it.**

- **Always update the docs in the same change that creates or alters a behaviour.** Any
  change to structure, schema, data flow, config keys, services/classes, or module
  behaviour MUST update the affected planning docs ([CLAUDE.md](CLAUDE.md),
  [README.md](README.md), [ARCHITECTURE.md](ARCHITECTURE.md), [SETUP.md](SETUP.md)) in the
  same commit — keep them in sync, don't defer it. Pure internal refactors with no
  observable change still get a journal entry (below).
- **Always write a journal entry** as a new `.claude/journal/NNN_*.md` (next sequential
  number, `# YYYY-MM-DD — <title>` heading). This is mandatory for big changes
  (new features, refactors, schema/flow changes, dependency or auth changes); record what
  changed, why, and any decisions or things deliberately left undone. Journal entries are
  append-only history — add a new one, never rewrite past entries.
- Track progress against phases in [.claude/PROJECT_PLAN.md](.claude/PROJECT_PLAN.md).
