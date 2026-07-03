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
- **Services** (`src/Moloni/Services/`): `DocumentService`, `OrderService`, `LogService`, `SettingsService`, `AuthService`, `CountryResolver` (ISO2 → Moloni country/language), `ProductResolver` (find-by-reference or create product per line), `TaxResolver` (find/create VAT tax by the order's rate + fiscal zone)
- **Api** (`src/Moloni/Api/`): `ApiClient` (native-cURL HTTP: OAuth grant/refresh + GraphQL), `MoloniClient` (domain wrapper)
- **GraphQL** (`src/Moloni/GraphQL/`): one class per query/mutation extending `AbstractOperation`; GraphQL string in the `QUERY` constant, plus `operation()` + `variables($data)`
- **Models** (`src/Moloni/Models/`): `Order`, `Document`, `Config`, `Log`, `Auth` (extend `AbstractModel`); `Whmcs` reads native WHMCS tables
- **Support** (`src/Moloni/Support/`): `Platform` (endpoints), `Context` (per-request session/token/company), `Company` (company payload + feature permissions via `limits`), `Lang` (i18n), `Template` (renderer)
- **Enums** (`src/Moloni/Enums/`): `DocumentType`, `DocumentStatus`, `ProductType`, `ProductTypeAT`, `TaxType`, `TaxFiscalZoneType`
- **Facades** (`src/Moloni/Facades/`): `LoggerFacade`
- **Exceptions** (`src/Moloni/Exceptions/`): `MoloniException` base → `ApiException`, `DocumentException`, `AuthException`, `ValidationException`

## Database tables (custom, `mod_moloni_on_*`)

Created by `src/Moloni/Database/Installer.php` on module activation.

- `mod_moloni_on_config` — key-value settings (document_type, document_status, document_set_id, tax_exemption, auto_create, measurement_unit_id, product_category_id, exemption_reason). Document-line VAT is derived from each WHMCS order's tax rate via `TaxResolver`, not stored here.
- `mod_moloni_on_auth` — single-row OAuth2 session (client_id, client_secret, access_token, refresh_token, expiries, company_id)
- `mod_moloni_on_orders` — order sync tracking (order_id, moloni_document_id, status, …)
- `mod_moloni_on_logs` — application logs (level, message, context, order_id, timestamp)
- `mod_moloni_on_documents` — created documents (order_id, order_total, invoice_id, invoice_date, invoice_status, invoice_total, value)

## UI pages (`templates/`)

`login.php` → `company.php` → dashboard: Orders (`document.php`), Documents (`documents.php`), Settings (`config.php`), Tools (`tools.php`), Logs (`logs.php`). Reusable parts in `Blocks/`, Bootstrap modals in `Modals/`.

## Conventions

- **PHP 7.4+**, **PSR-12**, PSR-4 autoload for `Moloni\` → `src/Moloni/`.
- **i18n**: no hardcoded UI strings — use `lang/en.php` and `lang/pt.php`. Portuguese uses first-person perspective.
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

- Track progress against phases in [.claude/PROJECT_PLAN.md](.claude/PROJECT_PLAN.md).
- Record decisions/progress as new `.claude/journal/NNN_*.md` entries.
- Keep the four planning docs in sync when schema, structure, or flow changes.
